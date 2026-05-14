<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-IRB-GUIDE-002: progress_message now carries plain-English explanations
 * (e.g. "Asking the LLM about field group 6 of 8 (fields 101–120 of 159).
 * The model reads your uploaded documents and proposes evidence-grounded
 * answers...") that easily exceed varchar(255). Widen to TEXT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->text('progress_message')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->string('progress_message', 255)->nullable()->change();
        });
    }
};
