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
        Schema::create('field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique();

            $table->string('label');
            $table->string('section', 128)->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_required')->default(false)->index();
            $table->string('input_type', 32)->default('text');

            $table->text('question_text')->nullable();
            $table->text('help_text')->nullable();
            $table->string('validation_rules')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_definitions');
    }
};
