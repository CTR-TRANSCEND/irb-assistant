<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FormQuestionOption — an option within a radio/checkbox question.
 *
 * Read-only at runtime; only the seed migration writes to this table.
 */
class FormQuestionOption extends Model
{
    protected $table = 'form_question_option';

    protected $fillable = [
        'form_question_id',
        'option_value',
        'option_label',
        'description',
        'display_order',
        'action_type',
        'action_target',
        'action_text',
        'footnote_refs',
        'requires_textarea',
        'conditional_textarea_label',
    ];

    protected function casts(): array
    {
        return [
            'footnote_refs' => 'array',
            'requires_textarea' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(FormQuestion::class, 'form_question_id');
    }
}
