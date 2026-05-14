<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Submission;
use App\Models\WorksheetAssistState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Loads HRP-398 guidance items and merges them with a submission's
 * worksheet_assist_state rows.
 *
 * The JSON schema is loaded once per process via the `array` cache store
 * (same pattern as SectionTriggerEvaluator). File mtime is not tracked
 * because the schema is static and only changes with a deploy.
 *
 * SPEC-IRB-FORMSV2-006 §C.1
 *
 * @MX:ANCHOR: [AUTO] loadItemsForSubmission() is the single data-preparation path for the HRP-398 panel.
 *
 * @MX:REASON: fan_in >= 3 — called by SubmissionController::show(), Hrp398PanelRenderTest, and Hrp398AggregateCountsTest.
 */
class Hrp398PanelService
{
    private const WORKSHEET_FORM_ID = 'HRP-398';

    private const SCHEMA_CACHE_KEY = 'hrp398_items';

    /**
     * Returns the 15 guidance items merged with a submission's persist state,
     * grouped by section.
     *
     * Shape: Collection of objects each having:
     *   - section_title: string
     *   - items: array of { id, label, examples, status, notes }
     *
     * @return Collection<int, array{section_title: string, items: list<array{id: string, label: string, examples: list<string>, status: string, notes: string|null}>}>
     */
    public function loadItemsForSubmission(Submission $submission): Collection
    {
        $schema = $this->loadSchema();
        $stateMap = $this->loadStateMap($submission);

        $result = collect();

        foreach ($schema['sections'] as $section) {
            $items = $this->extractDirectItems($section);

            $mergedItems = array_map(function (array $rawItem) use ($stateMap): array {
                $id = $rawItem['id'];
                $state = $stateMap[$id] ?? null;

                return [
                    'id' => $id,
                    'label' => $rawItem['label'],
                    'examples' => $this->extractExamples($rawItem),
                    'status' => $state?->status ?? 'not_started',
                    'notes' => $state?->notes,
                ];
            }, $items);

            // Always push every section so all 9 headings render in the panel.
            // Sections with no direct guidance items (e.g., additional_ethical_considerations
            // and privacy_confidentiality) render the accordion heading but an empty item list.
            $result->push([
                'section_title' => $section['title'],
                'items' => $mergedItems,
            ]);
        }

        return $result;
    }

    /**
     * Returns aggregate counts for the 15 guidance items.
     *
     * Keys: total, addressed, needs_work, not_applicable, not_started
     *
     * @return array{total: int, addressed: int, needs_work: int, not_applicable: int, not_started: int}
     */
    public function aggregateCounts(Submission $submission): array
    {
        $schema = $this->loadSchema();
        $stateMap = $this->loadStateMap($submission);

        $counts = [
            'total' => 0,
            'addressed' => 0,
            'needs_work' => 0,
            'not_applicable' => 0,
            'not_started' => 0,
        ];

        foreach ($schema['sections'] as $section) {
            $items = $this->extractDirectItems($section);

            foreach ($items as $rawItem) {
                $counts['total']++;
                $state = $stateMap[$rawItem['id']] ?? null;
                $status = $state?->status ?? 'not_started';

                match ($status) {
                    'addressed' => $counts['addressed']++,
                    'needs_work' => $counts['needs_work']++,
                    'not_applicable' => $counts['not_applicable']++,
                    default => $counts['not_started']++,
                };
            }
        }

        return $counts;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Load and cache the HRP-398 schema JSON.
     *
     * Uses the `array` store (process-lifetime cache) to avoid re-parsing the
     * JSON on every request, matching the SectionTriggerEvaluator pattern.
     *
     * @return array<string, mixed>
     *
     * @MX:NOTE: [AUTO] Cache key is stable per process; schema file is static between deploys.
     */
    private function loadSchema(): array
    {
        return Cache::store('array')->rememberForever(self::SCHEMA_CACHE_KEY, function (): array {
            // Post-Phase-2 flatten: docs/ is a sibling of app/ at the repo root.
            $path = base_path('docs/HRP-398_form_schema.json');

            if (! file_exists($path)) {
                throw new \RuntimeException("HRP-398 schema not found at {$path}");
            }

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);

            return $decoded;
        });
    }

    /**
     * Load all WorksheetAssistState rows for this submission (HRP-398),
     * keyed by item_id for O(1) lookup during merge.
     *
     * @return array<string, WorksheetAssistState>
     */
    private function loadStateMap(Submission $submission): array
    {
        /** @var array<string, WorksheetAssistState> $map */
        $map = WorksheetAssistState::query()
            ->where('submission_id', $submission->id)
            ->where('worksheet_form_id', self::WORKSHEET_FORM_ID)
            ->get()
            ->keyBy('item_id')
            ->all();

        return $map;
    }

    /**
     * Extract only the top-level guidance items from a section.
     *
     * The 15 "reviewer guidance items" referenced in the SPEC are exactly the
     * items at `section.items[]` — they do NOT include items nested inside
     * `section.subsections[].items[]` or `section.subsections[].groups[].items[]`.
     *
     * Across 9 sections this yields 1+3+4+3+1+1+0+0+2 = 15 items.
     * Sections 7 (additional_ethical_considerations) and 8 (privacy_confidentiality)
     * have 0 direct guidance items; they still appear as section accordion headings.
     *
     * @param  array<string, mixed>  $section
     * @return list<array<string, mixed>>
     */
    private function extractDirectItems(array $section): array
    {
        $items = [];

        foreach ($section['items'] ?? [] as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Normalize the examples/example field into a string array.
     *
     * The schema uses both `examples` (string) and `example` (string).
     * The view uses the array form for iteration.
     *
     * @param  array<string, mixed>  $rawItem
     * @return list<string>
     */
    private function extractExamples(array $rawItem): array
    {
        $raw = $rawItem['examples'] ?? $rawItem['example'] ?? null;

        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }

        return [$raw];
    }
}
