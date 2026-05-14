<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FormQuestion;
use App\Models\LlmProvider;
use App\Models\Submission;
use App\Services\Dto\DraftResult;
use Illuminate\Support\Facades\Log;

/**
 * Retrofit of LlmDraftingService for Submission + FormQuestion pairs.
 *
 * Anti-fabrication contract preserved verbatim from LlmDraftingService:
 *   - forbidden-inventions clause in system prompt
 *   - [SPECIFY:] placeholder syntax
 *   - post-scan flags unverified numerical/date tokens
 *   - non-empty responses always persisted (with warnings)
 *
 * REQ-IRB-GUIDE-009..013 (anti-fabrication), REQ-IRB-GUIDE-024a (failure logging)
 * SPEC-IRB-FORMSV2-004 §B.2
 *
 * @MX:ANCHOR: [AUTO] draftMissingField() is the single assistant-mode drafting entry point.
 *
 * @MX:REASON: fan_in >= 3 — SubmissionAnalysisService drafting loop, SubmissionAnalysisServiceTest,
 *             and future SubmissionAnswerController suggestion flow.
 */
class SubmissionDraftingService
{
    /**
     * Draft a suggestion for a missing submission field using the anti-fabrication contract.
     *
     * @param  string  $projectContext  Concatenation of user-confirmed submission_answer text values.
     *                                  Unconfirmed ai_draft rows excluded (Q1 ruling from LlmDraftingService).
     */
    public function draftMissingField(
        Submission $submission,
        FormQuestion $question,
        string $projectContext,
        LlmProvider $provider,
        LlmChatService $chat,
    ): DraftResult {
        $systemPrompt = $this->buildSystemPrompt($question);
        $userPrompt = $this->buildUserPrompt($question, $projectContext, $submission);

        // REQ-IRB-GUIDE-024: composed prompt stored verbatim in audit row
        $promptForAudit = $systemPrompt."\n---\n".$userPrompt;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        try {
            $rawResponse = $chat->chat($provider, $messages);
        } catch (\Throwable $e) {
            Log::warning('SubmissionDraftingService draft failed', [
                'exception' => $e->getMessage(),
                'submission_id' => $submission->id,
                'question_key' => $question->question_key,
            ]);

            return new DraftResult(
                value: '',
                isEmpty: true,
                warnings: [],
                promptForAudit: $promptForAudit,
                modelUsed: (string) $provider->model,
            );
        }

        $content = trim($rawResponse);

        if ($content === '') {
            Log::warning('SubmissionDraftingService draft failed', [
                'exception' => 'empty response',
                'submission_id' => $submission->id,
                'question_key' => $question->question_key,
            ]);

            return new DraftResult(
                value: '',
                isEmpty: true,
                warnings: [],
                promptForAudit: $promptForAudit,
                modelUsed: (string) $provider->model,
            );
        }

        // REQ-IRB-GUIDE-013: post-scan for unverified numerical/date tokens
        $warnings = $this->postScanWarnings($content);

        return new DraftResult(
            value: $content,
            isEmpty: false,
            warnings: $warnings,
            promptForAudit: $promptForAudit,
            modelUsed: (string) $provider->model,
        );
    }

    // ── Prompt builders ────────────────────────────────────────────────────────

    private function buildSystemPrompt(FormQuestion $question): string
    {
        return <<<PROMPT
You are assisting an IRB (Institutional Review Board) researcher fill out a regulatory form.

ANTI-FABRICATION RULES (mandatory):
1. Only include information you can reasonably infer from the context provided.
2. For any specific number, date, or statistic you cannot confirm, write [SPECIFY: brief description].
3. Do not invent study procedures, participant numbers, or risk mitigations.
4. Write in clear, professional regulatory language appropriate for an IRB submission.
5. If the context provides no relevant information, write a 1–2 sentence placeholder indicating what information is needed.

Field being filled: {$question->label}
Field purpose: {$question->instruction}
PROMPT;
    }

    private function buildUserPrompt(FormQuestion $question, string $projectContext, Submission $submission): string
    {
        $formTitle = $submission->formDefinition?->title ?? 'IRB form';

        $context = trim($projectContext) !== ''
            ? "Project context (confirmed answers):\n{$projectContext}"
            : 'No project context available yet.';

        return <<<PROMPT
{$context}

Task: Fill the following field in {$formTitle}.

Field label: {$question->label}
{$question->instruction}

Write a 2–4 sentence draft answer. Use [SPECIFY: description] for any specific details you cannot confirm from the context.
PROMPT;
    }

    // ── Post-scan ──────────────────────────────────────────────────────────────

    /**
     * Flag unverified numerical/date tokens (REQ-IRB-GUIDE-013).
     *
     * @return list<array{type: string, token: string}>
     */
    private function postScanWarnings(string $content): array
    {
        $warnings = [];

        // Match numbers (including decimals and percentages)
        if (preg_match_all('/\b\d[\d,.]*%?\b/', $content, $matches)) {
            foreach ($matches[0] as $token) {
                // Skip year-like 4-digit numbers 1900..2099 (usually safe)
                if (preg_match('/^(19|20)\d{2}$/', $token)) {
                    continue;
                }
                $warnings[] = ['type' => 'numeric', 'token' => $token];
            }
        }

        // Match date patterns
        if (preg_match_all('/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s+\d{4}\b/i', $content, $matches)) {
            foreach ($matches[0] as $token) {
                $warnings[] = ['type' => 'date', 'token' => $token];
            }
        }

        return $warnings;
    }
}
