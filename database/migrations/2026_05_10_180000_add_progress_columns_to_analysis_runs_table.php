<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-IRB-GUIDE-002: queue-backed analyze with live progress polling.
 *
 * Adds progress-tracking columns to analysis_runs so the queue worker
 * can publish heartbeat + step info that the frontend modal polls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            // 'queued' is a new valid value for the existing status column;
            // schema is varchar so no enum constraint to update.
            $table->string('progress_step', 64)->nullable()->after('status');
            $table->unsignedInteger('progress_current')->nullable()->after('progress_step');
            $table->unsignedInteger('progress_total')->nullable()->after('progress_current');
            $table->string('progress_message', 255)->nullable()->after('progress_total');
            $table->timestamp('last_heartbeat_at')->nullable()->after('progress_message');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->dropColumn([
                'progress_step',
                'progress_current',
                'progress_total',
                'progress_message',
                'last_heartbeat_at',
            ]);
        });
    }
};
