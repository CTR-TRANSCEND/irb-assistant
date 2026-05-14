<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FormDefinition — static form metadata.
 *
 * Read-only at runtime; only the seed migration writes to this table.
 * UNIQUE on form_code (business key) per CANONICAL_SCHEMA.sql D-1.
 */
class FormDefinition extends Model
{
    protected $table = 'form_definition';

    protected $fillable = [
        'form_code',
        'version',
        'title',
        'institution',
        'form_kind',
        'description',
        'instructions',
        'is_fillable',
        'is_retained',
        'schema_json_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'instructions' => 'array',
            'is_fillable' => 'boolean',
            'is_retained' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FormSection::class);
    }

    public function endnotes(): HasMany
    {
        return $this->hasMany(FormEndnote::class);
    }

    /**
     * Section groups are keyed by form_code (VARCHAR FK per RATIONALE D-4).
     */
    public function sectionGroups(): HasMany
    {
        return $this->hasMany(FormSectionGroup::class, 'form_code', 'form_code');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
