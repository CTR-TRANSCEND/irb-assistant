<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the POST /studies payload for study creation.
 *
 * Security: authorize() returns true because the route is already
 * protected by auth + verified middleware. user_id is NOT in the
 * validated payload — controllers MUST use Auth::id() (security review F2).
 *
 * SPEC-IRB-FORMSV2-004 §A
 */
class StoreStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'application_title' => ['nullable', 'string', 'max:500'],
            'pi_name' => ['nullable', 'string', 'max:255'],
            'project_summary' => ['nullable', 'string'],
            'oversight' => ['nullable', 'string'],
            'nickname' => ['nullable', 'string', 'max:255'],
        ];
    }
}
