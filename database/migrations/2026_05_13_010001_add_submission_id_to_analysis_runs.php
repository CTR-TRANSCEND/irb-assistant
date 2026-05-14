<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 PR-1: adds nullable submission_id FK to analysis_runs.
 *
 * Existing project_id column is kept nullable for backward compat —
 * legacy runs still reference projects. Phase 7 drops project_id.
 *
 * SPEC-IRB-FORMSV2-004 §D migration 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table): void {
            // Make project_id nullable so FormsV2 runs can be created without a project.
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable()->change();
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->unsignedBigInteger('submission_id')->nullable()->after('project_id');
            $table->foreign('submission_id')
                ->references('id')
                ->on('submission')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table): void {
            $table->dropForeign(['submission_id']);
            $table->dropColumn('submission_id');

            // Restore project_id to NOT NULL
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects');
        });
    }
};
