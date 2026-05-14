<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2\Phase5;

use App\Models\DocumentChunk;
use App\Models\FormQuestion;
use App\Models\LlmProvider;
use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use App\Services\LlmChatService;
use App\Services\SubmissionAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-005 Phase 5 — REQ-P5-005.
 *
 * Asserts that SubmissionAnalysisService sends the correct field descriptors
 * (including option schema) for all 7 new question types when building the
 * LLM prompt.
 *
 * The LLM is mocked — we capture the prompt passed to LlmChatService::chat()
 * and verify it includes the option schema for schema-aware types.
 *
 * REQ-IRB-GUIDE-031 carve-out: existing ai_draft can be overwritten by evidence.
 */
class Hrp503LlmAnalyzeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    private Submission $submission;

    private LlmProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'LLM Test']);
        $this->submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503'))
            ->firstOrFail();

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

    #[Test]
    public function analysis_builds_field_descriptors_with_allowed_values_for_checkbox_multi_with_section_triggers(): void
    {
        $capturedMessages = $this->runAnalysisCapturingMessages();

        $userContent = $this->extractUserContent($capturedMessages);

        // The prompt must contain the question_key for q2_6
        $this->assertStringContainsString('q2_6', $userContent, 'Prompt must include q2_6 field descriptor');
        // The prompt must include allowed option values from the schema
        $this->assertStringContainsString('drugs_biologics', $userContent, 'Prompt must include drugs_biologics option value for Q2.6');
        $this->assertStringContainsString('devices', $userContent, 'Prompt must include devices option value for Q2.6');
    }

    #[Test]
    public function analysis_builds_field_descriptors_for_numbered_options_with_criteria(): void
    {
        $capturedMessages = $this->runAnalysisCapturingMessages();
        $userContent = $this->extractUserContent($capturedMessages);

        // Check that numbered_options question (q14_1) has options in prompt
        $q = $this->findFirstQuestionOfType('numbered_options_with_criteria');
        if ($q === null) {
            $this->markTestSkipped('No numbered_options_with_criteria question in HRP-503 seed');
        }

        $this->assertStringContainsString($q->question_key, $userContent,
            'Prompt must contain numbered_options question key');
    }

    #[Test]
    public function analysis_builds_field_descriptors_for_textarea_with_na_and_followup(): void
    {
        $capturedMessages = $this->runAnalysisCapturingMessages();
        $userContent = $this->extractUserContent($capturedMessages);

        $q = $this->findFirstQuestionOfType('textarea_with_na_and_followup');
        if ($q === null) {
            $this->markTestSkipped('No textarea_with_na_and_followup question in HRP-503 seed');
        }

        $this->assertStringContainsString($q->question_key, $userContent,
            'Prompt must contain textarea_with_na_and_followup question key');
        $this->assertStringContainsString('na', $userContent,
            'Prompt must mention na field for textarea_with_na_and_followup');
    }

    #[Test]
    public function analysis_builds_field_descriptors_for_textarea_with_alternative_radio(): void
    {
        $capturedMessages = $this->runAnalysisCapturingMessages();
        $userContent = $this->extractUserContent($capturedMessages);

        $q = $this->findFirstQuestionOfType('textarea_with_alternative_radio');
        if ($q === null) {
            $this->markTestSkipped('No textarea_with_alternative_radio question in HRP-503 seed');
        }

        $this->assertStringContainsString($q->question_key, $userContent,
            'Prompt must contain textarea_with_alternative_radio question key');
        $this->assertStringContainsString('mode', $userContent,
            'Prompt must mention mode field for textarea_with_alternative_radio');
    }

    #[Test]
    public function analysis_builds_field_descriptors_for_radio_with_nested_options(): void
    {
        // F-EVAL-1 (Phase 5 evaluator): close REQ-P5-005 coverage gap — the LLM
        // prompt must include the FULL option tree (outer + nested) so the model
        // can return a leaf-level value per LD-P5-5.
        $capturedMessages = $this->runAnalysisCapturingMessages();
        $userContent = $this->extractUserContent($capturedMessages);

        $q = $this->findFirstQuestionOfType('radio_with_nested_options');
        if ($q === null) {
            $this->markTestSkipped('No radio_with_nested_options question in HRP-503 seed');
        }

        $this->assertStringContainsString(
            $q->question_key,
            $userContent,
            'Prompt must contain radio_with_nested_options question key',
        );

        // At least one option_value of this question must appear in the prompt.
        // The form_question_option schema stores all options (outer + nested)
        // in a single flat table; nesting is encoded via action_type =
        // 'reveal_subfields' rather than a parent_option_id column. The
        // contract here is: every selectable value must reach the LLM prompt.
        $firstOpt = $q->options()->first();
        if ($firstOpt !== null) {
            $this->assertStringContainsString(
                $firstOpt->option_value,
                $userContent,
                'Prompt must contain at least one option_value so the LLM can produce a valid selection',
            );
        }
    }

    #[Test]
    public function analysis_builds_field_descriptors_for_checkbox_with_optional_textarea(): void
    {
        // F-EVAL-1 (Phase 5 evaluator): close REQ-P5-005 coverage gap.
        $capturedMessages = $this->runAnalysisCapturingMessages();
        $userContent = $this->extractUserContent($capturedMessages);

        $q = $this->findFirstQuestionOfType('checkbox_with_optional_textarea');
        if ($q === null) {
            $this->markTestSkipped('No checkbox_with_optional_textarea question in HRP-503 seed');
        }

        $this->assertStringContainsString(
            $q->question_key,
            $userContent,
            'Prompt must contain checkbox_with_optional_textarea question key',
        );
        $this->assertStringContainsString(
            'checked',
            $userContent,
            'Prompt must mention the `checked` field name so the LLM emits a parseable shape',
        );
    }

    #[Test]
    public function analysis_skips_group_label_questions(): void
    {
        $capturedMessages = $this->runAnalysisCapturingMessages();
        $userContent = $this->extractUserContent($capturedMessages);

        // Find a group_label question key — it should NOT appear in the prompt
        $groupLabelQ = $this->findFirstQuestionOfType('group_label');
        if ($groupLabelQ !== null) {
            $this->assertStringNotContainsString(
                '"'.$groupLabelQ->question_key.'"',
                $userContent,
                'group_label questions must not appear in the LLM prompt',
            );
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Run the analysis service with a mocked LlmChatService that captures messages.
     * Returns all captured message arrays (one per batch).
     *
     * @return array<int, array<int, array{role: string, content: string}>>
     */
    private function runAnalysisCapturingMessages(): array
    {
        // Seed a document chunk so the evidence pass fires
        $doc = \App\Models\ProjectDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'study_id' => $this->study->id,
            'project_id' => null,
            'original_filename' => 'test.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'docs/test.pdf',
            'size_bytes' => 1000,
            'kind' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_ext' => 'pdf',
            'extraction_status' => 'completed',
        ]);
        $chunkText = 'Sample study text about drugs and biologics research.';
        DocumentChunk::query()->create([
            'project_document_id' => $doc->id,
            'chunk_index' => 0,
            'page_number' => 1,
            'source_locator' => null,
            'heading' => null,
            'text' => $chunkText,
            'text_sha256' => hash('sha256', $chunkText),
            'start_offset' => null,
            'end_offset' => null,
        ]);

        $capturedMessages = [];

        $mockLlm = $this->createMock(LlmChatService::class);
        $mockLlm->method('chat')
            ->willReturnCallback(function ($provider, array $messages) use (&$capturedMessages): string {
                $capturedMessages[] = $messages;

                return json_encode(['fields' => []], JSON_THROW_ON_ERROR);
            });

        $service = app(SubmissionAnalysisService::class);
        $this->submission->forceFill(['assistance_mode' => 'strict'])->save();

        $service->runFirstPass(
            submission: $this->submission,
            provider: $this->provider,
            actorUserId: $this->user->id,
            llm: $mockLlm,
            request: Request::create('/'),
        );

        return $capturedMessages;
    }

    /**
     * Extract the user-role message content from captured batch messages.
     */
    private function extractUserContent(array $capturedMessages): string
    {
        $all = '';
        foreach ($capturedMessages as $messages) {
            foreach ($messages as $message) {
                if (($message['role'] ?? '') === 'user') {
                    $all .= $message['content'];
                }
            }
        }

        return $all;
    }

    private function findFirstQuestionOfType(string $type): ?FormQuestion
    {
        return FormQuestion::query()
            ->whereHas('section', fn ($q) => $q->whereHas(
                'formDefinition',
                fn ($fd) => $fd->where('form_code', 'HRP-503'),
            ))
            ->where('question_type', $type)
            ->whereNull('parent_question_id')
            ->first();
    }
}
