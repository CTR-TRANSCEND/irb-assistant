<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2\Phase5;

use App\Models\Study;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-005 Phase 5 — REQ-P5-001 (section group nav).
 *
 * Covers:
 *   - HRP-503 show page renders 8 section-group nav entries.
 *   - Locked sections are rendered with "Locked" badge in HTML.
 *   - Visible sections render as anchor links (href="#section-X.0").
 *   - $sectionGroups view data is present with correct count.
 *   - $sectionVisibility view data is present.
 */
class SectionGroupNavTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Nav Test']);
    }

    // ── Basic render ──────────────────────────────────────────────────────────────

    #[Test]
    public function hrp503_show_returns_200_and_passes_section_groups_to_view(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk()
            ->assertViewIs('submissions.show');

        $sectionGroups = $response->viewData('sectionGroups');
        $this->assertNotNull($sectionGroups, '$sectionGroups must be passed to HRP-503 view');
        $this->assertCount(8, $sectionGroups, 'HRP-503 must have exactly 8 section groups');
    }

    #[Test]
    public function hrp503_show_passes_section_visibility_map_to_view(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk();

        $sectionVisibility = $response->viewData('sectionVisibility');
        $this->assertIsArray($sectionVisibility, '$sectionVisibility must be an array');
        $this->assertNotEmpty($sectionVisibility, '$sectionVisibility must not be empty');
    }

    // ── Locked sections rendered dimmed ───────────────────────────────────────────

    #[Test]
    public function gated_sections_are_locked_by_default_with_no_trigger_answers(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk();

        $sectionVisibility = $response->viewData('sectionVisibility');

        // Section 3.0 is gated by Q2.6 and should be locked with no answers
        $this->assertArrayHasKey('3.0', $sectionVisibility, 'Section 3.0 must be present in visibility map');
        $this->assertFalse($sectionVisibility['3.0'], 'Section 3.0 should be locked by default');

        // Verify the HTML contains the locked card for section 3.0
        $response->assertSee('Locked — complete the trigger question to unlock this section.', false);
    }

    #[Test]
    public function unlocked_section_renders_anchor_link_in_html(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk();

        $sectionVisibility = $response->viewData('sectionVisibility');

        // Section 1.0 is always visible (ungated)
        $this->assertTrue($sectionVisibility['1.0'] ?? false, 'Section 1.0 should be visible (ungated)');

        // Anchor link should be present in nav
        $response->assertSee('href="#section-1.0"', false);

        // The section element id should be present
        $response->assertSee('id="section-1.0"', false);
    }

    // ── After trigger fires ───────────────────────────────────────────────────────

    #[Test]
    public function triggered_section_becomes_visible_and_anchor_renders(): void
    {
        $submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503'))
            ->firstOrFail();

        // Fire Q2.6 with drugs_biologics to reveal section 3.0
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $submission->id,
                    'question_key' => 'q2_6',
                ]),
                ['json_value' => ['drugs_biologics']],
            )
            ->assertOk();

        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk();

        $sectionVisibility = $response->viewData('sectionVisibility');
        $this->assertTrue($sectionVisibility['3.0'] ?? false, 'Section 3.0 should be visible after Q2.6 trigger');

        // The locked card should NOT be present for 3.0, the real section should render
        $response->assertSee('id="section-3.0"', false);
    }

    // ── HRP-503c should NOT get section group nav ─────────────────────────────────

    #[Test]
    public function hrp503c_does_not_receive_section_groups(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503c',
                'tab' => 'review',
            ]))
            ->assertOk();

        $sectionGroups = $response->viewData('sectionGroups');
        $this->assertEmpty($sectionGroups, 'HRP-503c must not have section groups in view data');
    }
}
