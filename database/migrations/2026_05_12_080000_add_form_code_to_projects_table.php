<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-form rollout — Outstanding #49.
 *
 * Adds form_code enum discriminator to projects so the single-active-template
 * assumption can be replaced with per-project routing across HRP-503, HRP-503c,
 * and HRP-398. Backfill defaults to 'hrp503c' to preserve production behavior
 * (HRP-503c was the only template installed before this migration).
 *
 * The column is added without a DEFAULT, then backfilled in a separate statement,
 * then locked NOT NULL — this avoids MariaDB DDL-in-transaction surprises that
 * bit us on the 2026_05_08_000001 is_approved migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Phase 1: add nullable column (DDL, auto-committed on MariaDB).
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('form_code', ['hrp503', 'hrp503c', 'hrp398'])
                ->nullable()
                ->after('project_summary');
        });

        // Phase 2: backfill in a separate statement (DML, transaction-safe).
        DB::table('projects')
            ->whereNull('form_code')
            ->update(['form_code' => 'hrp503c']);

        // Phase 3: enforce NOT NULL once every row has a value.
        DB::statement(
            "ALTER TABLE projects MODIFY form_code ENUM('hrp503','hrp503c','hrp398') NOT NULL DEFAULT 'hrp503c'"
        );
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('form_code');
        });
    }
};
