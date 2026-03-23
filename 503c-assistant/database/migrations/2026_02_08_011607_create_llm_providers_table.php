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
        Schema::create('llm_providers', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('provider_type', 32)->index();

            $table->string('base_url')->nullable();
            $table->string('model')->nullable();
            $table->text('api_key')->nullable();
            $table->json('request_params')->nullable();

            $table->boolean('is_enabled')->default(false)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_external')->default(true)->index();

            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->text('last_test_error')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_providers');
    }
};
