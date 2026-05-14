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
 * SPEC-IRB-FORMSV2-006 Phase 6 — Aggregate count tests for HRP-398.
 *
 * 3 tests:
 *   - Zero state: all 15 items default to not_started
 *   - Partial state: 5 addressed + 3 needs_work + 7 not_started
 *   - Index badge text matches "HRP-398: {addressed}/15 items addressed" format
 *
 * Covers REQ-P6-003, S-P6-4.
 */
class Hrp398AggregateCountsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    private Submission $hrp398Submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Counts Test']);

        $this->hrp398Submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-398'))
            ->firstOrFail();
    }

    // ── Zero state ────────────────────────────────────────────────────────────

    #[Test]
    public function zero_state_all_15_items_default_to_not_started(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-398',
            ]))
            ->assertOk();

        $counts = $response->viewData('counts');

        $this->assertSame(15, $counts['total']);
        $this->assertSame(0, $counts['addressed']);
        $this->assertSame(0, $counts['needs_work']);
        $this->assertSame(0, $counts['not_applicable']);
        $this->assertSame(15, $counts['not_started']);
    }

    // ── Partial state ─────────────────────────────────────────────────────────

    #[Test]
    public function partial_state_5_addressed_3_needs_work_7_not_started(): void
    {
        // 15 direct-item IDs in schema order (top-level section->items[] only)
        $itemIds = [
            'inst_1',
            'tech_1_name_and_status', 'tech_2_purpose', 'tech_3_availability',
            'mdv_methodology', 'mdv_purpose', 'mdv_kind', 'mdv_adaptivity',
            'purpose_phase', 'purpose_role', 'purpose_inform_or_drive',
            'irb_1',
            'fda_1',
            'misc_future_modifications', 'misc_accountability',
        ];

        $addressed = array_slice($itemIds, 0, 5);   // items 0-4
        $needsWork = array_slice($itemIds, 5, 3);   // items 5-7
        // remaining 7 stay not_started (no rows inserted)

        foreach ($addressed as $id) {
            DB::table('worksheet_assist_state')->insert([
                'submission_id' => $this->hrp398Submission->id,
                'worksheet_form_id' => 'HRP-398',
                'item_id' => $id,
                'status' => 'addressed',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($needsWork as $id) {
            DB::table('worksheet_assist_state')->insert([
                'submission_id' => $this->hrp398Submission->id,
                'worksheet_form_id' => 'HRP-398',
                'item_id' => $id,
                'status' => 'needs_work',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-398',
            ]))
            ->assertOk();

        $counts = $response->viewData('counts');

        $this->assertSame(15, $counts['total']);
        $this->assertSame(5, $counts['addressed']);
        $this->assertSame(3, $counts['needs_work']);
        $this->assertSame(0, $counts['not_applicable']);
        $this->assertSame(7, $counts['not_started']);
    }

    // ── Badge format (REQ-049a) ────────────────────────────────────────────────

    #[Test]
    public function index_badge_text_matches_hrp398_addressed_of_15_format(): void
    {
        // Seed 5 addressed items so the badge is non-trivial (all must be from the 15 direct items)
        $itemIds = ['inst_1', 'tech_1_name_and_status', 'tech_2_purpose', 'tech_3_availability', 'irb_1'];

        foreach ($itemIds as $id) {
            DB::table('worksheet_assist_state')->insert([
                'submission_id' => $this->hrp398Submission->id,
                'worksheet_form_id' => 'HRP-398',
                'item_id' => $id,
                'status' => 'addressed',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // The panel view renders "Progress — {addressed}/{total} items addressed"
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-398',
            ]))
            ->assertOk();

        $html = $response->getContent();

        // The view renders: "Progress — 5/15 items addressed"
        $this->assertStringContainsString(
            '5/15 items addressed',
            $html,
            'Badge text must match "HRP-398: 5/15 items addressed" format (REQ-049a)',
        );
    }
}
