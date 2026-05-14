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
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('project_id')->constrained('projects');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users');

            $table->string('original_filename');
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path');

            $table->string('sha256', 64)->nullable()->index();
            $table->string('mime_type', 255);
            $table->string('file_ext', 16)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('kind', 16)->index();

            $table->string('extraction_status', 32)->default('pending')->index();
            $table->timestamp('extracted_at')->nullable();
            $table->text('extraction_error')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};
