<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-IRB-GUIDE-001 M1 — Add suggestion_source to project_field_values.
 *
 * REQ-IRB-GUIDE-015: nullable suggestion_source column, values: 'evidence', 'ai_draft', NULL.
 * One-time backfill: rows with non-empty suggested_value → suggestion_source = 'evidence'.
 * Rows with empty/null suggested_value → retain NULL.
 *
 * MariaDB DDL-in-transaction caveat (see SPEC-AUTH-001 lessons):
 * Schema::table() auto-commits on MariaDB. The backfill UPDATE runs immediately after
 * the ALTER TABLE as a separate DML statement wrapped in DB::transaction to keep the
 * UPDATE itself atomic. This mirrors 2026_05_08_000001_add_is_approved_to_users_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: DDL — add the nullable column (auto-commits on MariaDB).
        Schema::table('project_field_values', function (Blueprint $table) {
            $table->string('suggestion_source', 16)->nullable()->after('suggested_value');
            $table->index(['project_id', 'suggestion_source'], 'pfv_project_source_idx');
        });

        // Step 2: DML backfill — one-time update for existing rows.
        // REQ-IRB-GUIDE-015: rows with non-empty suggested_value get suggestion_source='evidence'.
        DB::transaction(function () {
            DB::table('project_field_values')
                ->whereNotNull('suggested_value')
                ->where('suggested_value', '!=', '')
                ->update(['suggestion_source' => 'evidence']);
        });
    }

    public function down(): void
    {
        Schema::table('project_field_values', function (Blueprint $table) {
            $table->dropIndex('pfv_project_source_idx');
            $table->dropColumn('suggestion_source');
        });
    }
};
