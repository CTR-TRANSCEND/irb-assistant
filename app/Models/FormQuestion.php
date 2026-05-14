<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FormQuestion — a question within a FormSection.
 *
 * Self-referencing: parent_question_id for subfields, criteria, sub-scenarios.
 * Read-only at runtime; only the seed migration writes to this table.
 */
class FormQuestion extends Model
{
    protected $table = 'form_question';

    protected $fillable = [
        'form_section_id',
        'parent_question_id',
        'question_key',
        'number_label',
        'label',
        'instruction',
        'note',
        'question_type',
        'is_required',
        'display_order',
        'conditional_logic',
        'skip_in_multi_site',
        'triggers_sub37',
        'footnote_refs',
        'external_ref_title',
        'external_ref_url',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'skip_in_multi_site' => 'boolean',
            'conditional_logic' => 'array',
            'triggers_sub37' => 'array',
            'footnote_refs' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FormSection::class, 'form_section_id');
    }

    /**
     * Parent question (for nested subfields / criteria / scenarios).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FormQuestion::class, 'parent_question_id');
    }

    /**
     * Child questions (subfields, criteria, scenarios).
     */
    public function children(): HasMany
    {
        return $this->hasMany(FormQuestion::class, 'parent_question_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(FormQuestionOption::class);
    }
}
