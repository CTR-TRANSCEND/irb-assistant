<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SectionTriggerEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-005 Phase 5 — Unit tests for SectionTriggerEvaluator.
 *
 * 7 tests: one per trigger question (Q2.6 / Q13.2 / Q37.1a / Q37.1b /
 * Q42-will / Q42-will-not) plus one confirming ungated sections are always visible.
 *
 * These tests hit the DB to verify the seeded trigger map is correct (the
 * evaluator loads from form_question_option rows, not hardcoded constants).
 */
class SectionTriggerEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Flush the in-process `array` cache between methods so the
     * SectionTriggerEvaluator's `rememberForever('section_trigger_map_hrp503', …)`
     * is rebuilt from the freshly-seeded DB rather than relying on implicit
     * container teardown. Locks the test contract per QA review F-QA-1.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Cache::store('array')->flush();
    }

    // ── Ungated sections ───────────────────────────────────────────────────────

    #[Test]
    public function ungated_sections_are_always_visible_regardless_of_answers(): void
    {
        $ungated = ['1.0', '2.0', '13.0', '21.0', '22.0', '29.0', '30.0', '37.0', '42.0'];

        foreach ($ungated as $sectionId) {
            $this->assertTrue(
                SectionTriggerEvaluator::isSectionVisible($sectionId, []),
                "Section {$sectionId} should be visible with no answers",
            );
            // Also with empty trigger answer
            $this->assertTrue(
                SectionTriggerEvaluator::isSectionVisible($sectionId, ['q2_6' => []]),
                "Section {$sectionId} should be visible even with q2_6=[]",
            );
        }
    }

    // ── Q2.6 — checkbox_multi_with_section_triggers ────────────────────────────

    #[Test]
    public function q2_6_drugs_biologics_reveals_section_3_0(): void
    {
        $answers = ['q2_6' => ['drugs_biologics']];

        $this->assertTrue(SectionTriggerEvaluator::isSectionVisible('3.0', $answers));
        $this->assertFalse(SectionTriggerEvaluator::isSectionVisible('4.0', $answers));
    }

    #[Test]
    public function q2_6_no_selection_locks_all_gated_sections(): void
    {
        $answers = ['q2_6' => []];

        foreach (['3.0', '4.0', '5.0', '6.0', '7.0', '8.0', '9.0', '10.0', '11.0', '12.0'] as $section) {
            $this->assertFalse(
                SectionTriggerEvaluator::isSectionVisible($section, $answers),
                "Section {$section} should be locked when q2_6=[]",
            );
        }
    }

    // ── Q13.2 — special population trigger ────────────────────────────────────

    #[Test]
    public function q13_2_pregnant_reveals_section_14_0(): void
    {
        $answers = ['q13_2' => ['pregnant']];

        $this->assertTrue(SectionTriggerEvaluator::isSectionVisible('14.0', $answers));
        // Children (section 16.0) should remain locked
        $this->assertFalse(SectionTriggerEvaluator::isSectionVisible('16.0', $answers));
    }

    #[Test]
    public function q13_2_children_reveals_section_16_0(): void
    {
        $answers = ['q13_2' => ['children']];

        $this->assertTrue(SectionTriggerEvaluator::isSectionVisible('16.0', $answers));
        $this->assertFalse(SectionTriggerEvaluator::isSectionVisible('14.0', $answers));
    }

    // ── Q37.1a (will_obtain) ───────────────────────────────────────────────────

    #[Test]
    public function q37_1_will_obtain_follows_hrp091_reveals_section_38_0(): void
    {
        $answers = ['q37_1_will_obtain' => ['follows_hrp_091']];

        $this->assertTrue(SectionTriggerEvaluator::isSectionVisible('38.0', $answers));
        $this->assertFalse(SectionTriggerEvaluator::isSectionVisible('39.0', $answers));
    }

    // ── Q37.1b (will_not_obtain) ───────────────────────────────────────────────

    #[Test]
    public function q37_1_will_not_obtain_waiver_reveals_section_40_0(): void
    {
        $answers = ['q37_1_will_not_obtain' => ['waiver_requested']];

        $this->assertTrue(SectionTriggerEvaluator::isSectionVisible('40.0', $answers));
        $this->assertFalse(SectionTriggerEvaluator::isSectionVisible('41.0', $answers));
    }

    // ── Q42 (will_obtain) ─────────────────────────────────────────────────────

    #[Test]
    public function q42_will_obtain_no_signature_reveals_section_43_0(): void
    {
        $answers = ['q42_will_obtain' => ['no_signature']];

        $this->assertTrue(SectionTriggerEvaluator::isSectionVisible('43.0', $answers));
    }

    // ── Q42 (will_not_obtain) ─────────────────────────────────────────────────

    #[Test]
    public function q42_will_not_obtain_waiver_reveals_section_43_0(): void
    {
        $answers = ['q42_will_not_obtain' => ['waiver_requested']];

        $this->assertTrue(SectionTriggerEvaluator::isSectionVisible('43.0', $answers));
    }

    // ── Gated section locked by default (no answers) ──────────────────────────

    #[Test]
    public function gated_sections_locked_by_default_with_no_answers(): void
    {
        $gated = ['3.0', '4.0', '5.0', '14.0', '15.0', '16.0', '38.0', '39.0', '40.0', '41.0', '43.0'];

        foreach ($gated as $sectionId) {
            $this->assertFalse(
                SectionTriggerEvaluator::isSectionVisible($sectionId, []),
                "Section {$sectionId} should be locked with no answers",
            );
        }
    }
}
