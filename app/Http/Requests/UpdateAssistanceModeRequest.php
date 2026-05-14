<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SPEC-IRB-GUIDE-001 M1 — Form request for POST /projects/{project:uuid}/assistance-mode.
 *
 * REQ-IRB-GUIDE-003: assistance_mode is required on this endpoint (not nullable).
 * REQ-IRB-GUIDE-005: ownership guard enforced in authorize().
 */
class UpdateAssistanceModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        // REQ-IRB-GUIDE-005: only the project owner can change the assistance mode.
        return $this->user() !== null
            && $project !== null
            && $project->owner_user_id === $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assistance_mode' => ['required', 'string', Rule::in(['strict', 'assistant'])],
        ];
    }
}
