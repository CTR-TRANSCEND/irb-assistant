<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SubmissionAnswer — EAV pattern for form answers.
 *
 * Four nullable value columns serve different question_type semantics:
 *   - text_value: textarea / text_short / text_long / explain fields
 *   - option_value: radio_single (the chosen option_value string)
 *   - bool_value: single checkboxes (na, confirm, criterion)
 *   - json_value: checkbox_multi (array of values) or nested bundles
 *
 * suggestion_source preserves the assistance_mode contract from SPEC-IRB-GUIDE-001
 * per REQ-IRB-FORMSV2-054.
 */
class SubmissionAnswer extends Model
{
    use HasFactory;

    protected $table = 'submission_answer';

    protected $fillable = [
        'submission_id',
        'question_key',
        'text_value',
        'option_value',
        'bool_value',
        'json_value',
        'suggestion_source',
    ];

    protected function casts(): array
    {
        return [
            'json_value' => 'array',
            'bool_value' => 'boolean',
            'answered_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
