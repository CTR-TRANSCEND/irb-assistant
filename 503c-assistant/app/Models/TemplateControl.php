<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class TemplateControl extends Model
{
    protected $fillable = [
        'template_version_id',
        'part',
        'control_index',
        'context_before',
        'context_after',
        'placeholder_text',
        'signature_sha256',
    ];

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }
}
