<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2\Phase6;

use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-006 Phase 6 — HRP-398 panel render tests.
 *
 * 4 tests per acceptance gate:
 *   - 9 section headings present in HTML
 *   - 15 guidance item labels present in HTML
 *   - Aggregate counts card present
 *   - REQ-014a regression: submission.status remains `tracking_only` after item status changes
 *
 * Covers REQ-P6-001, REQ-P6-003, REQ-P6-005 (S-P6-5).
 */
class Hrp398PanelRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    private Submission $hrp398Submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Render Test']);

        $this->hrp398Submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-398'))
            ->firstOrFail();
    }

    // ── 9 section headings present ─────────────────────────────────────────────

    #[Test]
    public function nine_section_headings_are_present_in_html(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-398',
            ]))
            ->assertOk()
            ->assertViewIs('submissions.hrp398_panel');

        $html = $response->getContent();

        $expectedTitles = [
            'Institutional Considerations',
            'Description of AI Technology',
            'For Model Development and Validation',
            'AI&#039;s Purpose in Study',  // Blade {{ }} HTML-escapes the apostrophe
            'Does This Study Require IRB Review?',
            'FDA: Is the technology possibly regulated by FDA?',
            'Additional Ethical Considerations',
            'Privacy &amp; Confidentiality',
            'Misc. Considerations',
        ];

        foreach ($expectedTitles as $title) {
            $this->assertStringContainsString(
                $title,
                $html,
                "Section heading '{$title}' must be present in rendered HTML",
            );
        }
    }

    // ── 15 guidance item labels present ───────────────────────────────────────

    #[Test]
    public function fifteen_guidance_item_labels_are_present_in_html(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-398',
            ]))
            ->assertOk();

        $html = $response->getContent();

        // Spot-check all 15 top-level section guidance item labels.
        // These are direct section->items[] entries (not group-nested or subsection items).
        // Sections 7 (additional_ethical_considerations) and 8 (privacy_confidentiality)
        // have 0 direct guidance items; section headings still appear in the accordion.
        $expectedLabels = [
            // institutional (1 item)
            'Does the study involve',
            // technology_description (3 items)
            'The protocol lists the name of the technology',
            'The protocol describes the purpose of the technology',
            'The protocol describes whether the technology is currently available',
            // model_dev_validation (4 items)
            'Does the technology have a transparent methodology',
            'Purpose of the Technology',
            'What kind of technology is being utilized',
            'Algorithm adaptivity',
            // ai_purpose_in_study (3 items)
            'What is the technology&#039;s CURRENT phase',
            'ROLE of the AI',
            'Is the technology intended to',
            // irb_review_required (1 item)
            'Refer to HRP-310',
            // fda_regulation (1 item)
            'Refer to HRP-307a',
            // misc_considerations (2 items)
            'Can the protocol be designed broad enough',
            'The protocol describes how technology is designed and implemented',
        ];

        foreach ($expectedLabels as $label) {
            $this->assertStringContainsString(
                $label,
                $html,
                "Item label containing '{$label}' must be present in rendered HTML",
            );
        }

        // Also verify the service returned exactly 15 items total
        $panelData = $response->viewData('panelData');
        $totalItems = collect($panelData)->sum(fn ($s) => count($s['items']));
        $this->assertSame(15, $totalItems, 'Panel must contain exactly 15 guidance items');
    }

    // ── Aggregate counts card present ─────────────────────────────────────────

    #[Test]
    public function aggregate_counts_card_is_present_in_html(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-398',
            ]))
            ->assertOk();

        $html = $response->getContent();

        // The counts card must contain the summary heading and all 4 chip labels
        $this->assertStringContainsString('Progress', $html, 'Counts card heading must be present');
        $this->assertStringContainsString('addressed', $html, 'addressed chip must be present');
        $this->assertStringContainsString('needs work', $html, 'needs work chip must be present');
        $this->assertStringContainsString('N/A', $html, 'N/A chip must be present');
        $this->assertStringContainsString('not started', $html, 'not started chip must be present');

        // Counts view data must be passed to view
        $counts = $response->viewData('counts');
        $this->assertArrayHasKey('total', $counts);
        $this->assertArrayHasKey('addressed', $counts);
        $this->assertArrayHasKey('needs_work', $counts);
        $this->assertArrayHasKey('not_applicable', $counts);
        $this->assertArrayHasKey('not_started', $counts);
        $this->assertSame(15, $counts['total']);
    }

    // ── REQ-014a regression: tracking_only invariant ──────────────────────────

    #[Test]
    public function submission_status_remains_tracking_only_after_item_status_change(): void
    {
        // Update an item
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.worksheet.update', [
                    'submission_uuid' => $this->hrp398Submission->id,
                    'item_id' => 'inst_1',
                ]),
                ['status' => 'addressed'],
            )
            ->assertOk();

        // Reload from DB — status must NOT have changed
        $fresh = Submission::find($this->hrp398Submission->id);
        $this->assertSame(
            'tracking_only',
            $fresh->status,
            'REQ-014a: Submission.status must remain tracking_only after worksheet item update',
        );
    }
}
