<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AnalysisCancelledException;
use App\Models\AnalysisRun;
use App\Models\AuditEvent;
use App\Models\DocumentChunk;
use App\Models\FormQuestion;
use App\Models\LlmProvider;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retrofit of ProjectAnalysisService for the Submission model.
 *
 * Operates on (submission_id, question_key) pairs instead of
 * (project_id, field_definition_id). Reads document chunks via
 * Study → ProjectDocument → DocumentChunk (Phase 4 PR-1 quickwin;
 * full upload retrofit to submission_uploads is Phase 5).
 *
 * Preserved contracts:
 *   - Progress callback heartbeats on analysis_runs row
 *   - Cancel detection via AnalysisCancelledException
 *   - REQ-IRB-GUIDE-031 carve-out: ai_draft re-analysis allowed
 *   - Strict mode: zero ai_draft rows produced
 *   - Assistant mode: SubmissionDraftingService called for missing fields
 *   - REQ-IRB-FORMSV2-060: HRP-398 rejected early
 *   - REQ-IRB-FORMSV2-059: project_context from submission denormalized fields
 *
 * SPEC-IRB-FORMSV2-004 §B.1
 *
 * @MX:ANCHOR: [AUTO] runFirstPass() is the single entry point for submission LLM analysis.
 *
 * @MX:REASON: fan_in >= 3 — AnalyzeSubmissionJob, SubmissionAnalysisController, SubmissionAnalysisServiceTest.
 *
 * @MX:WARN: [AUTO] Nested batch + evidence loops; cyclomatic complexity >= 15.
 *
 * @MX:REASON: LLM batch processing requires complex field filtering, evidence matching,
 *             and cancel detection at each checkpoint. Extracted from ProjectAnalysisService pattern.
 */
class SubmissionAnalysisService
{
    public function __construct(
        private readonly SubmissionDraftingService $drafting,
    ) {}

    /**
     * @param  ?callable  $progressCallback  fn(string $step, int $current, int $total, string $message): void
     * @param  ?AnalysisRun  $existingRun  Pre-created run row from the controller (can be null for testing).
     */
    public function runFirstPass(
        Submission $submission,
        LlmProvider $provider,
        int $actorUserId,
        LlmChatService $llm,
        Request $request,
        ?callable $progressCallback = null,
        ?AnalysisRun $existingRun = null,
    ): void {
        $progress = $progressCallback ?? static function (): void {};

        // REQ-IRB-FORMSV2-060: HRP-398 is guidance-only; reject before any LLM call.
        if ($submission->formDefinition->form_code === 'HRP-398') {
            throw new \RuntimeException('HRP-398 submissions cannot be analyzed (guidance-only per REQ-IRB-FORMSV2-060).');
        }

        $formDefinition = $submission->formDefinition;
        // Phase 5: also load children.options so nested radio options are available for buildFieldDescriptor().
        $formDefinition->loadMissing(['sections.questions.options', 'sections.questions.children.options']);

        // Collect all answerable questions from the form
        $questions = collect();
        foreach ($formDefinition->sections as $section) {
            foreach ($section->questions as $question) {
                if ($question->question_type !== 'group_label') {
                    $questions->push($question);
                }
            }
        }

        // Get existing answers
        $existingAnswers = SubmissionAnswer::query()
            ->where('submission_id', $submission->id)
            ->get()
            ->keyBy('question_key');

        // Filter to questions needing analysis (mirrors ProjectAnalysisService logic)
        $fieldsToProcess = $questions->filter(function (FormQuestion $q) use ($existingAnswers): bool {
            $answer = $existingAnswers->get($q->question_key);

            // Skip confirmed/edited (user has a final answer)
            if ($answer !== null) {
                if ($this->hasConfirmedValue($answer)) {
                    return false;
                }
                // REQ-IRB-GUIDE-031 carve-out: ai_draft can be re-analyzed
                // (evidence-grounded result may overwrite a stale ai_draft)
                if ($answer->suggestion_source !== 'ai_draft') {
                    return false;
                }
            }

            return true;
        })->values()->all();

        if (count($fieldsToProcess) === 0) {
            return;
        }

        // Fetch document chunks via Study → ProjectDocument → DocumentChunk
        // Phase 4 PR-1 quickwin; full submission_uploads retrofit is Phase 5.
        $studyId = $submission->study_id;
        $chunks = DocumentChunk::query()
            ->whereHas('document', fn ($q) => $q->where('study_id', $studyId))
            ->with(['document'])
            ->get();

        if ($chunks->isEmpty()) {
            // No documents uploaded yet — skip evidence pass; assistant mode may still draft
            if ($submission->assistance_mode === 'assistant') {
                if ($existingRun !== null) {
                    $existingRun->forceFill(['status' => 'running', 'started_at' => $existingRun->started_at ?? now()])->save();
                }
                $this->runDraftingIteration($submission, $provider, $actorUserId, $existingRun, $request, $progress, $existingAnswers);
            }
            $this->finalizeRun($existingRun, 'succeeded', null, [], [], []);

            return;
        }

        $chunkTextById = $chunks->mapWithKeys(fn ($c) => [$c->id => (string) $c->text]);

        if ($existingRun !== null) {
            $existingRun->forceFill([
                'status' => 'running',
                'started_at' => $existingRun->started_at ?? now(),
            ])->save();
        }

        $run = $existingRun;

        $chunkRows = $chunks
            ->sortByDesc(fn ($c) => mb_strlen((string) $c->text))
            ->take((int) config('irb.max_chunks_sent', 40))
            ->map(fn ($c) => [
                'chunk_id' => $c->id,
                'document' => $c->document?->original_filename,
                'page' => $c->page_number,
                'text' => mb_substr((string) $c->text, 0, (int) config('irb.max_chunk_chars_sent', 1200)),
            ])
            ->values()
            ->all();

        $fields = collect($fieldsToProcess)->map(fn (FormQuestion $q) => $this->buildFieldDescriptor($q))->values()->all();

        $requestPayloadRedacted = [
            'fields_total' => count($fields),
            'chunks_sent' => count($chunkRows),
            'provider' => ['name' => $provider->name, 'model' => $provider->model],
            'field_keys' => array_column($fields, 'question_key'),
        ];

        $allResults = [];
        $batchSize = (int) config('irb.analysis_batch_size', 20);
        $batches = array_chunk($fields, $batchSize);

        try {
            foreach ($batches as $batchIdx => $batch) {
                $progress(
                    'first_pass_batch',
                    $batchIdx + 1,
                    count($batches),
                    sprintf(
                        'Asking the LLM about question group %d of %d (fields %d–%d of %d).',
                        $batchIdx + 1,
                        count($batches),
                        $batchIdx * $batchSize + 1,
                        min(count($fields), ($batchIdx + 1) * $batchSize),
                        count($fields),
                    ),
                );

                $messages = [
                    ['role' => 'system', 'content' => 'You fill form fields from source excerpts. Output ONLY valid JSON. Do not include markdown. If unknown, use empty string.'],
                    ['role' => 'user', 'content' => $this->buildPrompt($batch, $chunkRows, $submission)],
                ];

                $content = $llm->chat($provider, $messages);

                try {
                    $json = $this->parseJsonFromContent($content);
                    $batchResults = is_array($json['fields'] ?? null) ? $json['fields'] : [];
                } catch (\RuntimeException $e) {
                    Log::warning('Submission analysis batch returned non-JSON; treating as empty', [
                        'submission_id' => $submission->id,
                        'batch_index' => $batchIdx,
                        'parse_error' => $e->getMessage(),
                    ]);
                    $batchResults = [];
                }

                $allResults[] = ['batch' => $batchIdx, 'keys' => array_column($batch, 'question_key')];

                foreach ($batchResults as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $questionKey = $row['field_key'] ?? null;
                    $valueText = $row['value'] ?? null;

                    if (! is_string($questionKey) || ! is_string($valueText)) {
                        continue;
                    }

                    $valueText = trim($valueText);
                    if ($valueText === '') {
                        continue;
                    }

                    $existingAnswer = $existingAnswers->get($questionKey);
                    if ($existingAnswer !== null && $this->hasConfirmedValue($existingAnswer)) {
                        continue;
                    }
                    // REQ-IRB-GUIDE-031 carve-out: allow overwriting ai_draft with evidence
                    if ($existingAnswer !== null && $existingAnswer->suggestion_source !== 'ai_draft') {
                        continue;
                    }

                    $evidenceRows = $row['evidence'] ?? [];
                    $toInsert = [];

                    if (is_array($evidenceRows)) {
                        foreach ($evidenceRows as $ev) {
                            if (! is_array($ev)) {
                                continue;
                            }
                            $chunkId = $ev['chunk_id'] ?? null;
                            $quote = $ev['quote'] ?? null;

                            if (! is_int($chunkId) && ! (is_string($chunkId) && ctype_digit($chunkId))) {
                                continue;
                            }
                            if (! is_string($quote) || trim($quote) === '') {
                                continue;
                            }

                            $chunkIdInt = (int) $chunkId;
                            if (! $chunkTextById->has($chunkIdInt)) {
                                continue;
                            }

                            $quote = trim($quote);
                            $chunkText = (string) $chunkTextById->get($chunkIdInt, '');
                            $startOff = mb_strpos($chunkText, $quote);
                            if ($startOff === false) {
                                continue;
                            }

                            $toInsert[] = [
                                'submission_id' => $submission->id,
                                'question_key' => $questionKey,
                                'chunk_id' => $chunkIdInt,
                                'quote_text' => $quote,
                                'chunk_offset_start' => $startOff,
                                'chunk_offset_end' => $startOff + mb_strlen($quote),
                            ];
                        }
                    }

                    // Require evidence for non-empty suggestions (Strict mode gates here too)
                    if (count($toInsert) === 0) {
                        continue;
                    }

                    // Upsert submission_answer with suggestion_source='evidence'
                    SubmissionAnswer::query()->updateOrCreate(
                        ['submission_id' => $submission->id, 'question_key' => $questionKey],
                        ['text_value' => $valueText, 'suggestion_source' => 'evidence'],
                    );

                    // Insert submission_field_evidence rows
                    foreach ($toInsert as $idx => $evRow) {
                        DB::table('submission_field_evidence')->updateOrInsert(
                            [
                                'submission_id' => $submission->id,
                                'question_key' => $questionKey,
                                'evidence_index' => $idx,
                            ],
                            array_merge($evRow, ['evidence_index' => $idx]),
                        );
                    }

                    AuditEvent::query()->create([
                        'occurred_at' => now(),
                        'actor_user_id' => $actorUserId,
                        'event_type' => 'submission.field.evidence_suggested',
                        'entity_type' => 'submission',
                        'entity_id' => $submission->id,
                        'entity_uuid' => null,
                        'project_id' => null,
                        'ip' => $request->ip() ?? '127.0.0.1',
                        'user_agent' => substr((string) $request->userAgent(), 0, 512),
                        'request_id' => null,
                        'payload' => ['question_key' => $questionKey, 'evidence_count' => count($toInsert)],
                    ]);
                }
            }

            // REQ-IRB-GUIDE-007 / REQ-IRB-GUIDE-008: drafting runs ONLY for assistant-mode submissions.
            // @MX:ANCHOR: [AUTO] Strict-mode gate — zero ai_draft rows for strict submissions.
            // @MX:REASON: REQ-IRB-GUIDE-008: Strict mode MUST NEVER produce ai_draft rows.
            if ($submission->assistance_mode === 'assistant') {
                $this->runDraftingIteration($submission, $provider, $actorUserId, $run, $request, $progress, $existingAnswers);
            }

            $progress('completed', 1, 1, 'Analysis complete');
            $this->finalizeRun($run, 'succeeded', null, $requestPayloadRedacted, $allResults, []);

        } catch (AnalysisCancelledException $e) {
            $this->finalizeRun($run, 'cancelled', $e->getMessage(), $requestPayloadRedacted, $allResults, []);
            throw $e;
        } catch (\Throwable $e) {
            $this->finalizeRun($run, 'failed', $e->getMessage(), $requestPayloadRedacted, $allResults, []);
            throw $e;
        }
    }

    // ── Drafting iteration (assistant mode only) ───────────────────────────────

    /**
     * @param  \Illuminate\Support\Collection<string, SubmissionAnswer>  $existingAnswers
     */
    private function runDraftingIteration(
        Submission $submission,
        LlmProvider $provider,
        int $actorUserId,
        ?AnalysisRun $run,
        Request $request,
        callable $progress,
        \Illuminate\Support\Collection $existingAnswers,
    ): void {
        // Reload fresh answers after evidence pass
        $freshAnswers = SubmissionAnswer::query()
            ->where('submission_id', $submission->id)
            ->get()
            ->keyBy('question_key');

        $formDefinition = $submission->formDefinition;
        $formDefinition->loadMissing(['sections.questions']);

        $allQuestions = collect();
        foreach ($formDefinition->sections as $section) {
            foreach ($section->questions as $q) {
                if ($q->question_type !== 'group_label') {
                    $allQuestions->push($q);
                }
            }
        }

        // Filter to still-missing fields (no confirmed answer, no evidence suggestion, no prior non-draft)
        $toDraft = $allQuestions->filter(function (FormQuestion $q) use ($freshAnswers): bool {
            $a = $freshAnswers->get($q->question_key);
            if ($a === null) {
                return true; // completely missing
            }
            if ($a->suggestion_source === 'ai_draft') {
                return false; // already has a draft
            }
            if ($this->hasConfirmedValue($a)) {
                return false;
            }
            if ($a->text_value !== null || $a->option_value !== null || $a->bool_value !== null || $a->json_value !== null) {
                return false; // has an evidence-grounded value
            }

            return true;
        })->values();

        $cap = max(1, (int) config('irb.drafting_max_per_run', 20));
        $toDraft = $toDraft->take($cap);

        // Build project context from confirmed/user-answered submission_answer rows
        $projectContext = $freshAnswers
            ->filter(fn ($a) => $this->hasConfirmedValue($a) || $a->suggestion_source === 'evidence')
            ->map(fn ($a, $key) => "{$key}: ".($a->text_value ?? $a->option_value ?? ''))
            ->implode("\n");

        $llm = app(LlmChatService::class);

        $draftingTotal = $toDraft->count();
        foreach ($toDraft as $idx => $question) {
            /** @var FormQuestion $question */
            $progress(
                'drafting_field',
                $idx + 1,
                $draftingTotal,
                sprintf(
                    'Drafting AI suggestion for question %d of %d ("%s").',
                    $idx + 1,
                    $draftingTotal,
                    mb_substr((string) ($question->label ?? 'unnamed'), 0, 60),
                ),
            );

            $draftResult = $this->drafting->draftMissingField(
                submission: $submission,
                question: $question,
                projectContext: $projectContext,
                provider: $provider,
                chat: $llm,
            );

            if ($draftResult->isEmpty) {
                continue;
            }

            SubmissionAnswer::query()->updateOrCreate(
                ['submission_id' => $submission->id, 'question_key' => $question->question_key],
                ['text_value' => $draftResult->value, 'suggestion_source' => 'ai_draft'],
            );

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_user_id' => $actorUserId,
                'event_type' => 'submission.field.ai_drafted',
                'entity_type' => 'submission',
                'entity_id' => $submission->id,
                'entity_uuid' => null,
                'project_id' => null,
                'ip' => $request->ip() ?? '127.0.0.1',
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'request_id' => null,
                'payload' => [
                    'question_key' => $question->question_key,
                    'has_warnings' => count($draftResult->warnings) > 0,
                    'model' => $draftResult->modelUsed,
                ],
            ]);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function hasConfirmedValue(SubmissionAnswer $answer): bool
    {
        // suggestion_source=null means user-confirmed (direct save)
        if ($answer->suggestion_source === null) {
            return true;
        }
        // 'evidence' = LLM-grounded, accepted by user
        if ($answer->suggestion_source === 'evidence') {
            return true;
        }

        return false;
    }

    // ── Phase 5 field descriptor ───────────────────────────────────────────────

    /**
     * Build a field descriptor for the LLM prompt.
     *
     * Phase 5 types (checkbox_multi_with_section_triggers, radio_with_nested_options,
     * numbered_options_with_criteria) include the allowed option schema in the descriptor
     * so the LLM emits schema-compliant values (REQ-P5-005, LD-P5-5).
     *
     * Maintained REQ-IRB-GUIDE-031 carve-out: evidence-grounded drafting behavior is
     * unchanged; the descriptor extension only enriches the system message, not the
     * evidence-matching logic.
     *
     * @return array<string, mixed>
     *
     * @MX:NOTE: [AUTO] Phase 5 extension: descriptors carry schema for option-type questions so LLM output is schema-valid.
     */
    private function buildFieldDescriptor(FormQuestion $question): array
    {
        $base = [
            'question_key' => $question->question_key,
            'label' => $question->label,
            'question_text' => $question->instruction ?? $question->label,
            'type' => $question->question_type,
        ];

        return match ($question->question_type) {
            'checkbox_multi_with_section_triggers',
            'numbered_options_with_criteria' => array_merge($base, [
                'allowed_values' => $question->options()->pluck('option_value', 'option_label')->all(),
                'value_format' => 'Return as JSON array of selected option value strings, e.g. ["option_a","option_b"]',
            ]),
            'radio_with_nested_options' => array_merge($base, [
                'allowed_values' => $this->collectNestedOptionValues($question),
                'value_format' => 'Return as a single option value string from the allowed_values list (may be a leaf value from nested options).',
            ]),
            'textarea_with_na_and_followup' => array_merge($base, [
                'value_format' => 'Return as JSON: {"na": false, "text": "...", "followup": "..."} or {"na": true, "text": null, "followup": null}',
            ]),
            'textarea_with_alternative_radio' => array_merge($base, [
                'value_format' => 'Return as JSON: {"mode": "text", "text": "...", "radio": null} or {"mode": "radio", "text": null, "radio": "option_value"}',
            ]),
            'checkbox_with_optional_textarea' => array_merge($base, [
                'value_format' => 'Return as JSON: {"checked": true, "text": "optional text or null"} or {"checked": false, "text": null}',
            ]),
            default => $base,
        };
    }

    /**
     * Collect all allowed option values (outer + nested) for radio_with_nested_options.
     *
     * @return array<string, string> option_label → option_value
     */
    private function collectNestedOptionValues(FormQuestion $question): array
    {
        $values = [];

        // Outer options
        foreach ($question->options as $opt) {
            $values[$opt->option_label] = $opt->option_value;
        }

        // Nested (child question) options
        $question->loadMissing('children.options');
        foreach ($question->children as $child) {
            foreach ($child->options as $opt) {
                $values[$opt->option_label] = $opt->option_value;
            }
        }

        return $values;
    }

    /**
     * @param  array<array{question_key: string, label: string, question_text: string}>  $fields
     * @param  array<array{chunk_id: int, document: string|null, page: int|null, text: string}>  $chunkRows
     */
    private function buildPrompt(array $fields, array $chunkRows, Submission $submission): string
    {
        $formTitle = $submission->formDefinition?->title ?? 'IRB form';

        // REQ-IRB-FORMSV2-059: inject project_context from submission's denormalized fields
        $projectContext = implode("\n", array_filter([
            $submission->title ? "Study title: {$submission->title}" : null,
            $submission->principal_investigator ? "PI: {$submission->principal_investigator}" : null,
            $submission->oversight ? "Oversight: {$submission->oversight}" : null,
        ]));

        $fieldsJson = json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $chunksJson = json_encode($chunkRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Fill fields in "{$formTitle}" using these document excerpts.

Project context:
{$projectContext}

Fields to fill (JSON):
{$fieldsJson}

Document excerpts (JSON):
{$chunksJson}

Respond ONLY with valid JSON in this shape:
{
  "fields": [
    {
      "field_key": "question_key_here",
      "value": "extracted text or empty string",
      "evidence": [
        {"chunk_id": 1, "quote": "verbatim excerpt from chunk text"}
      ]
    }
  ]
}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonFromContent(string $content): array
    {
        $content = trim($content);

        // Strip markdown code fences if present
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\n?/', '', $content) ?? $content;
            $content = preg_replace('/\n?```$/', '', $content) ?? $content;
        }

        $decoded = json_decode(trim($content), true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('LLM response is not valid JSON: '.mb_substr($content, 0, 200));
        }

        return $decoded;
    }

    private function finalizeRun(?AnalysisRun $run, string $status, ?string $error, array $requestPayload, array $responsePayload, array $extra): void
    {
        if ($run === null) {
            return;
        }

        $run->forceFill(array_filter([
            'status' => $status,
            'finished_at' => now(),
            'error' => $error,
            'request_payload' => $requestPayload ?: null,
            'response_payload' => $responsePayload ?: null,
        ], fn ($v) => $v !== null))->save();
    }
}
