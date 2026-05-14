<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudyRequest;
use App\Models\AuditEvent;
use App\Models\Study;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles Study CRUD — the top-level entity that groups Submissions.
 *
 * SPEC-IRB-FORMSV2-004 §A.1
 *
 * @MX:ANCHOR: [AUTO] StudyController is the entry point for all Study HTTP operations.
 *
 * @MX:REASON: fan_in >= 3 — used by routes/web.php resource registration, 5 test classes,
 *             and referenced from the frontend studies.show view.
 */
class StudyController extends Controller
{
    /**
     * GET /studies — list all studies for the authenticated user.
     * Eager-loads submissions to avoid N+1 on the index page.
     */
    public function index(Request $request): View
    {
        $studies = Study::query()
            ->where('user_id', $request->user()->id)
            ->with(['submissions'])
            ->orderByDesc('updated_at')
            ->get();

        return view('studies.index', [
            'studies' => $studies,
        ]);
    }

    /**
     * GET /studies/create — render the study creation form.
     */
    public function create(): View
    {
        return view('studies.create');
    }

    /**
     * POST /studies — create a new Study (+ 3 auto-created child Submissions via boot hook).
     *
     * Security: user_id is always taken from Auth::id() — never from request input.
     * REQ-IRB-FORMSV2-005, REQ-IRB-FORMSV2-011a
     */
    public function store(StoreStudyRequest $request): RedirectResponse
    {
        $study = Study::createForUser(
            (int) $request->user()->id,
            $request->validated(),
        );

        return redirect()
            ->route('studies.show', ['uuid' => $study->uuid])
            ->with('status', 'Study created.');
    }

    /**
     * GET /studies/{uuid} — show a study with its 3 submission tabs.
     *
     * Aborts 404 if the authenticated user is not the owner (security review F2).
     */
    public function show(Request $request, string $uuid): View
    {
        $study = Study::query()
            ->where('uuid', $uuid)
            ->with([
                'submissions.formDefinition',
                'submissions.answers',
                'documents',
            ])
            ->firstOrFail();

        if ($study->user_id !== $request->user()->id) {
            abort(404);
        }

        return view('studies.show', [
            'study' => $study,
        ]);
    }

    /**
     * DELETE /studies/{uuid} — delete a Study (FK CASCADE removes Submissions).
     *
     * Emits study.deleted audit event.
     */
    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        $study = Study::query()->where('uuid', $uuid)->firstOrFail();

        if ($study->user_id !== $request->user()->id) {
            abort(404);
        }

        $studyId = $study->id;
        $studyUuid = $study->uuid;

        $study->delete();

        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_user_id' => $request->user()->id,
            'event_type' => 'study.deleted',
            'entity_type' => 'study',
            'entity_id' => $studyId,
            'entity_uuid' => $studyUuid,
            'project_id' => null,
            'ip' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'request_id' => null,
            'payload' => ['study_id' => $studyId],
        ]);

        return redirect()
            ->route('studies.index')
            ->with('status', 'Study deleted.');
    }
}
