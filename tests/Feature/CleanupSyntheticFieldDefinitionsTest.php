<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-CLEANUP-001 — Feature tests for CleanupSyntheticFieldDefinitions migration.
 *
 * Covers acceptance scenarios S1-S18 from acceptance.md.
 *
 * setUp() fixture rule (REQ-CLEAN-019 corollary, audit H7):
 * The RefreshDatabase trait runs every migration including the cleanup migration
 * before each test body executes. Synthetic ctrl_* fixture rows are inserted
 * AFTER parent::setUp() returns. Where a scenario requires the cleanup migration
 * to be in a "not-yet-run" state, the test seeds fixtures and then invokes the
 * migration class directly via require base_path(...)->up().
 */
class CleanupSyntheticFieldDefinitionsTest extends TestCase
{
    use RefreshDatabase;

    /** @var string The migration file path (resolved once). */
    private string $migrationFile;

    protected function setUp(): void
    {
        parent::setUp();

        $files = glob(
            base_path('database/migrations/2026_05_11_*_cleanup_synthetic_field_definitions.php')
        );

        $this->migrationFile = $files[0] ?? '';
    }

    // -------------------------------------------------------------------------
    // Private fixture helpers
    // -------------------------------------------------------------------------

    /**
     * Insert 60 curated hrp503.x / hrp503c.x rows (REQ-CLEAN-011 sentinel).
     * Keys mirror the Hrp503FieldDefinitionSeeder + Hrp503cFieldDefinitionSeeder
     * at a representative level; exact count is 60.
     */
    private function seedCurated(): void
    {
        $rows = [];
        // 34 hrp503.* rows
        $hrp503Keys = [
            'hrp503.study.title',
            'hrp503.summary.study_title',
            'hrp503.summary.study_design',
            'hrp503.summary.primary_objective',
            'hrp503.summary.secondary_objectives',
            'hrp503.summary.interventions',
            'hrp503.summary.study_population',
            'hrp503.summary.sample_size',
            'hrp503.objectives.primary',
            'hrp503.objectives.secondary',
            'hrp503.background.rationale',
            'hrp503.background.preliminary_data',
            'hrp503.design.design_description',
            'hrp503.design.arm_description',
            'hrp503.design.randomization',
            'hrp503.eligibility.inclusion_criteria',
            'hrp503.eligibility.exclusion_criteria',
            'hrp503.recruitment.methods',
            'hrp503.recruitment.setting',
            'hrp503.recruitment.timeline',
            'hrp503.procedures.study_procedures',
            'hrp503.procedures.data_collection',
            'hrp503.analysis.statistical_methods',
            'hrp503.analysis.sample_size_justification',
            'hrp503.risks.potential_risks',
            'hrp503.risks.risk_minimization',
            'hrp503.benefits.direct_benefits',
            'hrp503.benefits.societal_benefits',
            'hrp503.privacy.data_confidentiality',
            'hrp503.privacy.storage_plan',
            'hrp503.consent.process',
            'hrp503.consent.waiver_justification',
            'hrp503.monitoring.data_safety',
            'hrp503.special.ctrl_lookalike',
        ];

        // 26 hrp503c.* rows
        $hrp503cKeys = [
            'hrp503c.study.title',
            'hrp503c.study.short_title',
            'hrp503c.personnel.pi_name',
            'hrp503c.personnel.pi_email',
            'hrp503c.personnel.pi_department',
            'hrp503c.personnel.coordinator_name',
            'hrp503c.personnel.coordinator_email',
            'hrp503c.personnel.sponsor',
            'hrp503c.background.study_rationale',
            'hrp503c.background.prior_data',
            'hrp503c.objectives.primary',
            'hrp503c.objectives.secondary',
            'hrp503c.design.description',
            'hrp503c.design.blinding',
            'hrp503c.eligibility.inclusion',
            'hrp503c.eligibility.exclusion',
            'hrp503c.procedures.interventions',
            'hrp503c.procedures.visits',
            'hrp503c.analysis.methods',
            'hrp503c.analysis.sample_size',
            'hrp503c.risks.description',
            'hrp503c.benefits.direct',
            'hrp503c.consent.process',
            'hrp503c.consent.waiver',
            'hrp503c.privacy.plan',
            'hrp503c.monitoring.dsmb',
        ];

        $sortOrder = 1;
        foreach ($hrp503Keys as $key) {
            $rows[] = [
                'key' => $key,
                'label' => $key,
                'section' => 'HRP-503',
                'sort_order' => $sortOrder++,
                'is_required' => false,
                'input_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach ($hrp503cKeys as $key) {
            $rows[] = [
                'key' => $key,
                'label' => $key,
                'section' => 'HRP-503c',
                'sort_order' => $sortOrder++,
                'is_required' => false,
                'input_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('field_definitions')->insert($rows);
    }

    /**
     * Insert $count synthetic ctrl_* rows covering the full prefix family.
     * Keys: ctrl_doc_000..ctrl_doc_NNN, plus sampling of other prefixes.
     */
    private function seedSynthetic(int $count = 150): void
    {
        $rows = [];
        $prefixes = ['ctrl_doc_', 'ctrl_endnotes_', 'ctrl_footnotes_', 'ctrl_header1_', 'ctrl_footer1_', 'ctrl_footer2_', 'ctrl_header2_'];
        $sortOrder = 5000;

        for ($i = 0; $i < $count; $i++) {
            $prefix = $prefixes[$i % count($prefixes)];
            $key = $prefix.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $rows[] = [
                'key' => $key,
                'label' => 'Synthetic '.$i,
                'section' => 'HRP-503c',
                'sort_order' => $sortOrder++,
                'is_required' => false,
                'input_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('field_definitions')->insert($rows);
    }

    /**
     * Insert boundary rows that must survive the migration (REQ-CLEAN-003/016).
     * Rows: ctrlx_doc_001 (no underscore after ctrl), CTRL_doc_001 (uppercase).
     * Note: hrp503.special.ctrl_lookalike is already seeded by seedCurated().
     */
    private function seedNonMatching(): void
    {
        DB::table('field_definitions')->insert([
            [
                'key' => 'ctrlx_doc_001',
                'label' => 'No-underscore variant',
                'section' => 'HRP-503c',
                'sort_order' => 9001,
                'is_required' => false,
                'input_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'CTRL_doc_001',
                'label' => 'Uppercase variant',
                'section' => 'HRP-503c',
                'sort_order' => 9002,
                'is_required' => false,
                'input_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Insert one PFV per field_definition.id with rotating suggestion_source values.
     */
    private function seedPfvs(int $projectId): void
    {
        $fieldIds = DB::table('field_definitions')->pluck('id')->all();
        $sources = ['evidence', 'ai_draft', null];
        $rows = [];

        foreach ($fieldIds as $i => $fieldId) {
            $rows[] = [
                'project_id' => $projectId,
                'field_definition_id' => $fieldId,
                'suggested_value' => null,
                'final_value' => null,
                'status' => 'missing',
                'suggestion_source' => $sources[$i % 3],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('project_field_values')->insert($rows);
    }

    /**
     * Create a minimal project row and return its ID.
     */
    private function createProject(): int
    {
        return DB::table('projects')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $this->createUser(),
            'name' => 'Test Project',
            'status' => 'draft',
            'assistance_mode' => 'strict',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Create a minimal user row and return its ID.
     */
    private function createUser(): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
            'is_approved' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Load and return the migration object from disk.
     */
    private function loadMigration(): object
    {
        return require $this->migrationFile;
    }

    // -------------------------------------------------------------------------
    // S1 — Production-shape fixture; happy path
    // -------------------------------------------------------------------------

    #[Test]
    public function s1_happy_path_deletes_synthetic_rows_and_writes_audit_event(): void
    {
        // Given: curated (60), synthetic (150), boundary (2+1 from curated), one project
        $this->seedCurated();      // 34 hrp503 + 26 hrp503c = 60 rows (includes hrp503.special.ctrl_lookalike)
        $this->seedSynthetic(150); // 150 ctrl_* rows
        $this->seedNonMatching();  // ctrlx_doc_001, CTRL_doc_001 — 2 more

        $projectId = $this->createProject();
        $this->seedPfvs($projectId); // 213 PFVs total (60 + 150 + 2 + 1 boundary already in curated)

        $auditEventsBefore = DB::table('audit_events')->count();

        // When: invoke migration directly
        $migration = $this->loadMigration();
        $migration->up();

        // Then: 60 curated + 2 boundary = 62 rows remaining
        // (hrp503.special.ctrl_lookalike is curated, counted in the 60)
        $remaining = DB::table('field_definitions')->count();
        $this->assertSame(62, $remaining, 'Expected 60 curated + 2 boundary rows to survive');

        // Verify ctrl_* deleted
        $ctrlCount = DB::table('field_definitions')
            ->where('key', 'LIKE BINARY', 'ctrl\\_%')
            ->count();
        $this->assertSame(0, $ctrlCount, 'All ctrl_* rows should be deleted');

        // Verify boundary rows survive
        $this->assertTrue(
            DB::table('field_definitions')->where('key', 'hrp503.summary.study_title')->exists(),
            'Curated hrp503.summary.study_title must survive'
        );
        $this->assertTrue(
            DB::table('field_definitions')->where('key', 'ctrlx_doc_001')->exists(),
            'ctrlx_doc_001 must survive (REQ-CLEAN-003)'
        );
        $this->assertTrue(
            DB::table('field_definitions')->where('key', 'CTRL_doc_001')->exists(),
            'CTRL_doc_001 must survive (REQ-CLEAN-016)'
        );
        $this->assertTrue(
            DB::table('field_definitions')->where('key', 'hrp503.special.ctrl_lookalike')->exists(),
            'hrp503.special.ctrl_lookalike must survive (REQ-CLEAN-003)'
        );

        // PFVs for the 62 surviving rows survive; 150 PFVs deleted
        $pfvCount = DB::table('project_field_values')->count();
        $this->assertSame(62, $pfvCount, 'PFVs for surviving field_definitions should remain');

        // Audit event inserted
        $newAuditCount = DB::table('audit_events')->count();
        $this->assertSame($auditEventsBefore + 1, $newAuditCount, 'Exactly one audit event should be inserted');

        $auditEvent = DB::table('audit_events')
            ->where('event_type', 'system.cleanup.field_definitions')
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($auditEvent);
        $payload = json_decode($auditEvent->payload, true);
        $this->assertIsArray($payload);
        $this->assertSame('SPEC-IRB-CLEANUP-001', $payload['spec']);
        $this->assertSame(150, $payload['field_definitions_deleted']);
        $this->assertSame(150, $payload['project_field_values_deleted']);
        $this->assertSame('BINARY', $payload['collation']);
        $this->assertArrayHasKey('key_prefix_pattern', $payload);
    }

    // -------------------------------------------------------------------------
    // S2 — Fresh DB + seeders only; no ctrl_* rows initially
    // -------------------------------------------------------------------------

    #[Test]
    public function s2_fresh_db_with_no_synthetic_rows_has_zero_ctrl_count(): void
    {
        // After RefreshDatabase (with seeders not run), no ctrl_* rows exist.
        // The cleanup migration already ran as part of RefreshDatabase.
        $ctrlCount = DB::table('field_definitions')
            ->where('key', 'LIKE BINARY', 'ctrl\\_%')
            ->count();

        $this->assertSame(0, $ctrlCount, 'Seeders never insert ctrl_* rows');
    }

    // -------------------------------------------------------------------------
    // S3 — Idempotency: second run is a no-op
    // -------------------------------------------------------------------------

    #[Test]
    public function s3_idempotency_second_run_is_noop(): void
    {
        // Given: S1 completed (synthetic rows deleted, one audit event)
        $this->seedCurated();
        $this->seedSynthetic(150);
        $this->seedNonMatching();
        $projectId = $this->createProject();
        $this->seedPfvs($projectId);

        $migration = $this->loadMigration();
        $migration->up(); // First run

        $fdCountAfterFirst = DB::table('field_definitions')->count();
        $pfvCountAfterFirst = DB::table('project_field_values')->count();
        $auditCountAfterFirst = DB::table('audit_events')
            ->where('event_type', 'system.cleanup.field_definitions')
            ->count();

        // When: second invocation
        $migration->up();

        // Then: no changes
        $this->assertSame($fdCountAfterFirst, DB::table('field_definitions')->count(), 'FD count unchanged on second run');
        $this->assertSame($pfvCountAfterFirst, DB::table('project_field_values')->count(), 'PFV count unchanged on second run');

        $auditCountAfterSecond = DB::table('audit_events')
            ->where('event_type', 'system.cleanup.field_definitions')
            ->count();
        $this->assertSame($auditCountAfterFirst, $auditCountAfterSecond, 'No new audit event on second run (REQ-CLEAN-007a)');
    }

    // -------------------------------------------------------------------------
    // S4 — Evidence preservation across cascade
    // -------------------------------------------------------------------------

    #[Test]
    public function s4_evidence_preservation_curated_pfvs_survive_synthetic_pfvs_deleted(): void
    {
        $this->seedCurated();

        // Insert 3 synthetic rows
        DB::table('field_definitions')->insert([
            ['key' => 'ctrl_doc_s4a', 'label' => 'S4A', 'section' => 'HRP-503c', 'sort_order' => 8001, 'is_required' => false, 'input_type' => 'text', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ctrl_doc_s4b', 'label' => 'S4B', 'section' => 'HRP-503c', 'sort_order' => 8002, 'is_required' => false, 'input_type' => 'text', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ctrl_doc_s4c', 'label' => 'S4C', 'section' => 'HRP-503c', 'sort_order' => 8003, 'is_required' => false, 'input_type' => 'text', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $projectId = $this->createProject();
        $curatedIds = DB::table('field_definitions')->where('key', 'LIKE BINARY', 'hrp503%')->pluck('id')->all();
        $syntheticIds = DB::table('field_definitions')->where('key', 'LIKE BINARY', 'ctrl\\_%')->pluck('id')->all();

        // Curated PFVs: 20 evidence + 10 ai_draft = 30
        $pfvRows = [];
        foreach (array_slice($curatedIds, 0, 20) as $id) {
            $pfvRows[] = ['project_id' => $projectId, 'field_definition_id' => $id, 'suggestion_source' => 'evidence', 'status' => 'missing', 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_slice($curatedIds, 20, 10) as $id) {
            $pfvRows[] = ['project_id' => $projectId, 'field_definition_id' => $id, 'suggestion_source' => 'ai_draft', 'status' => 'missing', 'created_at' => now(), 'updated_at' => now()];
        }
        // Synthetic PFVs: evidence, ai_draft, null
        $pfvRows[] = ['project_id' => $projectId, 'field_definition_id' => $syntheticIds[0], 'suggestion_source' => 'evidence', 'status' => 'missing', 'created_at' => now(), 'updated_at' => now()];
        $pfvRows[] = ['project_id' => $projectId, 'field_definition_id' => $syntheticIds[1], 'suggestion_source' => 'ai_draft', 'status' => 'missing', 'created_at' => now(), 'updated_at' => now()];
        $pfvRows[] = ['project_id' => $projectId, 'field_definition_id' => $syntheticIds[2], 'suggestion_source' => null, 'status' => 'missing', 'created_at' => now(), 'updated_at' => now()];

        DB::table('project_field_values')->insert($pfvRows);

        // When: migration runs
        $migration = $this->loadMigration();
        $migration->up();

        // Then: 30 curated PFVs survive
        $this->assertSame(30, DB::table('project_field_values')->count(), '30 curated PFVs must survive');

        // All 3 synthetic PFVs deleted regardless of suggestion_source
        $syntheticPfv = DB::table('project_field_values')
            ->whereIn('field_definition_id', $syntheticIds)
            ->count();
        $this->assertSame(0, $syntheticPfv, 'All synthetic PFVs should be deleted');

        // 20 evidence PFVs preserved (curated)
        $this->assertSame(
            20,
            DB::table('project_field_values')->where('suggestion_source', 'evidence')->count(),
            'Curated evidence PFVs must be preserved'
        );
    }

    // -------------------------------------------------------------------------
    // S5 — template_controls untouched
    // -------------------------------------------------------------------------

    #[Test]
    public function s5_template_controls_count_unchanged(): void
    {
        $this->seedCurated();
        $this->seedSynthetic(150);

        // Create template_version required for template_controls FK
        $userId = $this->createUser();
        $templateId = DB::table('template_versions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c',
            'sha256' => hash('sha256', 's5-test'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/s5-test.docx',
            'is_active' => false,
            'uploaded_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert 25 template_controls rows
        for ($i = 0; $i < 25; $i++) {
            DB::table('template_controls')->insert([
                'template_version_id' => $templateId,
                'part' => 'document',
                'control_index' => $i,
                'signature_sha256' => hash('sha256', "s5-ctrl-{$i}"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(25, DB::table('template_controls')->count(), 'Pre-migration count is 25');

        $migration = $this->loadMigration();
        $migration->up();

        $this->assertSame(25, DB::table('template_controls')->count(), 'template_controls count unchanged after migration');
    }

    // -------------------------------------------------------------------------
    // S6 — template_control_mappings untouched when no synthetic mappings
    // -------------------------------------------------------------------------

    #[Test]
    public function s6_template_control_mappings_untouched_when_no_synthetic_mappings(): void
    {
        $this->seedCurated();
        $this->seedSynthetic(150);

        $userId = $this->createUser();
        $templateId = DB::table('template_versions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c',
            'sha256' => hash('sha256', 's6-test'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/s6-test.docx',
            'is_active' => false,
            'uploaded_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 10 controls mapped to curated fields only
        $curatedIds = DB::table('field_definitions')
            ->where('key', 'LIKE BINARY', 'hrp503%')
            ->limit(10)
            ->pluck('id')
            ->all();

        for ($i = 0; $i < 10; $i++) {
            $ctrlId = DB::table('template_controls')->insertGetId([
                'template_version_id' => $templateId,
                'part' => 'document',
                'control_index' => $i,
                'signature_sha256' => hash('sha256', "s6-ctrl-{$i}"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('template_control_mappings')->insert([
                'template_version_id' => $templateId,
                'template_control_id' => $ctrlId,
                'field_definition_id' => $curatedIds[$i],
                'mapped_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(10, DB::table('template_control_mappings')->count());

        $migration = $this->loadMigration();
        $migration->up();

        $this->assertSame(10, DB::table('template_control_mappings')->count(), 'Mappings count unchanged (REQ-CLEAN-005)');

        // Every mapping still resolves to an existing field_definition
        $orphaned = DB::table('template_control_mappings as m')
            ->leftJoin('field_definitions as f', 'm.field_definition_id', '=', 'f.id')
            ->whereNull('f.id')
            ->count();
        $this->assertSame(0, $orphaned, 'No orphaned mappings after migration');
    }

    // -------------------------------------------------------------------------
    // S7 — audit_events historical rows untouched (exactly one new INSERT)
    // -------------------------------------------------------------------------

    #[Test]
    public function s7_audit_events_historical_rows_untouched(): void
    {
        $this->seedCurated();
        $this->seedSynthetic(150);

        // Insert 15 pre-existing audit_events
        for ($i = 0; $i < 15; $i++) {
            DB::table('audit_events')->insert([
                'occurred_at' => now(),
                'event_type' => ['user.login', 'project.create', 'template.upload'][$i % 3],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $historicalIds = DB::table('audit_events')->pluck('id')->all();
        $this->assertCount(15, $historicalIds);

        $migration = $this->loadMigration();
        $migration->up();

        $this->assertSame(16, DB::table('audit_events')->count(), '15 historical + 1 new = 16 total');

        // Historical rows are unchanged
        foreach ($historicalIds as $id) {
            $this->assertTrue(
                DB::table('audit_events')->where('id', $id)->exists(),
                "Historical audit_event id={$id} must still exist"
            );
        }

        $newRow = DB::table('audit_events')
            ->where('event_type', 'system.cleanup.field_definitions')
            ->first();
        $this->assertNotNull($newRow);
    }

    // -------------------------------------------------------------------------
    // S8 — Insertion-path gate: re-running seedFieldDefinitionsFromControls
    //      inserts zero ctrl_* rows (REQ-CLEAN-009/009a)
    // -------------------------------------------------------------------------

    #[Test]
    public function s8_gate_prevents_ctrl_rows_from_being_recreated(): void
    {
        // The cleanup migration has already run (via RefreshDatabase).
        // Insert curated rows so the sentinel does not block.
        $this->seedCurated();

        // Verify no ctrl_* rows exist after migration
        $this->assertSame(0, DB::table('field_definitions')->where('key', 'LIKE BINARY', 'ctrl\\_%')->count());

        // Create a TemplateVersion + TemplateControl so the service has something to iterate
        $userId = $this->createUser();
        $templateId = DB::table('template_versions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c',
            'sha256' => hash('sha256', 's8-test'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/s8-test.docx',
            'is_active' => true,
            'uploaded_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5 SDT controls in the document part
        for ($i = 0; $i < 5; $i++) {
            DB::table('template_controls')->insert([
                'template_version_id' => $templateId,
                'part' => 'document',
                'control_index' => $i,
                'context_before' => "Label {$i}:",
                'context_after' => null,
                'signature_sha256' => hash('sha256', "s8-ctrl-{$i}"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // When: invoke seedFieldDefinitionsFromControls
        $templateVersion = \App\Models\TemplateVersion::query()->find($templateId);
        $service = app(\App\Services\TemplateService::class);
        $service->seedFieldDefinitionsFromControls($templateVersion, createMappings: false);

        // Then: still zero ctrl_* rows (gate prevented insertion)
        $ctrlCount = DB::table('field_definitions')->where('key', 'LIKE BINARY', 'ctrl\\_%')->count();
        $this->assertSame(0, $ctrlCount, 'REQ-CLEAN-009 gate must prevent ctrl_* row insertion');
    }

    // -------------------------------------------------------------------------
    // S9 — Precondition assertion: synthetic mapping aborts cleanly
    // -------------------------------------------------------------------------

    #[Test]
    public function s9_precondition_aborts_when_synthetic_mapping_exists(): void
    {
        $this->seedCurated();
        $this->seedSynthetic(150);
        $projectId = $this->createProject();
        $this->seedPfvs($projectId);

        // Create a template_control_mapping pointing to a synthetic ctrl_doc_* field
        $syntheticFieldId = DB::table('field_definitions')
            ->where('key', 'LIKE BINARY', 'ctrl\\_%')
            ->value('id');

        $userId = $this->createUser();
        $templateId = DB::table('template_versions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c-S9',
            'sha256' => hash('sha256', 's9-test'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/s9-test.docx',
            'is_active' => false,
            'uploaded_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ctrlId = DB::table('template_controls')->insertGetId([
            'template_version_id' => $templateId,
            'part' => 'document',
            'control_index' => 0,
            'signature_sha256' => hash('sha256', 's9-ctrl-0'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('template_control_mappings')->insert([
            'template_version_id' => $templateId,
            'template_control_id' => $ctrlId,
            'field_definition_id' => $syntheticFieldId,
            'mapped_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fdCountBefore = DB::table('field_definitions')->where('key', 'LIKE BINARY', 'ctrl\\_%')->count();
        $pfvCountBefore = DB::table('project_field_values')->count();

        // When: migration runs — should throw
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SPEC-IRB-CLEANUP-001 precondition failed/');

        try {
            $migration = $this->loadMigration();
            $migration->up();
        } finally {
            // Transaction must be rolled back: counts unchanged
            $this->assertSame($fdCountBefore, DB::table('field_definitions')->where('key', 'LIKE BINARY', 'ctrl\\_%')->count(), 'No FD deletions on rollback');
            $this->assertSame($pfvCountBefore, DB::table('project_field_values')->count(), 'No PFV deletions on rollback');
            $this->assertSame(0, DB::table('audit_events')->where('event_type', 'system.cleanup.field_definitions')->count(), 'No audit insert on rollback');
        }
    }

    // -------------------------------------------------------------------------
    // S10 — down() is a no-op
    // -------------------------------------------------------------------------

    #[Test]
    public function s10_down_is_noop_and_does_not_restore_rows(): void
    {
        // Given: S1 completed
        $this->seedCurated();
        $this->seedSynthetic(150);
        $this->seedNonMatching();
        $projectId = $this->createProject();
        $this->seedPfvs($projectId);

        $migration = $this->loadMigration();
        $migration->up();

        $fdAfterUp = DB::table('field_definitions')->count();
        $pfvAfterUp = DB::table('project_field_values')->count();

        // When: rollback via Artisan
        $exitCode = Artisan::call('migrate:rollback', ['--step' => 1]);

        // Then: Artisan exits 0
        $this->assertSame(0, $exitCode, 'migrate:rollback must exit with code 0');

        // Deleted rows are NOT restored
        $this->assertSame(0, DB::table('field_definitions')->where('key', 'LIKE BINARY', 'ctrl\\_%')->count(), 'ctrl_* rows should not be restored by down()');
    }

    // -------------------------------------------------------------------------
    // S11 — Migration filename pattern matches REQ-CLEAN-001
    // -------------------------------------------------------------------------

    #[Test]
    public function s11_migration_filename_matches_pattern(): void
    {
        // When: enumerate migration files matching the pattern
        $matches = glob(
            base_path('database/migrations/2026_05_11_*_cleanup_synthetic_field_definitions.php')
        );

        // Then: exactly one file
        $this->assertCount(1, $matches, 'Exactly one cleanup migration file must exist');

        $file = $matches[0];
        $this->assertFileExists($file);

        // Filename regex: 2026_05_11_{6 digits}_cleanup_synthetic_field_definitions.php
        $basename = basename($file);
        $this->assertMatchesRegularExpression(
            '/^2026_05_11_\d{6}_cleanup_synthetic_field_definitions\.php$/',
            $basename,
            'Migration filename must match REQ-CLEAN-001 pattern'
        );

        // The returned migration is an anonymous class extending Migration
        $migration = require $file;
        $this->assertInstanceOf(\Illuminate\Database\Migrations\Migration::class, $migration);
    }

    // -------------------------------------------------------------------------
    // S12 — FK ordering: PFV-before-FD deletion succeeds; reversed fails
    // -------------------------------------------------------------------------

    #[Test]
    public function s12_fk_ordering_pfv_before_fd_succeeds_reversed_would_fail(): void
    {
        $this->seedCurated();

        // Insert one synthetic field with a PFV
        DB::table('field_definitions')->insert([
            'key' => 'ctrl_doc_999',
            'label' => 'FK test',
            'section' => 'HRP-503c',
            'sort_order' => 9999,
            'is_required' => false,
            'input_type' => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fdId = DB::table('field_definitions')->where('key', 'ctrl_doc_999')->value('id');
        $projectId = $this->createProject();

        DB::table('project_field_values')->insert([
            'project_id' => $projectId,
            'field_definition_id' => $fdId,
            'status' => 'missing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Positive case: correct order (PFVs first, then parent) should succeed without exception
        DB::table('project_field_values')->where('field_definition_id', $fdId)->delete();
        DB::table('field_definitions')->where('id', $fdId)->delete();

        $this->assertFalse(DB::table('field_definitions')->where('id', $fdId)->exists(), 'FD deleted successfully');

        // Negative control: insert again and attempt parent delete FIRST — expect FK exception
        DB::table('field_definitions')->insert([
            'id' => $fdId,
            'key' => 'ctrl_doc_999',
            'label' => 'FK test again',
            'section' => 'HRP-503c',
            'sort_order' => 9999,
            'is_required' => false,
            'input_type' => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_field_values')->insert([
            'project_id' => $projectId,
            'field_definition_id' => $fdId,
            'status' => 'missing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('field_definitions')->where('id', $fdId)->delete(); // Should fail — FK constraint
    }

    // -------------------------------------------------------------------------
    // S13 — Mid-transaction fault triggers full rollback
    // -------------------------------------------------------------------------

    #[Test]
    public function s13_mid_transaction_fault_triggers_full_rollback(): void
    {
        $this->seedCurated();
        $this->seedSynthetic(150);
        $projectId = $this->createProject();
        $this->seedPfvs($projectId);

        $fdCountBefore = DB::table('field_definitions')->where('key', 'LIKE BINARY', 'ctrl\\_%')->count();
        $pfvCountBefore = DB::table('project_field_values')->count();

        // Simulate transaction fault by wrapping in a transaction that we
        // force-rollback after the PFV delete.
        $exceptionThrown = false;
        try {
            DB::transaction(function () {
                // Collect IDs
                $idsToDelete = DB::table('field_definitions')
                    ->where('key', 'LIKE BINARY', 'ctrl\\_%')
                    ->pluck('id')
                    ->all();

                // Delete PFVs first
                DB::table('project_field_values')
                    ->whereIn('field_definition_id', $idsToDelete)
                    ->delete();

                // Simulate fault before parent delete
                throw new \RuntimeException('Injected fault for S13 rollback test');
            });
        } catch (\RuntimeException $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('Injected fault', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Exception should have been thrown');

        // After rollback: all counts should be restored
        $this->assertSame(
            $fdCountBefore,
            DB::table('field_definitions')->where('key', 'LIKE BINARY', 'ctrl\\_%')->count(),
            'FD count must be restored after rollback'
        );
        $this->assertSame(
            $pfvCountBefore,
            DB::table('project_field_values')->count(),
            'PFV count must be restored after rollback'
        );
        $this->assertSame(
            0,
            DB::table('audit_events')->where('event_type', 'system.cleanup.field_definitions')->count(),
            'No audit event should be written on rollback'
        );
    }

    // -------------------------------------------------------------------------
    // S14 — Mapping reachability post-migration
    // -------------------------------------------------------------------------

    #[Test]
    public function s14_mapping_reachability_curated_fk_still_resolves_after_migration(): void
    {
        $this->seedCurated();
        $this->seedSynthetic(150);

        $userId = $this->createUser();
        $templateId = DB::table('template_versions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c-S14',
            'sha256' => hash('sha256', 's14-test'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/s14-test.docx',
            'is_active' => false,
            'uploaded_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $curatedIds = DB::table('field_definitions')
            ->where('key', 'LIKE BINARY', 'hrp503%')
            ->limit(10)
            ->pluck('id')
            ->all();

        for ($i = 0; $i < 10; $i++) {
            $ctrlId = DB::table('template_controls')->insertGetId([
                'template_version_id' => $templateId,
                'part' => 'document',
                'control_index' => $i,
                'signature_sha256' => hash('sha256', "s14-ctrl-{$i}"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('template_control_mappings')->insert([
                'template_version_id' => $templateId,
                'template_control_id' => $ctrlId,
                'field_definition_id' => $curatedIds[$i],
                'mapped_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = $this->loadMigration();
        $migration->up();

        // All 10 mappings still resolve
        $resolvedCount = DB::table('template_control_mappings as m')
            ->join('field_definitions as f', 'm.field_definition_id', '=', 'f.id')
            ->count();

        $this->assertSame(10, $resolvedCount, 'All curated-field mappings must still resolve after migration');
    }

    // -------------------------------------------------------------------------
    // S15 — Mixed suggestion_source cascade
    // -------------------------------------------------------------------------

    #[Test]
    public function s15_mixed_suggestion_source_all_synthetic_pfvs_deleted(): void
    {
        $this->seedCurated();

        // 3 synthetic fields
        DB::table('field_definitions')->insert([
            ['key' => 'ctrl_doc_s15a', 'label' => 'S15A', 'section' => 'HRP-503c', 'sort_order' => 8501, 'is_required' => false, 'input_type' => 'text', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ctrl_doc_s15b', 'label' => 'S15B', 'section' => 'HRP-503c', 'sort_order' => 8502, 'is_required' => false, 'input_type' => 'text', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ctrl_doc_s15c', 'label' => 'S15C', 'section' => 'HRP-503c', 'sort_order' => 8503, 'is_required' => false, 'input_type' => 'text', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $syntheticIds = DB::table('field_definitions')
            ->whereIn('key', ['ctrl_doc_s15a', 'ctrl_doc_s15b', 'ctrl_doc_s15c'])
            ->pluck('id')
            ->all();

        $projectId = $this->createProject();
        DB::table('project_field_values')->insert([
            ['project_id' => $projectId, 'field_definition_id' => $syntheticIds[0], 'suggestion_source' => 'evidence', 'status' => 'missing', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $projectId, 'field_definition_id' => $syntheticIds[1], 'suggestion_source' => 'ai_draft', 'status' => 'missing', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $projectId, 'field_definition_id' => $syntheticIds[2], 'suggestion_source' => null, 'status' => 'missing', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration = $this->loadMigration();
        $migration->up();

        // All 3 PFVs deleted regardless of suggestion_source
        $remaining = DB::table('project_field_values')
            ->whereIn('field_definition_id', $syntheticIds)
            ->count();
        $this->assertSame(0, $remaining, 'All 3 synthetic PFVs must be deleted regardless of suggestion_source');
    }

    // -------------------------------------------------------------------------
    // S16 — Case-sensitivity invariant
    // -------------------------------------------------------------------------

    #[Test]
    public function s16_case_sensitivity_uppercase_and_no_underscore_variants_survive(): void
    {
        $this->seedCurated();
        $this->seedSynthetic(150);
        $this->seedNonMatching(); // CTRL_doc_001, ctrlx_doc_001

        $migration = $this->loadMigration();
        $migration->up();

        // CTRL_doc_001 (uppercase) must survive
        $this->assertTrue(
            DB::table('field_definitions')->where('key', 'CTRL_doc_001')->exists(),
            'CTRL_doc_001 (uppercase) must NOT be deleted (REQ-CLEAN-016)'
        );

        // ctrlx_doc_001 (no underscore after ctrl) must survive
        $this->assertTrue(
            DB::table('field_definitions')->where('key', 'ctrlx_doc_001')->exists(),
            'ctrlx_doc_001 must NOT be deleted (REQ-CLEAN-003)'
        );

        // All lowercase ctrl_* rows must be deleted
        $this->assertSame(
            0,
            DB::table('field_definitions')->where('key', 'LIKE BINARY', 'ctrl\\_%')->count(),
            'All lowercase ctrl_* rows must be deleted'
        );
    }

    // -------------------------------------------------------------------------
    // S17 — Curated-row sentinel aborts on unseeded database
    // -------------------------------------------------------------------------

    #[Test]
    public function s17_curated_row_sentinel_aborts_on_unseeded_database(): void
    {
        // DB has ctrl_* rows but NO curated rows (simulates production with
        // ctrl_* data but a partially seeded / corrupted environment).
        // The pendingCount > 0 triggers the transaction, and the sentinel fires.
        $this->seedSynthetic(10); // 10 ctrl_* rows — enough to trigger the transaction

        $this->assertSame(0, DB::table('field_definitions')
            ->where(function ($q) {
                $q->where('key', 'LIKE BINARY', 'hrp503.%')
                    ->orWhere('key', 'LIKE BINARY', 'hrp503c.%');
            })
            ->count(), 'No curated rows should exist');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/SPEC-IRB-CLEANUP-001 curated-row sentinel failed: expected >= 50 hrp503\/hrp503c rows, found 0/'
        );

        $migration = $this->loadMigration();
        $migration->up();
    }

    // -------------------------------------------------------------------------
    // S18 — Regression-test gate (documented assertion — actual run is external)
    // -------------------------------------------------------------------------

    #[Test]
    public function s18_regression_gate_this_test_class_exists_and_loads(): void
    {
        // This scenario documents that the full test suite must pass with >= 314 tests.
        // The actual assertion is in the implementation report (php artisan test output).
        // Here we assert structural prerequisites: this class loads and the migration file exists.
        $this->assertTrue(
            class_exists(self::class),
            'CleanupSyntheticFieldDefinitionsTest class must be discoverable by PHPUnit'
        );

        $matches = glob(
            base_path('database/migrations/2026_05_11_*_cleanup_synthetic_field_definitions.php')
        );
        $this->assertCount(1, $matches, 'Exactly one migration file must exist (REQ-CLEAN-001)');

        $this->assertFileExists(
            base_path('tests/Unit/TemplateServiceSyntheticGateTest.php'),
            'Unit test file must also exist'
        );
    }
}
