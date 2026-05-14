<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Exceptions\InvalidSubmissionStateTransition;
use App\Models\FormDefinition;
use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Defense-in-depth security tests added in response to the Phase 3 security audit.
 *
 * Locks down:
 *   - F1: Submission::saving event enforces tracking_only invariant across all
 *         persistence paths (not just markStatus()).
 *   - F2: Submission::$fillable no longer accepts user_id / study_id /
 *         form_definition_id / status — mass assignment cannot rebind these.
 *
 * The audit's F1 listed 4 bypass paths that all must throw or no-op:
 *   1. $submission->update(['status' => 'submitted']) on a tracking_only row
 *   2. $submission->status = 'submitted'; $submission->save() on tracking_only
 *   3. $submission->fill(['status' => 'submitted'])->save() on tracking_only
 *   4. Submission::factory()->create(['status' => 'submitted']) → status filter
 */
class SubmissionInvariantTest extends TestCase
{
    use RefreshDatabase;

    private Submission $hrp398Sub;

    private Submission $hrp503Sub;

    protected function setUp(): void
    {
        parent::setUp();

        // Run the FormsV2 seed migration since RefreshDatabase wipes seeded data.
        $this->seedFormsV2();

        $user = User::factory()->create();
        $study = Study::createForUser($user->id, ['application_title' => 'Inv-test']);
        // boot hook auto-created 3 submissions
        $this->hrp398Sub = $study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-398'))
            ->firstOrFail();
        $this->hrp503Sub = $study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503'))
            ->firstOrFail();
    }

    private function seedFormsV2(): void
    {
        // The seed migration has run via RefreshDatabase; just verify FormDefinition exists.
        if (FormDefinition::query()->count() < 3) {
            $this->fail('FormDefinition seed not present after migrate:fresh — Phase 3 migration broken.');
        }
    }

    public function test_update_with_mass_assignment_cannot_change_tracking_only_status(): void
    {
        // Bypass path #1: mass assignment via update(). Because status was
        // removed from $fillable (F2), this is a NO-OP (silently ignored), not
        // a throw. But the row's status must remain tracking_only.
        $this->hrp398Sub->update(['status' => 'submitted']);

        $this->hrp398Sub->refresh();
        $this->assertSame('tracking_only', $this->hrp398Sub->status);
    }

    public function test_direct_attribute_assignment_throws_on_tracking_only(): void
    {
        // Bypass path #2: direct attribute set + save(). The saving event
        // listener must catch this.
        $this->expectException(InvalidSubmissionStateTransition::class);
        $this->hrp398Sub->status = 'submitted';
        $this->hrp398Sub->save();
    }

    public function test_fill_then_save_cannot_change_tracking_only(): void
    {
        // Bypass path #3: fill (which respects $fillable so status not set) + save().
        // Should NOT change status because status is not in fillable.
        $this->hrp398Sub->fill(['status' => 'submitted'])->save();
        $this->hrp398Sub->refresh();
        $this->assertSame('tracking_only', $this->hrp398Sub->status);
    }

    public function test_mark_status_throws_on_tracking_only(): void
    {
        $this->expectException(InvalidSubmissionStateTransition::class);
        $this->hrp398Sub->markStatus('submitted');
    }

    public function test_raw_db_update_is_rejected_by_db_trigger(): void
    {
        // Phase 4 PR-1 adds a MariaDB BEFORE UPDATE trigger that rejects any
        // attempt to change status away from tracking_only at the DB level.
        // This supersedes the Phase 3 "documents the bypass" note.
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('submission')->where('id', $this->hrp398Sub->id)->update(['status' => 'submitted']);
    }

    public function test_normal_status_transition_on_hrp503_still_works(): void
    {
        $this->assertSame('draft', $this->hrp503Sub->status);
        $this->hrp503Sub->markStatus('submitted');
        $this->hrp503Sub->refresh();
        $this->assertSame('submitted', $this->hrp503Sub->status);
    }

    public function test_invalid_status_value_is_rejected(): void
    {
        $this->expectException(InvalidSubmissionStateTransition::class);
        $this->hrp503Sub->markStatus('not-a-real-status');
    }

    public function test_mass_assignment_cannot_rebind_user_id(): void
    {
        $otherUser = User::factory()->create();
        $this->hrp503Sub->update(['user_id' => $otherUser->id]);
        $this->hrp503Sub->refresh();
        $this->assertNotSame($otherUser->id, $this->hrp503Sub->user_id);
    }

    public function test_mass_assignment_cannot_rebind_study_id(): void
    {
        $otherUser = User::factory()->create();
        $otherStudy = Study::createForUser($otherUser->id, ['application_title' => 'Other study']);
        $this->hrp503Sub->update(['study_id' => $otherStudy->id]);
        $this->hrp503Sub->refresh();
        $this->assertNotSame($otherStudy->id, $this->hrp503Sub->study_id);
    }

    public function test_mass_assignment_cannot_rebind_form_definition_id(): void
    {
        $otherDef = FormDefinition::query()->where('form_code', 'HRP-398')->firstOrFail();
        $this->hrp503Sub->update(['form_definition_id' => $otherDef->id]);
        $this->hrp503Sub->refresh();
        $this->assertNotSame($otherDef->id, $this->hrp503Sub->form_definition_id);
    }
}
