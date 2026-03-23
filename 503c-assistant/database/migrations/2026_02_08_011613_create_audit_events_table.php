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
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();

            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users');

            $table->string('event_type', 64)->index();
            $table->string('entity_type', 64)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->uuid('entity_uuid')->nullable()->index();

            $table->foreignId('project_id')->nullable()->constrained('projects');

            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('request_id', 64)->nullable()->index();

            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
