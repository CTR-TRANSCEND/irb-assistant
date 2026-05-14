<?php

declare(strict_types=1);

/**
 * LLM-related configuration values.
 *
 * M6: Expose IRB_ALLOW_LLM_LOOPBACK through the config layer so that
 * config:cache does not break production deployments. env() reads return
 * null after php artisan config:cache; config() reads always work.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Allow LLM loopback addresses (REQ-LLM-009)
    |--------------------------------------------------------------------------
    |
    | When true, BaseUrlValidator permits loopback addresses (127.x.x.x, ::1,
    | 0.0.0.0/8) in base_url fields. Intended ONLY for local development.
    | NEVER enable in production.
    |
    */
    'allow_loopback' => env('IRB_ALLOW_LLM_LOOPBACK', false),
];
