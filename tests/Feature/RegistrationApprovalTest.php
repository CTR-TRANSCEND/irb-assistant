<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for SPEC-AUTH-001: Self-Registration with Admin Approval Gate.
 *
 * Covers all 24 Given/When/Then scenarios defined in acceptance.md (S1–S24,
 * including S11b, S14b, S14c, S21, S22, S23, S24).
 *
 * Tests are intentionally RED in this worktree until backend-impl and
 * frontend-impl branches merge (migration, factory states, routes, and
 * controller actions do not yet exist).
 */
class RegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------

    /**
     * Submit the registration form with sensible defaults.
     */
    private function registerUser(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('register'), array_merge([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides));
    }

    /**
     * Create an admin user with is_approved=true, is_active=true.
     *
     * Assumption for backend-impl: factory default after SPEC-AUTH-001 migration
     * sets is_approved=true; no 'admin()' factory state is required because role
     * can be specified directly.
     */
    private function makeAdmin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'admin',
            'is_approved' => true,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Create a pending (unapproved) user.
     *
     * Assumption for backend-impl: UserFactory::unapproved() state must be added
     * that sets is_approved=false, is_active=true.
     */
    private function makePendingUser(array $overrides = []): User
    {
        return User::factory()->unapproved()->create(array_merge([
            'name' => 'Pending User',
            'email' => 'pending@example.com',
        ], $overrides));
    }

    /**
     * Act as a freshly created admin for chained request calls.
     * Returns $this for fluent chaining: $this->asAdmin()->post(...).
     */
    private function asAdmin(?User $admin = null): static
    {
        $this->actingAs($admin ?? $this->makeAdmin());

        return $this;
    }

    // -------------------------------------------------------
    // S1 — Registration creates a pending row; no session
    // Maps to: REQ-AUTH-001, REQ-AUTH-004, REQ-AUTH-010, REQ-AUTH-011, REQ-AUTH-044
    // -------------------------------------------------------

    #[Test]
    public function s1_register_creates_pending_user_no_session(): void
    {
        Event::fake([Registered::class]);

        $response = $this->post(route('register'), [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // REQ-AUTH-011: redirect to login with pending flash
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        // REQ-AUTH-044: no authenticated session
        $this->assertGuest();

        // REQ-AUTH-010: row created with correct defaults
        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'role' => 'user',
            'is_approved' => false,
            'is_active' => true,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        // REQ-AUTH-011: Registered event fires exactly once
        Event::assertDispatched(Registered::class, 1);
    }

    // -------------------------------------------------------
    // S2 — Pending user cannot log in
    // Maps to: REQ-AUTH-003, REQ-AUTH-012
    // -------------------------------------------------------

    #[Test]
    public function s2_pending_user_cannot_login(): void
    {
        $this->makePendingUser(['email' => 'bob@example.com']);

        $response = $this->post(route('login'), [
            'email' => 'bob@example.com',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors();

        // REQ-AUTH-012: message must mention "pending"
        $errors = session('errors');
        $allMessages = $errors ? implode(' ', $errors->all()) : '';
        $this->assertStringContainsStringIgnoringCase('pending', $allMessages);
    }

    // -------------------------------------------------------
    // S3 — Admin approves pending user; audit row written; user can log in
    // Maps to: REQ-AUTH-013, REQ-AUTH-002, REQ-AUTH-024, REQ-AUTH-041
    // -------------------------------------------------------

    #[Test]
    public function s3_admin_approves_pending_user_audit_written_user_can_login(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin@example.com']);
        $carol = $this->makePendingUser(['email' => 'carol@example.com']);

        $before = now()->subSecond();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.approve', $carol));

        $response->assertRedirect(route('admin.index', ['tab' => 'users']));
        $response->assertSessionHas('status');

        // REQ-AUTH-013: fields set atomically
        $carol->refresh();
        $this->assertTrue((bool) $carol->is_approved);
        $this->assertNotNull($carol->approved_at);
        $this->assertTrue($carol->approved_at->gte($before));
        $this->assertEquals($admin->id, $carol->approved_by);

        // REQ-AUTH-024: audit row written
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user.approved',
            'actor_user_id' => $admin->id,
            'entity_id' => $carol->id,
        ]);

        // User can subsequently log in (REQ-AUTH-003 satisfied)
        $loginResponse = $this->post(route('login'), [
            'email' => 'carol@example.com',
            'password' => 'password',
        ]);
        $this->assertAuthenticatedAs($carol->fresh());
    }

    // -------------------------------------------------------
    // S4 — Admin rejects pending user; row deleted; audit preserved; email reusable
    // Maps to: REQ-AUTH-014, REQ-AUTH-024, REQ-AUTH-041
    // -------------------------------------------------------

    #[Test]
    public function s4_admin_rejects_pending_user_row_deleted_audit_preserved(): void
    {
        $admin = $this->makeAdmin();
        $dave = $this->makePendingUser(['name' => 'Dave', 'email' => 'dave@example.com']);
        $daveId = $dave->id;

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.reject', $dave));

        $response->assertRedirect(route('admin.index', ['tab' => 'users']));
        $response->assertSessionHas('status');

        // REQ-AUTH-014: hard delete
        $this->assertDatabaseMissing('users', ['email' => 'dave@example.com']);

        // REQ-AUTH-041: audit row preserves identity
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user.rejected',
            'actor_user_id' => $admin->id,
            'entity_id' => $daveId,
        ]);

        $audit = AuditEvent::where('event_type', 'user.rejected')->where('entity_id', $daveId)->firstOrFail();
        $payload = $audit->payload;
        $this->assertEquals('dave@example.com', $payload['email']);
        $this->assertEquals('Dave', $payload['name']);
        $this->assertEquals('user', $payload['role']);

        // Email available for re-registration (EX-006: no soft-delete)
        $re = $this->post(route('register'), [
            'name' => 'Dave Again',
            'email' => 'dave@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'dave@example.com']);
    }

    // -------------------------------------------------------
    // S5 — Deactivated approved user gets distinct error message
    // Maps to: REQ-AUTH-003, REQ-AUTH-020
    // -------------------------------------------------------

    #[Test]
    public function s5_deactivated_user_gets_distinct_error_message(): void
    {
        User::factory()->create([
            'email' => 'eve@example.com',
            'is_approved' => true,
            'is_active' => false,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'eve@example.com',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors();

        // REQ-AUTH-020: "deactivated" message, distinct from "pending"
        $errors = session('errors');
        $allMessages = $errors ? implode(' ', $errors->all()) : '';
        $this->assertStringContainsStringIgnoringCase('deactivated', $allMessages);
        $this->assertStringNotContainsStringIgnoringCase('pending', $allMessages);
    }

    // -------------------------------------------------------
    // S6 — Migration backfill: schema columns exist; factory default is approved
    // Maps to: REQ-AUTH-022, REQ-AUTH-023
    // -------------------------------------------------------

    #[Test]
    public function s6_migration_backfill_schema_columns_exist_and_defaults_correct(): void
    {
        // REQ-AUTH-001 / REQ-AUTH-002: new columns must exist after migration
        $this->assertTrue(Schema::hasColumn('users', 'is_approved'));
        $this->assertTrue(Schema::hasColumn('users', 'approved_at'));
        $this->assertTrue(Schema::hasColumn('users', 'approved_by'));

        // REQ-AUTH-022: factory default post-migration is is_approved=true
        // (simulates existing-user backfill; factory::definition() sets is_approved=true by default)
        $existingUser = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'is_approved' => true,
        ]);

        // approved_at and approved_by remain null (backfill only flips is_approved)
        $existingUser->refresh();
        $this->assertNull($existingUser->approved_at);
        $this->assertNull($existingUser->approved_by);
    }

    // -------------------------------------------------------
    // S7 — Admin deactivates approved non-admin user; subsequent login fails
    // Maps to: REQ-AUTH-015, REQ-AUTH-020, REQ-AUTH-024
    // -------------------------------------------------------

    #[Test]
    public function s7_admin_deactivates_user_subsequent_login_fails(): void
    {
        $admin = $this->makeAdmin();
        $frank = User::factory()->create([
            'email' => 'frank@example.com',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.users.deactivate', $frank));

        $response->assertRedirect(route('admin.index', ['tab' => 'users']));

        $frank->refresh();
        $this->assertFalse((bool) $frank->is_active);
        $this->assertTrue((bool) $frank->is_approved); // is_approved unchanged

        // REQ-AUTH-024: audit row written
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user.deactivated',
            'actor_user_id' => $admin->id,
            'entity_id' => $frank->id,
        ]);

        // REQ-AUTH-020: deactivated user cannot log in
        $this->post(route('login'), [
            'email' => 'frank@example.com',
            'password' => 'password',
        ]);
        $this->assertGuest();
    }

    // -------------------------------------------------------
    // S8 — Admin reactivates deactivated user; login succeeds
    // Maps to: REQ-AUTH-016, REQ-AUTH-024
    // -------------------------------------------------------

    #[Test]
    public function s8_admin_reactivates_deactivated_user(): void
    {
        $admin = $this->makeAdmin();
        $grace = User::factory()->create([
            'email' => 'grace@example.com',
            'is_approved' => true,
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.users.activate', $grace));

        $response->assertRedirect(route('admin.index', ['tab' => 'users']));

        $grace->refresh();
        $this->assertTrue((bool) $grace->is_active);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user.activated',
            'actor_user_id' => $admin->id,
            'entity_id' => $grace->id,
        ]);

        // Grace can log in after reactivation
        $this->post(route('login'), [
            'email' => 'grace@example.com',
            'password' => 'password',
        ]);
        $this->assertAuthenticatedAs($grace->fresh());
    }

    // -------------------------------------------------------
    // S9 — Admin cannot deactivate themselves
    // Maps to: REQ-AUTH-040, REQ-AUTH-018
    // -------------------------------------------------------

    #[Test]
    public function s9_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin1@example.com']);

        $response = $this->actingAs($admin)
            ->post(route('admin.users.deactivate', $admin));

        $response->assertForbidden();

        $admin->refresh();
        $this->assertTrue((bool) $admin->is_active);
        $this->assertDatabaseCount('audit_events', 0);
    }

    // -------------------------------------------------------
    // S10 — Admin cannot reject another admin
    // Maps to: REQ-AUTH-040
    // -------------------------------------------------------

    #[Test]
    public function s10_admin_cannot_reject_another_admin(): void
    {
        $admin1 = $this->makeAdmin(['email' => 'admin1@example.com']);
        $admin2 = $this->makeAdmin(['email' => 'admin2@example.com']);

        $response = $this->actingAs($admin1)
            ->delete(route('admin.users.reject', $admin2));

        $response->assertForbidden();

        $this->assertDatabaseHas('users', ['email' => 'admin2@example.com']);
        $this->assertDatabaseCount('audit_events', 0);
    }

    // -------------------------------------------------------
    // S11 — Admin cannot delete another admin
    // Maps to: REQ-AUTH-040
    // -------------------------------------------------------

    #[Test]
    public function s11_admin_cannot_delete_another_admin(): void
    {
        $admin1 = $this->makeAdmin(['email' => 'admin1@example.com']);
        $admin2 = $this->makeAdmin(['email' => 'admin2@example.com']);

        $response = $this->actingAs($admin1)
            ->delete(route('admin.users.destroy', $admin2));

        $response->assertForbidden();

        $this->assertDatabaseHas('users', ['email' => 'admin2@example.com']);
        $this->assertDatabaseCount('audit_events', 0);
    }

    // -------------------------------------------------------
    // S11b — Admin cannot self-approve
    // Maps to: REQ-AUTH-018
    // -------------------------------------------------------

    #[Test]
    public function s11b_admin_cannot_self_approve(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.approve', $admin));

        $response->assertForbidden();
        $admin->refresh();
        // is_approved unchanged
        $this->assertTrue((bool) $admin->is_approved);
        $this->assertDatabaseCount('audit_events', 0);
    }

    // -------------------------------------------------------
    // S11b — Admin cannot self-reject
    // Maps to: REQ-AUTH-018
    // -------------------------------------------------------

    #[Test]
    public function s11b_admin_cannot_self_reject(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.reject', $admin));

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseCount('audit_events', 0);
    }

    // -------------------------------------------------------
    // S11b — Admin cannot self-activate
    // Maps to: REQ-AUTH-018
    // -------------------------------------------------------

    #[Test]
    public function s11b_admin_cannot_self_activate(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.activate', $admin));

        $response->assertForbidden();
        $admin->refresh();
        $this->assertTrue((bool) $admin->is_active); // unchanged
        $this->assertDatabaseCount('audit_events', 0);
    }

    // -------------------------------------------------------
    // S12 — Non-admin cannot invoke admin user-management endpoints
    // Maps to: REQ-AUTH-040 (admin middleware)
    // -------------------------------------------------------

    #[Test]
    public function s12_non_admin_cannot_approve_users(): void
    {
        $henry = User::factory()->create([
            'role' => 'user',
            'is_approved' => true,
            'is_active' => true,
        ]);
        $ivy = $this->makePendingUser(['email' => 'ivy@example.com']);

        $response = $this->actingAs($henry)
            ->post(route('admin.users.approve', $ivy));

        $response->assertForbidden();

        $ivy->refresh();
        $this->assertFalse((bool) $ivy->is_approved);
    }

    // -------------------------------------------------------
    // S13 — Duplicate email registration rejected; no new row
    // Maps to: REQ-AUTH-042
    // -------------------------------------------------------

    #[Test]
    public function s13_duplicate_email_registration_rejected_no_row_created(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);
        $countBefore = User::count();

        $response = $this->post(route('register'), [
            'name' => 'Jane Duplicate',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertEquals($countBefore, User::count());
        $this->assertGuest();
    }

    // -------------------------------------------------------
    // S14 — Approve is idempotent; second call returns 302+flash; zero extra audit rows
    // Maps to: REQ-AUTH-043 (binary-pinned)
    // -------------------------------------------------------

    #[Test]
    public function s14_approve_is_idempotent_second_returns_302_with_flash_no_extra_audit(): void
    {
        $admin = $this->makeAdmin();
        $kyle = $this->makePendingUser(['email' => 'kyle@example.com']);

        // First approve — winner
        $first = $this->actingAs($admin)
            ->post(route('admin.users.approve', $kyle));
        $first->assertRedirect(route('admin.index', ['tab' => 'users']));
        $first->assertSessionHas('status');

        $auditAfterFirst = AuditEvent::where('event_type', 'user.approved')
            ->where('entity_id', $kyle->id)
            ->count();
        $this->assertEquals(1, $auditAfterFirst);

        $approvedAt = $kyle->fresh()->approved_at;
        $approvedBy = $kyle->fresh()->approved_by;

        // Second approve — idempotent no-op; REQ-AUTH-043 requires 302+flash NOT 4xx
        $second = $this->actingAs($admin)
            ->post(route('admin.users.approve', $kyle));
        $second->assertRedirect(route('admin.index', ['tab' => 'users']));
        $second->assertSessionHas('status'); // no-op flash message

        $auditAfterSecond = AuditEvent::where('event_type', 'user.approved')
            ->where('entity_id', $kyle->id)
            ->count();
        $this->assertEquals(1, $auditAfterSecond); // zero new rows

        // approved_at and approved_by remain from the first call
        $kyle->refresh();
        $this->assertEquals($approvedAt->toDateTimeString(), $kyle->approved_at->toDateTimeString());
        $this->assertEquals($approvedBy, $kyle->approved_by);
    }

    // -------------------------------------------------------
    // S14b — Reject on an already-approved user returns 404
    // Maps to: REQ-AUTH-014 (pending-only guard)
    // -------------------------------------------------------

    #[Test]
    public function s14b_reject_on_approved_user_returns_404(): void
    {
        $admin = $this->makeAdmin();
        $quinn = User::factory()->create([
            'email' => 'quinn@example.com',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.reject', $quinn));

        $response->assertNotFound();

        $this->assertDatabaseHas('users', ['email' => 'quinn@example.com']);
        $this->assertDatabaseCount('audit_events', 0);
    }

    // -------------------------------------------------------
    // S14c — Delete on a pending user returns 404
    // Maps to: REQ-AUTH-017 (approved-only guard)
    // -------------------------------------------------------

    #[Test]
    public function s14c_delete_on_pending_user_returns_404(): void
    {
        $admin = $this->makeAdmin();
        $rachel = $this->makePendingUser(['email' => 'rachel@example.com']);

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $rachel));

        $response->assertNotFound();

        $this->assertDatabaseHas('users', ['email' => 'rachel@example.com']);
        $this->assertDatabaseCount('audit_events', 0);
    }

    // -------------------------------------------------------
    // S15 — Audit payload exact key set for user.approved
    // Maps to: REQ-AUTH-041
    // -------------------------------------------------------

    #[Test]
    public function s15_audit_payload_exact_keys_user_approved(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makePendingUser();

        $this->actingAs($admin)->post(route('admin.users.approve', $user));

        $audit = AuditEvent::where('event_type', 'user.approved')
            ->where('entity_id', $user->id)
            ->firstOrFail();
        $payload = $audit->payload;
        $actual = array_keys($payload);
        sort($actual);

        $expected = ['approved_at', 'approved_by', 'email', 'id', 'is_active', 'is_approved', 'name', 'role'];
        sort($expected);

        $this->assertEquals($expected, $actual);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('remember_token', $payload);
    }

    // -------------------------------------------------------
    // S15 — Audit payload exact key set for user.rejected
    // Maps to: REQ-AUTH-041
    // -------------------------------------------------------

    #[Test]
    public function s15_audit_payload_exact_keys_user_rejected(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makePendingUser();
        $userId = $user->id;

        $this->actingAs($admin)->delete(route('admin.users.reject', $user));

        $audit = AuditEvent::where('event_type', 'user.rejected')
            ->where('entity_id', $userId)
            ->firstOrFail();
        $payload = $audit->payload;
        $actual = array_keys($payload);
        sort($actual);

        $expected = ['email', 'id', 'is_active', 'is_approved', 'name', 'role'];
        sort($expected);

        $this->assertEquals($expected, $actual);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('remember_token', $payload);
        // rejected users were never approved; no approved_at / approved_by
        $this->assertArrayNotHasKey('approved_at', $payload);
        $this->assertArrayNotHasKey('approved_by', $payload);
    }

    // -------------------------------------------------------
    // S15 — Audit payload exact key set for user.deactivated
    // Maps to: REQ-AUTH-041
    // -------------------------------------------------------

    #[Test]
    public function s15_audit_payload_exact_keys_user_deactivated(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['is_approved' => true, 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.users.deactivate', $user));

        $audit = AuditEvent::where('event_type', 'user.deactivated')
            ->where('entity_id', $user->id)
            ->firstOrFail();
        $payload = $audit->payload;
        $actual = array_keys($payload);
        sort($actual);

        $expected = ['email', 'id', 'is_active', 'is_approved', 'name', 'role'];
        sort($expected);

        $this->assertEquals($expected, $actual);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('remember_token', $payload);
    }

    // -------------------------------------------------------
    // S15 — Audit payload exact key set for user.activated
    // Maps to: REQ-AUTH-041
    // -------------------------------------------------------

    #[Test]
    public function s15_audit_payload_exact_keys_user_activated(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['is_approved' => true, 'is_active' => false]);

        $this->actingAs($admin)->post(route('admin.users.activate', $user));

        $audit = AuditEvent::where('event_type', 'user.activated')
            ->where('entity_id', $user->id)
            ->firstOrFail();
        $payload = $audit->payload;
        $actual = array_keys($payload);
        sort($actual);

        $expected = ['email', 'id', 'is_active', 'is_approved', 'name', 'role'];
        sort($expected);

        $this->assertEquals($expected, $actual);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('remember_token', $payload);
    }

    // -------------------------------------------------------
    // S15 — Audit payload exact key set for user.deleted
    // Maps to: REQ-AUTH-041
    // -------------------------------------------------------

    #[Test]
    public function s15_audit_payload_exact_keys_user_deleted(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['is_approved' => true, 'is_active' => true]);
        $userId = $user->id;

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

        $audit = AuditEvent::where('event_type', 'user.deleted')
            ->where('entity_id', $userId)
            ->firstOrFail();
        $payload = $audit->payload;
        $actual = array_keys($payload);
        sort($actual);

        $expected = ['approved_at', 'approved_by', 'email', 'id', 'is_active', 'is_approved', 'name', 'role'];
        sort($expected);

        $this->assertEquals($expected, $actual);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('remember_token', $payload);
    }

    // -------------------------------------------------------
    // S16 — Admin-like email still creates pending user with role=user
    // Maps to: REQ-AUTH-044
    // -------------------------------------------------------

    #[Test]
    public function s16_admin_like_email_creates_pending_user_not_admin(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Admin Lookalike',
            'email' => 'admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'role' => 'user',   // must NOT be 'admin'
            'is_approved' => false,
        ]);
    }

    // -------------------------------------------------------
    // S17 — Approve on non-existent user ID returns 404
    // Maps to: route model binding defense-in-depth
    // -------------------------------------------------------

    #[Test]
    public function s17_approve_nonexistent_user_returns_404(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->post('/admin/users/999999/approve');

        $response->assertNotFound();
        $this->assertDatabaseCount('audit_events', 0);
    }

    // -------------------------------------------------------
    // S18 — Session invalidated when user is deactivated mid-session
    // Maps to: REQ-AUTH-021
    // -------------------------------------------------------

    #[Test]
    public function s18_session_invalidated_after_deactivation(): void
    {
        $admin = $this->makeAdmin();
        $liam = User::factory()->create([
            'email' => 'liam@example.com',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // Admin deactivates Liam
        $this->actingAs($admin)->post(route('admin.users.deactivate', $liam));

        // Liam hits a protected route — middleware must kick him out
        $response = $this->actingAs($liam)->get(route('studies.index'));

        // Should be redirected (EnsureUserIsActive extended to check is_active)
        $response->assertRedirect();
        $this->assertGuest();
    }

    // -------------------------------------------------------
    // S19 — Re-registration after rejection succeeds (no soft-delete; EX-006)
    // Maps to: EX-006, REQ-AUTH-014
    // -------------------------------------------------------

    #[Test]
    public function s19_reregister_after_reject_succeeds_with_new_row(): void
    {
        $admin = $this->makeAdmin();
        $mia = $this->makePendingUser(['name' => 'Mia', 'email' => 'mia@example.com']);
        $miaId = $mia->id;

        $this->actingAs($admin)->delete(route('admin.users.reject', $mia));
        $this->assertDatabaseMissing('users', ['email' => 'mia@example.com']);

        $response = $this->post(route('register'), [
            'name' => 'Mia Again',
            'email' => 'mia@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', ['email' => 'mia@example.com']);

        $newUser = User::where('email', 'mia@example.com')->firstOrFail();
        $this->assertNotEquals($miaId, $newUser->id);  // fresh row, not undelete
        $this->assertFalse((bool) $newUser->is_approved);
    }

    // -------------------------------------------------------
    // S20 — Pending count badge reflects pending users only
    // Maps to: REQ-AUTH-030
    // -------------------------------------------------------

    #[Test]
    public function s20_pending_badge_count_shows_only_pending_users(): void
    {
        $admin = $this->makeAdmin();

        // 3 pending
        User::factory()->count(3)->create(['is_approved' => false, 'is_active' => true]);
        // 5 approved active
        User::factory()->count(5)->create(['is_approved' => true, 'is_active' => true]);
        // 2 deactivated (approved but inactive) — must NOT count toward badge
        User::factory()->count(2)->create(['is_approved' => true, 'is_active' => false]);

        $response = $this->actingAs($admin)->get(route('admin.index'));

        $response->assertSuccessful();
        // Controller must pass pending_count variable to the view
        $response->assertViewHas('pending_count', 3);
    }

    // -------------------------------------------------------
    // S21 — Admin deletes approved non-admin user (REQ-AUTH-017 happy path)
    // Maps to: REQ-AUTH-017, REQ-AUTH-024, REQ-AUTH-025, REQ-AUTH-041
    // -------------------------------------------------------

    #[Test]
    public function s21_admin_deletes_approved_non_admin_user(): void
    {
        $admin = $this->makeAdmin();
        $noah = User::factory()->create([
            'name' => 'Noah',
            'email' => 'noah@example.com',
            'role' => 'user',
            'is_approved' => true,
            'is_active' => true,
        ]);
        $noahId = $noah->id;

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $noah));

        $response->assertRedirect(route('admin.index', ['tab' => 'users']));
        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('users', ['email' => 'noah@example.com']);

        // REQ-AUTH-024: audit row written with event_type distinct from user.rejected
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user.deleted',
            'actor_user_id' => $admin->id,
            'entity_id' => $noahId,
        ]);

        // REQ-AUTH-041: payload preserves identity; is_approved=true (approved at time of delete)
        $audit = AuditEvent::where('event_type', 'user.deleted')->where('entity_id', $noahId)->firstOrFail();
        $payload = $audit->payload;
        $this->assertEquals('noah@example.com', $payload['email']);
        $this->assertEquals('Noah', $payload['name']);
        $this->assertEquals('user', $payload['role']);
        $this->assertTrue((bool) $payload['is_approved']);
    }

    // -------------------------------------------------------
    // S22 — Concurrent approve race; loser gets 302+flash; zero extra audit rows
    // Maps to: REQ-AUTH-043, REQ-AUTH-024
    // -------------------------------------------------------

    #[Test]
    public function s22_concurrent_approve_race_loser_gets_302_no_extra_audit(): void
    {
        $admin1 = $this->makeAdmin(['email' => 'admin1@example.com']);
        $admin2 = $this->makeAdmin(['email' => 'admin2@example.com']);
        $oliver = $this->makePendingUser(['email' => 'oliver@example.com']);

        // Admin1 wins the race (approves first)
        $this->actingAs($admin1)->post(route('admin.users.approve', $oliver));

        $auditCountAfterWinner = AuditEvent::where('event_type', 'user.approved')
            ->where('entity_id', $oliver->id)
            ->count();
        $this->assertEquals(1, $auditCountAfterWinner);

        $winnerApprovedBy = $oliver->fresh()->approved_by;
        $this->assertEquals($admin1->id, $winnerApprovedBy);

        // Admin2 arrives after commit — WHERE is_approved=false guard fires (REQ-AUTH-043)
        // Must return 302 redirect with flash NOT a 4xx
        $loserResponse = $this->actingAs($admin2)
            ->post(route('admin.users.approve', $oliver));
        $loserResponse->assertRedirect(route('admin.index', ['tab' => 'users']));
        $loserResponse->assertSessionHas('status'); // no-op flash

        // Zero new audit rows written for the losing call
        $auditCountAfterLoser = AuditEvent::where('event_type', 'user.approved')
            ->where('entity_id', $oliver->id)
            ->count();
        $this->assertEquals(1, $auditCountAfterLoser);

        // approved_by reflects admin1 (winner), not overwritten by admin2 (loser)
        $oliver->refresh();
        $this->assertEquals($admin1->id, $oliver->approved_by);
    }

    // -------------------------------------------------------
    // S23 — Re-registration after Delete (approved user) succeeds (EX-006)
    // Maps to: REQ-AUTH-017, EX-006
    // -------------------------------------------------------

    #[Test]
    public function s23_reregister_after_delete_approved_user_creates_fresh_row(): void
    {
        $admin = $this->makeAdmin();
        $paul = User::factory()->create([
            'name' => 'Paul',
            'email' => 'paul@example.com',
            'is_approved' => true,
            'is_active' => true,
        ]);
        $paulId = $paul->id;

        $this->actingAs($admin)->delete(route('admin.users.destroy', $paul));
        $this->assertDatabaseMissing('users', ['email' => 'paul@example.com']);

        $response = $this->post(route('register'), [
            'name' => 'Paul Again',
            'email' => 'paul@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', ['email' => 'paul@example.com']);

        $newUser = User::where('email', 'paul@example.com')->firstOrFail();
        $this->assertNotEquals($paulId, $newUser->id);  // fresh row; not an undelete
        $this->assertFalse((bool) $newUser->is_approved);
    }

    // -------------------------------------------------------
    // S24 — Audit fault injection rolls back user-row mutation (REQ-AUTH-024)
    // Maps to: REQ-AUTH-024
    // -------------------------------------------------------

    #[Test]
    public function s24_audit_fault_injection_rolls_back_user_mutation(): void
    {
        $admin = $this->makeAdmin();
        $sam = $this->makePendingUser(['email' => 'sam@example.com']);

        // Force AuditService::log() to throw, simulating a DB write failure
        $this->mock(AuditService::class, function ($mock): void {
            $mock->shouldReceive('log')
                ->andThrow(new \RuntimeException('Simulated audit DB failure'));
        });

        $response = $this->actingAs($admin)
            ->post(route('admin.users.approve', $sam));

        // REQ-AUTH-024: exception propagates → 5xx (true transactional atomicity)
        $response->assertServerError();

        // Sam's row must remain pending (user-row UPDATE rolled back with the audit INSERT)
        $this->assertDatabaseHas('users', [
            'email' => 'sam@example.com',
            'is_approved' => false,
        ]);

        // No user.approved audit row exists for Sam
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'user.approved',
            'entity_id' => $sam->id,
        ]);
    }
}
