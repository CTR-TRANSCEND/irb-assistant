<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 PR-1: creates submission_field_evidence table.
 *
 * Each row records one evidence chunk supporting an AI-suggested
 * submission_answer row per REQ-IRB-FORMSV2-052.
 *
 * SPEC-IRB-FORMSV2-004 §D migration 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE submission_field_evidence (
                id                  BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
                submission_id       BIGINT UNSIGNED     NOT NULL,
                question_key        VARCHAR(64)         NOT NULL,
                evidence_index      SMALLINT UNSIGNED   NOT NULL COMMENT 'zero-based; allows multiple chunks per question',
                chunk_id            BIGINT UNSIGNED     NULL COMMENT 'FK to document_chunks.id (nullable if chunk deleted)',
                chunk_offset_start  INT UNSIGNED        NULL,
                chunk_offset_end    INT UNSIGNED        NULL,
                quote_text          TEXT                NULL COMMENT 'verbatim excerpt supporting the suggestion',
                confidence_score    DECIMAL(3,2)        NULL COMMENT '0.00..1.00',
                created_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_evidence (submission_id, question_key, evidence_index),
                KEY ix_evidence_submission (submission_id),
                KEY ix_evidence_chunk (chunk_id),
                CONSTRAINT fk_sfe_submission FOREIGN KEY (submission_id)
                    REFERENCES submission(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS submission_field_evidence');
    }
};
