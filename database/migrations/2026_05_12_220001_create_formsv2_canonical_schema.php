<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical schema migration — Phase 3 (SPEC-IRB-FORMSV2-003).
 *
 * Cites umbrella REQ-IRB-FORMSV2-021, 011, 011a, 013, 014, 014a, 019, 029b.
 *
 * Creates 11 tables in parent-before-child dependency order:
 *   1.  form_definition        (static form metadata)
 *   2.  form_section           (FK → form_definition)
 *   3.  form_question          (FK → form_section, self-FK parent_question_id)
 *   4.  form_question_option   (FK → form_question)
 *   5.  form_endnote           (FK → form_definition)
 *   6.  form_section_group     (FK → form_definition.form_code via UNIQUE KEY)
 *   7.  studies                (NEW — REQ-IRB-FORMSV2-011; FK → users)
 *   8.  submission             (FK → studies + form_definition + users; widened ENUM per REQ-014a)
 *   9.  submission_answer      (FK → submission)
 *   10. submission_upload      (FK → submission)
 *   11. worksheet_assist_state (FK → submission)
 *
 * down() is REAL per REQ-IRB-FORMSV2-029b — DROPs tables in reverse dep order.
 *
 * Raw CREATE TABLE via DB::statement() is used for ENUM columns to ensure
 * MariaDB DDL fidelity (ENUM widening matters for REQ-014a).
 */
return new class extends Migration
{
    /**
     * @MX:ANCHOR: [AUTO] Creates all 11 FormsV2 tables; called by migrate:fresh and production deploy.
     *
     * @MX:REASON: fan_in >= 3 — called by migrate:fresh, production deploy script, and tests/Feature/FormsV2/SchemaMigrationTest.php
     *
     * @MX:SPEC: REQ-IRB-FORMSV2-021, REQ-IRB-FORMSV2-011, REQ-IRB-FORMSV2-013, REQ-IRB-FORMSV2-014a, REQ-IRB-FORMSV2-029b
     */
    public function up(): void
    {
        // ── 1. form_definition ──────────────────────────────────────────────────
        // Matches CANONICAL_SCHEMA.sql §1 verbatim.
        // UNIQUE KEY on (form_code, version) per REQ-IRB-FORMSV2-027 idempotency.
        DB::statement("
            CREATE TABLE form_definition (
                id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                form_code        VARCHAR(32)     NOT NULL COMMENT 'e.g., HRP-503c, HRP-503, HRP-398',
                version          VARCHAR(32)     NOT NULL COMMENT 'e.g., 07.01.2024',
                title            VARCHAR(255)    NOT NULL,
                institution      VARCHAR(128)    NULL,
                form_kind        ENUM('application','guidance_worksheet') NOT NULL DEFAULT 'application',
                description      TEXT            NULL,
                instructions     JSON            NULL,
                is_fillable      TINYINT(1)      NOT NULL DEFAULT 1,
                is_retained      TINYINT(1)      NOT NULL DEFAULT 1,
                schema_json_path VARCHAR(255)    NULL COMMENT 'Relative path to JSON schema file',
                is_active        TINYINT(1)      NOT NULL DEFAULT 1,
                created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_form_version (form_code, version),
                UNIQUE KEY uk_form_code (form_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 2. form_section ─────────────────────────────────────────────────────
        DB::statement("
            CREATE TABLE form_section (
                id                 INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                form_definition_id INT UNSIGNED    NOT NULL,
                section_code       VARCHAR(16)     NOT NULL COMMENT 'e.g., 1.0, 2.0, 3.0',
                title              VARCHAR(255)    NOT NULL,
                description        TEXT            NULL,
                display_order      SMALLINT UNSIGNED NOT NULL,
                conditional_logic  JSON            NULL,
                multi_site_note    TEXT            NULL,
                section_end_marker VARCHAR(255)    NULL,
                external_ref_title VARCHAR(255)    NULL,
                external_ref_url   TEXT            NULL,
                created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_section_code_per_form (form_definition_id, section_code),
                KEY ix_section_form (form_definition_id, display_order),
                CONSTRAINT fk_section_form FOREIGN KEY (form_definition_id)
                    REFERENCES form_definition(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 3. form_question ────────────────────────────────────────────────────
        // ENUM matches CANONICAL_SCHEMA.sql §3 exactly (including 'checkbox' per D-5).
        DB::statement("
            CREATE TABLE form_question (
                id                 INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                form_section_id    INT UNSIGNED    NOT NULL,
                parent_question_id INT UNSIGNED    NULL COMMENT 'For nested subfields, criteria, sub-scenarios',
                question_key       VARCHAR(64)     NOT NULL COMMENT 'e.g., q2_1, q3_6_scenario_a2',
                number_label       VARCHAR(64)     NULL COMMENT 'e.g., 2.1, 3.6, A(2), B(1), A(6) - identifiable',
                label              TEXT            NOT NULL,
                instruction        TEXT            NULL,
                note               TEXT            NULL,
                question_type      ENUM(
                                     'text_short',
                                     'text_long',
                                     'textarea',
                                     'textarea_with_na',
                                     'textarea_with_multiple_na',
                                     'radio_single',
                                     'radio_with_conditional_text',
                                     'checkbox',
                                     'checkbox_single',
                                     'checkbox_multi',
                                     'checkbox_multi_with_subfields',
                                     'scenario_group',
                                     'exception_group',
                                     'na_or_explain',
                                     'na_or_criteria_checklist',
                                     'na_or_multi_checkbox',
                                     'na_or_confirm',
                                     'criterion_checkbox',
                                     'group_label',
                                     'checkbox_multi_with_section_triggers',
                                     'radio_with_nested_options',
                                     'numbered_options_with_criteria',
                                     'textarea_with_na_and_followup',
                                     'textarea_with_alternative_radio',
                                     'checkbox_with_optional_textarea',
                                     'checkbox_with_textarea'
                                   ) NOT NULL,
                is_required        TINYINT(1)      NOT NULL DEFAULT 0,
                display_order      SMALLINT UNSIGNED NOT NULL,
                conditional_logic  JSON            NULL,
                skip_in_multi_site TINYINT(1)      NOT NULL DEFAULT 0,
                triggers_sub37     JSON            NULL,
                footnote_refs      JSON            NULL,
                external_ref_title VARCHAR(255)    NULL,
                external_ref_url   TEXT            NULL,
                created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_question_key_per_section (form_section_id, question_key),
                KEY ix_question_section (form_section_id, display_order),
                KEY ix_question_parent (parent_question_id),
                CONSTRAINT fk_question_section FOREIGN KEY (form_section_id)
                    REFERENCES form_section(id) ON DELETE CASCADE,
                CONSTRAINT fk_question_parent FOREIGN KEY (parent_question_id)
                    REFERENCES form_question(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 4. form_question_option ─────────────────────────────────────────────
        DB::statement("
            CREATE TABLE form_question_option (
                id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                form_question_id        INT UNSIGNED    NOT NULL,
                option_value            VARCHAR(64)     NOT NULL COMMENT 'machine value, e.g., yes, no, surveys, na',
                option_label            TEXT            NOT NULL,
                description             TEXT            NULL,
                display_order           SMALLINT UNSIGNED NOT NULL,
                action_type             ENUM('none','stop_and_submit','stop_engaged',
                                             'stop_not_engaged','stop_or_skip_to_3.0',
                                             'skip_to','continue','triggers_section',
                                             'reveal_subfields') NOT NULL DEFAULT 'none',
                action_target           VARCHAR(64)     NULL,
                action_text             TEXT            NULL,
                footnote_refs           JSON            NULL,
                requires_textarea       TINYINT(1)      NOT NULL DEFAULT 0,
                conditional_textarea_label TEXT         NULL,
                created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ix_option_question (form_question_id, display_order),
                CONSTRAINT fk_option_question FOREIGN KEY (form_question_id)
                    REFERENCES form_question(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 5. form_endnote ─────────────────────────────────────────────────────
        DB::statement("
            CREATE TABLE form_endnote (
                id                 INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                form_definition_id INT UNSIGNED    NOT NULL,
                endnote_key        VARCHAR(16)     NOT NULL COMMENT 'e.g., e2, e3',
                endnote_text       TEXT            NOT NULL,
                created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_endnote (form_definition_id, endnote_key),
                CONSTRAINT fk_endnote_form FOREIGN KEY (form_definition_id)
                    REFERENCES form_definition(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 6. form_section_group ───────────────────────────────────────────────
        // RATIONALE D-4: FK via form_definition.form_code (VARCHAR UNIQUE KEY).
        DB::statement("
            CREATE TABLE form_section_group (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                form_code       VARCHAR(32)     NOT NULL COMMENT 'FK to form_definition.form_code',
                display_order   INT             NOT NULL,
                label           TEXT            NOT NULL,
                section_ids_json JSON           NOT NULL COMMENT 'Array of section_code strings',
                created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_form (form_code),
                CONSTRAINT fk_fsg_form FOREIGN KEY (form_code)
                    REFERENCES form_definition(form_code) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 7. studies (NEW — REQ-IRB-FORMSV2-011) ─────────────────────────────
        // Note: user_id is BIGINT UNSIGNED to match Laravel's default users.id (bigIncrements).
        DB::statement('
            CREATE TABLE studies (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid                VARCHAR(36)     NOT NULL,
                user_id             BIGINT UNSIGNED NOT NULL,
                application_title   VARCHAR(500)    NULL,
                pi_name             VARCHAR(255)    NULL,
                project_summary     TEXT            NULL,
                oversight           TEXT            NULL,
                nickname            VARCHAR(255)    NULL,
                created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_study_uuid (uuid),
                KEY ix_study_user (user_id),
                CONSTRAINT fk_study_user FOREIGN KEY (user_id)
                    REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // ── 8. submission (REQ-IRB-FORMSV2-014a: 7-value ENUM + study_id FK) ───
        // UNIQUE (study_id, form_definition_id) per REQ-IRB-FORMSV2-013.
        // @MX:NOTE: [AUTO] submission.status ENUM widened to 7 values vs CANONICAL_SCHEMA.sql 6
        //   because REQ-IRB-FORMSV2-014a adds 'tracking_only' (reserved for HRP-398 rows).
        DB::statement("
            CREATE TABLE submission (
                id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                study_id               BIGINT UNSIGNED NOT NULL COMMENT 'FK to studies (REQ-IRB-FORMSV2-011)',
                form_definition_id     INT UNSIGNED    NOT NULL,
                user_id                BIGINT UNSIGNED NOT NULL COMMENT 'BIGINT to match users.id (bigIncrements)',
                status                 ENUM('draft','submitted','under_review',
                                            'approved','rejected','withdrawn',
                                            'tracking_only')
                                       NOT NULL DEFAULT 'draft'
                                       COMMENT 'tracking_only: reserved for HRP-398 rows; terminal state per REQ-IRB-FORMSV2-014a',
                assistance_mode        VARCHAR(32)     NULL,
                title                  TEXT            NULL,
                principal_investigator VARCHAR(255)    NULL,
                oversight              TEXT            NULL,
                routing_outcome        VARCHAR(128)    NULL,
                routing_outcome_at     VARCHAR(64)     NULL,
                submitted_at           DATETIME        NULL,
                created_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_study_form (study_id, form_definition_id)
                    COMMENT 'REQ-IRB-FORMSV2-013: one submission per form per study',
                KEY ix_submission_user (user_id, status),
                KEY ix_submission_form (form_definition_id, status),
                CONSTRAINT fk_submission_study FOREIGN KEY (study_id)
                    REFERENCES studies(id) ON DELETE CASCADE,
                CONSTRAINT fk_submission_form FOREIGN KEY (form_definition_id)
                    REFERENCES form_definition(id),
                CONSTRAINT fk_submission_user FOREIGN KEY (user_id)
                    REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 9. submission_answer ────────────────────────────────────────────────
        // `suggestion_source` added per REQ-IRB-FORMSV2-054 (assistance_mode contract).
        DB::statement("
            CREATE TABLE submission_answer (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                submission_id    BIGINT UNSIGNED NOT NULL,
                question_key     VARCHAR(64)     NOT NULL,
                text_value       TEXT            NULL,
                option_value     VARCHAR(64)     NULL,
                bool_value       TINYINT(1)      NULL,
                json_value       JSON            NULL,
                suggestion_source VARCHAR(16)    NULL COMMENT 'e.g., assistant, user; REQ-IRB-FORMSV2-054',
                answered_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_answer (submission_id, question_key),
                KEY ix_answer_submission (submission_id),
                CONSTRAINT fk_answer_submission FOREIGN KEY (submission_id)
                    REFERENCES submission(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 10. submission_upload ────────────────────────────────────────────────
        DB::statement('
            CREATE TABLE submission_upload (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                submission_id    BIGINT UNSIGNED NOT NULL,
                question_key     VARCHAR(64)     NULL,
                original_filename VARCHAR(255)   NOT NULL,
                storage_path     VARCHAR(512)    NOT NULL,
                mime_type        VARCHAR(128)    NULL,
                file_size_bytes  BIGINT UNSIGNED NULL,
                uploaded_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ix_upload_submission (submission_id),
                CONSTRAINT fk_upload_submission FOREIGN KEY (submission_id)
                    REFERENCES submission(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // ── 11. worksheet_assist_state ───────────────────────────────────────────
        // RATIONALE D-3: FK to submission(id), not submission(submission_id).
        // Per REQ-IRB-FORMSV2-020: submission_id must resolve to an HRP-503 submission.
        // Enforcement is at the model layer (WorksheetAssistState::creating event).
        DB::statement("
            CREATE TABLE worksheet_assist_state (
                id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                submission_id      BIGINT UNSIGNED NOT NULL,
                worksheet_form_id  VARCHAR(32)     NOT NULL COMMENT 'e.g., HRP-398',
                item_id            VARCHAR(128)    NOT NULL,
                status             ENUM('not_started','addressed','needs_work','not_applicable')
                                   NOT NULL DEFAULT 'not_started',
                notes              TEXT            NULL,
                reviewed_at        TIMESTAMP       NULL,
                reviewed_by_user   BIGINT UNSIGNED NULL,
                created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_submission_worksheet_item (submission_id, worksheet_form_id, item_id),
                KEY idx_status (status),
                CONSTRAINT fk_wsa_submission FOREIGN KEY (submission_id)
                    REFERENCES submission(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migration — DROP TABLE in reverse dependency order.
     * Per REQ-IRB-FORMSV2-029b.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'worksheet_assist_state',
            'submission_upload',
            'submission_answer',
            'submission',
            'studies',
            'form_section_group',
            'form_endnote',
            'form_question_option',
            'form_question',
            'form_section',
            'form_definition',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }
};
