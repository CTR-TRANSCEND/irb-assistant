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
        Schema::create('project_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects');
            $table->foreignId('field_definition_id')->constrained('field_definitions');

            $table->longText('suggested_value')->nullable();
            $table->longText('final_value')->nullable();

            $table->string('status', 32)->default('missing')->index();
            $table->decimal('confidence', 6, 5)->nullable();

            $table->timestamp('suggested_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users');

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'field_definition_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_field_values');
    }
};
