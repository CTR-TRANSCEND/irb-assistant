<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    public function update(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:admin,user'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($user->id === $request->user()->id && (bool) $data['is_active'] === false) {
            return back()->withErrors(['is_active' => 'You cannot disable your own account.']);
        }

        if ($user->role === 'admin' && (string) $data['role'] !== 'admin') {
            $adminCount = User::query()->where('role', 'admin')->where('is_active', true)->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['role' => 'Cannot remove the last active admin.']);
            }
        }

        $before = [
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
        ];

        // M4: wrap user mutation + audit insert in DB::transaction() for atomicity
        // (mirrors approve/reject/activate/deactivate/destroy per REQ-AUTH-024).
        DB::transaction(function () use ($request, $user, $audit, $data, $before): void {
            $user->role = (string) $data['role'];
            $user->is_active = (bool) $data['is_active'];
            $user->save();

            $audit->log(
                request: $request,
                eventType: 'admin.user.updated',
                project: null,
                entityType: 'user',
                entityId: $user->id,
                entityUuid: null,
                payload: [
                    'before' => $before,
                    'after' => [
                        'role' => $user->role,
                        'is_active' => (bool) $user->is_active,
                    ],
                ],
            );
        });

        return redirect()->route('admin.index', ['tab' => 'users'])->with('status', 'User updated.');
    }

    // @MX:ANCHOR: [AUTO] Approve action — high fan_in via route; carries the atomic-update
    //             invariant (UPDATE WHERE is_approved=false) and the transactional audit
    //             atomicity contract (REQ-AUTH-013, REQ-AUTH-024, REQ-AUTH-043).
    // @MX:REASON: Second concurrent Approve call must observe affected_rows=0 and return
    //             302+flash (not 4xx). The WHERE guard + DB::transaction() together make
    //             this race-safe and audit-trail consistent. Do not remove either guard.
    // @MX:SPEC: SPEC-AUTH-001 REQ-AUTH-013, REQ-AUTH-018, REQ-AUTH-024, REQ-AUTH-040, REQ-AUTH-043
    public function approve(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        // REQ-AUTH-040: admins cannot be approved/acted on by other admins.
        // REQ-AUTH-018: self-id guard — admin cannot approve themselves.
        if ($user->isAdmin() || $user->id === $request->user()->id) {
            abort(403);
        }

        return DB::transaction(function () use ($request, $user, $audit): RedirectResponse {
            // @MX:WARN: [AUTO] Atomic UPDATE with WHERE is_approved=false guard prevents double-approval.
            // @MX:REASON: Race window between concurrent admin clicks. Idempotency depends on this guard —
            //             affected_rows===0 means the loser of the race, and no audit row should be written.
            //             Do NOT replace this with $user->update(['is_approved' => true]) which is not atomic.
            $affected = DB::table('users')
                ->where('id', $user->id)
                ->where('is_approved', false)
                ->update([
                    'is_approved' => true,
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                ]);

            // REQ-AUTH-043: second Approve on an already-approved row → 302 redirect with
            // flash "User already approved or no longer pending" — NOT a 4xx.
            // Batch C copy: emit a user-friendly sentence, not the raw slug.
            if ($affected === 0) {
                return redirect()->route('admin.index', ['tab' => 'users'])
                    ->with('status', 'User already approved or no longer pending.');
            }

            // REQ-AUTH-013: write audit row with allow-listed payload (REQ-AUTH-041).
            // Reload the model to get accurate approved_at / approved_by values.
            $refreshed = User::findOrFail($user->id);

            $audit->log(
                request: $request,
                eventType: 'user.approved',
                project: null,
                entityType: 'user',
                entityId: $user->id,
                entityUuid: null,
                payload: $refreshed->auditableAttributes('user.approved'),
            );

            return redirect()->route('admin.index', ['tab' => 'users'])
                ->with('status', 'User approved.');
        });
    }

    // @MX:ANCHOR: [AUTO] Reject action — pending-only path (REQ-AUTH-014). Captures audit
    //             payload BEFORE deletion so the email/name/role are preserved in the audit
    //             trail after the row is hard-deleted. REQ-AUTH-024 atomicity enforced via
    //             DB::transaction(). Returns 404 if target is an already-approved user
    //             (Reject and Delete are distinct code paths — REQ-AUTH-014 / REQ-AUTH-017).
    // @MX:REASON: Audit payload must be captured BEFORE $user->delete(). If captured after,
    //             the data is gone. The WHERE is_approved=false 404 guard ensures this method
    //             is never confused with destroy() at the business-logic level.
    // @MX:SPEC: SPEC-AUTH-001 REQ-AUTH-014, REQ-AUTH-018, REQ-AUTH-024, REQ-AUTH-040, REQ-AUTH-041
    public function reject(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        // REQ-AUTH-040: admins cannot be rejected by other admins.
        // REQ-AUTH-018: self-id guard — admin cannot reject themselves.
        if ($user->isAdmin() || $user->id === $request->user()->id) {
            abort(403);
        }

        // REQ-AUTH-014: Reject is restricted to pending users only.
        // Returns 404 if target is already approved (distinct from destroy()).
        if ($user->is_approved) {
            abort(404);
        }

        // REQ-AUTH-041: capture allow-listed payload BEFORE deletion.
        $payload = $user->auditableAttributes('user.rejected');

        DB::transaction(function () use ($request, $user, $audit, $payload): void {
            $user->delete();

            $audit->log(
                request: $request,
                eventType: 'user.rejected',
                project: null,
                entityType: 'user',
                entityId: $payload['id'],
                entityUuid: null,
                payload: $payload,
            );
        });

        return redirect()->route('admin.index', ['tab' => 'users'])
            ->with('status', 'User rejected and removed.');
    }

    /**
     * Deactivate an approved non-admin user (sets is_active = false).
     *
     * REQ-AUTH-015, REQ-AUTH-018, REQ-AUTH-024, REQ-AUTH-040.
     */
    public function deactivate(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        // REQ-AUTH-040: admins cannot be deactivated by other admins.
        // REQ-AUTH-018: self-id guard — admin cannot deactivate themselves.
        if ($user->isAdmin() || $user->id === $request->user()->id) {
            abort(403);
        }

        DB::transaction(function () use ($request, $user, $audit): void {
            $user->update(['is_active' => false]);

            $audit->log(
                request: $request,
                eventType: 'user.deactivated',
                project: null,
                entityType: 'user',
                entityId: $user->id,
                entityUuid: null,
                payload: $user->auditableAttributes('user.deactivated'),
            );
        });

        return redirect()->route('admin.index', ['tab' => 'users'])
            ->with('status', 'User deactivated.');
    }

    /**
     * Reactivate a deactivated user (sets is_active = true).
     *
     * REQ-AUTH-016, REQ-AUTH-018, REQ-AUTH-024, REQ-AUTH-040.
     */
    public function activate(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        // REQ-AUTH-040: admins cannot be activated by other admins.
        // REQ-AUTH-018: self-id guard — admin cannot activate themselves.
        if ($user->isAdmin() || $user->id === $request->user()->id) {
            abort(403);
        }

        DB::transaction(function () use ($request, $user, $audit): void {
            $user->update(['is_active' => true]);

            $audit->log(
                request: $request,
                eventType: 'user.activated',
                project: null,
                entityType: 'user',
                entityId: $user->id,
                entityUuid: null,
                payload: $user->auditableAttributes('user.activated'),
            );
        });

        return redirect()->route('admin.index', ['tab' => 'users'])
            ->with('status', 'User activated.');
    }

    // @MX:ANCHOR: [AUTO] Destroy (Delete) action — approved-only path (REQ-AUTH-017).
    //             Carries the approved-only guard (404 if pending) and the cascade FK
    //             invariant (projects.owner_user_id → CASCADE, per REQ-AUTH-025 resolution).
    //             Audit payload captured BEFORE deletion (REQ-AUTH-041). REQ-AUTH-024
    //             atomicity enforced via DB::transaction().
    // @MX:REASON: Distinct code path from reject(). This method targets is_approved=true
    //             users; reject() targets is_approved=false users. The 404 guard enforces
    //             this separation at the controller level. Cascading deletes all of the
    //             user's owned projects (FK resolution: CASCADE per SPEC-AUTH-001 §3.6).
    // @MX:SPEC: SPEC-AUTH-001 REQ-AUTH-017, REQ-AUTH-018, REQ-AUTH-024, REQ-AUTH-025, REQ-AUTH-040, REQ-AUTH-041
    public function destroy(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        // REQ-AUTH-040: admins cannot be deleted by other admins.
        // REQ-AUTH-018: self-id guard — admin cannot delete themselves.
        if ($user->isAdmin() || $user->id === $request->user()->id) {
            abort(403);
        }

        // REQ-AUTH-017: Delete is restricted to approved users only.
        // Returns 404 if target is pending (distinct from reject()).
        if (! $user->is_approved) {
            abort(404);
        }

        // REQ-AUTH-041: capture allow-listed payload BEFORE deletion.
        // user.deleted includes approved_at and approved_by (user was approved).
        $payload = $user->auditableAttributes('user.deleted');

        DB::transaction(function () use ($request, $user, $audit, $payload): void {
            // REQ-AUTH-025: projects.owner_user_id FK is set to CASCADE ON DELETE
            // (via migration 2026_05_08_000002_set_projects_user_id_cascade_on_delete).
            // All owned projects are deleted automatically by the DB engine.
            $user->delete();

            $audit->log(
                request: $request,
                eventType: 'user.deleted',
                project: null,
                entityType: 'user',
                entityId: $payload['id'],
                entityUuid: null,
                payload: $payload,
            );
        });

        return redirect()->route('admin.index', ['tab' => 'users'])
            ->with('status', 'User deleted.');
    }
}
