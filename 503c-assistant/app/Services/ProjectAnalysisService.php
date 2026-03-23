<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentChunk;
use App\Models\FieldEvidence;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\AnalysisRun;
use App\Models\ProjectFieldValue;
use App\Models\TemplateControlMapping;
use App\Models\TemplateVersion;
use Illuminate\Support\Facades\Crypt;

class ProjectAnalysisService
{
    public function runFirstPass(Project $project, LlmProvider $provider, int $actorUserId, LlmChatService $llm): void
    {
        $template = TemplateVersion::query()->where('is_active', true)->orderByDesc('created_at')->first();
        if ($template === null) {
            throw new \RuntimeException('No active template configured');
        }

        $mappedFieldIds = TemplateControlMapping::query()
            ->where('template_version_id', $template->id)
            ->pluck('field_definition_id')
            ->unique()
            ->values()
            ->all();

        if (count($mappedFieldIds) === 0) {
            throw new \RuntimeException('No template mappings configured. Map controls to fields in Admin > Templates.');
        }

        $chunks = DocumentChunk::query()
            ->whereHas('document', fn ($q) => $q->where('project_id', $project->id))
            ->with(['document'])
            ->get();

        if ($chunks->isEmpty()) {
            return;
        }

        $chunkTextById = $chunks
            ->mapWithKeys(fn (DocumentChunk $c) => [$c->id => (string) $c->text]);

        $values = ProjectFieldValue::query()
            ->where('project_id', $project->id)
            ->whereIn('field_definition_id', $mappedFieldIds)
            ->with('field')
            ->get();

        $run = AnalysisRun::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'project_id' => $project->id,
            'llm_provider_id' => $provider->id,
            'created_by_user_id' => $actorUserId,
            'status' => 'running',
            'started_at' => now(),
            'prompt_version' => 'first-pass-v1',
        ]);

        $chunkRows = $chunks
            ->sortByDesc(fn ($c) => mb_strlen((string) $c->text))
            ->take((int) env('IRB_MAX_CHUNKS_SENT', 40))
            ->map(function (DocumentChunk $c) {
                $docName = $c->document?->original_filename;
                return [
                    'chunk_id' => $c->id,
                    'document' => $docName,
                    'page' => $c->page_number,
                    'text' => mb_substr((string) $c->text, 0, (int) env('IRB_MAX_CHUNK_CHARS_SENT', 1200)),
                ];
            })
            ->values()
            ->all();

        $fields = $values
            ->map(function (ProjectFieldValue $v) {
                return [
                    'field_key' => (string) ($v->field?->key ?? ''),
                    'label' => (string) ($v->field?->label ?? ''),
                    'question_text' => (string) ($v->field?->question_text ?? ''),
                ];
            })
            ->filter(fn ($f) => $f['field_key'] !== '')
            ->values()
            ->all();

        // Only ask the model for missing/unfilled fields.
        $fields = array_values(array_filter($fields, function (array $f) use ($values): bool {
            $pv = $values->first(fn (ProjectFieldValue $v) => $v->field?->key === $f['field_key']);
            if ($pv === null) {
                return false;
            }

            if ($pv->final_value !== null && trim($pv->final_value) !== '') {
                return false;
            }

            if (in_array($pv->status, ['confirmed', 'edited'], true)) {
                return false;
            }

            if ($pv->suggested_value !== null && trim($pv->suggested_value) !== '') {
                return false;
            }

            return true;
        }));

        if (count($fields) === 0) {
            return;
        }

        $batchSize = (int) env('IRB_ANALYSIS_BATCH_SIZE', 20);
        $batches = array_chunk($fields, $batchSize);

        $requestPayloadFull = [
            'fields_total' => count($fields),
            'batch_size' => $batchSize,
            'chunks_sent' => count($chunkRows),
            'provider' => [
                'name' => $provider->name,
                'type' => $provider->provider_type,
                'model' => $provider->model,
                'base_url' => $provider->base_url,
            ],
            'fields' => $fields,
            'chunks' => $chunkRows,
        ];

        $requestPayloadRedacted = [
            'fields_total' => count($fields),
            'batch_size' => $batchSize,
            'chunks_sent' => count($chunkRows),
            'provider' => [
                'name' => $provider->name,
                'type' => $provider->provider_type,
                'model' => $provider->model,
                'base_url' => $provider->base_url,
            ],
            'chunk_meta' => array_map(static function (array $row): array {
                return [
                    'chunk_id' => $row['chunk_id'] ?? null,
                    'document' => $row['document'] ?? null,
                    'page' => $row['page'] ?? null,
                    'text_chars' => mb_strlen((string) ($row['text'] ?? '')),
                ];
            }, $chunkRows),
            'field_keys' => array_map(static fn (array $row): string => (string) ($row['field_key'] ?? ''), $fields),
        ];

        $payloadEncKeyId = $this->payloadEncryptionKeyId();

        $allResults = [];

        try {
            foreach ($batches as $batchIdx => $batch) {
                $messages = [
                    [
                        'role' => 'system',
                        'content' => 'You fill form fields from source excerpts. Output ONLY valid JSON. Do not include markdown. If unknown, use empty string.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildPrompt($batch, $chunkRows),
                    ],
                ];

                $content = $llm->chat($provider, $messages);
                $json = $this->parseJsonFromContent($content);

                $batchResults = $json['fields'] ?? null;
                if (! is_array($batchResults)) {
                    throw new \RuntimeException('LLM response missing fields array');
                }

                $allResults[] = [
                    'batch' => $batchIdx,
                    'raw' => $json,
                ];

                foreach ($batchResults as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $fieldKey = $row['field_key'] ?? null;
                $valueText = $row['value'] ?? null;

                if (! is_string($fieldKey) || ! is_string($valueText)) {
                    continue;
                }

                $pv = $values->first(fn (ProjectFieldValue $v) => $v->field?->key === $fieldKey);
                if ($pv === null) {
                    continue;
                }

                if ($pv->final_value !== null && trim($pv->final_value) !== '') {
                    continue;
                }

                if (in_array($pv->status, ['confirmed', 'edited'], true)) {
                    continue;
                }

                if ($pv->suggested_value !== null && trim($pv->suggested_value) !== '') {
                    continue;
                }

                $valueText = trim($valueText);
                if ($valueText === '') {
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
                        $startOffset = mb_strpos($chunkText, $quote);
                        if ($startOffset === false) {
                            continue;
                        }
                        $endOffset = $startOffset + mb_strlen($quote);

                        $toInsert[] = [
                            'analysis_run_id' => $run->id,
                            'project_field_value_id' => $pv->id,
                            'document_chunk_id' => $chunkIdInt,
                            'excerpt_text' => $quote,
                            'excerpt_sha256' => hash('sha256', $quote),
                            'start_offset' => $startOffset,
                            'end_offset' => $endOffset,
                        ];
                    }
                }

                // Require evidence for non-empty suggestions.
                if (count($toInsert) === 0) {
                    continue;
                }

                $pv->analysis_run_id = $run->id;
                $pv->suggested_value = $valueText;
                $pv->status = 'suggested';
                $pv->suggested_at = now();
                $pv->confidence = isset($row['confidence']) && is_numeric($row['confidence']) ? (float) $row['confidence'] : null;
                $pv->updated_by_user_id = $actorUserId;
                $pv->save();

                FieldEvidence::query()->where('project_field_value_id', $pv->id)->delete();

                foreach ($toInsert as $rowIns) {
                    FieldEvidence::query()->create($rowIns);
                }
            }
            }

            $run->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'request_payload' => $requestPayloadRedacted,
                'request_payload_enc' => $this->encryptPayload($requestPayloadFull),
                'response_payload' => $this->redactResponsePayload($allResults),
                'response_payload_enc' => $this->encryptPayload(['batches' => $allResults]),
                'payload_enc_key_id' => $payloadEncKeyId,
            ]);

            $project->forceFill([
                'last_analyzed_at' => now(),
                'status' => 'draft',
            ])->save();
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => $e->getMessage(),
                'request_payload' => $requestPayloadRedacted,
                'request_payload_enc' => $this->encryptPayload($requestPayloadFull),
                'response_payload' => $this->redactResponsePayload($allResults),
                'response_payload_enc' => $this->encryptPayload(['batches' => $allResults]),
                'payload_enc_key_id' => $payloadEncKeyId,
            ]);

            throw $e;
        }
    }

    /**
     * @param list<array{field_key: string, label: string}> $fields
     * @param list<array{chunk_id: int, document: string|null, page: int|null, text: string}> $chunks
     */
    private function buildPrompt(array $fields, array $chunks): string
    {
        $expectedFieldKeys = [];
        foreach ($fields as $f) {
            if (isset($f['field_key']) && is_string($f['field_key']) && $f['field_key'] !== '') {
                $expectedFieldKeys[] = $f['field_key'];
            }
        }

        $json = json_encode([
            'task' => 'Fill HRP-503c fields from uploaded project documents',
            'instructions' => [
                'Return ONLY JSON (no markdown).',
                'Preferred response shape: {"schema_version":"irb.first_pass.v2","fields":[{"field_key":string,"value":string,"confidence":number,"evidence":[{"chunk_id":number,"quote":string}]}]}.',
                'If you cannot find support, set value to empty string and evidence to [].',
                'Use only the provided chunks as evidence; cite chunk_id for each field.',
                'If value is non-empty, include at least one evidence row.',
            ],
            'expected_field_keys' => $expectedFieldKeys,
            'fields' => $fields,
            'chunks' => $chunks,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode analysis prompt: '.json_last_error_msg());
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonFromContent(string $content): array
    {
        $content = trim($content);

        $fence = [];
        if (preg_match('~```(?:json)?\s*(\{.*\})\s*```~s', $content, $fence) === 1) {
            $content = trim((string) ($fence[1] ?? $content));
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new \RuntimeException('LLM response is not JSON');
        }

        $slice = substr($content, $start, $end - $start + 1);
        $decoded2 = json_decode($slice, true);
        if (! is_array($decoded2)) {
            throw new \RuntimeException('Failed to parse JSON from LLM response');
        }

        return $decoded2;
    }

    private function scoreChunk(string $label, string $text): int
    {
        $label = strtolower($label);
        $textLower = strtolower($text);

        $words = preg_split('/[^a-z0-9]+/i', $label) ?: [];
        $stop = array_fill_keys([
            'the', 'and', 'or', 'of', 'to', 'a', 'an', 'in', 'on', 'for', 'with', 'by', 'from', 'this', 'that', 'is',
            'section', 'complete', 'provide', 'select', 'choose', 'option',
        ], true);

        $score = 0;
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '' || isset($stop[$w]) || strlen($w) < 3) {
                continue;
            }

            if (str_contains($textLower, $w)) {
                $score += 2;
            }
        }

        return $score;
    }

    private function extractSuggestedValue(string $chunkText): string
    {
        $t = trim($chunkText);
        if ($t === '') {
            return '';
        }

        $parts = preg_split('/\n{2,}/', $t) ?: [];
        $first = trim((string) ($parts[0] ?? ''));
        if ($first === '') {
            $first = $t;
        }

        // Keep suggestions short by default; users can expand/edit.
        if (mb_strlen($first) > 500) {
            $first = mb_substr($first, 0, 500);
        }

        return $first;
    }

    private function extractEvidenceExcerpt(string $chunkText): string
    {
        $t = trim($chunkText);
        if (mb_strlen($t) > 650) {
            return mb_substr($t, 0, 650);
        }

        return $t;
    }

    private function encryptPayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            throw new \RuntimeException('Failed to serialize analysis payload for encryption.');
        }

        return Crypt::encryptString($json);
    }

    private function payloadEncryptionKeyId(): ?string
    {
        $configured = trim((string) env('IRB_DB_PAYLOAD_ENC_KEY_ID', ''));

        return $configured !== '' ? $configured : null;
    }

    private function redactResponsePayload(array $allResults): array
    {
        $batchMeta = [];
        foreach ($allResults as $batch) {
            $rows = $batch['raw']['fields'] ?? [];
            if (! is_array($rows)) {
                $rows = [];
            }

            $fieldKeys = [];
            $evidenceCount = 0;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $key = $row['field_key'] ?? null;
                if (is_string($key) && $key !== '') {
                    $fieldKeys[] = $key;
                }

                $evidence = $row['evidence'] ?? [];
                if (is_array($evidence)) {
                    $evidenceCount += count($evidence);
                }
            }

            $batchMeta[] = [
                'batch' => $batch['batch'] ?? null,
                'rows' => count($rows),
                'field_keys' => $fieldKeys,
                'evidence_count' => $evidenceCount,
            ];
        }

        return [
            'batch_count' => count($allResults),
            'batches' => $batchMeta,
        ];
    }
}
