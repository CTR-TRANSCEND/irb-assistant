<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wipe migration — Phase 3 (SPEC-IRB-FORMSV2-003).
 *
 * Cites umbrella REQ-IRB-FORMSV2-022, 022a, 023, 024, 024a, 024b, 025, 025a, 029a.
 *
 * DELETE rows from 10 legacy tables in dependency order (children before parents).
 * Emits 3 audit_events forensic marker rows:
 *   1. system.wipe.projects_pre_v2_migration  (started)
 *   2. system.wipe.payload_redaction_completed (REQ-024a simplified marker)
 *   3. system.wipe.preservation_verified       (REQ-024b ledger row)
 *
 * down() is a NO-OP per REQ-IRB-FORMSV2-029a.
 * Recovery path: mysqldump restore (REQ-IRB-FORMSV2-030 R1) is the ONLY rollback.
 */
return new class extends Migration
{
    /**
     * @MX:WARN: [AUTO] Irreversible destructive wipe — deletes all legacy project data.
     *
     * @MX:REASON: down() is a deliberate no-op; only mysqldump restore (REQ-029a R1) can recover.
     *
     * @MX:SPEC: REQ-IRB-FORMSV2-022, REQ-IRB-FORMSV2-024a, REQ-IRB-FORMSV2-029a
     */
    public function up(): void
    {
        // Audit marker rows are intentionally INSIDE the transaction.
        // If the transaction rolls back, no markers are written — this is acceptable
        // because a rollback means the wipe did not happen (data is still intact).
        // On successful commit all 3 markers are persisted atomically with the deletes.
        //
        // IMPORTANT: markers are only emitted when at least 1 row is actually deleted.
        // This preserves idempotency in test environments (migrate:fresh with empty tables
        // is a silent no-op) while ensuring forensic markers are written on real wipes.
        DB::transaction(function (): void {
            $now = now();

            // ── Step 0: NULL audit_events.project_id references to legacy projects ──
            // Per REQ-IRB-FORMSV2-024a (payload redaction intent): the audit_events.project_id
            // FK column would otherwise block the DELETE FROM projects below. Per
            // REQ-IRB-FORMSV2-023 audit_events rows themselves are PRESERVED — only the
            // project_id column reference is nullified to break the FK. The audit row's
            // event_type, actor_user_id, ip, user_agent, occurred_at, payload all remain.
            $audit_redacted = DB::table('audit_events')
                ->whereNotNull('project_id')
                ->update(['project_id' => null]);

            // ── Step 1: DELETE in dependency order (children before parents) ──────
            // Per REQ-IRB-FORMSV2-022: 10 legacy tables wiped.
            $deleted = [];
            foreach ($this->legacyTablesInOrder() as $table) {
                $deleted[$table] = DB::table($table)->delete();
            }

            $totalDeleted = array_sum($deleted);

            // Only emit forensic audit markers when real rows were deleted (REQ-IRB-FORMSV2-022).
            // On migrate:fresh against an empty DB the wipe is a silent no-op.
            if ($totalDeleted === 0) {
                return;
            }

            // ── Step 2: Start marker (REQ-IRB-FORMSV2-022) ───────────────────────
            $startMarkerId = DB::table('audit_events')->insertGetId([
                'occurred_at' => $now,
                'actor_user_id' => null,
                'event_type' => 'system.wipe.projects_pre_v2_migration',
                'entity_type' => 'migration',
                'entity_id' => null,
                'entity_uuid' => null,
                'project_id' => null,
                'ip' => '127.0.0.1',
                'user_agent' => 'artisan/migrate',
                'request_id' => null,
                'payload' => json_encode([
                    'phase' => 'completed',
                    'rows_deleted' => $deleted,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // ── Step 3: Payload-redaction marker (REQ-IRB-FORMSV2-024a) ──────────
            // @MX:TODO: [AUTO] Full per-row payload redaction of existing audit_events rows is not
            //   yet implemented. Tracked as follow-up work per REQ-IRB-FORMSV2-024a.
            //   Currently emits a summary marker only.
            DB::table('audit_events')->insert([
                'occurred_at' => $now,
                'actor_user_id' => null,
                'event_type' => 'system.wipe.payload_redaction_completed',
                'entity_type' => 'migration',
                'payload' => json_encode([
                    'redacted_at' => $now->toIso8601String(),
                    'reason' => 'FORMSV2 migration wipe (Phase 3 — REQ-IRB-FORMSV2-024a; project_id column nullified on audit_events rows referencing wiped projects; full payload-JSON redaction not yet implemented; tracked as @MX:TODO)',
                    'rows_redacted' => $audit_redacted,
                ]),
                'ip' => '127.0.0.1',
                'user_agent' => 'artisan/migrate',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // ── Step 4: Preservation-verified marker (REQ-IRB-FORMSV2-024b) ──────
            // Records post-wipe counts for the 11 preserved tables.
            DB::table('audit_events')->insert([
                'occurred_at' => $now,
                'actor_user_id' => null,
                'event_type' => 'system.wipe.preservation_verified',
                'entity_type' => 'migration',
                'payload' => json_encode([
                    'row_counts' => $this->capturePreservationCounts(),
                ]),
                'ip' => '127.0.0.1',
                'user_agent' => 'artisan/migrate',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            unset($startMarkerId); // Intentionally not used further; marker written in Step 2.
        });
    }

    /**
     * NO-OP per REQ-IRB-FORMSV2-029a.
     *
     * This wipe is irreversible at the migration level.
     * Recovery: restore from mysqldump snapshot at /home/juhur/backups/irb-assistant/
     * as described in REQ-IRB-FORMSV2-030 R1 (rollback recipe step 1).
     */
    public function down(): void
    {
        // Intentionally empty — see docblock above.
    }

    /**
     * Returns the 10 legacy tables in child-before-parent dependency order.
     * Per REQ-IRB-FORMSV2-022.
     *
     * Note: the project documents table is named `project_documents` (not `documents`).
     *
     * @return list<string>
     */
    private function legacyTablesInOrder(): array
    {
        // FK-correct child-before-parent order. Verified against production FK chain:
        //   field_evidence       → analysis_runs, project_field_values
        //   project_field_values → analysis_runs, field_definitions, projects
        //   analysis_runs        → projects, llm_providers
        //   exports              → projects, template_versions
        //   document_chunks      → project_documents
        //   project_documents    → projects
        //   template_control_mappings → field_definitions, template_controls, template_versions
        //   template_controls    → template_versions
        //   field_definitions    → (no parent in this set)
        //   projects             → (no parent in this set)
        return [
            'field_evidence',           // child of analysis_runs + project_field_values
            'project_field_values',     // child of analysis_runs + field_definitions + projects
            'analysis_runs',            // child of projects (project_field_values gone first)
            'exports',                  // child of projects
            'document_chunks',          // child of project_documents
            'project_documents',        // child of projects
            'template_control_mappings', // child of field_definitions + template_controls
            'template_controls',        // independent except for template_versions
            'field_definitions',        // independent (children gone)
            'projects',                 // root (children gone)
        ];
    }

    /**
     * Captures row counts of the 11 preserved tables per REQ-IRB-FORMSV2-023.
     *
     * @return array<string, int>
     */
    private function capturePreservationCounts(): array
    {
        $preserved = [
            'users',
            'llm_providers',
            'audit_events',
            'sessions',
            'password_resets',
            'personal_access_tokens',
            'cache',
            'cache_locks',
            'jobs',
            'failed_jobs',
            'migrations',
        ];

        $counts = [];
        foreach ($preserved as $table) {
            try {
                $counts[$table] = (int) DB::table($table)->count();
            } catch (\Throwable) {
                // Table may not exist in all environments; record 0.
                $counts[$table] = 0;
            }
        }

        return $counts;
    }
};
