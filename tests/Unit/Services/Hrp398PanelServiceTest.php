<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use App\Services\Hrp398PanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-006 Phase 6 — Unit tests for Hrp398PanelService.
 *
 * 4 tests:
 *   - Item ordering preserved (schema order)
 *   - Missing assist-state row → item defaults to not_started
 *   - Cache flushed in setUp (mirror SectionTriggerEvaluatorTest F-QA-1 pattern)
 *   - aggregateCounts edge cases (all addressed → addressed=15, total=15)
 *
 * Covers REQ-P6-001, REQ-P6-003.
 */
class Hrp398PanelServiceTest extends TestCase
{
    use RefreshDatabase;

    private Hrp398PanelService $service;

    private Submission $hrp398Submission;

    /**
     * Flush the `array` cache between methods so the service's
     * rememberForever('hrp398_items', …) is rebuilt from scratch.
     * Locks the test contract per QA review F-QA-1.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Cache::store('array')->flush();

        $this->service = new Hrp398PanelService;

        $user = User::factory()->create(['is_approved' => true]);
        $study = Study::createForUser($user->id, ['application_title' => 'Service Test']);

        $this->hrp398Submission = $study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-398'))
            ->firstOrFail();
    }

    // ── Item ordering preserved ────────────────────────────────────────────────

    #[Test]
    public function item_ordering_matches_schema_order(): void
    {
        $panelData = $this->service->loadItemsForSubmission($this->hrp398Submission);

        // First section must be "Institutional Considerations" with item inst_1
        $firstSection = $panelData->first();
        $this->assertNotNull($firstSection);
        $this->assertSame('Institutional Considerations', $firstSection['section_title']);
        $this->assertSame('inst_1', $firstSection['items'][0]['id']);

        // Last section must be "Misc. Considerations" with misc_future_modifications first
        $lastSection = $panelData->last();
        $this->assertNotNull($lastSection);
        $this->assertSame('Misc. Considerations', $lastSection['section_title']);
        $this->assertSame('misc_future_modifications', $lastSection['items'][0]['id']);
    }

    // ── Missing assist-state → not_started ────────────────────────────────────

    #[Test]
    public function missing_assist_state_defaults_to_not_started(): void
    {
        // No worksheet_assist_state rows for this submission
        $panelData = $this->service->loadItemsForSubmission($this->hrp398Submission);

        foreach ($panelData as $section) {
            foreach ($section['items'] as $item) {
                $this->assertSame(
                    'not_started',
                    $item['status'],
                    "Item '{$item['id']}' with no DB row must default to not_started",
                );
                $this->assertNull($item['notes'], "Item '{$item['id']}' notes must be null when no DB row");
            }
        }
    }

    // ── Cache is flushed between tests (F-QA-1 pattern) ──────────────────────

    #[Test]
    public function schema_cache_is_rebuilt_after_flush(): void
    {
        // First call — populates cache
        $first = $this->service->loadItemsForSubmission($this->hrp398Submission);

        // Flush and rebuild — must produce identical result
        Cache::store('array')->flush();
        $service2 = new Hrp398PanelService;
        $second = $service2->loadItemsForSubmission($this->hrp398Submission);

        $this->assertCount($first->count(), $second);

        // Item IDs must be identical in both calls
        $firstIds = $first->flatMap(fn ($s) => collect($s['items'])->pluck('id'))->values()->all();
        $secondIds = $second->flatMap(fn ($s) => collect($s['items'])->pluck('id'))->values()->all();

        $this->assertSame($firstIds, $secondIds, 'Item IDs must be identical after cache flush and rebuild');
    }

    // ── aggregateCounts edge case: all addressed ──────────────────────────────

    #[Test]
    public function all_items_addressed_yields_addressed_15_and_total_15(): void
    {
        $itemIds = [
            'inst_1',
            'tech_1_name_and_status', 'tech_2_purpose', 'tech_3_availability',
            'mdv_methodology', 'mdv_purpose', 'mdv_kind', 'mdv_adaptivity',
            'purpose_phase', 'purpose_role', 'purpose_inform_or_drive',
            'irb_1',
            'fda_1',
            'misc_future_modifications', 'misc_accountability',
        ];

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

        $counts = $this->service->aggregateCounts($this->hrp398Submission);

        $this->assertSame(15, $counts['total']);
        $this->assertSame(15, $counts['addressed']);
        $this->assertSame(0, $counts['needs_work']);
        $this->assertSame(0, $counts['not_applicable']);
        $this->assertSame(0, $counts['not_started']);
    }
}
