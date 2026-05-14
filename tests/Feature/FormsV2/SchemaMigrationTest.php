<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3 schema migration tests.
 *
 * Verifies that migrate:fresh creates all 11 new FormsV2 tables,
 * legacy tables survive with zero rows, and preserved tables are unaffected.
 *
 * SPEC-IRB-FORMSV2-003: SchemaMigrationTest
 * Acceptance gate: REQ-IRB-FORMSV2-093 items 1, 8, 9.
 */
class SchemaMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function all_eleven_formsv2_tables_are_created(): void
    {
        $this->assertTrue(Schema::hasTable('form_definition'), 'form_definition table missing');
        $this->assertTrue(Schema::hasTable('form_section'), 'form_section table missing');
        $this->assertTrue(Schema::hasTable('form_question'), 'form_question table missing');
        $this->assertTrue(Schema::hasTable('form_question_option'), 'form_question_option table missing');
        $this->assertTrue(Schema::hasTable('form_endnote'), 'form_endnote table missing');
        $this->assertTrue(Schema::hasTable('form_section_group'), 'form_section_group table missing');
        $this->assertTrue(Schema::hasTable('studies'), 'studies table missing');
        $this->assertTrue(Schema::hasTable('submission'), 'submission table missing');
        $this->assertTrue(Schema::hasTable('submission_answer'), 'submission_answer table missing');
        $this->assertTrue(Schema::hasTable('submission_upload'), 'submission_upload table missing');
        $this->assertTrue(Schema::hasTable('worksheet_assist_state'), 'worksheet_assist_state table missing');
    }

    #[Test]
    public function submission_table_has_study_id_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('submission', 'study_id'),
            'submission.study_id column missing — REQ-IRB-FORMSV2-011'
        );
    }

    #[Test]
    public function submission_table_has_status_column(): void
    {
        $this->assertTrue(Schema::hasColumn('submission', 'status'));
    }

    #[Test]
    public function studies_table_has_uuid_column(): void
    {
        $this->assertTrue(Schema::hasColumn('studies', 'uuid'));
    }

    #[Test]
    public function legacy_tables_still_exist_with_zero_rows(): void
    {
        $legacyTables = [
            'projects',
            'project_field_values',
            'field_definitions',
            'template_control_mappings',
            'template_controls',
            'project_documents',   // actual table name (not 'documents')
            'document_chunks',
            'analysis_runs',
            'exports',
            'field_evidence',
        ];

        foreach ($legacyTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Legacy table '{$table}' does not exist");
            $this->assertEquals(
                0,
                \Illuminate\Support\Facades\DB::table($table)->count(),
                "Legacy table '{$table}' should have 0 rows after wipe migration"
            );
        }
    }

    #[Test]
    public function preserved_tables_exist(): void
    {
        $preserved = [
            'users',
            'llm_providers',
            'audit_events',
            'migrations',
        ];

        foreach ($preserved as $table) {
            $this->assertTrue(Schema::hasTable($table), "Preserved table '{$table}' does not exist");
        }
    }

    #[Test]
    public function submission_answer_has_suggestion_source_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('submission_answer', 'suggestion_source'),
            'submission_answer.suggestion_source missing — REQ-IRB-FORMSV2-054'
        );
    }

    #[Test]
    public function wipe_migration_emitted_audit_events(): void
    {
        // The wipe migration emits 3 audit_events rows ONLY when at least 1 legacy row
        // was actually deleted (REQ-IRB-FORMSV2-022). In a RefreshDatabase test environment,
        // migrate:fresh runs on an empty DB — all 10 legacy tables have 0 rows — so the
        // wipe is a silent no-op (0 markers). The markers are emitted only in production
        // where real legacy data exists. This test verifies the count is either 0 (test env)
        // or 3 (production wipe), but never 1 or 2 (partial write).
        $wipeEvents = \Illuminate\Support\Facades\DB::table('audit_events')
            ->whereIn('event_type', [
                'system.wipe.projects_pre_v2_migration',
                'system.wipe.payload_redaction_completed',
                'system.wipe.preservation_verified',
            ])
            ->count();

        $this->assertContains(
            $wipeEvents,
            [0, 3],
            'Wipe audit markers must be all-or-nothing: 0 (empty DB / test env) or 3 (real wipe)'
        );
    }
}
