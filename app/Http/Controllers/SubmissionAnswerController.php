<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\FormQuestion;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Services\AnswerValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Handles per-question answer upserts and draft acceptance.
 *
 * SPEC-IRB-FORMSV2-004 §A.3
 *
 * @MX:ANCHOR: [AUTO] update() is the single write path for all submission_answer rows (user-initiated).
 *
 * @MX:REASON: fan_in >= 3 — called by AJAX, form POST, and SubmissionAnswerControllerTest.
 */
class SubmissionAnswerController extends Controller
{
    public function __construct(private readonly AnswerValidator $answerValidator) {}

    /**
     * PUT /submissions/{submission_uuid}/answers/{question_key}
     *
     * Upserts a submission_answer row. Captures routing_outcome if the
     * answered option triggers a stop_* action.
     *
     * Returns JSON for XHR requests; redirects back for non-XHR.
     *
     * @MX:WARN: [AUTO] Routing outcome side-effect on Submission row embedded in the answer write path.
     *
     * @MX:REASON: Both the answer write and the routing_outcome update must be atomic; they share a DB::transaction.
     */
    public function update(Request $request, string $submission_uuid, string $question_key): JsonResponse|RedirectResponse
    {
        $submission = $this->resolveSubmission($submission_uuid, $request);
        $question = $this->resolveQuestion($submission, $question_key);

        // Validate input shape per question_type (before TX — Validator throws 422 on bad input)
        $payload = $this->answerValidator->validateAnswer($question, $request->all());

        // Evaluator F1: atomically persist the answer + routing_outcome + audit row.
        // Otherwise a partial failure (e.g., DB drop between writes) leaves the answer
        // saved but routing_outcome stale — the form would render as if the stop-action
        // was accepted, with no routing decision recorded.
        $routingOutcome = DB::transaction(function () use ($submission, $question, $question_key, $payload, $request): ?string {
            // Upsert the answer — suggestion_source=null means user-confirmed (not ai_draft)
            SubmissionAnswer::query()->updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'question_key' => $question_key,
                ],
                array_merge($payload, ['suggestion_source' => null]),
            );

            $terminalTypes = ['stop_and_submit', 'stop_engaged', 'stop_not_engaged', 'stop_or_skip_to_3.0'];
            $outcome = null;

            if ($payload['option_value'] !== null) {
                $option = $question->options()
                    ->where('option_value', $payload['option_value'])
                    ->whereIn('action_type', $terminalTypes)
                    ->first();

                if ($option !== null) {
                    $outcome = $option->action_type;
                    $submission->forceFill([
                        'routing_outcome' => $outcome,
                        'routing_outcome_at' => $question_key,
                    ])->save();

                    AuditEvent::query()->create([
                        'occurred_at' => now(),
                        'actor_user_id' => $request->user()->id,
                        'event_type' => 'submission.routing_outcome.captured',
                        'entity_type' => 'submission',
                        'entity_id' => $submission->id,
                        'entity_uuid' => null,
                        'project_id' => null,
                        'ip' => $request->ip() ?? '127.0.0.1',
                        'user_agent' => substr((string) $request->userAgent(), 0, 512),
                        'request_id' => null,
                        'payload' => [
                            'question_key' => $question_key,
                            'routing_outcome' => $outcome,
                            'option_value' => $payload['option_value'],
                        ],
                    ]);
                }
            }

            return $outcome;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'routing_outcome' => $routingOutcome,
            ]);
        }

        return redirect()->back()->with('status', 'Answer saved.');
    }

    /**
     * POST /submissions/{submission_uuid}/answers/{question_key}/accept-draft
     *
     * Assistant mode only: flips an existing ai_draft row's suggestion_source
     * to 'evidence' (user accepted the draft). REQ-IRB-FORMSV2-054.
     */
    public function acceptDraft(Request $request, string $submission_uuid, string $question_key): JsonResponse|RedirectResponse
    {
        $submission = $this->resolveSubmission($submission_uuid, $request);

        $answer = SubmissionAnswer::query()
            ->where('submission_id', $submission->id)
            ->where('question_key', $question_key)
            ->where('suggestion_source', 'ai_draft')
            ->firstOrFail();

        $answer->forceFill(['suggestion_source' => 'evidence'])->save();

        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_user_id' => $request->user()->id,
            'event_type' => 'submission.field.ai_draft_accepted',
            'entity_type' => 'submission',
            'entity_id' => $submission->id,
            'entity_uuid' => null,
            'project_id' => null,
            'ip' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'request_id' => null,
            'payload' => ['question_key' => $question_key],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('status', 'Draft accepted.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function resolveSubmission(string $submissionUuid, Request $request): Submission
    {
        // Submission table has no uuid column in Phase 3 schema — resolve by id string.
        // @MX:TODO: add uuid column to submission table in Phase 5.
        //
        // Security review F7: defensive guard — the route param is named
        // `submission_uuid` for forward-compat but is currently an integer id.
        // Reject non-numeric input early instead of relying on `(int) 'xyz' = 0`
        // and `findOrFail(0)` indirection.
        if (! ctype_digit($submissionUuid)) {
            abort(404);
        }

        $submission = Submission::query()->findOrFail((int) $submissionUuid);

        if ($submission->user_id !== $request->user()->id) {
            abort(404);
        }

        return $submission;
    }

    private function resolveQuestion(Submission $submission, string $questionKey): FormQuestion
    {
        return FormQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('form_definition_id', $submission->form_definition_id))
            ->where('question_key', $questionKey)
            ->firstOrFail();
    }
}
