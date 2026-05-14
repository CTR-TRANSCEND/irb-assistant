<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Exceptions\InvalidSubmissionStateTransition;
use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies HRP-398 tracking_only is a terminal status.
 *
 * SPEC-IRB-FORMSV2-003: Hrp398TrackingOnlyTest
 * Scenario S-P3-3: HRP-398 tracking_only is terminal.
 * Cites REQ-IRB-FORMSV2-014a.
 */
class Hrp398TrackingOnlyTest extends TestCase
{
    use RefreshDatabase;

    private Submission $hrp398Submission;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['is_approved' => true]);
        $study = Study::createForUser($user->id, ['pi_name' => 'Dr. Test',
        ]);

        // Find the auto-created HRP-398 submission
        $this->hrp398Submission = $study->submissions()
            ->join('form_definition', 'submission.form_definition_id', '=', 'form_definition.id')
            ->where('form_definition.form_code', 'HRP-398')
            ->select('submission.*')
            ->first();
    }

    #[Test]
    public function hrp398_submission_has_tracking_only_status(): void
    {
        $this->assertEquals('tracking_only', $this->hrp398Submission->status);
    }

    #[Test]
    public function mark_status_draft_throws_for_tracking_only(): void
    {
        $this->expectException(InvalidSubmissionStateTransition::class);
        $this->hrp398Submission->markStatus('draft');
    }

    #[Test]
    public function mark_status_submitted_throws_for_tracking_only(): void
    {
        $this->expectException(InvalidSubmissionStateTransition::class);
        $this->hrp398Submission->markStatus('submitted');
    }

    #[Test]
    public function mark_status_approved_throws_for_tracking_only(): void
    {
        $this->expectException(InvalidSubmissionStateTransition::class);
        $this->hrp398Submission->markStatus('approved');
    }

    #[Test]
    public function mark_status_withdrawn_throws_for_tracking_only(): void
    {
        $this->expectException(InvalidSubmissionStateTransition::class);
        $this->hrp398Submission->markStatus('withdrawn');
    }

    #[Test]
    public function status_remains_tracking_only_after_failed_transition(): void
    {
        try {
            $this->hrp398Submission->markStatus('draft');
        } catch (InvalidSubmissionStateTransition) {
            // Expected
        }

        // Reload from DB
        $fresh = Submission::find($this->hrp398Submission->id);
        $this->assertEquals('tracking_only', $fresh->status, 'Status changed despite rejected transition');
    }

    #[Test]
    public function hrp503_submission_can_transition_to_submitted(): void
    {
        // Verify that non-tracking_only submissions CAN transition
        $user = User::factory()->create(['is_approved' => true]);
        $study = Study::createForUser($user->id, []);

        $hrp503Submission = $study->submissions()
            ->join('form_definition', 'submission.form_definition_id', '=', 'form_definition.id')
            ->where('form_definition.form_code', 'HRP-503')
            ->select('submission.*')
            ->first();

        $hrp503Submission->markStatus('submitted');
        $this->assertEquals('submitted', Submission::find($hrp503Submission->id)->status);
    }
}
