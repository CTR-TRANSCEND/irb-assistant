<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| IRB-assistant runtime configuration
|--------------------------------------------------------------------------
|
| Centralizes all IRB_* env vars behind config() reads.
|
| Why this exists: Laravel's `env()` helper returns null at runtime when
| `php artisan config:cache` has been run, because the cached config file
| is the only source of truth in that mode and `.env` is no longer parsed.
| Reading env vars *only* through this file means the cached config bakes
| the values in, and `config('irb.*')` works regardless of cache state.
|
| Never call env() outside config files. See SECURITY_CHECKLIST.md for the
| 2026-05-10 incident that established this rule.
|
*/

return [

    /*
    | File at-rest encryption keyring (libsodium XChaCha20-Poly1305).
    | Format: id1:base64key1,id2:base64key2 (no spaces).
    */
    'file_encryption_keys' => env('IRB_FILE_ENCRYPTION_KEYS', ''),
    'file_encryption_active_key_id' => env('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID', ''),

    /*
    | DB payload encryption key id (used by ProjectAnalysisService).
    */
    'db_payload_enc_key_id' => env('IRB_DB_PAYLOAD_ENC_KEY_ID', ''),

    /*
    | Analysis behavior knobs.
    */
    'max_chunks_sent' => (int) env('IRB_MAX_CHUNKS_SENT', 40),
    'max_chunk_chars_sent' => (int) env('IRB_MAX_CHUNK_CHARS_SENT', 1200),
    'analysis_batch_size' => (int) env('IRB_ANALYSIS_BATCH_SIZE', 20),

    /*
    | SPEC-IRB-GUIDE-001 M1: AI drafting cap per analysis run.
    */
    'drafting_max_per_run' => (int) env('IRB_DRAFTING_MAX_PER_RUN', 20),

    /*
    | PDF extraction limits (DocumentExtractionService).
    */
    'pdftotext_timeout_seconds' => (int) env('IRB_PDFTOTEXT_TIMEOUT_SECONDS', 20),
    'pdf_max_pages' => (int) env('IRB_PDF_MAX_PAGES', 200),
    'pdf_max_text_bytes' => (int) env('IRB_PDF_MAX_TEXT_BYTES', 5_000_000),
    'pdf_parser_memory_mb' => (int) env('IRB_PDF_PARSER_MEMORY_MB', 256),

    /*
    | Upload + retention defaults (overridable via SystemSettings UI).
    */
    'max_upload_bytes' => (int) env('IRB_MAX_UPLOAD_BYTES', 104857600),
    'retention_days' => (int) env('IRB_RETENTION_DAYS', 14),

    /*
    | External LLM gate (overridable via SystemSettings UI).
    */
    'allow_external_llm' => filter_var(
        (string) env('IRB_ALLOW_EXTERNAL_LLM', 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | LlmChatService HTTP timeout in seconds. Sized for 20B reasoning models
    | (gpt-oss-20b, DeepSeek-R1) on multi-field batches — chain-of-thought +
    | JSON emission can run 2-4 minutes on a slow GPU.
    |
    | Apache vhost `ProxyTimeout` and PHP-FPM `request_terminate_timeout`
    | MUST be ≥ this value, or the user will hit a 504 Gateway Timeout
    | even though the chat call would have completed.
    */
    'llm_chat_timeout_seconds' => (int) env('IRB_LLM_CHAT_TIMEOUT_SECONDS', 600),

];
