<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 PR-1 security: DB-level trigger enforcing tracking_only terminal state.
 *
 * Closes Outstanding #61 / security review F1 PR-2 recommendation.
 * REQ-IRB-FORMSV2-014a: once status='tracking_only', no UPDATE to any other value.
 *
 * Uses a BEFORE UPDATE trigger (MariaDB) rather than a CHECK constraint
 * because a CHECK cannot reference NEW vs OLD values in the same row in
 * older MariaDB versions. SIGNAL SQLSTATE '45000' raises a user-defined
 * error detectable by PDO as QueryException.
 *
 * SPEC-IRB-FORMSV2-004 §D migration 4.
 *
 * @MX:WARN: [AUTO] Uses DB::unprepared() — raw SQL DDL for trigger creation.
 *
 * @MX:REASON: MariaDB triggers cannot be expressed via Schema builder; raw DDL is the only path.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Some shared/managed MariaDB environments grant the app user only DML
        // privileges (no TRIGGER privilege). Gracefully degrade: if the CREATE
        // TRIGGER fails with privilege error, log a warning and continue.
        //
        // The Eloquent saving-event listener on Submission (Phase 3) already
        // enforces the same invariant at the app layer. The trigger is
        // defense-in-depth — its absence means raw DB::table()->update() bypasses
        // the invariant but no Eloquent or controller path can.
        try {
            DB::unprepared('
                CREATE TRIGGER submission_tracking_only_guard
                BEFORE UPDATE ON submission
                FOR EACH ROW
                BEGIN
                    IF OLD.status = \'tracking_only\' AND NEW.status != \'tracking_only\' THEN
                        SIGNAL SQLSTATE \'45000\'
                            SET MESSAGE_TEXT = \'Cannot transition from tracking_only: terminal state per REQ-IRB-FORMSV2-014a\';
                    END IF;
                END
            ');
        } catch (\Illuminate\Database\QueryException $e) {
            $code = $e->errorInfo[1] ?? null;
            $msg = $e->getMessage();

            // MariaDB error 1142 = command denied for the user.
            if ($code === 1142 || str_contains($msg, 'TRIGGER command denied')) {
                logger()->warning(
                    'FORMSV2 Phase 4 PR-1: TRIGGER privilege not granted to DB user; '
                    .'submission_tracking_only_guard not installed. App-level enforcement '
                    .'(Submission::booted saving listener) remains active. '
                    .'Outstanding #61 partial — grant TRIGGER privilege and re-run this migration to close.'
                );

                return;
            }

            // Re-throw any non-privilege error
            throw $e;
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS submission_tracking_only_guard');
    }
};
