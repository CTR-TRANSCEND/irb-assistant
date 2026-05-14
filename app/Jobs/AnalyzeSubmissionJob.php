<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\AnalysisCancelledException;
use App\Models\AnalysisRun;
use App\Models\AuditEvent;
use App\Models\LlmProvider;
use App\Models\Submission;
use App\Models\User;
use App\Services\LlmChatService;
use App\Services\SubmissionAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue-backed submission analysis with live progress polling.
 *
 * Retrofit of AnalyzeProjectJob for the Submission model.
 * Dispatches SubmissionAnalysisService::runFirstPass() off the request thread.
 * Publishes heartbeat/progress to analysis_runs for frontend modal polling.
 *
 * SPEC-IRB-FORMSV2-004 §C
 *
 * @MX:ANCHOR: [AUTO] AnalyzeSubmissionJob::handle() is the queue entry point for submission analysis.
 *
 * @MX:REASON: fan_in >= 3 — SubmissionAnalysisController::analyze(), tests, and supervisor queue worker.
 */
class AnalyzeSubmissionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Don't auto-retry: a failed run leaves partial DB state; user can click Analyze again.
     */
    public int $tries = 1;

    /**
     * Matches AnalyzeProjectJob ceiling (systemd --timeout=1800 defense-in-depth).
     */
    public int $timeout = 1800;

    public function __construct(
        public int $analysisRunId,
        public int $submissionId,
        public int $providerId,
        public int $actorUserId,
        public ?string $actorIp = null,
        public ?string $actorUserAgent = null,
        public ?string $actorRequestId = null,
    ) {}

    public function handle(SubmissionAnalysisService $analysis, LlmChatService $llm): void
    {
        $run = AnalysisRun::query()->findOrFail($this->analysisRunId);
        $submission = Submission::query()->with('formDefinition')->findOrFail($this->submissionId);
        $provider = LlmProvider::query()->findOrFail($this->providerId);

        // Security review F5: defense-in-depth ownership re-check at the worker
        // boundary. Phase 4 PR-1 controllers already enforce this at the HTTP
        // boundary; this guard catches any future code path that dispatches the
        // job without authorizing first (admin tooling, queue replay, retries).
        if ($submission->user_id !== $this->actorUserId) {
            throw new \RuntimeException(
                "AnalyzeSubmissionJob authorization failure: actor user_id={$this->actorUserId} "
                ."does not match submission user_id={$submission->user_id}."
            );
        }

        $synthetic = $this->buildSyntheticRequest();

        $progressCallback = function (string $step, int $current, int $total, string $message) use ($run): void {
            // Cancellation checkpoint — mirrors AnalyzeProjectJob pattern
            $currentStatus = $run->fresh()?->status;
            if ($currentStatus === 'cancelling') {
                throw new AnalysisCancelledException('Cancelled by user at step '.$step);
            }

            $run->forceFill([
                'progress_step' => $step,
                'progress_current' => $current,
                'progress_total' => $total,
                'progress_message' => $message,
                'last_heartbeat_at' => now(),
            ])->save();
        };

        try {
            $analysis->runFirstPass(
                submission: $submission,
                provider: $provider,
                actorUserId: $this->actorUserId,
                llm: $llm,
                request: $synthetic,
                progressCallback: $progressCallback,
                existingRun: $run,
            );

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_user_id' => $this->actorUserId,
                'event_type' => 'submission.analyzed',
                'entity_type' => 'submission',
                'entity_id' => $submission->id,
                'entity_uuid' => null,
                'project_id' => null,
                'ip' => $this->actorIp ?? '127.0.0.1',
                'user_agent' => substr((string) $this->actorUserAgent, 0, 512),
                'request_id' => null,
                'payload' => ['mode' => 'first-pass', 'analysis_run_id' => $run->id],
            ]);
        } catch (\Throwable $e) {
            Log::error('AnalyzeSubmissionJob failed', [
                'analysis_run_id' => $this->analysisRunId,
                'submission_id' => $this->submissionId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Failed handler — marks run as failed if not already in a terminal state.
     */
    public function failed(\Throwable $e): void
    {
        $run = AnalysisRun::query()->find($this->analysisRunId);
        if ($run !== null && ! in_array($run->status, ['succeeded', 'failed', 'cancelled'], true)) {
            $run->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => 'Job failed: '.substr($e->getMessage(), 0, 500),
            ])->save();
        }
    }

    private function buildSyntheticRequest(): Request
    {
        $request = Request::create('/submissions/internal/analyze', 'POST');

        if ($this->actorIp !== null) {
            $request->server->set('REMOTE_ADDR', $this->actorIp);
        }
        if ($this->actorUserAgent !== null) {
            $request->headers->set('User-Agent', $this->actorUserAgent);
        }
        if ($this->actorRequestId !== null) {
            $request->headers->set('X-Request-Id', $this->actorRequestId);
        }

        $userId = $this->actorUserId;
        $request->setUserResolver(function () use ($userId) {
            return User::query()->find($userId);
        });

        return $request;
    }
}
