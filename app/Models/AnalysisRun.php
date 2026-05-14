<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisRun extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        // Phase 4 PR-1 added the submission_id FK column but never registered
        // it for mass-assignment. Without this entry, SubmissionAnalysisController
        // silently writes NULL → status endpoint cannot locate the run →
        // _progress_modal never observes progress. Outstanding #72 fallout.
        'submission_id',
        'llm_provider_id',
        'created_by_user_id',
        'status',
        'progress_step',
        'progress_current',
        'progress_total',
        'progress_message',
        'last_heartbeat_at',
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
            'last_heartbeat_at' => 'datetime',
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
