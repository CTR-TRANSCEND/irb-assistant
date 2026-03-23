<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class TemplateVersion extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'sha256',
        'storage_disk',
        'storage_path',
        'is_active',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function controls(): HasMany
    {
        return $this->hasMany(TemplateControl::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(TemplateControlMapping::class);
    }
}
