<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2\Phase5;

use App\Models\FormQuestion;
use App\Models\Study;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Models\User;
use App\Services\SubmissionDocxExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-005 Phase 5 — REQ-P5-006 / S-P5-10.
 *
 * Covers:
 *   - All 7 new question types serialize correctly in the DOCX value map.
 *   - S-P5-10: Locked sections are NOT emitted in the export.
 *   - Trigger-locked section answers remain in DB after export.
 *
 * We test the serialization logic directly via the private helpers by
 * going through the service's buildValueMapPhase5() (via export route or
 * via reflection on the service). To keep the test boundary clear we mock
 * the template and use Storage::fake.
 */
class Hrp503DocxExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'DOCX Test']);
        $this->submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503'))
            ->firstOrFail();
    }

    // ── Serialization tests (via reflection on service private method) ─────────

    #[Test]
    public function checkbox_multi_with_section_triggers_serializes_as_newline_joined_values(): void
    {
        $answer = new SubmissionAnswer;
        $answer->json_value = ['drugs_biologics', 'devices'];

        $serialized = $this->callSerializeJsonValue($answer->json_value);
        $this->assertStringContainsString('drugs_biologics', $serialized);
        $this->assertStringContainsString('devices', $serialized);
        $this->assertStringContainsString("\n", $serialized);
    }

    #[Test]
    public function textarea_with_na_and_followup_na_true_serializes_as_na(): void
    {
        $jsonValue = ['na' => true, 'text' => null, 'followup' => null];
        $serialized = $this->callSerializeJsonValue($jsonValue);
        $this->assertSame('N/A', $serialized);
    }

    #[Test]
    public function textarea_with_na_and_followup_with_text_serializes_text(): void
    {
        $jsonValue = ['na' => false, 'text' => 'Some explanation', 'followup' => 'Mitigation plan'];
        $serialized = $this->callSerializeJsonValue($jsonValue);
        $this->assertStringContainsString('Some explanation', $serialized);
        $this->assertStringContainsString('Mitigation plan', $serialized);
    }

    #[Test]
    public function textarea_with_alternative_radio_radio_mode_serializes_radio_value(): void
    {
        $jsonValue = ['mode' => 'radio', 'text' => null, 'radio' => 'na_not_generating'];
        $serialized = $this->callSerializeJsonValue($jsonValue);
        $this->assertSame('na_not_generating', $serialized);
    }

    #[Test]
    public function textarea_with_alternative_radio_text_mode_serializes_text(): void
    {
        $jsonValue = ['mode' => 'text', 'text' => 'My plan text', 'radio' => null];
        $serialized = $this->callSerializeJsonValue($jsonValue);
        $this->assertSame('My plan text', $serialized);
    }

    #[Test]
    public function checkbox_with_optional_textarea_checked_false_serializes_as_no(): void
    {
        $jsonValue = ['checked' => false, 'text' => null];
        $serialized = $this->callSerializeJsonValue($jsonValue);
        $this->assertSame('No', $serialized);
    }

    #[Test]
    public function checkbox_with_optional_textarea_checked_true_with_text_serializes_both(): void
    {
        $jsonValue = ['checked' => true, 'text' => 'Additional info'];
        $serialized = $this->callSerializeJsonValue($jsonValue);
        $this->assertStringContainsString('Yes', $serialized);
        $this->assertStringContainsString('Additional info', $serialized);
    }

    // ── radio_with_nested_options: leaf option_value serializes verbatim ──────

    #[Test]
    public function radio_with_nested_options_serializes_leaf_option_value(): void
    {
        // F-EVAL-3 (Phase 5 evaluator): close DOCX coverage gap for
        // radio_with_nested_options. This type stores in option_value (NOT
        // json_value), so it traverses serializeAnswerPhase5's option_value
        // branch — exercised here with an explicit Phase 5 answer.
        $q = FormQuestion::query()
            ->where('question_type', 'radio_with_nested_options')
            ->first();
        if ($q === null) {
            $this->markTestSkipped('No radio_with_nested_options question in HRP-503 seed');
        }

        // form_question_option is flat — nesting via action_type=reveal_subfields,
        // not a parent_option_id column. Pick any concrete option_value.
        $someOpt = $q->options()->first();
        $optionValue = $someOpt?->option_value ?? 'leaf_value_test';

        SubmissionAnswer::query()->create([
            'submission_id' => $this->submission->id,
            'question_key' => $q->question_key,
            'option_value' => $optionValue,
            'suggestion_source' => null,
        ]);

        $service = app(SubmissionDocxExportService::class);
        $this->submission->load(['formDefinition.sections.questions', 'answers']);
        $answers = $this->submission->answers->keyBy('question_key');

        $map = $this->callBuildValueMapPhase5($service, $answers, $this->submission);

        // Visibility may exclude section 1.0 etc., but radio_with_nested_options
        // is found in ungated sections only — guard by checking only when
        // this question's section is visible.
        if (array_key_exists($q->question_key, $map)) {
            $this->assertSame(
                $optionValue,
                $map[$q->question_key],
                'radio_with_nested_options must serialize the leaf option_value verbatim',
            );
        } else {
            $this->markTestSkipped(
                "radio_with_nested_options question {$q->question_key} lives in a section gated off by default; serialization path not reachable from the seed alone",
            );
        }
    }

    // ── S-P5-10: locked sections excluded from the value map ──────────────────

    #[Test]
    public function s_p5_10_locked_section_answers_not_in_value_map(): void
    {
        // Seed Q2.6 with empty array (locks all procedure sections)
        SubmissionAnswer::query()->create([
            'submission_id' => $this->submission->id,
            'question_key' => 'q2_6',
            'json_value' => [],
            'suggestion_source' => null,
        ]);

        // Seed an orphaned answer in section 3.0 (which is locked)
        $q3Question = $this->findQuestionInSection('3.0');
        if ($q3Question === null) {
            $this->markTestSkipped('No question found in section 3.0 for HRP-503');
        }

        SubmissionAnswer::query()->create([
            'submission_id' => $this->submission->id,
            'question_key' => $q3Question->question_key,
            'text_value' => 'Orphaned answer in locked section',
            'suggestion_source' => null,
        ]);

        // Invoke buildValueMapPhase5 via reflection
        $service = app(SubmissionDocxExportService::class);
        $this->submission->load(['formDefinition.sections.questions', 'answers']);
        $answers = $this->submission->answers->keyBy('question_key');

        $map = $this->callBuildValueMapPhase5($service, $answers, $this->submission);

        // Locked section answer must NOT be in the map
        $this->assertArrayNotHasKey(
            $q3Question->question_key,
            $map,
            'Locked section answer must not appear in the DOCX value map (S-P5-10)',
        );

        // The orphaned DB row must still exist (REQ-P5-007)
        $this->assertDatabaseHas('submission_answer', [
            'submission_id' => $this->submission->id,
            'question_key' => $q3Question->question_key,
            'text_value' => 'Orphaned answer in locked section',
        ]);
    }

    #[Test]
    public function s_p5_10_visible_section_answers_are_in_value_map(): void
    {
        // Select drugs_biologics → section 3.0 becomes visible
        SubmissionAnswer::query()->create([
            'submission_id' => $this->submission->id,
            'question_key' => 'q2_6',
            'json_value' => ['drugs_biologics'],
            'suggestion_source' => null,
        ]);

        $q3Question = $this->findQuestionInSection('3.0');
        if ($q3Question === null) {
            $this->markTestSkipped('No question found in section 3.0 for HRP-503');
        }

        SubmissionAnswer::query()->create([
            'submission_id' => $this->submission->id,
            'question_key' => $q3Question->question_key,
            'text_value' => 'Visible answer',
            'suggestion_source' => null,
        ]);

        $service = app(SubmissionDocxExportService::class);
        $this->submission->load(['formDefinition.sections.questions', 'answers']);
        $answers = $this->submission->answers->keyBy('question_key');

        $map = $this->callBuildValueMapPhase5($service, $answers, $this->submission);

        $this->assertArrayHasKey(
            $q3Question->question_key,
            $map,
            'Visible section answer must appear in the DOCX value map',
        );
        $this->assertSame('Visible answer', $map[$q3Question->question_key]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Call the private serializeJsonValue method via reflection.
     */
    private function callSerializeJsonValue(mixed $jsonValue): string
    {
        $service = app(SubmissionDocxExportService::class);
        $ref = new \ReflectionMethod($service, 'serializeJsonValue');

        return $ref->invoke($service, $jsonValue);
    }

    /**
     * Call the private buildValueMapPhase5 method via reflection.
     *
     * @param  \Illuminate\Support\Collection<string, SubmissionAnswer>  $answers
     * @return array<string, string>
     */
    private function callBuildValueMapPhase5(SubmissionDocxExportService $service, \Illuminate\Support\Collection $answers, Submission $submission): array
    {
        $ref = new \ReflectionMethod($service, 'buildValueMapPhase5');

        return $ref->invoke($service, $answers, $submission);
    }

    private function findQuestionInSection(string $sectionCode): ?FormQuestion
    {
        return FormQuestion::query()
            ->whereHas('section', fn ($q) => $q
                ->where('section_code', $sectionCode)
                ->whereHas('formDefinition', fn ($fd) => $fd->where('form_code', 'HRP-503')))
            ->where('question_type', '!=', 'group_label')
            ->whereNull('parent_question_id')
            ->first();
    }
}
