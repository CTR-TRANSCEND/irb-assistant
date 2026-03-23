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
        Schema::create('exports', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects');
            $table->foreignId('template_version_id')->constrained('template_versions');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');

            $table->string('status', 32)->default('queued')->index();
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
