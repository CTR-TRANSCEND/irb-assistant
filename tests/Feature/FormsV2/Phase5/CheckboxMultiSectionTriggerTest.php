<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2\Phase5;

use App\Models\FormQuestion;
use App\Models\Study;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-005 Phase 5 — S-P5-1 and S-P5-2.
 *
 * Covers:
 *   - Q2.6 trigger fires: selected options reveal downstream sections in nav + content
 *   - Q2.6 deselect re-locks sections but preserves submission_answer rows (REQ-P5-007)
 *   - Q13.2 trigger fires for special populations
 */
class CheckboxMultiSectionTriggerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Trigger Test']);
        $this->submission = $this->getSubmissionByFormCode('HRP-503');
    }

    // ── S-P5-1: Q2.6 reveals sections 3.0 and 4.0 ────────────────────────────

    #[Test]
    public function s_p5_1_q2_6_drugs_and_devices_reveals_two_sections(): void
    {
        $q26 = $this->findQuestion('q2_6');
        $this->assertNotNull($q26, 'q2_6 must be present in HRP-503 seed');

        // Submit Q2.6 with drugs_biologics and devices
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => 'q2_6',
                ]),
                ['json_value' => ['drugs_biologics', 'devices']],
            )
            ->assertOk();

        // Fetch the submission show page and assert section visibility
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk();

        $sectionVisibility = $response->viewData('sectionVisibility');

        $this->assertTrue($sectionVisibility['3.0'] ?? false, 'Section 3.0 should be visible after Q2.6 drugs_biologics');
        $this->assertTrue($sectionVisibility['4.0'] ?? false, 'Section 4.0 should be visible after Q2.6 devices');
        $this->assertFalse($sectionVisibility['5.0'] ?? true, 'Section 5.0 should remain locked');
        $this->assertFalse($sectionVisibility['12.0'] ?? true, 'Section 12.0 should remain locked');
    }

    // ── S-P5-2: Q2.6 deselect re-locks section but preserves answers ──────────

    #[Test]
    public function s_p5_2_q2_6_deselect_relocks_but_preserves_answers(): void
    {
        // Step 1: Select drugs_biologics to reveal section 3.0
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => 'q2_6',
                ]),
                ['json_value' => ['drugs_biologics']],
            )
            ->assertOk();

        // Step 2: Seed an answer in section 3.0 (any question in that section).
        // QA review F-QA-2: assert the question exists so the REQ-P5-007
        // preservation check below cannot silently no-op.
        $q3Question = $this->findQuestionInSection('3.0');
        $this->assertNotNull(
            $q3Question,
            'Section 3.0 must have at least one non-group_label question seeded for REQ-P5-007 to be verifiable',
        );
        SubmissionAnswer::query()->updateOrCreate(
            ['submission_id' => $this->submission->id, 'question_key' => $q3Question->question_key],
            ['text_value' => 'Preserved answer', 'suggestion_source' => null],
        );

        // Step 3: Deselect Q2.6 → empty array
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => 'q2_6',
                ]),
                ['json_value' => []],
            )
            ->assertOk();

        // Step 4: Section 3.0 must be locked
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk();

        $sectionVisibility = $response->viewData('sectionVisibility');
        $this->assertFalse($sectionVisibility['3.0'] ?? true, 'Section 3.0 should be locked after Q2.6=[]');

        // Step 5: REQ-P5-007 — section 3.0 answers must still exist in the DB
        $this->assertDatabaseHas('submission_answer', [
            'submission_id' => $this->submission->id,
            'question_key' => $q3Question->question_key,
            'text_value' => 'Preserved answer',
        ]);

        // Step 6: Re-select drugs_biologics → answers re-appear
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => 'q2_6',
                ]),
                ['json_value' => ['drugs_biologics']],
            )
            ->assertOk();

        $response2 = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk();

        $sectionVisibility2 = $response2->viewData('sectionVisibility');
        $this->assertTrue($sectionVisibility2['3.0'] ?? false, 'Section 3.0 should be visible again after re-selecting drugs_biologics');
    }

    // ── Q13.2 — special population trigger ────────────────────────────────────

    #[Test]
    public function s_p5_3_q13_2_pregnant_reveals_section_14_0(): void
    {
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => 'q13_2',
                ]),
                ['json_value' => ['pregnant', 'children']],
            )
            ->assertOk();

        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk();

        $sectionVisibility = $response->viewData('sectionVisibility');
        $this->assertTrue($sectionVisibility['14.0'] ?? false, 'Section 14.0 should be visible for pregnant');
        $this->assertTrue($sectionVisibility['16.0'] ?? false, 'Section 16.0 should be visible for children');
        $this->assertFalse($sectionVisibility['18.0'] ?? true, 'Section 18.0 (prisoners) should remain locked');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function getSubmissionByFormCode(string $formCode): Submission
    {
        return $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', $formCode))
            ->firstOrFail();
    }

    private function findQuestion(string $questionKey): ?FormQuestion
    {
        return FormQuestion::query()
            ->whereHas('section', fn ($q) => $q->whereHas(
                'formDefinition',
                fn ($fd) => $fd->where('form_code', 'HRP-503'),
            ))
            ->where('question_key', $questionKey)
            ->first();
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
