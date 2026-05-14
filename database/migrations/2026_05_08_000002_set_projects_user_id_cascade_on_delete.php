<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-AUTH-001 §3.6 — FK preflight resolution (a): CASCADE.
 *
 * REQ-AUTH-025: Verify and resolve projects.owner_user_id FK behavior before
 *               AdminUserController::destroy() ships.
 *
 * RESOLUTION CHOSEN: CASCADE
 * - projects.owner_user_id  (NOT NULL)  → ON DELETE CASCADE
 *   Deleting an approved user will also delete all their owned projects and
 *   the cascade propagates through child tables (project_documents, etc.)
 *   that themselves CASCADE from projects. Admin UI should surface a confirmation
 *   ("this will also delete N projects owned by this user").
 *
 * All other nullable user FK columns are migrated to ON DELETE SET NULL so
 * that deleting a user does not produce FK-constraint 500 errors in MariaDB.
 * These are audit-trail / attribution columns where NULL is semantically correct
 * after the referenced user is removed.
 *
 * Nullable FKs converted to SET NULL:
 * - project_documents.uploaded_by_user_id
 * - project_field_values.updated_by_user_id
 * - system_settings.updated_by_user_id
 * - template_versions.uploaded_by_user_id
 * - template_control_mappings.mapped_by_user_id
 * - analysis_runs.created_by_user_id
 * - exports.created_by_user_id
 * - audit_events.actor_user_id
 */
return new class extends Migration
{
    public function up(): void
    {
        // DDL auto-commits on MariaDB/MySQL — DB::transaction cannot wrap these statements.
        // Each Schema::table() runs as its own implicit transaction. If migration is interrupted
        // mid-flight, re-running `php artisan migrate` is idempotent (dropForeign+foreign
        // re-applies the same FK definition).
        // projects.owner_user_id — NOT NULL → CASCADE
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->foreign('owner_user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        // project_documents.uploaded_by_user_id — nullable → SET NULL
        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by_user_id']);
            $table->foreign('uploaded_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // project_field_values.updated_by_user_id — nullable → SET NULL
        Schema::table('project_field_values', function (Blueprint $table) {
            $table->dropForeign(['updated_by_user_id']);
            $table->foreign('updated_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // system_settings.updated_by_user_id — nullable → SET NULL
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropForeign(['updated_by_user_id']);
            $table->foreign('updated_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // template_versions.uploaded_by_user_id — nullable → SET NULL
        Schema::table('template_versions', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by_user_id']);
            $table->foreign('uploaded_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // template_control_mappings.mapped_by_user_id — nullable → SET NULL
        Schema::table('template_control_mappings', function (Blueprint $table) {
            $table->dropForeign(['mapped_by_user_id']);
            $table->foreign('mapped_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // analysis_runs.created_by_user_id — nullable → SET NULL
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // exports.created_by_user_id — nullable → SET NULL
        Schema::table('exports', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // audit_events.actor_user_id — nullable → SET NULL
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropForeign(['actor_user_id']);
            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Restore all FKs to default RESTRICT (no ON DELETE action). DDL auto-commits.
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->foreign('owner_user_id')->references('id')->on('users');
        });

        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by_user_id']);
            $table->foreign('uploaded_by_user_id')->references('id')->on('users');
        });

        Schema::table('project_field_values', function (Blueprint $table) {
            $table->dropForeign(['updated_by_user_id']);
            $table->foreign('updated_by_user_id')->references('id')->on('users');
        });

        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropForeign(['updated_by_user_id']);
            $table->foreign('updated_by_user_id')->references('id')->on('users');
        });

        Schema::table('template_versions', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by_user_id']);
            $table->foreign('uploaded_by_user_id')->references('id')->on('users');
        });

        Schema::table('template_control_mappings', function (Blueprint $table) {
            $table->dropForeign(['mapped_by_user_id']);
            $table->foreign('mapped_by_user_id')->references('id')->on('users');
        });

        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });

        Schema::table('exports', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropForeign(['actor_user_id']);
            $table->foreign('actor_user_id')->references('id')->on('users');
        });
    }
};
