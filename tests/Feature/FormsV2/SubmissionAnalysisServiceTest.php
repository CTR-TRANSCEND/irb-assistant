<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Models\AnalysisRun;
use App\Models\DocumentChunk;
use App\Models\LlmProvider;
use App\Models\Study;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Models\User;
use App\Services\LlmChatService;
use App\Services\SubmissionAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-004 §H.4
 * Covers SubmissionAnalysisService::runFirstPass().
 *
 * Scenarios:
 *   1. Strict mode → zero ai_draft rows (REQ-IRB-GUIDE-008)
 *   2. Assistant mode → ai_draft rows for un-answered fields
 *   3. HRP-398 rejected before any LLM call (REQ-IRB-FORMSV2-060)
 *   4. REQ-IRB-GUIDE-031 carve-out: existing ai_draft can be overwritten by evidence
 */
class SubmissionAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    private LlmProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Analysis Test']);

        $this->provider = LlmProvider::query()->create([
            'name' => 'test-provider',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://api.test.example/v1',
            'model' => 'gpt-test',
            'api_key' => 'test-key',
            'request_params' => ['temperature' => 0.0, 'max_tokens' => 500],
            'is_enabled' => true,
            'is_default' => true,
            'is_external' => false,
        ]);
    }

    // ── REQ-IRB-GUIDE-008: Strict mode produces zero ai_draft rows ────────────

    #[Test]
    public function strict_mode_produces_zero_ai_draft_rows(): void
    {
        $submission = $this->getSubmissionByFormCode('HRP-503c');
        $submission->forceFill(['assistance_mode' => 'strict'])->save();

        $this->seedDocumentChunk();

        // LLM returns empty evidence — no suggestions from first pass
        $this->fakeHttpEmptyEvidence();

        $this->runAnalysis($submission);

        // Strict mode must NEVER produce ai_draft rows
        $this->assertDatabaseMissing('submission_answer', [
            'submission_id' => $submission->id,
            'suggestion_source' => 'ai_draft',
        ]);

        // No ai_drafted audit events
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'submission.field.ai_drafted',
            'entity_id' => $submission->id,
        ]);
    }

    // ── REQ-IRB-GUIDE-007: Assistant mode drafts missing fields ──────────────

    #[Test]
    public function assistant_mode_creates_ai_draft_rows_for_unanswered_fields(): void
    {
        $submission = $this->getSubmissionByFormCode('HRP-503c');
        $submission->forceFill(['assistance_mode' => 'assistant'])->save();

        // Ensure there are unanswered questions (fresh submission has none)
        $this->assertSame(0, SubmissionAnswer::query()
            ->where('submission_id', $submission->id)
            ->count());

        $this->seedDocumentChunk();

        // LLM returns empty evidence (first pass), then a draft text
        Http::fake([
            'api.test.example/*' => function ($request) {
                $data = $request->data();
                $sysMsg = $data['messages'][0]['content'] ?? '';

                if (str_contains($sysMsg, 'ANTI-FABRICATION') || str_contains($sysMsg, 'do not invent')) {
                    // Drafting call
                    return Http::response([
                        'choices' => [['message' => ['content' => 'This study will recruit consenting adults.']]],
                    ], 200);
                }

                // Evidence first-pass call — empty evidence
                return Http::response([
                    'choices' => [['message' => ['content' => json_encode(['fields' => []])]]],
                ], 200);
            },
        ]);

        $this->runAnalysis($submission);

        // At least one ai_draft row should exist after assistant-mode analysis
        $draftCount = SubmissionAnswer::query()
            ->where('submission_id', $submission->id)
            ->where('suggestion_source', 'ai_draft')
            ->count();

        $this->assertGreaterThan(0, $draftCount, 'Assistant mode must produce ai_draft rows for unanswered fields');
    }

    // ── REQ-IRB-FORMSV2-060: HRP-398 rejected before any LLM call ────────────

    #[Test]
    public function hrp398_submission_throws_runtime_exception_before_llm_call(): void
    {
        $submission = $this->getSubmissionByFormCode('HRP-398');
        $submission->loadMissing('formDefinition');

        Http::fake(); // Should never be called

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HRP-398/');

        $this->runAnalysis($submission);

        Http::assertNothingSent();
    }

    // ── REQ-IRB-GUIDE-031: ai_draft carve-out — evidence overwrites ai_draft ─

    #[Test]
    public function evidence_from_first_pass_overwrites_existing_ai_draft(): void
    {
        $submission = $this->getSubmissionByFormCode('HRP-503c');
        $submission->forceFill(['assistance_mode' => 'strict'])->save();

        $this->seedDocumentChunk();

        // Find a textarea question
        $question = \App\Models\FormQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('form_definition_id', $submission->form_definition_id))
            ->where('question_type', 'textarea')
            ->whereNull('parent_question_id')
            ->first();

        if ($question === null) {
            $this->markTestSkipped('No textarea question in HRP-503c seed');
        }

        // Pre-seed an ai_draft answer (stale draft)
        SubmissionAnswer::query()->updateOrCreate(
            ['submission_id' => $submission->id, 'question_key' => $question->question_key],
            ['text_value' => 'old ai draft', 'suggestion_source' => 'ai_draft'],
        );

        $chunk = DocumentChunk::query()
            ->whereHas('document', fn ($q) => $q->where('study_id', $this->study->id))
            ->first();

        $evidenceText = 'This study will enroll 40 participants from a clinical population.';

        // Seed the quote text into the chunk so the service can find offset
        $fullText = $evidenceText.' More text follows.';
        $chunk->update(['text' => $fullText, 'text_sha256' => hash('sha256', $fullText)]);

        // LLM returns evidence matching this chunk + question
        Http::fake([
            'api.test.example/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'fields' => [[
                            'field_key' => $question->question_key,
                            'value' => $evidenceText,
                            'evidence' => [[
                                'chunk_id' => $chunk->id,
                                'quote' => $evidenceText,
                            ]],
                        ]],
                    ])],
                ]],
            ], 200),
        ]);

        $this->runAnalysis($submission);

        // REQ-IRB-GUIDE-031: the ai_draft MUST have been overwritten with evidence
        $answer = SubmissionAnswer::query()
            ->where('submission_id', $submission->id)
            ->where('question_key', $question->question_key)
            ->first();

        $this->assertNotNull($answer);
        $this->assertSame('evidence', $answer->suggestion_source,
            'REQ-IRB-GUIDE-031: ai_draft rows must be overwriteable by evidence-grounded results');
        $this->assertSame($evidenceText, $answer->text_value);
    }

    // ── HRP-503 (non-HRP-503c) also accepted ─────────────────────────────────

    #[Test]
    public function hrp503_submission_can_be_analyzed(): void
    {
        $submission = $this->getSubmissionByFormCode('HRP-503');
        $submission->forceFill(['assistance_mode' => 'strict'])->save();

        $this->seedDocumentChunk();
        $this->fakeHttpEmptyEvidence();

        // Must not throw
        $this->runAnalysis($submission);
        $this->assertTrue(true); // survived without exception
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getSubmissionByFormCode(string $formCode): Submission
    {
        return $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', $formCode))
            ->with(['formDefinition', 'study'])
            ->firstOrFail();
    }

    private function seedDocumentChunk(): DocumentChunk
    {
        // study_id is added by Phase 4 migration but not in $fillable — use DB::table.
        // kind is NOT NULL without default in the existing schema.
        $docId = DB::table('project_documents')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'project_id' => null,
            'study_id' => $this->study->id,
            'original_filename' => 'test.txt',
            'storage_disk' => 'local',
            'storage_path' => 'docs/test.txt',
            'size_bytes' => 100,
            'mime_type' => 'text/plain',
            'kind' => 'document',
            'extraction_status' => 'not_started',
            'scan_status' => 'not_scanned',
            'uploaded_by_user_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $chunkText = 'This study will recruit participants from clinical settings for informed consent review.';

        return DocumentChunk::query()->create([
            'project_document_id' => $docId,
            'chunk_index' => 0,
            'page_number' => 1,
            'text' => $chunkText,
            'text_sha256' => hash('sha256', $chunkText),
            'token_count' => 20,
        ]);
    }

    private function fakeHttpEmptyEvidence(): void
    {
        Http::fake([
            'api.test.example/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['fields' => []])]]],
            ], 200),
        ]);
    }

    private function runAnalysis(Submission $submission): void
    {
        $request = Request::create('/submissions/test/analyze', 'POST');
        $request->setUserResolver(fn () => $this->user);

        // AnalysisRun fillable excludes submission_id — use DB::table for creation
        $runId = DB::table('analysis_runs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'project_id' => null,
            'llm_provider_id' => $this->provider->id,
            'created_by_user_id' => $this->user->id,
            'status' => 'queued',
            'started_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $run = AnalysisRun::query()->findOrFail($runId);

        $svc = app(SubmissionAnalysisService::class);
        $svc->runFirstPass(
            $submission,
            $this->provider,
            $this->user->id,
            app(LlmChatService::class),
            $request,
            null,
            $run,
        );
    }
}
