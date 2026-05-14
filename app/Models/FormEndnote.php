<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FormEndnote — footnote/endnote text for a FormDefinition.
 *
 * Read-only at runtime; only the seed migration writes to this table.
 */
class FormEndnote extends Model
{
    protected $table = 'form_endnote';

    protected $fillable = [
        'form_definition_id',
        'endnote_key',
        'endnote_text',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function formDefinition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class);
    }
}
