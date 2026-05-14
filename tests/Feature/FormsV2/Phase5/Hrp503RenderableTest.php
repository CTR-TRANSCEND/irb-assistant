<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2\Phase5;

use App\Models\FormSection;
use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-005 Phase 5 — Golden path renderable test.
 *
 * Acceptance gate item 8: HRP-503 golden-path test.
 * Covers:
 *   - HRP-503 show page returns HTTP 200.
 *   - All 43 section IDs are present in HTML (visible or locked).
 *   - Q2.6 fillable (PUT returns 200).
 *   - Filling Q2.6 with 3 triggers unlocks 3 sections.
 */
class Hrp503RenderableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'HRP-503 Renderable']);
        $this->submission = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503'))
            ->firstOrFail();
    }

    // ── Golden path: page loads and all section anchors present ──────────────────

    #[Test]
    public function hrp503_show_page_returns_200(): void
    {
        $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk()
            ->assertViewIs('submissions.show');
    }

    #[Test]
    public function all_section_anchor_ids_are_present_in_html(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk();

        // Get all section codes from the DB
        $sectionCodes = FormSection::query()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503'))
            ->pluck('section_code')
            ->all();

        $this->assertNotEmpty($sectionCodes, 'HRP-503 must have seeded sections');

        $html = $response->getContent();
        foreach ($sectionCodes as $code) {
            $this->assertStringContainsString(
                'id="section-'.$code.'"',
                $html,
                "Section anchor id=\"section-{$code}\" must be present in HTML (visible or locked)",
            );
        }
    }

    #[Test]
    public function hrp503_has_43_or_more_sections(): void
    {
        $count = FormSection::query()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503'))
            ->count();

        $this->assertGreaterThanOrEqual(43, $count, 'HRP-503 must have at least 43 sections seeded');
    }

    // ── Q2.6 fillable ─────────────────────────────────────────────────────────────

    #[Test]
    public function q2_6_is_fillable_via_put_and_returns_200(): void
    {
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => 'q2_6',
                ]),
                ['json_value' => ['drugs_biologics']],
            )
            ->assertOk();
    }

    // ── 3 triggers unlock 3 sections ─────────────────────────────────────────────

    #[Test]
    public function q2_6_with_three_options_unlocks_three_sections(): void
    {
        // Fill Q2.6 with drugs_biologics (→3.0), devices (→4.0), ai (→5.0)
        $this->actingAs($this->user)
            ->putJson(
                route('submissions.answers.update', [
                    'submission_uuid' => $this->submission->id,
                    'question_key' => 'q2_6',
                ]),
                ['json_value' => ['drugs_biologics', 'devices', 'ai']],
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

        $unlocked = array_filter($sectionVisibility, fn (bool $visible) => $visible);
        // Always-visible sections (ungated) count plus the 3 newly unlocked ones
        // We just assert at least 3 gated sections are now visible
        $gatedButVisible = array_filter(
            $sectionVisibility,
            fn (bool $visible, string $code) => $visible && ! in_array($code, ['1.0', '2.0', '13.0', '21.0', '22.0', '23.0', '24.0', '25.0', '26.0', '27.0', '28.0', '29.0', '30.0', '31.0', '32.0', '33.0', '34.0', '35.0', '36.0', '37.0', '42.0'], true),
            ARRAY_FILTER_USE_BOTH,
        );

        $this->assertGreaterThanOrEqual(
            3,
            count($gatedButVisible),
            'At least 3 gated sections should be visible after submitting 3 Q2.6 options',
        );
    }

    // ── Locked sections preserve answers ─────────────────────────────────────────

    #[Test]
    public function locked_sections_render_as_locked_card_not_questions(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-503',
                'tab' => 'review',
            ]))
            ->assertOk();

        // Section 3.0 is locked by default; it should show the locked card text
        $response->assertSee('Locked — complete the trigger question to unlock this section.', false);
    }
}
