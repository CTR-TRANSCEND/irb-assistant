<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-IRB-FORMSV2-007 Phase 7 — Legacy schema DROP migration.
 *
 * DRAFT STATUS — this file lives in `database/migrations-drafts/` and is NOT
 * picked up by `php artisan migrate`. To activate, see the directory README.
 *
 * Drops the legacy Project / ProjectFieldValue / FieldDefinition / AnalysisRun
 * schema. Phase 3 (PR #6) already DELETEd all rows; this migration removes
 * the now-empty table definitions.
 *
 * Pre-flight (operator MUST verify before activating):
 * 1. `SELECT COUNT(*) FROM projects` → 0 expected
 * 2. `SELECT COUNT(*) FROM project_field_values` → 0 expected
 * 3. `SELECT COUNT(*) FROM analysis_runs` → 0 expected
 * 4. `SELECT COUNT(*) FROM audit_events WHERE project_id IS NOT NULL` → 0 expected
 * 5. Confirm no production traffic to /projects/* in last 7 days (Apache logs)
 * 6. Fresh DB backup taken
 *
 * Order of DROPs respects FK dependency:
 * - project_field_values (FK → projects, FK → analysis_runs)
 * - analysis_runs       (FK → projects)
 * - field_definitions   (referenced by project_field_values only)
 * - project_documents   (FK → projects; verify Phase 4 PR-1's study_id is the
 *                       only non-null FK in use first)
 * - projects            (last — referenced by all above)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pre-flight assertions — abort if any legacy table is non-empty
        $this->assertEmpty('projects');
        $this->assertEmpty('project_field_values');
        $this->assertEmpty('analysis_runs');

        // Defensively NULLify any lingering audit_events.project_id references
        // (Phase 3 wipe migration did this in Step 0; this is a re-affirmation).
        DB::table('audit_events')
            ->whereNotNull('project_id')
            ->update(['project_id' => null]);

        Schema::disableForeignKeyConstraints();

        // FK order matters even with constraints disabled in some MariaDB
        // versions — drop leaf tables first.
        Schema::dropIfExists('project_field_values');
        Schema::dropIfExists('analysis_runs');
        Schema::dropIfExists('field_definitions');

        // project_documents: Phase 4 PR-1 added a nullable study_id column.
        // If active rows reference study_id, DO NOT drop — keep the table.
        // Operator decision required:
        $activeStudyDocs = DB::table('project_documents')
            ->whereNotNull('study_id')
            ->count();
        if ($activeStudyDocs === 0) {
            Schema::dropIfExists('project_documents');
        } else {
            // Leave project_documents in place for the Submission upload flow.
            // The `project_id` column can be NULLified later.
        }

        Schema::dropIfExists('projects');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Rollback is via DB backup restore. See migrations-drafts/README.md.
        throw new \RuntimeException(
            'SPEC-IRB-FORMSV2-007 DROP migration is NOT reversible. '
            .'Restore from the pre-DROP DB backup.'
        );
    }

    /**
     * Abort the migration if a table has any rows.
     */
    private function assertEmpty(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return; // already dropped, nothing to assert
        }
        $count = DB::table($table)->count();
        if ($count > 0) {
            throw new \RuntimeException(
                "SPEC-IRB-FORMSV2-007 abort: {$table} has {$count} rows; "
                .'Phase 3 wipe must complete before activating this migration.'
            );
        }
    }
};
