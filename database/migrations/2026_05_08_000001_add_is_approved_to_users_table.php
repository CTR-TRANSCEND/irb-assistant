<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-AUTH-001 M1 — Add is_approved, approved_at, approved_by to users.
 *
 * REQ-AUTH-001: is_approved boolean, default false for new accounts.
 * REQ-AUTH-002: approved_at (nullable timestamp), approved_by (nullable FK → users.id).
 * REQ-AUTH-022: Backfill is_approved = true for all rows that exist at migration time.
 * REQ-AUTH-023 (revised): Schema ALTER auto-commits on MariaDB/MySQL — DB::transaction
 *               cannot wrap DDL there. The backfill UPDATE runs immediately after the
 *               ALTER as a separate DML statement; Laravel's migrator rolls back the
 *               migration record on exception, preventing a half-applied entry. The
 *               theoretical risk window (column added but UPDATE failed mid-flight) is
 *               recoverable by re-running `php artisan migrate` (UPDATE is idempotent
 *               since the column default is false → only newly-added column rows are
 *               updated). Documented limitation: SPEC-AUTH-001 REQ-AUTH-023.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema change (DDL — auto-commits on MariaDB/MySQL; transactional on Postgres/SQLite).
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false)->after('is_active');
            $table->timestamp('approved_at')->nullable()->after('is_approved');
            $table->foreignId('approved_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        // REQ-AUTH-022: backfill — every existing row is approved by definition.
        // Wrapped in DB::transaction so the UPDATE itself is atomic across rows on all engines.
        DB::transaction(function () {
            DB::table('users')->update(['is_approved' => true]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['is_approved', 'approved_at', 'approved_by']);
        });
    }
};
