<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FormSectionGroup — navigation grouping for form sections (HRP-503).
 *
 * RATIONALE D-4: FK is via form_code (VARCHAR), not form_definition.id.
 * The FK references form_definition(form_code) via a UNIQUE KEY.
 * section_ids_json stores an array of section_code strings.
 *
 * Read-only at runtime; only the seed migration writes to this table.
 */
class FormSectionGroup extends Model
{
    protected $table = 'form_section_group';

    protected $fillable = [
        'form_code',
        'display_order',
        'label',
        'section_ids_json',
    ];

    protected function casts(): array
    {
        return [
            'section_ids_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Retrieve the parent FormDefinition via form_code lookup.
     * Cannot use standard BelongsTo since the FK is a VARCHAR business key.
     */
    public function formDefinition(): ?FormDefinition
    {
        return FormDefinition::where('form_code', $this->form_code)->first();
    }
}
