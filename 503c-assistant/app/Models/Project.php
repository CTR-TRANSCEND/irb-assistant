<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'owner_user_id',
        'name',
        'status',
        'llm_provider_id',
        'required_total_count',
        'required_completed_count',
        'last_analyzed_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'last_analyzed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ProjectFieldValue::class);
    }

    public function analysisRuns(): HasMany
    {
        return $this->hasMany(AnalysisRun::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(Export::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }
}
