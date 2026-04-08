<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AnalysisRun extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'llm_provider_id',
        'created_by_user_id',
        'status',
        'prompt_version',
        'started_at',
        'finished_at',
        'request_payload',
        'request_payload_enc',
        'response_payload',
        'response_payload_enc',
        'payload_enc_key_id',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
