<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class FieldDefinition extends Model
{
    use HasFactory;
    protected $fillable = [
        'key',
        'label',
        'section',
        'sort_order',
        'is_required',
        'input_type',
        'question_text',
        'help_text',
        'validation_rules',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    public function projectValues(): HasMany
    {
        return $this->hasMany(ProjectFieldValue::class);
    }
}
