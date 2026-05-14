<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2\Phase5;

use App\Models\FormQuestion;
use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-005 Phase 5 — REQ-P5-004 Validation scenarios.
 *
 * 7 validator scenarios — one per new question type:
 *   1. checkbox_multi_with_section_triggers
 *   2. radio_with_nested_options
 *   3. numbered_options_with_criteria
 *   4. textarea_with_na_and_followup
 *   5. textarea_with_alternative_radio
 *   6. checkbox_with_optional_textarea
 *   7. group_label (rejects any input)
 *
 * Each scenario tests: valid shape accepts, invalid shape rejects (422).
 */
class Hrp503AnswerValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Validation Test']);
        $this->submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503'))
            ->firstOrFail();
    }

    // ── 1. checkbox_multi_with_section_triggers ────────────────────────────────

    #[Test]
    public function checkbox_multi_with_section_triggers_accepts_valid_option_values(): void
    {
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => 'q2_6',
                ]),
                ['json_value' => ['drugs_biologics', 'devices']],
            )
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);

        $this->assertDatabaseHas('submission_answer', [
            'submission_id' => $this->submission->id,
            'question_key' => 'q2_6',
        ]);
    }

    #[Test]
    public function checkbox_multi_with_section_triggers_rejects_invalid_option_value(): void
    {
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => 'q2_6',
                ]),
                ['json_value' => ['not_a_real_option']],
            )
            ->assertStatus(422);
    }

    // ── 2. radio_with_nested_options ──────────────────────────────────────────

    #[Test]
    public function radio_with_nested_options_accepts_outer_option_value(): void
    {
        $question = $this->findFirstQuestionOfType('radio_with_nested_options');
        $this->assertNotNull($question, 'radio_with_nested_options question must exist in HRP-503');

        $firstOption = $question->options()->first();
        $this->assertNotNull($firstOption, 'radio_with_nested_options must have options');

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['option_value' => $firstOption->option_value],
            )
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);
    }

    #[Test]
    public function radio_with_nested_options_rejects_unknown_value(): void
    {
        $question = $this->findFirstQuestionOfType('radio_with_nested_options');
        $this->assertNotNull($question);

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['option_value' => 'completely_invalid_value_xyz'],
            )
            ->assertStatus(422);
    }

    // ── 3. numbered_options_with_criteria ─────────────────────────────────────

    #[Test]
    public function numbered_options_with_criteria_accepts_valid_array(): void
    {
        $question = $this->findFirstQuestionOfType('numbered_options_with_criteria');
        $this->assertNotNull($question, 'numbered_options_with_criteria must exist in HRP-503');

        $firstOption = $question->options()->first();
        $this->assertNotNull($firstOption);

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['json_value' => [$firstOption->option_value]],
            )
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);
    }

    #[Test]
    public function numbered_options_with_criteria_rejects_invalid_option_id(): void
    {
        $question = $this->findFirstQuestionOfType('numbered_options_with_criteria');
        $this->assertNotNull($question);

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['json_value' => ['option_999_not_real']],
            )
            ->assertStatus(422);
    }

    // ── 4. textarea_with_na_and_followup ──────────────────────────────────────

    #[Test]
    public function textarea_with_na_and_followup_accepts_na_true_shape(): void
    {
        $question = $this->findFirstQuestionOfType('textarea_with_na_and_followup');
        $this->assertNotNull($question, 'textarea_with_na_and_followup must exist in HRP-503');

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['json_value' => ['na' => true, 'text' => null, 'followup' => null]],
            )
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);

        $this->assertDatabaseHas('submission_answer', [
            'submission_id' => $this->submission->id,
            'question_key' => $question->question_key,
        ]);
    }

    #[Test]
    public function textarea_with_na_and_followup_rejects_na_true_with_text(): void
    {
        $question = $this->findFirstQuestionOfType('textarea_with_na_and_followup');
        $this->assertNotNull($question);

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['json_value' => ['na' => true, 'text' => 'Should not be here', 'followup' => null]],
            )
            ->assertStatus(422);
    }

    // ── 5. textarea_with_alternative_radio ────────────────────────────────────

    #[Test]
    public function textarea_with_alternative_radio_accepts_text_mode(): void
    {
        $question = $this->findFirstQuestionOfType('textarea_with_alternative_radio');
        $this->assertNotNull($question, 'textarea_with_alternative_radio must exist in HRP-503');

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['json_value' => ['mode' => 'text', 'text' => 'Some plan text', 'radio' => null]],
            )
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);
    }

    #[Test]
    public function textarea_with_alternative_radio_radio_wins_when_both_provided(): void
    {
        $question = $this->findFirstQuestionOfType('textarea_with_alternative_radio');
        $this->assertNotNull($question);

        // S-P5-7: radio selection wins
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['json_value' => ['mode' => 'radio', 'text' => 'some text', 'radio' => 'na_not_generating']],
            )
            ->assertOk();

        $answer = $this->submission->fresh()->answers->firstWhere('question_key', $question->question_key);
        $this->assertNotNull($answer);
        $jsonValue = $answer->json_value;
        $this->assertIsArray($jsonValue);
        $this->assertSame('radio', $jsonValue['mode']);
        $this->assertNull($jsonValue['text']);
        $this->assertSame('na_not_generating', $jsonValue['radio']);
    }

    #[Test]
    public function textarea_with_alternative_radio_rejects_invalid_mode(): void
    {
        $question = $this->findFirstQuestionOfType('textarea_with_alternative_radio');
        $this->assertNotNull($question);

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['json_value' => ['mode' => 'invalid_mode', 'text' => null, 'radio' => null]],
            )
            ->assertStatus(422);
    }

    // ── 6. checkbox_with_optional_textarea ────────────────────────────────────

    #[Test]
    public function checkbox_with_optional_textarea_accepts_checked_true_with_null_text(): void
    {
        // S-P5-8: checked=true but empty textarea is valid
        $question = $this->findFirstQuestionOfType('checkbox_with_optional_textarea');
        $this->assertNotNull($question, 'checkbox_with_optional_textarea must exist in HRP-503');

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['json_value' => ['checked' => true, 'text' => null]],
            )
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);
    }

    #[Test]
    public function checkbox_with_optional_textarea_rejects_unchecked_with_text(): void
    {
        $question = $this->findFirstQuestionOfType('checkbox_with_optional_textarea');
        $this->assertNotNull($question);

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['json_value' => ['checked' => false, 'text' => 'Cannot have text when unchecked']],
            )
            ->assertStatus(422);
    }

    // ── 7. group_label rejects any submitted answer ────────────────────────────

    #[Test]
    public function group_label_rejects_any_submitted_value(): void
    {
        $question = $this->findFirstQuestionOfType('group_label');
        $this->assertNotNull($question, 'group_label must exist in HRP-503');

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                ['text_value' => 'Should not be accepted'],
            )
            ->assertStatus(422);
    }

    #[Test]
    public function group_label_accepts_empty_payload(): void
    {
        $question = $this->findFirstQuestionOfType('group_label');
        $this->assertNotNull($question);

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => $question->question_key,
                ]),
                [],
            )
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

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
