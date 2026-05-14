<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-IRB-METADATA-001 — Project metadata captured at creation.
 *
 * Adds three nullable columns to projects:
 *   - application_title : the formal title of the IRB application (long form, distinct from short nickname `name`)
 *   - pi_name           : Principal Investigator full name (autofilled from authenticated user)
 *   - project_summary   : 2-3 sentence background description fed to the LLM prompt
 *
 * Pre-existing projects keep NULLs; the create form populates these fields
 * for new projects. ProjectAnalysisService::buildPrompt() reads these and
 * injects them as `project_context` into the LLM payload so the model has
 * study background when proposing field values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('application_title', 500)->nullable()->after('name');
            $table->string('pi_name', 255)->nullable()->after('application_title');
            $table->text('project_summary')->nullable()->after('pi_name');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['application_title', 'pi_name', 'project_summary']);
        });
    }
};
