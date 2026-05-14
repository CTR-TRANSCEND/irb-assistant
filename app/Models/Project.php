<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\TemplateService;
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
        'application_title',
        'pi_name',
        'project_summary',
        'status',
        'assistance_mode',
        'form_code',
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

    /**
     * Outstanding #49 — multi-form rollout.
     * Returns the template name for this project's form_code.
     * Falls back to 'HRP-503c' for unrecognised form codes.
     */
    public function templateName(): string
    {
        return TemplateService::FORM_TEMPLATES[$this->form_code]['name'] ?? 'HRP-503c';
    }

    /**
     * Outstanding #49 — multi-form rollout.
     * Returns the field_definition key prefix for this project's form_code (e.g. 'hrp398.').
     * The trailing dot ensures hrp503.% does NOT match hrp503c.* keys.
     */
    public function fieldDefinitionKeyPrefix(): string
    {
        return $this->form_code.'.';
    }

    /**
     * REQ-IRB-GUIDE-007: helper used by ProjectAnalysisService to gate the drafting iteration.
     */
    public function isAssistantMode(): bool
    {
        return $this->assistance_mode === 'assistant';
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
