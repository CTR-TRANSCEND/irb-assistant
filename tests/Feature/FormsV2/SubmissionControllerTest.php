<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-004 §H.2
 * Covers SubmissionController::show() and ::updateAssistanceMode().
 */
class SubmissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Test Study']);
    }

    // ── show ───────────────────────────────────────────────────────────────────

    #[Test]
    public function show_hrp503c_returns_submission_view(): void
    {
        $this->actingAs($this->user)
            ->get(route('submissions.show', ['uuid' => $this->study->uuid, 'form_code' => 'HRP-503c']))
            ->assertOk()
            ->assertViewIs('submissions.show');
    }

    #[Test]
    public function show_hrp398_returns_panel_view(): void
    {
        // Phase 6 (SPEC-IRB-FORMSV2-006): placeholder replaced by hrp398_panel.
        $this->actingAs($this->user)
            ->get(route('submissions.show', ['uuid' => $this->study->uuid, 'form_code' => 'HRP-398']))
            ->assertOk()
            ->assertViewIs('submissions.hrp398_panel');
    }

    #[Test]
    public function show_returns_404_for_non_owner(): void
    {
        $other = User::factory()->create(['is_approved' => true]);
        $otherStudy = Study::createForUser($other->id, []);

        $this->actingAs($this->user)
            ->get(route('submissions.show', ['uuid' => $otherStudy->uuid, 'form_code' => 'HRP-503c']))
            ->assertNotFound();
    }

    // ── updateAssistanceMode ───────────────────────────────────────────────────

    #[Test]
    public function update_assistance_mode_happy_path_strict(): void
    {
        $submission = $this->getHrp503cSubmission();

        $this->actingAs($this->user)
            ->post(route('submissions.assistance_mode', ['uuid' => $this->study->uuid, 'form_code' => 'HRP-503c']), [
                'assistance_mode' => 'strict',
            ])
            ->assertRedirect();

        $this->assertSame('strict', $submission->fresh()->assistance_mode);
    }

    #[Test]
    public function update_assistance_mode_happy_path_assistant(): void
    {
        $this->actingAs($this->user)
            ->post(route('submissions.assistance_mode', ['uuid' => $this->study->uuid, 'form_code' => 'HRP-503c']), [
                'assistance_mode' => 'assistant',
            ])
            ->assertRedirect();

        $submission = $this->getHrp503cSubmission();
        $this->assertSame('assistant', $submission->assistance_mode);
    }

    #[Test]
    public function update_assistance_mode_rejects_invalid_value(): void
    {
        $this->actingAs($this->user)
            ->post(route('submissions.assistance_mode', ['uuid' => $this->study->uuid, 'form_code' => 'HRP-503c']), [
                'assistance_mode' => 'turbo', // invalid
            ])
            ->assertSessionHasErrors('assistance_mode');
    }

    #[Test]
    public function update_assistance_mode_returns_404_for_non_owner(): void
    {
        $other = User::factory()->create(['is_approved' => true]);
        $otherStudy = Study::createForUser($other->id, []);

        $this->actingAs($this->user)
            ->post(route('submissions.assistance_mode', ['uuid' => $otherStudy->uuid, 'form_code' => 'HRP-503c']), [
                'assistance_mode' => 'strict',
            ])
            ->assertNotFound();
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function getHrp503cSubmission(): Submission
    {
        return $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503c'))
            ->firstOrFail();
    }
}
