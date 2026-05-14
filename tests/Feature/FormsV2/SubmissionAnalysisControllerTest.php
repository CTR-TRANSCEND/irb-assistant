<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Models\LlmProvider;
use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Outstanding #72 — Analyze endpoint must return JSON to XHR clients
 * but redirect to the submission Review tab for standard form POSTs so
 * the existing _progress_modal.blade.php auto-opens via bootstrap().
 *
 * Covers SubmissionAnalysisController::analyze() response-shape branching.
 */
class SubmissionAnalysisControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    private Submission $hrp503cSubmission;

    private Submission $hrp398Submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Test Study']);
        $this->hrp503cSubmission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503c'))
            ->firstOrFail();
        $this->hrp398Submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-398'))
            ->firstOrFail();

        // Minimal enabled provider so analyze() does not 422 on missing provider.
        LlmProvider::query()->create([
            'name' => 'test-provider',
            'provider_type' => 'openai_compatible',
            'base_url' => 'http://127.0.0.1:9999',
            'is_enabled' => true,
            'is_external' => false,
            'is_default' => true,
            'model' => 'test-model',
            'api_key' => null,
        ]);

        Queue::fake();
    }

    // ── A1 (#72) Non-XHR path → 302 redirect + flash status ────────────────────

    #[Test]
    public function analyze_non_xhr_post_redirects_back_to_submission_with_flash(): void
    {
        $response = $this->actingAs($this->user)
            ->from(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503c',
            ]))
            ->post(route('submissions.analyze', [
                'submission_uuid' => $this->hrp503cSubmission->id,
            ]));

        $response->assertRedirect(route('submissions.show', [
            'uuid' => $this->study->uuid,
            'form_code' => 'HRP-503c',
        ]));
        // Pin the exact flash text — the modal's bootstrap() relies on this being
        // truthy/non-empty for the success toast to render.
        $response->assertSessionHas('status', 'Analysis queued — the progress modal will open shortly.');

        $this->assertDatabaseHas('analysis_runs', [
            'submission_id' => $this->hrp503cSubmission->id,
            'status' => 'queued',
        ]);
    }

    #[Test]
    public function analyze_xhr_post_still_returns_json_payload(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('submissions.analyze', [
                'submission_uuid' => $this->hrp503cSubmission->id,
            ]));

        $response->assertOk();
        $response->assertJsonStructure(['run_id', 'status']);
        $response->assertJsonFragment(['status' => 'queued']);
    }

    #[Test]
    public function analyze_non_xhr_post_on_hrp398_redirects_with_error_flash(): void
    {
        $response = $this->actingAs($this->user)
            ->from(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-398',
            ]))
            ->post(route('submissions.analyze', [
                'submission_uuid' => $this->hrp398Submission->id,
            ]));

        $response->assertRedirect(route('submissions.show', [
            'uuid' => $this->study->uuid,
            'form_code' => 'HRP-398',
        ]));
        $response->assertSessionHas('error', 'HRP-398 is guidance-only; analysis is not supported.');

        $this->assertDatabaseMissing('analysis_runs', [
            'submission_id' => $this->hrp398Submission->id,
        ]);
    }

    #[Test]
    public function analyze_xhr_post_on_hrp398_still_returns_422_json(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('submissions.analyze', [
                'submission_uuid' => $this->hrp398Submission->id,
            ]));

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'error' => 'HRP-398 is guidance-only; analysis is not supported.',
        ]);
    }

    // ── No-provider path (closes evaluator MEDIUM + testing HIGH gap) ──────────

    #[Test]
    public function analyze_non_xhr_post_redirects_to_show_with_error_when_no_provider(): void
    {
        // Disable the provider seeded in setUp() so the null-provider branch fires.
        LlmProvider::query()->update(['is_enabled' => false]);

        $response = $this->actingAs($this->user)
            ->from(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503c',
            ]))
            ->post(route('submissions.analyze', [
                'submission_uuid' => $this->hrp503cSubmission->id,
            ]));

        // Must redirect to the NAMED submissions.show route (not back()) so the
        // user lands correctly regardless of Referer presence.
        $response->assertRedirect(route('submissions.show', [
            'uuid' => $this->study->uuid,
            'form_code' => 'HRP-503c',
        ]));
        $response->assertSessionHas('error', 'No enabled LLM provider available.');

        $this->assertDatabaseMissing('analysis_runs', [
            'submission_id' => $this->hrp503cSubmission->id,
        ]);
    }

    #[Test]
    public function analyze_xhr_post_returns_422_when_no_provider(): void
    {
        LlmProvider::query()->update(['is_enabled' => false]);

        $response = $this->actingAs($this->user)
            ->postJson(route('submissions.analyze', [
                'submission_uuid' => $this->hrp503cSubmission->id,
            ]));

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'error' => 'No enabled LLM provider available.',
        ]);
    }
}
