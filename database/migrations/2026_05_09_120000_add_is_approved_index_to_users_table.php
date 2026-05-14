<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3: Compound index on users(is_approved, created_at).
 *
 * AdminController::index() queries WHERE is_approved=false ORDER BY created_at DESC
 * on every admin Users tab load. Without this index the query performs a full table
 * scan. The compound index allows the DB to satisfy both the filter and the sort
 * from a single index range scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['is_approved', 'created_at'], 'users_is_approved_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_is_approved_created_at_idx');
        });
    }
};
