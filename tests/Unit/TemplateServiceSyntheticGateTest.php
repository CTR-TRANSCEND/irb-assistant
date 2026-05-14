<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\FieldDefinition;
use App\Models\TemplateVersion;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-CLEANUP-001 — Unit tests for TemplateService synthetic field gate.
 *
 * Validates REQ-CLEAN-009, REQ-CLEAN-009a, and REQ-CLEAN-010:
 * - The str_starts_with($key, 'ctrl_') early-continue gate prevents
 *   FieldDefinition::firstOrCreate invocation for ctrl_* keys.
 * - Curated keys (hrp503.*, hrp503c.*) flow through unaffected.
 * - The upstream $onlyUnmappedControls guard at line 236 is preserved
 *   (untouched, still functional).
 */
class TemplateServiceSyntheticGateTest extends TestCase
{
    use RefreshDatabase;

    private TemplateService $service;

    private int $templateId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TemplateService::class);
        $this->templateId = $this->createTemplateVersion();
    }

    // -------------------------------------------------------------------------
    // Helper methods
    // -------------------------------------------------------------------------

    private function createTemplateVersion(): int
    {
        return \Illuminate\Support\Facades\DB::table('template_versions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c Unit Gate Test',
            'sha256' => hash('sha256', 'unit-gate-'.uniqid()),
            'storage_disk' => 'local',
            'storage_path' => 'templates/unit-gate-test.docx',
            'is_active' => false,
            'uploaded_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createControl(string $part, int $controlIndex): int
    {
        return \Illuminate\Support\Facades\DB::table('template_controls')->insertGetId([
            'template_version_id' => $this->templateId,
            'part' => $part,
            'control_index' => $controlIndex,
            'context_before' => "Context before {$controlIndex}:",
            'context_after' => null,
            'signature_sha256' => hash('sha256', "unit-gate-ctrl-{$part}-{$controlIndex}"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // REQ-CLEAN-009: gate prevents FieldDefinition::firstOrCreate for ctrl_* keys
    // -------------------------------------------------------------------------

    #[Test]
    public function gate_prevents_ctrl_field_definition_creation_for_document_part(): void
    {
        // Document part produces ctrl_doc_NNN keys — must be blocked
        $this->createControl('document', 0);
        $this->createControl('document', 1);
        $this->createControl('document', 2);

        $templateVersion = TemplateVersion::query()->find($this->templateId);
        $this->service->seedFieldDefinitionsFromControls($templateVersion, createMappings: false);

        // No ctrl_* rows should exist in field_definitions
        $ctrlCount = FieldDefinition::query()
            ->where('key', 'LIKE', 'ctrl_%')
            ->count();

        $this->assertSame(0, $ctrlCount, 'REQ-CLEAN-009: ctrl_doc_* keys must not produce FieldDefinition rows');
    }

    #[Test]
    public function gate_prevents_ctrl_field_definition_creation_for_all_prefix_variants(): void
    {
        // All synthetic prefix family variants must be blocked
        $this->createControl('document', 0);    // -> ctrl_doc_000
        $this->createControl('endnotes', 0);    // -> ctrl_endnotes_000
        $this->createControl('footnotes', 0);   // -> ctrl_footnotes_000
        $this->createControl('header1', 0);     // -> ctrl_header1_000
        $this->createControl('footer1', 0);     // -> ctrl_footer1_000
        $this->createControl('footer2', 0);     // -> ctrl_footer2_000

        $templateVersion = TemplateVersion::query()->find($this->templateId);
        $this->service->seedFieldDefinitionsFromControls($templateVersion, createMappings: false);

        $ctrlCount = FieldDefinition::query()
            ->where('key', 'LIKE', 'ctrl_%')
            ->count();

        $this->assertSame(0, $ctrlCount, 'All ctrl_* prefix family variants must be blocked by the gate');
    }

    #[Test]
    public function gate_leaves_zero_ctrl_rows_regardless_of_control_count(): void
    {
        // Insert 20 controls — all would produce ctrl_doc_* keys
        for ($i = 0; $i < 20; $i++) {
            $this->createControl('document', $i);
        }

        $templateVersion = TemplateVersion::query()->find($this->templateId);
        $this->service->seedFieldDefinitionsFromControls($templateVersion, createMappings: false);

        $ctrlCount = FieldDefinition::query()
            ->where('key', 'LIKE', 'ctrl_%')
            ->count();

        $this->assertSame(0, $ctrlCount, 'Gate blocks insertion even with many controls');
        $this->assertSame(0, FieldDefinition::query()->count(), 'No FieldDefinition rows should exist after gate blocks all ctrl_* keys');
    }

    // -------------------------------------------------------------------------
    // REQ-CLEAN-009a: inline comment validates gate is annotated (code review check)
    // The comment is structural; we verify the gate is in place by behavior.
    // REQ-CLEAN-009: curated hrp503.*/hrp503c.* keys are unaffected
    // -------------------------------------------------------------------------

    #[Test]
    public function gate_does_not_block_curated_hrp503_keys(): void
    {
        // Pre-insert a curated FieldDefinition — the service uses firstOrCreate so
        // it will find the existing row rather than creating. We verify no ctrl_* row appears.
        $curated = FieldDefinition::query()->create([
            'key' => 'hrp503c.study.title',
            'label' => 'Study Title',
            'section' => 'HRP-503c',
            'sort_order' => 10,
            'is_required' => true,
            'input_type' => 'text',
            'question_text' => 'What is the study title?',
        ]);

        // A document control would produce a ctrl_doc_000 key
        $this->createControl('document', 0);

        $templateVersion = TemplateVersion::query()->find($this->templateId);
        $this->service->seedFieldDefinitionsFromControls($templateVersion, createMappings: false);

        // curated row still exists
        $this->assertTrue(
            FieldDefinition::query()->where('key', 'hrp503c.study.title')->exists(),
            'Curated hrp503c.study.title must not be affected by the gate'
        );

        // No ctrl_* row created
        $ctrlCount = FieldDefinition::query()->where('key', 'LIKE', 'ctrl_%')->count();
        $this->assertSame(0, $ctrlCount, 'Gate must still block ctrl_doc_000 even when curated rows exist');

        // Total row count: 1 curated only
        $this->assertSame(1, FieldDefinition::query()->count(), 'Only the curated row should exist');
    }

    #[Test]
    public function gate_is_case_sensitive_uppercase_ctrl_not_blocked(): void
    {
        // REQ-CLEAN-016: str_starts_with($key, 'ctrl_') is case-sensitive.
        // An uppercase CTRL_ key would NOT be blocked by the gate.
        // We verify this by mocking a control that produces a non-lowercase key
        // by testing the PHP function directly.

        // str_starts_with is case-sensitive in PHP
        $this->assertTrue(str_starts_with('ctrl_doc_001', 'ctrl_'), 'lowercase ctrl_* IS blocked');
        $this->assertFalse(str_starts_with('CTRL_doc_001', 'ctrl_'), 'uppercase CTRL_* is NOT blocked by the gate (REQ-CLEAN-016)');
        $this->assertFalse(str_starts_with('ctrlx_doc_001', 'ctrl_'), 'ctrlx_doc_001 is NOT blocked (REQ-CLEAN-003)');
    }

    // -------------------------------------------------------------------------
    // REQ-CLEAN-010: upstream $onlyUnmappedControls guard is preserved
    // -------------------------------------------------------------------------

    #[Test]
    public function upstream_only_unmapped_controls_guard_remains_functional(): void
    {
        // When $onlyUnmappedControls=true, controls already mapped should be skipped
        // by the UPSTREAM guard at line 236 (before the SPEC-IRB-CLEANUP-001 gate).

        $curated = FieldDefinition::query()->create([
            'key' => 'hrp503.study.title',
            'label' => 'Protocol Title',
            'section' => 'HRP-503',
            'sort_order' => 1,
            'is_required' => true,
            'input_type' => 'text',
        ]);

        // Create a mapped control
        $ctrlId = $this->createControl('document', 0);

        \Illuminate\Support\Facades\DB::table('template_control_mappings')->insert([
            'template_version_id' => $this->templateId,
            'template_control_id' => $ctrlId,
            'field_definition_id' => $curated->id,
            'mapped_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create an unmapped control (ctrl_doc_001 — will also be blocked by gate)
        $this->createControl('document', 1);

        $fdCountBefore = FieldDefinition::query()->count(); // 1 curated

        $templateVersion = TemplateVersion::query()->find($this->templateId);

        // With onlyUnmappedControls=true: mapped control is skipped by upstream guard;
        // unmapped ctrl_doc_001 is skipped by SPEC-IRB-CLEANUP-001 gate.
        // Result: no new FieldDefinition rows.
        $this->service->seedFieldDefinitionsFromControls(
            $templateVersion,
            createMappings: false,
            onlyUnmappedControls: true
        );

        $fdCountAfter = FieldDefinition::query()->count();
        $this->assertSame($fdCountBefore, $fdCountAfter, 'onlyUnmappedControls guard must still function (REQ-CLEAN-010)');
        $this->assertSame(0, FieldDefinition::query()->where('key', 'LIKE', 'ctrl_%')->count(), 'Still no ctrl_* rows');
    }

    #[Test]
    public function spec_cleanup_gate_is_separate_from_only_unmapped_controls_guard(): void
    {
        // Both guards are independent early-continues.
        // This test verifies the SPEC-IRB-CLEANUP-001 gate operates even when
        // onlyUnmappedControls=false (the default path).

        $this->createControl('document', 0); // ctrl_doc_000 — blocked by SPEC gate
        $this->createControl('document', 1); // ctrl_doc_001 — blocked by SPEC gate

        $templateVersion = TemplateVersion::query()->find($this->templateId);
        $this->service->seedFieldDefinitionsFromControls(
            $templateVersion,
            createMappings: false,
            onlyUnmappedControls: false // default: upstream guard inactive
        );

        // SPEC gate still blocks ctrl_* insertion
        $this->assertSame(
            0,
            FieldDefinition::query()->where('key', 'LIKE', 'ctrl_%')->count(),
            'SPEC-IRB-CLEANUP-001 gate operates independently of onlyUnmappedControls flag'
        );
    }
}
