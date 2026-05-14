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
        Schema::create('template_control_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_version_id')->constrained('template_versions');
            $table->foreignId('template_control_id')->constrained('template_controls');
            $table->foreignId('field_definition_id')->constrained('field_definitions');
            $table->foreignId('mapped_by_user_id')->nullable()->constrained('users');

            $table->timestamps();

            $table->unique(['template_control_id'], 'tcm_ctrl_uq');
            $table->unique(['template_version_id', 'field_definition_id'], 'tcm_tpl_field_uq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_control_mappings');
    }
};
