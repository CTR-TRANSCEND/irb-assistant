<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\AuditService;
use App\Support\BaseUrlAuditSanitizer;
use App\Support\BaseUrlValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * SPEC-LLM-001 REQ-LLM-011/012/014 — request validation + validation-failed
 * audit hook for the model-discovery endpoint.
 */
class DiscoverProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin middleware on the route handles authorization.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'provider_type' => ['required', 'string', 'in:openai,openai_compat,ollama,lmstudio,glm47'],
            'base_url' => ['required', 'string', 'max:2048', new BaseUrlValidator],
            'api_key' => ['nullable', 'string', 'max:4096'],
        ];
    }

    /**
     * @MX:NOTE: REQ-LLM-014 reconciliation — emit 'validation_failed' audit row
     *          BEFORE the 422 is thrown so the audit guarantee covers all
     *          admin-authenticated discovery attempts.
     */
    protected function failedValidation(Validator $validator): void
    {
        try {
            /** @var AuditService $audit */
            $audit = app(AuditService::class);
            $audit->log(
                request: $this,
                eventType: 'admin.provider.models_discovered',
                project: null,
                entityType: 'llm_provider',
                entityId: null,
                entityUuid: null,
                payload: [
                    'provider_type' => $this->input('provider_type'),
                    'base_url' => BaseUrlAuditSanitizer::sanitize(
                        is_string($this->input('base_url')) ? $this->input('base_url') : null
                    ),
                    'api_key' => '[REDACTED]',
                    'result' => 'validation_failed',
                    'errors' => $validator->errors()->toArray(),
                ],
            );
        } catch (\Throwable $e) {
            // Audit failure must NEVER swallow the 422 response.
            \Illuminate\Support\Facades\Log::warning('Audit log failed in DiscoverProviderRequest', [
                'message' => $e->getMessage(),
            ]);
        }

        throw new ValidationException($validator);
    }
}
