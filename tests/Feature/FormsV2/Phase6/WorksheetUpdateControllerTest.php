<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2\Phase6;

use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-006 Phase 6 — WorksheetAssistStateController tests.
 *
 * 6 tests per acceptance gate item 7:
 *   - 200 on valid status + notes update
 *   - 422 on invalid status
 *   - 422 on notes > 65535
 *   - 404 on non-owner submission attempt (NotFound to prevent enumeration)
 *   - 422 on non-HRP-398 submission (form_code mismatch)
 *   - Idempotent: 2 calls to same item upsert correctly
 *
 * Covers S-P6-1, S-P6-2, S-P6-3, S-P6-6.
 */
class WorksheetUpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    private Submission $hrp398Submission;

    private Submission $hrp503Submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Phase 6 Test']);

        $this->hrp398Submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-398'))
            ->firstOrFail();

        $this->hrp503Submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503'))
            ->firstOrFail();
    }

    // ── 200 on valid update ────────────────────────────────────────────────────

    #[Test]
    public function valid_status_and_notes_returns_200(): void
    {
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.worksheet.update', [
                    'submission_uuid' => $this->hrp398Submission->id,
                    'item_id' => 'inst_1',
                ]),
                [
                    'status' => 'addressed',
                    'notes' => 'Confirmed in protocol section 2.',
                ],
            )
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);

        $this->assertDatabaseHas('worksheet_assist_state', [
            'submission_id' => $this->hrp398Submission->id,
            'worksheet_form_id' => 'HRP-398',
            'item_id' => 'inst_1',
            'status' => 'addressed',
            'notes' => 'Confirmed in protocol section 2.',
        ]);
    }

    // ── 422 on invalid status ─────────────────────────────────────────────────

    #[Test]
    public function invalid_status_returns_422(): void
    {
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.worksheet.update', [
                    'submission_uuid' => $this->hrp398Submission->id,
                    'item_id' => 'inst_1',
                ]),
                ['status' => 'invalid_status'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('status');
    }

    // ── 422 on notes > 65535 ──────────────────────────────────────────────────

    #[Test]
    public function notes_exceeding_65535_chars_returns_422(): void
    {
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.worksheet.update', [
                    'submission_uuid' => $this->hrp398Submission->id,
                    'item_id' => 'inst_1',
                ]),
                [
                    'status' => 'addressed',
                    'notes' => str_repeat('x', 65536),
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('notes');
    }

    // ── 404 on non-owner attempt ──────────────────────────────────────────────

    #[Test]
    public function non_owner_gets_404_to_prevent_enumeration(): void
    {
        $other = User::factory()->create(['is_approved' => true]);

        $this->actingAs($other)
            ->putJson(
                route('submissions.worksheet.update', [
                    'submission_uuid' => $this->hrp398Submission->id,
                    'item_id' => 'inst_1',
                ]),
                ['status' => 'addressed'],
            )
            ->assertNotFound();
    }

    // ── 422 on non-HRP-398 form ────────────────────────────────────────────────

    #[Test]
    public function non_hrp398_submission_returns_422(): void
    {
        // Attempt to post worksheet state for an HRP-503 submission
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.worksheet.update', [
                    'submission_uuid' => $this->hrp503Submission->id,
                    'item_id' => 'inst_1',
                ]),
                ['status' => 'addressed'],
            )
            ->assertStatus(422);
    }

    // ── Idempotent upsert (S-P6-6) ────────────────────────────────────────────

    #[Test]
    public function second_call_overwrites_first_upsert_correctly(): void
    {
        $route = route('submissions.worksheet.update', [
            'submission_uuid' => $this->hrp398Submission->id,
            'item_id' => 'tech_1_name_and_status',
        ]);

        // First call
        $this->actingAs($this->user)
            ->putJson($route, ['status' => 'needs_work', 'notes' => 'First note'])
            ->assertOk();

        // Second call with different status
        $this->actingAs($this->user)
            ->putJson($route, ['status' => 'addressed', 'notes' => 'Updated note'])
            ->assertOk();

        // Only 1 row should exist (UNIQUE constraint on submission_id, worksheet_form_id, item_id)
        $count = DB::table('worksheet_assist_state')
            ->where('submission_id', $this->hrp398Submission->id)
            ->where('worksheet_form_id', 'HRP-398')
            ->where('item_id', 'tech_1_name_and_status')
            ->count();

        $this->assertSame(1, $count, 'UNIQUE constraint: only 1 row per (submission_id, worksheet_form_id, item_id)');

        $this->assertDatabaseHas('worksheet_assist_state', [
            'submission_id' => $this->hrp398Submission->id,
            'worksheet_form_id' => 'HRP-398',
            'item_id' => 'tech_1_name_and_status',
            'status' => 'addressed',
            'notes' => 'Updated note',
        ]);
    }
}
