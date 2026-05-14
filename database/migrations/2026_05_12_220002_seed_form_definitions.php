<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed migration — Phase 3 (SPEC-IRB-FORMSV2-003).
 *
 * Cites umbrella REQ-IRB-FORMSV2-026, 027, 028, 031, 032, 033, 034, 035, 036, 029c.
 *
 * Inserts 3 form_definition rows + full form structure for HRP-503 and HRP-503c.
 * HRP-398 form_definition row ONLY (LD-9: not fillable, not retained, not seeded).
 *
 * IDEMPOTENCY (REQ-027): Each form is seeded only if its form_definition row does not
 * already exist with the current version. This gives simple version-gated idempotency:
 * re-running on an existing DB with same versions is a no-op. Full upsert correctness
 * for child rows is traded for simpler logic.
 *
 * CHUNKING (REQ risks-table row 2): bulk inserts via DB::table()->insert() in chunks
 * of 50 rows to avoid PHP memory limits on HRP-503's 247 questions.
 *
 * Q29.3/Q29.4/Q29.5 INLINE ADD (REQ-028): These 3 textarea questions exist in the
 * source DOCX (Section 29.0) but are absent from the supplied JSON. They are added
 * inline per docs/phase0-verification/HRP-503-diff.md Notable Discrepancy #1.
 *
 * down() is REAL per REQ-IRB-FORMSV2-029c — DELETEs in child→parent order scoped
 * to the 3 form_codes.
 *
 * @MX:ANCHOR: [AUTO] Primary seed entry point for all form structure data.
 *
 * @MX:REASON: fan_in >= 3 — called by migrate:fresh, production deploy, and tests/Feature/FormsV2/SeedTest.php
 *
 * @MX:SPEC: REQ-IRB-FORMSV2-026, REQ-IRB-FORMSV2-027, REQ-IRB-FORMSV2-028, REQ-IRB-FORMSV2-032
 */
return new class extends Migration
{
    /** Relative paths under docs/ for schema_json_path column. */
    private const JSON_PATHS = [
        'HRP-503c' => 'docs/HRP-503c_form_schema.json',
        'HRP-503' => 'docs/HRP-503_form_schema.json',
        'HRP-398' => 'docs/HRP-398_form_schema.json',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            // ── Step 1: Load JSON schemas ─────────────────────────────────────
            // Post-Phase-2 flatten: Laravel app is at repo root; docs/ is a
            // sibling of app/. (Pre-flatten this was dirname(base_path()).'/docs'
            // because base_path() resolved to the 503c-assistant/ subdir.)
            $docsPath = base_path('docs');
            $hrp503c = json_decode(
                file_get_contents($docsPath.'/HRP-503c_form_schema.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $hrp503 = json_decode(
                file_get_contents($docsPath.'/HRP-503_form_schema.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $hrp398 = json_decode(
                file_get_contents($docsPath.'/HRP-398_form_schema.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            // ── Step 2: Upsert 3 form_definition rows ────────────────────────
            $now = now()->toDateTimeString();

            DB::table('form_definition')->upsert(
                [
                    [
                        'form_code' => 'HRP-503c',
                        'version' => $hrp503c['version'],
                        'title' => $hrp503c['title'],
                        'institution' => $hrp503c['institution'] ?? 'Sanford Health',
                        'form_kind' => 'application',
                        'description' => $hrp503c['description'] ?? null,
                        'instructions' => json_encode($hrp503c['instructions'] ?? []),
                        'is_fillable' => 1,
                        'is_retained' => 1,
                        'schema_json_path' => self::JSON_PATHS['HRP-503c'],
                        'is_active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'form_code' => 'HRP-503',
                        'version' => $hrp503['version'],
                        'title' => $hrp503['title'],
                        'institution' => $hrp503['institution'] ?? 'Sanford Health',
                        'form_kind' => 'application',
                        'description' => $hrp503['description'] ?? null,
                        'instructions' => json_encode($hrp503['instructions'] ?? []),
                        'is_fillable' => 1,
                        'is_retained' => 1,
                        'schema_json_path' => self::JSON_PATHS['HRP-503'],
                        'is_active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'form_code' => 'HRP-398',
                        'version' => $hrp398['version'],
                        'title' => $hrp398['title'],
                        'institution' => $hrp398['institution'] ?? 'Sanford Health',
                        'form_kind' => 'guidance_worksheet',
                        'description' => $hrp398['description'] ?? null,
                        'instructions' => json_encode($hrp398['instructions'] ?? []),
                        'is_fillable' => 0,
                        'is_retained' => 0,
                        'schema_json_path' => self::JSON_PATHS['HRP-398'],
                        'is_active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ],
                ['form_code'],
                ['version', 'title', 'institution', 'form_kind', 'description', 'instructions',
                    'is_fillable', 'is_retained', 'schema_json_path', 'updated_at']
            );

            // Look up form_definition IDs
            $formIds = DB::table('form_definition')
                ->whereIn('form_code', ['HRP-503c', 'HRP-503', 'HRP-398'])
                ->pluck('id', 'form_code')
                ->all();

            // ── Step 3: HRP-503c full seed ────────────────────────────────────
            $this->seedHrp503c($hrp503c, $formIds['HRP-503c'], $now);

            // ── Step 4: HRP-503 full seed ─────────────────────────────────────
            $this->seedHrp503($hrp503, $formIds['HRP-503'], $now);

            // ── Step 5: HRP-398 — form_definition only (LD-9) ────────────────
            // No form_section, form_question, or form_question_option for HRP-398.
            // worksheet_assist_state uses item_id from JSON items array directly.
        });
    }

    /**
     * Seed HRP-503c: sections, questions (21 parents + 23 children = 44 nodes), options, endnotes.
     *
     * @MX:WARN: [AUTO] Complex nested question tree; criteria stored as form_question_option rows.
     *
     * @MX:REASON: Scenarios and exceptions stored as child form_question rows; criteria within exceptions
     *   stored as form_question_option rows to maintain the 44-node target per REQ-IRB-FORMSV2-031.
     */
    private function seedHrp503c(array $json, int $formDefId, string $now): void
    {
        // Idempotency: skip if sections already exist for this form
        if (DB::table('form_section')->where('form_definition_id', $formDefId)->exists()) {
            return;
        }

        $sectionOrder = 0;
        foreach ($json['sections'] as $sectionData) {
            $sectionOrder++;
            $sectionId = DB::table('form_section')->insertGetId([
                'form_definition_id' => $formDefId,
                'section_code' => $sectionData['section_id'],
                'title' => $sectionData['title'],
                'description' => $sectionData['description'] ?? null,
                'display_order' => $sectionOrder,
                'conditional_logic' => isset($sectionData['conditional_logic'])
                    ? json_encode($sectionData['conditional_logic'])
                    : null,
                'section_end_marker' => $sectionData['section_end_marker'] ?? null,
                'external_ref_title' => $sectionData['external_reference']['title'] ?? null,
                'external_ref_url' => $sectionData['external_reference']['url'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $questionOrder = 0;
            foreach ($sectionData['questions'] as $qData) {
                $questionOrder++;
                $questionId = $this->insertQuestion(
                    sectionId: $sectionId,
                    parentId: null,
                    qData: $qData,
                    order: $questionOrder,
                    now: $now
                );

                // Insert options for this question
                $this->insertOptionsForQuestion($questionId, $qData, $now);

                // Handle subfields (checkbox_multi_with_subfields)
                if (isset($qData['options'])) {
                    $subOrder = 0;
                    foreach ($qData['options'] as $opt) {
                        foreach ($opt['subfields'] ?? [] as $sfData) {
                            $subOrder++;
                            $sfId = $this->insertQuestion(
                                sectionId: $sectionId,
                                parentId: $questionId,
                                qData: $sfData,
                                order: $subOrder,
                                now: $now
                            );
                            // Sub-subfields
                            foreach ($sfData['subfields'] ?? [] as $ssfData) {
                                $subOrder++;
                                $this->insertQuestion(
                                    sectionId: $sectionId,
                                    parentId: $sfId,
                                    qData: $ssfData,
                                    order: $subOrder,
                                    now: $now
                                );
                            }
                        }
                    }
                }

                // Handle scenario_group (q3_6) — scenarios become child questions
                if (($qData['type'] ?? '') === 'scenario_group') {
                    $scOrder = 0;
                    foreach ($qData['scenarios'] ?? [] as $scData) {
                        $scOrder++;
                        $scQuestionData = [
                            'id' => $scData['id'],
                            'number_label' => $scData['code'] ?? null,
                            'label' => $scData['label'],
                            'instruction' => $scData['explain_label'] ?? null,
                            'note' => $scData['note'] ?? null,
                            'type' => $scData['type'],
                            'footnote_refs' => $scData['footnote_refs'] ?? (isset($scData['footnote_ref']) ? [$scData['footnote_ref']] : null),
                            'triggers_sub37' => $scData['triggers_sub37'] ?? null,
                        ];
                        $this->insertQuestion(
                            sectionId: $sectionId,
                            parentId: $questionId,
                            qData: $scQuestionData,
                            order: $scOrder,
                            now: $now
                        );
                    }
                }

                // Handle exception_group (q3_7) — exceptions become child questions
                // Criteria within exceptions become form_question_option rows (not child questions)
                // This achieves the 44-node count target.
                if (($qData['type'] ?? '') === 'exception_group') {
                    $excOrder = 0;
                    foreach ($qData['exceptions'] ?? [] as $excData) {
                        $excOrder++;
                        $excQuestionData = [
                            'id' => $excData['id'],
                            'number_label' => $excData['code'] ?? null,
                            'label' => $excData['label'],
                            'instruction' => $excData['criteria_instruction'] ?? null,
                            'note' => $excData['note'] ?? null,
                            'type' => $excData['type'],
                            'footnote_refs' => $excData['footnote_refs'] ?? (isset($excData['footnote_ref']) ? [$excData['footnote_ref']] : null),
                        ];
                        $excQId = $this->insertQuestion(
                            sectionId: $sectionId,
                            parentId: $questionId,
                            qData: $excQuestionData,
                            order: $excOrder,
                            now: $now
                        );

                        // Criteria → form_question_option rows
                        $crOrder = 0;
                        foreach ($excData['criteria'] ?? [] as $cr) {
                            $crOrder++;
                            $optRows = [];
                            $optRows[] = [
                                'form_question_id' => $excQId,
                                'option_value' => $cr['id'],
                                'option_label' => $cr['label'],
                                'description' => null,
                                'display_order' => $crOrder,
                                'action_type' => 'none',
                                'action_target' => null,
                                'action_text' => null,
                                'footnote_refs' => null,
                                'requires_textarea' => 0,
                                'conditional_textarea_label' => null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                            // Subcriteria as additional options
                            foreach ($cr['subcriteria'] ?? [] as $scr) {
                                $crOrder++;
                                $optRows[] = [
                                    'form_question_id' => $excQId,
                                    'option_value' => $scr['id'],
                                    'option_label' => $scr['label'],
                                    'description' => null,
                                    'display_order' => $crOrder,
                                    'action_type' => 'none',
                                    'action_target' => null,
                                    'action_text' => null,
                                    'footnote_refs' => null,
                                    'requires_textarea' => 0,
                                    'conditional_textarea_label' => null,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }
                            DB::table('form_question_option')->insert($optRows);
                        }
                    }
                }
            }
        }

        // Endnotes
        $endnotesData = $json['endnotes'] ?? [];
        if (! empty($endnotesData)) {
            $endnoteRows = [];
            foreach ($endnotesData as $key => $text) {
                $endnoteRows[] = [
                    'form_definition_id' => $formDefId,
                    'endnote_key' => $key,
                    'endnote_text' => $text,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($endnoteRows, 50) as $chunk) {
                DB::table('form_endnote')->insert($chunk);
            }
        }
    }

    /**
     * Seed HRP-503: sections, questions (176 parents + ~72 children), options, section groups.
     * Inline-adds Q29.3, Q29.4, Q29.5 per REQ-IRB-FORMSV2-028.
     *
     * @MX:WARN: [AUTO] Large dataset — 247+ questions; chunked inserts required.
     *
     * @MX:REASON: HRP-503 has 43 sections and ~248 total question rows; single insert would
     *   exceed PHP's default memory limit on constrained hosting environments.
     *
     * @MX:SPEC: REQ-IRB-FORMSV2-028, REQ-IRB-FORMSV2-032, REQ-IRB-FORMSV2-035
     */
    private function seedHrp503(array $json, int $formDefId, string $now): void
    {
        // Idempotency: skip if sections already exist
        if (DB::table('form_section')->where('form_definition_id', $formDefId)->exists()) {
            return;
        }

        $sectionOrder = 0;
        foreach ($json['sections'] as $sectionData) {
            $sectionOrder++;
            $sectionId = DB::table('form_section')->insertGetId([
                'form_definition_id' => $formDefId,
                'section_code' => $sectionData['section_id'],
                'title' => $sectionData['title'],
                'description' => $sectionData['description'] ?? null,
                'display_order' => $sectionOrder,
                'conditional_logic' => isset($sectionData['conditional_logic'])
                    ? json_encode($sectionData['conditional_logic'])
                    : null,
                'section_end_marker' => $sectionData['section_end_marker'] ?? null,
                'external_ref_title' => $sectionData['external_reference']['title'] ?? null,
                'external_ref_url' => $sectionData['external_reference']['url'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $questions = $sectionData['questions'];

            // REQ-IRB-FORMSV2-028: Inline-add Q29.3, Q29.4, Q29.5 to Section 29.0.
            // Source: docs/phase0-verification/HRP-503-diff.md Notable Discrepancy #1.
            if ($sectionData['section_id'] === '29.0') {
                $questions = array_merge($questions, $this->q29Additions());
            }

            $questionOrder = 0;
            $questionBatch = [];
            $optionBatch = [];
            $childBatch = [];

            foreach ($questions as $qData) {
                $questionOrder++;
                $qId = $this->insertQuestion(
                    sectionId: $sectionId,
                    parentId: null,
                    qData: $qData,
                    order: $questionOrder,
                    now: $now
                );

                // Collect options
                foreach ($this->buildOptionRows($qId, $qData, $now) as $optRow) {
                    $optionBatch[] = $optRow;
                }

                // Collect children (subfields in options)
                $subOrder = 0;
                foreach ($qData['options'] ?? [] as $opt) {
                    foreach ($opt['subfields'] ?? [] as $sfData) {
                        $subOrder++;
                        $sfId = $this->insertQuestion(
                            sectionId: $sectionId,
                            parentId: $qId,
                            qData: $sfData,
                            order: $subOrder,
                            now: $now
                        );
                        // Collect child options
                        foreach ($this->buildOptionRows($sfId, $sfData, $now) as $optRow) {
                            $optionBatch[] = $optRow;
                        }
                        // Sub-subfields
                        foreach ($sfData['subfields'] ?? [] as $ssfData) {
                            $subOrder++;
                            $this->insertQuestion(
                                sectionId: $sectionId,
                                parentId: $sfId,
                                qData: $ssfData,
                                order: $subOrder,
                                now: $now
                            );
                        }
                    }
                }

                // Flush option batch per section to keep memory bounded
                if (count($optionBatch) >= 50) {
                    DB::table('form_question_option')->insert($optionBatch);
                    $optionBatch = [];
                }
            }

            // Flush remaining options for this section
            if (! empty($optionBatch)) {
                DB::table('form_question_option')->insert($optionBatch);
                $optionBatch = [];
            }
        }

        // Section groups (REQ-IRB-FORMSV2-035) — from JSON section_groups
        $sectionGroups = $json['section_groups'] ?? [];
        if (! empty($sectionGroups)) {
            $groupRows = [];
            foreach ($sectionGroups as $idx => $group) {
                $groupRows[] = [
                    'form_code' => 'HRP-503',
                    'display_order' => $idx + 1,
                    'label' => $group['label'],
                    'section_ids_json' => json_encode($group['section_ids']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('form_section_group')->insert($groupRows);
        }
    }

    /**
     * Q29.3, Q29.4, Q29.5 definitions per HRP-503-diff.md Notable Discrepancy #1.
     *
     * Source DOCX Section 29 has 5 questions; supplied JSON has only 29.1 and 29.2.
     * These 3 are the missing textarea questions confirmed by blank-reparse at items #606-#614.
     *
     * @return array<int, array<string, mixed>>
     */
    private function q29Additions(): array
    {
        return [
            [
                'id' => 'q29_3',
                'number' => '29.3',
                'label' => 'Describe the enrollment coordination plan across sites.',
                'type' => 'textarea',
                'required' => true,
            ],
            [
                'id' => 'q29_4',
                'number' => '29.4',
                'label' => 'Describe the adverse-event reporting plan across sites.',
                'type' => 'textarea',
                'required' => true,
            ],
            [
                'id' => 'q29_5',
                'number' => '29.5',
                'label' => 'Describe the local site investigator oversight plan.',
                'type' => 'textarea',
                'required' => true,
            ],
        ];
    }

    /**
     * Insert a form_question row and return its ID.
     *
     * @param  array<string, mixed>  $qData
     */
    private function insertQuestion(
        int $sectionId,
        ?int $parentId,
        array $qData,
        int $order,
        string $now
    ): int {
        $qType = $qData['type'] ?? 'textarea';

        // Resolve footnote_refs: may be string (single ref) or array
        $footnoteRefs = null;
        if (isset($qData['footnote_refs'])) {
            $footnoteRefs = json_encode(
                is_array($qData['footnote_refs']) ? $qData['footnote_refs'] : [$qData['footnote_refs']]
            );
        } elseif (isset($qData['footnote_ref'])) {
            $footnoteRefs = json_encode([$qData['footnote_ref']]);
        }

        return DB::table('form_question')->insertGetId([
            'form_section_id' => $sectionId,
            'parent_question_id' => $parentId,
            'question_key' => $qData['id'],
            'number_label' => $qData['number'] ?? ($qData['number_label'] ?? null),
            'label' => $qData['label'] ?? $qData['id'],
            'instruction' => $qData['instruction'] ?? null,
            'note' => $qData['note'] ?? null,
            'question_type' => $qType,
            'is_required' => ($qData['required'] ?? false) ? 1 : 0,
            'display_order' => $order,
            'conditional_logic' => isset($qData['conditional_logic'])
                ? json_encode($qData['conditional_logic'])
                : null,
            'skip_in_multi_site' => ($qData['skip_in_multi_site'] ?? false) ? 1 : 0,
            'triggers_sub37' => isset($qData['triggers_sub37'])
                ? json_encode($qData['triggers_sub37'])
                : null,
            'footnote_refs' => $footnoteRefs,
            'external_ref_title' => $qData['external_reference']['title'] ?? null,
            'external_ref_url' => $qData['external_reference']['url'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Insert form_question_option rows for a given question.
     *
     * @param  array<string, mixed>  $qData
     */
    private function insertOptionsForQuestion(int $questionId, array $qData, string $now): void
    {
        $rows = $this->buildOptionRows($questionId, $qData, $now);
        if (! empty($rows)) {
            foreach (array_chunk($rows, 50) as $chunk) {
                DB::table('form_question_option')->insert($chunk);
            }
        }
    }

    /**
     * Build form_question_option rows for a question (without inserting).
     *
     * @param  array<string, mixed>  $qData
     * @return array<int, array<string, mixed>>
     */
    private function buildOptionRows(int $questionId, array $qData, string $now): array
    {
        $rows = [];
        $order = 0;

        foreach ($qData['options'] ?? [] as $opt) {
            $order++;

            // Map action from JSON to our ENUM
            $actionType = 'none';
            $actionTarget = null;
            $actionText = null;

            if (isset($opt['action'])) {
                $actionType = $this->mapActionType($opt['action']);
                $actionText = $opt['action_text'] ?? null;
            } elseif (isset($opt['triggers_section'])) {
                $actionType = 'triggers_section';
                $actionTarget = $opt['triggers_section'];
            } elseif (isset($opt['skip_to'])) {
                $actionType = 'skip_to';
                $actionTarget = $opt['skip_to'];
            }

            $optValue = $opt['value'] ?? ($opt['label'] ?? 'option_'.$order);

            $rows[] = [
                'form_question_id' => $questionId,
                'option_value' => $optValue,
                'option_label' => $opt['label'],
                'description' => $opt['description'] ?? null,
                'display_order' => $order,
                'action_type' => $actionType,
                'action_target' => $actionTarget,
                'action_text' => $actionText,
                'footnote_refs' => isset($opt['footnote_refs'])
                    ? json_encode(is_array($opt['footnote_refs']) ? $opt['footnote_refs'] : [$opt['footnote_refs']])
                    : (isset($opt['footnote_ref']) ? json_encode([$opt['footnote_ref']]) : null),
                'requires_textarea' => ($opt['requires_textarea'] ?? false) ? 1 : 0,
                'conditional_textarea_label' => $opt['textarea_label'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * Map JSON action strings to the form_question_option.action_type ENUM.
     */
    private function mapActionType(string $action): string
    {
        return match ($action) {
            'stop_and_submit' => 'stop_and_submit',
            'stop_engaged' => 'stop_engaged',
            'stop_not_engaged' => 'stop_not_engaged',
            'stop_or_skip_to_3.0' => 'stop_or_skip_to_3.0',
            'skip_to' => 'skip_to',
            'continue' => 'continue',
            'triggers_section' => 'triggers_section',
            'reveal_subfields' => 'reveal_subfields',
            default => 'none',
        };
    }

    /**
     * Reverse the migration — DELETE in child→parent order, scoped to the 3 form_codes.
     * Per REQ-IRB-FORMSV2-029c.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            // Resolve form_definition IDs for scoping
            $formIds = DB::table('form_definition')
                ->whereIn('form_code', ['HRP-503', 'HRP-503c', 'HRP-398'])
                ->pluck('id')
                ->all();

            if (empty($formIds)) {
                return;
            }

            // Child options → child questions → endnotes → section groups → sections → definitions
            $sectionIds = DB::table('form_section')
                ->whereIn('form_definition_id', $formIds)
                ->pluck('id')
                ->all();

            if (! empty($sectionIds)) {
                $questionIds = DB::table('form_question')
                    ->whereIn('form_section_id', $sectionIds)
                    ->pluck('id')
                    ->all();

                if (! empty($questionIds)) {
                    DB::table('form_question_option')
                        ->whereIn('form_question_id', $questionIds)
                        ->delete();

                    DB::table('form_question')
                        ->whereIn('form_section_id', $sectionIds)
                        ->delete();
                }

                DB::table('form_section')
                    ->whereIn('form_definition_id', $formIds)
                    ->delete();
            }

            DB::table('form_endnote')
                ->whereIn('form_definition_id', $formIds)
                ->delete();

            DB::table('form_section_group')
                ->whereIn('form_code', ['HRP-503', 'HRP-503c', 'HRP-398'])
                ->delete();

            DB::table('form_definition')
                ->whereIn('form_code', ['HRP-503', 'HRP-503c', 'HRP-398'])
                ->delete();
        });
    }
};
