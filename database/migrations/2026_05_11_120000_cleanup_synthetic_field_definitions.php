<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-IRB-CLEANUP-001 — Purge synthetic ctrl_* field_definitions.
 *
 * Deletes every field_definitions row whose key matches the synthetic
 * prefix family produced by TemplateService::syntheticFieldKeyForControl()
 * (line 490 of app/Services/TemplateService.php). Prefix family covered:
 *   ctrl_doc_*, ctrl_endnotes_*, ctrl_footnotes_*,
 *   ctrl_header{N}_*, ctrl_footer{N}_*
 *
 * Case-sensitivity: LIKE BINARY forces byte-exact matching (REQ-CLEAN-002,
 * REQ-CLEAN-016). A row keyed CTRL_doc_001 (uppercase) is NOT matched.
 *
 * Cascade: PFVs deleted FIRST to satisfy the ON DELETE RESTRICT FK
 * declared in 2026_02_08_011604_create_project_field_values_table.php
 * line 17 (REQ-CLEAN-004).
 *
 * Audit: Direct insert into audit_events (AuditService::log requires a
 * Request object, not available in CLI migration context — see spec.md
 * Findings #4). All NULL columns confirmed nullable in
 * 2026_02_08_011613_create_audit_events_table.php lines 14-33.
 *
 * Idempotency: Second run finds zero matching rows, skips audit insert
 * (REQ-CLEAN-006, REQ-CLEAN-007a).
 *
 * Reversibility: down() is a no-op. Rollback is mysqldump restore
 * (REQ-CLEAN-014, REQ-CLEAN-018).
 */
return new class extends Migration
{
    public function up(): void
    {
        // REQ-CLEAN-006 / REQ-CLEAN-007a: Early idempotency check outside the
        // transaction. If there are no ctrl_* rows to delete, skip the sentinel
        // checks and the transaction entirely. This prevents the REQ-CLEAN-015
        // sentinel from falsely aborting a fresh-migration pass on a test or
        // production database that has already been cleaned (second invocation).
        $pendingCount = DB::table('field_definitions')
            ->where('key', 'LIKE BINARY', 'ctrl\\_%')
            ->count();

        if ($pendingCount === 0) {
            // Nothing to delete — second run or fresh DB with no synthetic rows.
            // REQ-CLEAN-006: must complete without exception.
            // REQ-CLEAN-007a: no audit insert.
            return;
        }

        DB::transaction(function () {
            // REQ-CLEAN-013: precondition assertion with row-locking SELECT.
            // SELECT ... FOR UPDATE blocks concurrent INSERTs into either
            // template_control_mappings or field_definitions for the
            // duration of this transaction, closing the TOCTOU race.
            $orphanRiskCount = DB::table('template_control_mappings')
                ->join('field_definitions', 'template_control_mappings.field_definition_id', '=', 'field_definitions.id')
                ->where('field_definitions.key', 'LIKE BINARY', 'ctrl\\_%')
                ->lockForUpdate()
                ->count();

            if ($orphanRiskCount > 0) {
                throw new \RuntimeException(
                    'SPEC-IRB-CLEANUP-001 precondition failed: template_control_mappings references synthetic ctrl_* field_definitions; aborting to avoid orphaned mappings'
                );
            }

            // REQ-CLEAN-015: curated-row sentinel — abort if database
            // appears unseeded (< 50 hrp503/hrp503c rows expected ~60).
            $curatedCount = DB::table('field_definitions')
                ->where(function ($q) {
                    $q->where('key', 'LIKE BINARY', 'hrp503.%')
                        ->orWhere('key', 'LIKE BINARY', 'hrp503c.%');
                })
                ->count();

            if ($curatedCount < 50) {
                throw new \RuntimeException(
                    'SPEC-IRB-CLEANUP-001 curated-row sentinel failed: expected >= 50 hrp503/hrp503c rows, found '.$curatedCount.'; aborting to prevent catastrophic data loss in unseeded environment'
                );
            }

            // REQ-CLEAN-002/002a: deletion predicate (case-sensitive, byte-exact).
            // Prefix family covered: ctrl_doc_*, ctrl_endnotes_*, ctrl_footnotes_*,
            // ctrl_header{N}_*, ctrl_footer{N}_*. Canonical generator:
            // TemplateService::syntheticFieldKeyForControl() at line 490.
            $idsToDelete = DB::table('field_definitions')
                ->where('key', 'LIKE BINARY', 'ctrl\\_%')
                ->pluck('id')
                ->all();

            // REQ-CLEAN-004: PFVs first (FK ON DELETE RESTRICT).
            // REQ-CLEAN-012: no suggestion_source filter — delete all matching PFVs.
            $pfvDeleted = empty($idsToDelete)
                ? 0
                : DB::table('project_field_values')
                    ->whereIn('field_definition_id', $idsToDelete)
                    ->delete();

            // REQ-CLEAN-004 (parent delete after PFVs).
            $fdDeleted = empty($idsToDelete)
                ? 0
                : DB::table('field_definitions')
                    ->whereIn('id', $idsToDelete)
                    ->delete();

            // REQ-CLEAN-007 + REQ-CLEAN-007a: insert audit event ONLY if work was done.
            if ($fdDeleted > 0) {
                DB::table('audit_events')->insert([
                    'occurred_at' => now(),
                    'actor_user_id' => null,
                    'event_type' => 'system.cleanup.field_definitions',
                    'entity_type' => 'field_definition',
                    'entity_id' => null,
                    'entity_uuid' => null,
                    'project_id' => null,
                    'ip' => null,
                    'user_agent' => null,
                    'request_id' => null,
                    'payload' => json_encode([
                        'spec' => 'SPEC-IRB-CLEANUP-001',
                        'field_definitions_deleted' => $fdDeleted,
                        'project_field_values_deleted' => $pfvDeleted,
                        'key_prefix_pattern' => 'ctrl\\_%',
                        'collation' => 'BINARY',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * REQ-CLEAN-014: forward-only migration. Rollback is mysqldump restore
     * (see REQ-CLEAN-018 and plan.md "Rollback procedure" section).
     */
    public function down(): void
    {
        // No-op by design. See SPEC-IRB-CLEANUP-001 REQ-CLEAN-014.
    }
};
