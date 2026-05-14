<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 PR-1: adds nullable study_id FK to project_documents so the
 * SubmissionAnalysisService can join chunks via Study → ProjectDocument
 * without requiring the full submission_uploads pipeline (Phase 5).
 *
 * SPEC-IRB-FORMSV2-004 §D migration 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_documents', function (Blueprint $table): void {
            // Make project_id nullable so study-scoped documents can be created without a project.
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable()->change();
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->unsignedBigInteger('study_id')->nullable()->after('project_id');
            $table->foreign('study_id')
                ->references('id')
                ->on('studies')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('project_documents', function (Blueprint $table): void {
            $table->dropForeign(['study_id']);
            $table->dropColumn('study_id');

            // Restore project_id to NOT NULL
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects');
        });
    }
};
