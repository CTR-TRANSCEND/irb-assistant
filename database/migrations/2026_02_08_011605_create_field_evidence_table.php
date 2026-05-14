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
        Schema::create('field_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_field_value_id')->constrained('project_field_values');
            $table->foreignId('document_chunk_id')->constrained('document_chunks');

            $table->text('excerpt_text');
            $table->string('excerpt_sha256', 64)->index();
            $table->unsignedInteger('start_offset')->nullable();
            $table->unsignedInteger('end_offset')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_evidence');
    }
};
