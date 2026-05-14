<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-IRB-GUIDE-001 M1 — Add assistance_mode to projects.
 *
 * REQ-IRB-GUIDE-001: The projects table SHALL carry a non-null assistance_mode column.
 * REQ-IRB-GUIDE-002: New rows default to 'assistant' at the controller layer.
 *
 * DB default is 'strict' (conservative — existing rows receive 'strict' automatically
 * via the column-add ALTER TABLE on MariaDB). The controller default ('assistant') is
 * enforced at the controller/FormRequest layer, not at the DB level.
 *
 * MariaDB DDL-in-transaction caveat (documented in SPEC-AUTH-001 lessons):
 * Schema::table() auto-commits on MariaDB/MySQL. The column default 'strict' means
 * no separate backfill UPDATE is needed — existing rows receive 'strict' from the
 * column default itself. This keeps the migration atomic (single DDL statement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // REQ-IRB-GUIDE-001: non-null string column; default 'strict' so existing
            // rows are backfilled automatically by the DB engine without a separate UPDATE.
            $table->string('assistance_mode', 16)->default('strict')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('assistance_mode');
        });
    }
};
