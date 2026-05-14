<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 PR-1: adds nullable submission_id FK to exports table.
 *
 * Keeps project_id nullable for backward compat (legacy exports reference projects).
 * SubmissionDocxExportService populates submission_id; ExportController reads it.
 *
 * SPEC-IRB-FORMSV2-004 §D companion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $table): void {
            // Make project_id nullable so FormsV2 exports can be created without a project.
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable()->change();
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            // Make template_version_id nullable — FormsV2 exports use ad-hoc templates.
            $table->dropForeign(['template_version_id']);
            $table->unsignedBigInteger('template_version_id')->nullable()->change();
            $table->foreign('template_version_id')->references('id')->on('template_versions')->onDelete('set null');

            $table->unsignedBigInteger('submission_id')->nullable()->after('project_id');
            $table->foreign('submission_id')
                ->references('id')
                ->on('submission')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table): void {
            $table->dropForeign(['submission_id']);
            $table->dropColumn('submission_id');

            // Restore template_version_id to NOT NULL
            $table->dropForeign(['template_version_id']);
            $table->unsignedBigInteger('template_version_id')->nullable(false)->change();
            $table->foreign('template_version_id')->references('id')->on('template_versions');

            // Restore project_id to NOT NULL
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects');
        });
    }
};
