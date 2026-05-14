<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateControlMapping extends Model
{
    protected $fillable = [
        'template_version_id',
        'template_control_id',
        'field_definition_id',
        'mapped_by_user_id',
    ];

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    public function control(): BelongsTo
    {
        return $this->belongsTo(TemplateControl::class, 'template_control_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FieldDefinition::class, 'field_definition_id');
    }

    public function mappedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by_user_id');
    }
}
