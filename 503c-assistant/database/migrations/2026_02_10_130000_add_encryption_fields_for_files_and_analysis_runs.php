<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('storage_path');
            $table->string('encryption_key_id', 128)->nullable()->after('is_encrypted');
        });

        Schema::table('exports', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('storage_path');
            $table->string('encryption_key_id', 128)->nullable()->after('is_encrypted');
        });

        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->longText('request_payload_enc')->nullable()->after('request_payload');
            $table->longText('response_payload_enc')->nullable()->after('response_payload');
            $table->string('payload_enc_key_id', 128)->nullable()->after('response_payload_enc');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->dropColumn(['request_payload_enc', 'response_payload_enc', 'payload_enc_key_id']);
        });

        Schema::table('exports', function (Blueprint $table) {
            $table->dropColumn(['is_encrypted', 'encryption_key_id']);
        });

        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropColumn(['is_encrypted', 'encryption_key_id']);
        });
    }
};
