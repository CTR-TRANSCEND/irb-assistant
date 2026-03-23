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
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_document_id')->constrained('project_documents');
            $table->unsignedInteger('chunk_index');

            $table->unsignedInteger('page_number')->nullable();
            $table->string('source_locator', 255)->nullable();
            $table->string('heading', 512)->nullable();

            $table->longText('text');
            $table->string('text_sha256', 64)->index();

            $table->unsignedInteger('start_offset')->nullable();
            $table->unsignedInteger('end_offset')->nullable();

            $table->timestamps();

            $table->unique(['project_document_id', 'chunk_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
