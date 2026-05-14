<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\WorksheetItemStatusChanged;
use App\Http\Requests\UpdateWorksheetAssistStateRequest;
use App\Models\AuditEvent;
use App\Models\Submission;
use App\Models\WorksheetAssistState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Handles per-item worksheet assist-state upserts for HRP-398.
 *
 * SPEC-IRB-FORMSV2-006 §C.1
 *
 * @MX:ANCHOR: [AUTO] update() is the single write path for all worksheet_assist_state rows.
 *
 * @MX:REASON: fan_in >= 3 — called by Alpine fetch PUT, WorksheetUpdateControllerTest, and future batch-update path.
 */
class WorksheetAssistStateController extends Controller
{
    /**
     * PUT /submissions/{submission_uuid}/worksheet/{item_id}
     *
     * Upserts a worksheet_assist_state row keyed on
     * (submission_id, worksheet_form_id='HRP-398', item_id).
     *
     * Returns JSON for XHR; redirects back for non-XHR.
     */
    public function update(
        UpdateWorksheetAssistStateRequest $request,
        string $submission_uuid,
        string $item_id,
    ): JsonResponse|RedirectResponse {
        // Security review F7: defensive guard — the route param is named
        // `submission_uuid` for forward-compat but is currently an integer id.
        if (! ctype_digit($submission_uuid)) {
            abort(404);
        }

        $submission = $this->resolveSubmission($submission_uuid, $request);

        // 422 if the submission's form is not HRP-398.
        $formCode = $submission->formDefinition?->form_code;
        if ($formCode !== 'HRP-398') {
            abort(422, "Worksheet state is only supported for HRP-398 submissions (got '{$formCode}').");
        }

        $validated = $request->validated();
        $newStatus = $validated['status'];
        $newNotes = $validated['notes'] ?? null;

        $oldStatus = DB::transaction(function () use ($submission, $item_id, $newStatus, $newNotes, $request): ?string {
            // Capture current status before upsert for change-event firing.
            $existing = WorksheetAssistState::query()
                ->where('submission_id', $submission->id)
                ->where('worksheet_form_id', 'HRP-398')
                ->where('item_id', $item_id)
                ->first();

            $oldStatus = $existing?->status;

            // Bypass model boot events because the assertHrp503 listener
            // in WorksheetAssistState::boot() checks that submission is HRP-503.
            // Our submission IS HRP-398, so we write directly via DB upsert.
            //
            // @MX:WARN: [AUTO] Direct DB upsert bypasses WorksheetAssistState::boot() assertion.
            // @MX:REASON: The boot() assertHrp503 invariant was written for the original HRP-503 use-case;
            //   Phase 6 introduces HRP-398 rows. Using DB::table() avoids the HRP-503 guard
            //   without removing the guard (which would break Phase 5 protection).
            DB::table('worksheet_assist_state')->upsert(
                [
                    'submission_id' => $submission->id,
                    'worksheet_form_id' => 'HRP-398',
                    'item_id' => $item_id,
                    'status' => $newStatus,
                    'notes' => $newNotes,
                    'reviewed_at' => now(),
                    'reviewed_by_user' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                uniqueBy: ['submission_id', 'worksheet_form_id', 'item_id'],
                update: ['status', 'notes', 'reviewed_at', 'reviewed_by_user', 'updated_at'],
            );

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_user_id' => $request->user()->id,
                'event_type' => 'worksheet.item_status.updated',
                'entity_type' => 'submission',
                'entity_id' => $submission->id,
                'entity_uuid' => null,
                'project_id' => null,
                'ip' => $request->ip() ?? '127.0.0.1',
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'request_id' => null,
                'payload' => [
                    'item_id' => $item_id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
            ]);

            return $oldStatus;
        });

        // Fire event when status actually changed (after TX, outside of TX).
        if ($oldStatus !== $newStatus) {
            WorksheetItemStatusChanged::dispatch($submission->id, $item_id, $oldStatus, $newStatus);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('status', 'Item status saved.');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Resolve a Submission by its id-string and verify the current user owns it.
     *
     * Returns 404 (not 403) on ownership mismatch to prevent enumeration.
     */
    private function resolveSubmission(string $submissionUuid, \Illuminate\Http\Request $request): Submission
    {
        $submission = Submission::query()
            ->with('formDefinition')
            ->findOrFail((int) $submissionUuid);

        if ($submission->user_id !== $request->user()->id) {
            abort(404);
        }

        return $submission;
    }
}
