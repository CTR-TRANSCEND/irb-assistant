<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'template_version_id',
        'created_by_user_id',
        'status',
        'storage_disk',
        'storage_path',
        'is_encrypted',
        'encryption_key_id',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
