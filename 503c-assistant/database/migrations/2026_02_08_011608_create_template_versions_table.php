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
        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('sha256', 64)->unique();
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path');

            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};
