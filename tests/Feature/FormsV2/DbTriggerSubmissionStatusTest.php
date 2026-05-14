<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Models\Study;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-004 §H.6
 * Covers the MariaDB BEFORE UPDATE trigger `submission_tracking_only_guard`.
 *
 * The trigger raises SQLSTATE 45000 when an attempt is made to change
 * `status` away from `tracking_only`. This is DB-level enforcement of
 * REQ-IRB-FORMSV2-040 / Outstanding Issue #61.
 *
 * Note: The trigger is installed by migration
 * 2026_05_13_010003_add_check_constraint_submission_status.php.
 * RefreshDatabase re-runs all migrations including this one, so the trigger
 * should be present for every test in this class.
 */
class DbTriggerSubmissionStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Trigger Test']);
    }

    // ── Trigger enforcement ───────────────────────────────────────────────────

    #[Test]
    public function updating_status_away_from_tracking_only_throws_query_exception(): void
    {
        // Force a submission directly into tracking_only by bypassing Eloquent protections
        $submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-398'))
            ->first();

        $this->assertNotNull($submission, 'HRP-398 submission must exist');

        // Set to tracking_only via raw SQL to bypass Eloquent listeners
        DB::table('submission')
            ->where('id', $submission->id)
            ->update(['status' => 'tracking_only']);

        $this->assertDatabaseHas('submission', [
            'id' => $submission->id,
            'status' => 'tracking_only',
        ]);

        // Now attempt to change status away — trigger must reject it
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('submission')
            ->where('id', $submission->id)
            ->update(['status' => 'draft']);
    }

    #[Test]
    public function trigger_allows_updating_non_status_column_on_tracking_only_row(): void
    {
        $submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-398'))
            ->first();

        $this->assertNotNull($submission);

        DB::table('submission')
            ->where('id', $submission->id)
            ->update(['status' => 'tracking_only']);

        // Updating assistance_mode (non-status column) must succeed even on tracking_only rows
        DB::table('submission')
            ->where('id', $submission->id)
            ->update(['assistance_mode' => 'strict']);

        $this->assertDatabaseHas('submission', [
            'id' => $submission->id,
            'status' => 'tracking_only',
            'assistance_mode' => 'strict',
        ]);
    }

    #[Test]
    public function trigger_allows_keeping_status_as_tracking_only(): void
    {
        $submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-398'))
            ->first();

        $this->assertNotNull($submission);

        DB::table('submission')
            ->where('id', $submission->id)
            ->update(['status' => 'tracking_only']);

        // Setting status to tracking_only again must be a no-op (trigger allows same value)
        DB::table('submission')
            ->where('id', $submission->id)
            ->update(['status' => 'tracking_only']);

        $this->assertDatabaseHas('submission', [
            'id' => $submission->id,
            'status' => 'tracking_only',
        ]);
    }

    #[Test]
    public function non_tracking_only_submission_allows_status_transition(): void
    {
        $submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503c'))
            ->first();

        $this->assertNotNull($submission);

        // Ensure it starts as draft
        $this->assertSame('draft', $submission->status);

        // draft → submitted should succeed (not tracking_only)
        DB::table('submission')
            ->where('id', $submission->id)
            ->update(['status' => 'submitted']);

        $this->assertDatabaseHas('submission', [
            'id' => $submission->id,
            'status' => 'submitted',
        ]);
    }
}
