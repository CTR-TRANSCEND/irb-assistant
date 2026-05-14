<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WorksheetAssistState — optional progress tracking for the HRP-398 worksheet.
 *
 * CRITICAL (REQ-IRB-FORMSV2-020): The submission_id MUST resolve to a Submission
 * whose form_definition has form_code='HRP-503'. This is the HRP-503-context
 * worksheet assist state — NOT an HRP-398 or HRP-503c submission.
 *
 * This invariant is enforced in the 'creating' event below.
 *
 * @MX:WARN: [AUTO] creating event enforces that submission's form is HRP-503; throws on violation.
 *
 * @MX:REASON: REQ-IRB-FORMSV2-020 invariant — worksheet_assist_state rows are only valid for
 *   HRP-503 submissions. Enforcement at model layer prevents silent data corruption.
 *
 * @MX:SPEC: REQ-IRB-FORMSV2-020
 */
class WorksheetAssistState extends Model
{
    use HasFactory;

    protected $table = 'worksheet_assist_state';

    protected $fillable = [
        'submission_id',
        'worksheet_form_id',
        'item_id',
        'status',
        'notes',
        'reviewed_at',
        'reviewed_by_user',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        // Security review F3 (HIGH): assert the HRP-503-only invariant on BOTH
        // creating and updating events, since an attacker could otherwise first
        // create a row pointing at an HRP-503 submission then update the row's
        // submission_id to an HRP-503c or HRP-398 submission to bypass the
        // creating-only check.
        $assertHrp503 = function (WorksheetAssistState $state): void {
            $submission = Submission::with('formDefinition')
                ->find($state->submission_id);

            if ($submission === null) {
                throw new \InvalidArgumentException(
                    "WorksheetAssistState: submission_id={$state->submission_id} not found."
                );
            }

            $formCode = $submission->formDefinition?->form_code;

            if ($formCode !== 'HRP-503') {
                throw new \InvalidArgumentException(
                    "WorksheetAssistState requires an HRP-503 submission (form_code='HRP-503'). "
                    ."Got form_code='{$formCode}' for submission_id={$state->submission_id}. "
                    .'REQ-IRB-FORMSV2-020 invariant violated.'
                );
            }
        };

        static::creating($assertHrp503);
        static::updating($assertHrp503);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
