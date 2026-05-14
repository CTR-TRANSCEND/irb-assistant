<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the PUT /submissions/{submission_uuid}/worksheet/{item_id} payload.
 *
 * Security:
 *   - `status` is constrained to the 4-value enum using Rule::in() (Phase 5 F-SEC-3 pattern).
 *   - `notes` is capped at 65535 chars to match the TEXT column size (LD-P6-3).
 *   - `user_id` and `submission_id` are NOT validated here; they come from the
 *     authenticated session and route resolution respectively.
 *
 * SPEC-IRB-FORMSV2-006 §C.1
 */
class UpdateWorksheetAssistStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level auth is handled by the auth+verified middleware group.
        // Ownership validation is handled in the controller via resolveSubmission().
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(['not_started', 'addressed', 'needs_work', 'not_applicable']),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:65535',
            ],
        ];
    }
}
