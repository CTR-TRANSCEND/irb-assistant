<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_run_id',
        'project_id',
        'field_definition_id',
        'suggested_value',
        'suggestion_source',
        'final_value',
        'status',
        'confidence',
        'suggested_at',
        'confirmed_at',
        'updated_by_user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'suggested_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * REQ-IRB-GUIDE-019 / REQ-IRB-GUIDE-020: helper used by Blade components to conditionally
     * render amber styling + Accept-draft button.
     */
    public function isAiDraft(): bool
    {
        return $this->suggestion_source === 'ai_draft';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FieldDefinition::class, 'field_definition_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(FieldEvidence::class);
    }
}
