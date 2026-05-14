<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Models\FormQuestion;
use App\Models\Study;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-004 §H.3
 * Covers SubmissionAnswerController::update() and ::acceptDraft().
 *
 * Note: routes use submission.id (integer) as the UUID parameter because
 * the submission table has no separate uuid column in Phase 3 schema.
 * The controller resolves by findOrFail(int).
 */
class SubmissionAnswerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    private Submission $hrp503cSubmission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Test']);
        $this->hrp503cSubmission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503c'))
            ->firstOrFail();
    }

    // ── update (text answer) ───────────────────────────────────────────────────

    #[Test]
    public function update_saves_text_answer_for_text_question(): void
    {
        $question = $this->findFirstQuestionOfType('textarea');
        $this->assertNotNull($question, 'No textarea question found in HRP-503c seed');

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->hrp503cSubmission->id,
                    'question_key' => $question->question_key,
                ]),
                ['text_value' => 'Test answer text'],
            )
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);

        $this->assertDatabaseHas('submission_answer', [
            'submission_id' => $this->hrp503cSubmission->id,
            'question_key' => $question->question_key,
            'text_value' => 'Test answer text',
        ]);
    }

    #[Test]
    public function update_saves_radio_answer_for_radio_question(): void
    {
        $question = $this->findFirstQuestionOfType('radio_single');

        if ($question === null) {
            $this->markTestSkipped('No radio_single question in HRP-503c seed');
        }

        $firstOption = $question->options()->first();
        $this->assertNotNull($firstOption, 'radio_single question must have options');

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->hrp503cSubmission->id,
                    'question_key' => $question->question_key,
                ]),
                ['option_value' => $firstOption->option_value],
            )
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);

        $this->assertDatabaseHas('submission_answer', [
            'submission_id' => $this->hrp503cSubmission->id,
            'question_key' => $question->question_key,
            'option_value' => $firstOption->option_value,
        ]);
    }

    #[Test]
    public function update_captures_routing_outcome_on_stop_action(): void
    {
        // Find a question whose option has a stop_* action_type
        $stopOption = \App\Models\FormQuestionOption::query()
            ->whereIn('action_type', ['stop_and_submit', 'stop_engaged', 'stop_not_engaged', 'stop_or_skip_to_3.0'])
            ->whereHas('question.section', fn ($q) => $q->where('form_definition_id', $this->hrp503cSubmission->form_definition_id))
            ->first();

        if ($stopOption === null) {
            $this->markTestSkipped('No stop_* option in HRP-503c seed — routing outcome test skipped');
        }

        $question = $stopOption->question;

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->hrp503cSubmission->id,
                    'question_key' => $question->question_key,
                ]),
                ['option_value' => $stopOption->option_value],
            )
            ->assertOk()
            ->assertJsonPath('routing_outcome', $stopOption->action_type);

        $this->assertDatabaseHas('submission', [
            'id' => $this->hrp503cSubmission->id,
            'routing_outcome' => $stopOption->action_type,
            'routing_outcome_at' => $question->question_key,
        ]);
    }

    #[Test]
    public function update_returns_404_for_non_owner(): void
    {
        $other = User::factory()->create(['is_approved' => true]);
        $otherStudy = Study::createForUser($other->id, []);
        $otherSub = $otherStudy->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503c'))
            ->first();

        $question = $this->findFirstQuestionOfType('textarea');
        $this->assertNotNull($question);

        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $otherSub->id,
                    'question_key' => $question->question_key,
                ]),
                ['text_value' => 'Attempt'],
            )
            ->assertNotFound();
    }

    // ── acceptDraft ────────────────────────────────────────────────────────────

    #[Test]
    public function accept_draft_flips_suggestion_source_to_evidence(): void
    {
        $question = $this->findFirstQuestionOfType('textarea');
        $this->assertNotNull($question);

        // Seed an ai_draft answer
        SubmissionAnswer::query()->create([
            'submission_id' => $this->hrp503cSubmission->id,
            'question_key' => $question->question_key,
            'text_value' => 'AI drafted text',
            'suggestion_source' => 'ai_draft',
        ]);

        $this->actingAs($this->user)
            ->postJson(
                route('submissions.answers.accept_draft', [
                    'submission_uuid' => $this->hrp503cSubmission->id,
                    'question_key' => $question->question_key,
                ]),
            )
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);

        $this->assertDatabaseHas('submission_answer', [
            'submission_id' => $this->hrp503cSubmission->id,
            'question_key' => $question->question_key,
            'suggestion_source' => 'evidence',
        ]);
    }

    #[Test]
    public function accept_draft_fails_when_no_ai_draft_exists(): void
    {
        $question = $this->findFirstQuestionOfType('textarea');
        $this->assertNotNull($question);

        $this->actingAs($this->user)
            ->postJson(
                route('submissions.answers.accept_draft', [
                    'submission_uuid' => $this->hrp503cSubmission->id,
                    'question_key' => $question->question_key,
                ]),
            )
            ->assertNotFound();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function findFirstQuestionOfType(string $type): ?FormQuestion
    {
        return FormQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('form_definition_id', $this->hrp503cSubmission->form_definition_id))
            ->where('question_type', $type)
            ->whereNull('parent_question_id')
            ->first();
    }
}
