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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_user_id')->constrained('users');

            $table->string('name');
            $table->string('status', 32)->default('draft')->index();

            $table->unsignedInteger('required_total_count')->default(0);
            $table->unsignedInteger('required_completed_count')->default(0);

            $table->timestamp('last_analyzed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
