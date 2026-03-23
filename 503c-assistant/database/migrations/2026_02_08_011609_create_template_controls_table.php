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
        Schema::create('template_controls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_version_id')->constrained('template_versions');
            $table->string('part', 32)->default('document')->index();
            $table->unsignedInteger('control_index')->index();

            $table->text('context_before')->nullable();
            $table->text('context_after')->nullable();
            $table->text('placeholder_text')->nullable();

            $table->string('signature_sha256', 64)->index();

            $table->timestamps();

            $table->unique(['template_version_id', 'part', 'control_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_controls');
    }
};
