<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_field_values', function (Blueprint $table) {
            $table->foreignId('analysis_run_id')->nullable()->after('project_id')->constrained('analysis_runs');
        });

        Schema::table('field_evidence', function (Blueprint $table) {
            $table->foreignId('analysis_run_id')->nullable()->after('project_field_value_id')->constrained('analysis_runs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('field_evidence', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analysis_run_id');
        });

        Schema::table('project_field_values', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analysis_run_id');
        });
    }
};
