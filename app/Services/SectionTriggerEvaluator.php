<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FormQuestionOption;
use Illuminate\Support\Facades\Cache;

/**
 * Pure-function service that evaluates cross-section trigger visibility for HRP-503.
 *
 * Trigger questions (Q2.6 / Q13.2 / Q37.1a / Q37.1b / Q42-will / Q42-will-not)
 * gate the visibility of entire downstream sections. Mapping is loaded once from
 * the seeded form_question_option rows (action_type = 'triggers_section') rather
 * than from any hardcoded constant.
 *
 * LD-P5-1: Visibility only — hidden sections are never deleted from the DB.
 * REQ-P5-003, REQ-P5-007
 *
 * @MX:ANCHOR: [AUTO] isSectionVisible() is the single visibility arbiter for HRP-503 sections.
 *
 * @MX:REASON: fan_in >= 3 — SubmissionController::show(), SubmissionDocxExportService, and test suite.
 */
class SectionTriggerEvaluator
{
    /**
     * Question keys whose selected options trigger downstream section visibility.
     * These match the question_key values seeded by 2026_05_12_220002_seed_form_definitions.
     */
    private const TRIGGER_QUESTION_KEYS = [
        'q2_6',
        'q13_2',
        'q37_1_will_obtain',
        'q37_1_will_not_obtain',
        'q42_will_obtain',
        'q42_will_not_obtain',
    ];

    /**
     * Sections that are ALWAYS visible (not gated by any trigger question).
     * Based on the HRP-503 schema section_groups structure.
     */
    private const UNGATED_SECTIONS = [
        '1.0',
        '2.0',
        '13.0',
        '21.0',
        '22.0',
        '23.0',
        '24.0',
        '25.0',
        '26.0',
        '27.0',
        '28.0',
        '29.0',
        '30.0',
        '31.0',
        '32.0',
        '33.0',
        '34.0',
        '35.0',
        '36.0',
        '37.0',
        '42.0',
    ];

    /**
     * Evaluate whether a section should be visible based on the current answer set.
     *
     * Returns true for ungated sections unconditionally.
     * For gated sections, returns true only if at least one trigger question
     * has a selected option whose action_target matches the section_id.
     *
     * @param  string  $sectionId  e.g. "3.0", "14.0"
     * @param  array<string, mixed>  $allAnswers  question_key → raw submitted value (scalar or array)
     */
    public static function isSectionVisible(string $sectionId, array $allAnswers): bool
    {
        if (in_array($sectionId, self::UNGATED_SECTIONS, true)) {
            return true;
        }

        // Build the trigger map (cached per request)
        $triggerMap = self::buildTriggerMap();

        // For each trigger question, check if any selected option fires for this section
        foreach (self::TRIGGER_QUESTION_KEYS as $qKey) {
            if (! isset($triggerMap[$qKey])) {
                continue;
            }

            $selectedValues = self::normalizeSelectedValues($allAnswers[$qKey] ?? null);

            foreach ($selectedValues as $value) {
                if (isset($triggerMap[$qKey][$value]) && $triggerMap[$qKey][$value] === $sectionId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Build a full visibility map for all sections of a given form definition.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\FormSection>  $sections
     * @param  array<string, mixed>  $allAnswers  question_key → raw value
     * @return array<string, bool> section_code → visible
     */
    public static function buildSectionVisibilityMap(
        \Illuminate\Support\Collection $sections,
        array $allAnswers,
    ): array {
        $map = [];
        foreach ($sections as $section) {
            $map[$section->section_code] = self::isSectionVisible($section->section_code, $allAnswers);
        }

        return $map;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Load the trigger map from the seeded form_question_option rows.
     *
     * Structure: question_key → [option_value => section_id]
     *
     * The result is cached in the application container's per-request cache
     * to avoid repeated DB hits on the same page load.
     *
     * @return array<string, array<string, string>>
     *
     * @MX:NOTE: [AUTO] Cache key is stable per process; triggers never change at runtime (read-only seed data).
     */
    private static function buildTriggerMap(): array
    {
        return Cache::store('array')->rememberForever('section_trigger_map_hrp503', function (): array {
            $map = [];

            $rows = FormQuestionOption::query()
                ->where('action_type', 'triggers_section')
                ->whereHas('question', function ($q): void {
                    $q->whereIn('question_key', self::TRIGGER_QUESTION_KEYS)
                        ->whereHas('section', fn ($s) => $s->whereHas(
                            'formDefinition',
                            fn ($fd) => $fd->where('form_code', 'HRP-503'),
                        ));
                })
                ->with('question:id,question_key')
                ->get(['form_question_id', 'option_value', 'action_target']);

            foreach ($rows as $row) {
                $qKey = $row->question?->question_key;
                if ($qKey === null) {
                    continue;
                }
                $map[$qKey][$row->option_value] = $row->action_target;
            }

            return $map;
        });
    }

    /**
     * Normalize an answer value into an array of selected strings.
     * Handles: null, string (radio), array (checkbox_multi).
     *
     * @return string[]
     */
    private static function normalizeSelectedValues(mixed $rawValue): array
    {
        if ($rawValue === null || $rawValue === '' || $rawValue === []) {
            return [];
        }

        if (is_string($rawValue)) {
            return [$rawValue];
        }

        if (is_array($rawValue)) {
            return array_values(array_filter(
                array_map('strval', $rawValue),
                fn ($v) => $v !== '',
            ));
        }

        return [];
    }
}
