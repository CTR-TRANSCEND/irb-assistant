<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FormSection — a section within a FormDefinition.
 *
 * Read-only at runtime; only the seed migration writes to this table.
 */
class FormSection extends Model
{
    protected $table = 'form_section';

    protected $fillable = [
        'form_definition_id',
        'section_code',
        'title',
        'description',
        'display_order',
        'conditional_logic',
        'multi_site_note',
        'section_end_marker',
        'external_ref_title',
        'external_ref_url',
    ];

    protected function casts(): array
    {
        return [
            'conditional_logic' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function formDefinition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FormQuestion::class);
    }
}
