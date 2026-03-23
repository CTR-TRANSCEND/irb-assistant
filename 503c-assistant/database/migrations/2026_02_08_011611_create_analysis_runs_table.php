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
        Schema::create('analysis_runs', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects');
            $table->foreignId('llm_provider_id')->nullable()->constrained('llm_providers');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');

            $table->string('status', 32)->default('queued')->index();
            $table->string('prompt_version', 64)->default('v1');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_runs');
    }
};
