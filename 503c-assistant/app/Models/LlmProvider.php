<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmProvider extends Model
{
    protected $hidden = [
        'api_key',
    ];

    protected $fillable = [
        'name',
        'provider_type',
        'base_url',
        'model',
        'api_key',
        'request_params',
        'is_enabled',
        'is_default',
        'is_external',
        'last_tested_at',
        'last_test_ok',
        'last_test_error',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'request_params' => 'array',
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'is_external' => 'boolean',
            'last_tested_at' => 'datetime',
            'last_test_ok' => 'boolean',
        ];
    }
}
