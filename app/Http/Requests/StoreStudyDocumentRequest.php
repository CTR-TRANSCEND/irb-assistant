<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Validates the POST /studies/{study_uuid}/documents payload.
 *
 * SPEC-IRB-FORMSV2-008 REQ-P8-002
 *
 * Authorization is handled by StudyDocumentController (ownership check via route
 * param + Auth::id()), so authorize() returns true unconditionally.
 */
class StoreStudyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // max: in kilobytes → 102400 KB = 100 MB  (LD-P8-4)
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:102400'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The upload must be a valid file.',
            'file.mimes' => 'Only PDF, DOC, and DOCX files are accepted.',
            'file.max' => 'The file may not be larger than 100 MB.',
        ];
    }
}
