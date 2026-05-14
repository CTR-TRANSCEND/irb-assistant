<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\AnalyzeSubmissionJob;
use App\Models\AnalysisRun;
use App\Models\AuditEvent;
use App\Models\LlmProvider;
use App\Models\Submission;
use App\Services\AuditService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Submission analysis: queue/status/cancel for AnalyzeSubmissionJob.
 *
 * Mirrors ProjectAnalysisController but operates on Submission rows.
 * SPEC-IRB-FORMSV2-004 §A.4
 * REQ-IRB-FORMSV2-060: HRP-398 submissions MUST NOT be analyzed.
 */
class SubmissionAnalysisController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditService $audit,
    ) {}

    /**
     * POST /submissions/{submission_uuid}/analyze
     *
     * Creates an analysis_runs row and dispatches AnalyzeSubmissionJob.
     * REQ-IRB-FORMSV2-060: rejects HRP-398 immediately with 422.
     *
     * Response shape: returns JSON for XHR clients (Accept: application/json),
     * else redirects to the submission's Review tab so the existing
     * `_progress_modal.blade.php` auto-opens via `bootstrap()` →
     * `fetchStatus(true)`. Fixes Outstanding #72 (raw-JSON-in-browser regression).
     */
    public function analyze(Request $request, string $submission_uuid): JsonResponse|RedirectResponse
    {
        $submission = $this->resolveSubmission($submission_uuid, $request);

        // REQ-IRB-FORMSV2-060: HRP-398 is guidance-only; analysis forbidden.
        if ($submission->formDefinition->form_code === 'HRP-398') {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'HRP-398 is guidance-only; analysis is not supported.',
                ], 422);
            }

            return redirect()
                ->route('submissions.show', [
                    'uuid' => $submission->study->uuid,
                    'form_code' => $submission->formDefinition->form_code,
                ])
                ->with('error', 'HRP-398 is guidance-only; analysis is not supported.');
        }

        $allowExternal = $this->settings->bool('allow_external_llm', (bool) config('irb.allow_external_llm', false));

        $provider = LlmProvider::query()
            ->where('is_enabled', true)
            ->when(! $allowExternal, fn ($q) => $q->where('is_external', false))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        if ($provider === null) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No enabled LLM provider available.'], 422);
            }

            // Match the HRP-398 redirect pattern (named route, not back()) so the
            // user lands on the submission Review tab regardless of Referer.
            return redirect()
                ->route('submissions.show', [
                    'uuid' => $submission->study->uuid,
                    'form_code' => $submission->formDefinition->form_code,
                ])
                ->with('error', 'No enabled LLM provider available.');
        }

        $run = AnalysisRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'project_id' => null,
            'submission_id' => $submission->id,
            'llm_provider_id' => $provider->id,
            'created_by_user_id' => $request->user()->id,
            'status' => 'queued',
            'started_at' => now(),
            'prompt_version' => 'submission-first-pass-v1',
            'progress_step' => 'queued',
            'progress_current' => 0,
            'progress_total' => 0,
            'progress_message' => 'Queued — waiting for worker',
            'last_heartbeat_at' => now(),
        ]);

        AnalyzeSubmissionJob::dispatch(
            analysisRunId: $run->id,
            submissionId: $submission->id,
            providerId: $provider->id,
            actorUserId: $request->user()->id,
            actorIp: $request->ip(),
            actorUserAgent: substr((string) $request->userAgent(), 0, 512),
            actorRequestId: substr((string) $request->header('X-Request-Id'), 0, 64) ?: null,
        );

        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_user_id' => $request->user()->id,
            'event_type' => 'submission.analysis.queued',
            'entity_type' => 'analysis_run',
            'entity_id' => $run->id,
            'entity_uuid' => $run->uuid,
            'project_id' => null,
            'ip' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'request_id' => null,
            'payload' => ['submission_id' => $submission->id, 'mode' => 'first-pass'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'run_id' => $run->id,
                'status' => 'queued',
            ]);
        }

        // Non-XHR (standard form POST from the Review/Analyze tab): redirect back
        // so the progress modal auto-opens via its existing bootstrap() hook.
        // Outstanding #72.
        return redirect()
            ->route('submissions.show', [
                'uuid' => $submission->study->uuid,
                'form_code' => $submission->formDefinition->form_code,
            ])
            ->with('status', 'Analysis queued — the progress modal will open shortly.');
    }

    /**
     * GET /submissions/{submission_uuid}/analyze/status
     *
     * Returns JSON snapshot of the latest analysis run for this submission.
     */
    public function status(Request $request, string $submission_uuid): JsonResponse
    {
        $submission = $this->resolveSubmission($submission_uuid, $request);

        $run = AnalysisRun::query()
            ->where('submission_id', $submission->id)
            ->orderByDesc('id')
            ->first();

        if ($run === null) {
            return response()->json(['has_run' => false]);
        }

        $heartbeatAgeSec = $run->last_heartbeat_at
            ? round(max(0, (float) now()->diffInSeconds($run->last_heartbeat_at, true)), 1)
            : null;

        $isStale = in_array($run->status, ['queued', 'running'], true)
            && $heartbeatAgeSec !== null
            && $heartbeatAgeSec > 90;

        return response()->json([
            'has_run' => true,
            'run' => [
                'uuid' => $run->uuid,
                'status' => $run->status,
                'progress_step' => $run->progress_step,
                'progress_current' => $run->progress_current,
                'progress_total' => $run->progress_total,
                'progress_message' => $run->progress_message,
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'last_heartbeat_at' => $run->last_heartbeat_at?->toIso8601String(),
                'heartbeat_age_seconds' => $heartbeatAgeSec,
                'is_stale' => $isStale,
                'error' => $run->status === 'failed' ? $run->error : null,
            ],
        ]);
    }

    /**
     * POST /submissions/{submission_uuid}/analyze/cancel
     *
     * Sets the latest running/queued run to 'cancelling'. The job observes
     * this at its next checkpoint and throws AnalysisCancelledException.
     */
    public function cancel(Request $request, string $submission_uuid): JsonResponse
    {
        $submission = $this->resolveSubmission($submission_uuid, $request);

        $run = AnalysisRun::query()
            ->where('submission_id', $submission->id)
            ->whereIn('status', ['queued', 'running'])
            ->orderByDesc('id')
            ->first();

        if ($run === null) {
            return response()->json([
                'success' => false,
                'error' => 'No active analysis run to cancel.',
            ], 422);
        }

        $run->forceFill(['status' => 'cancelling'])->save();

        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_user_id' => $request->user()->id,
            'event_type' => 'submission.analysis.cancel_requested',
            'entity_type' => 'analysis_run',
            'entity_id' => $run->id,
            'entity_uuid' => $run->uuid,
            'project_id' => null,
            'ip' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'request_id' => null,
            'payload' => ['previous_step' => $run->progress_step],
        ]);

        return response()->json([
            'success' => true,
            'status' => 'cancelling',
            'message' => 'Cancellation requested.',
        ]);
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function resolveSubmission(string $submissionId, Request $request): Submission
    {
        $submission = Submission::query()
            ->with(['formDefinition', 'study'])
            ->findOrFail((int) $submissionId);

        if ($submission->user_id !== $request->user()->id) {
            abort(404);
        }

        return $submission;
    }
}
