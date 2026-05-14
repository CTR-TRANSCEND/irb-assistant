<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DiscoverProviderRequest;
use App\Models\LlmProvider;
use App\Services\AuditService;
use App\Services\LlmChatService;
use App\Services\LlmDiscoveryService;
use App\Support\BaseUrlAuditSanitizer;
use App\Support\BaseUrlValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProviderController extends Controller
{
    public function store(Request $request, AuditService $audit): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'provider_type' => ['required', 'string', 'in:openai,openai_compat,ollama,lmstudio,glm47'],
            // SPEC-LLM-001 REQ-LLM-012: harden base_url with BaseUrlValidator + max:2048.
            'base_url' => ['nullable', 'string', 'max:2048', new BaseUrlValidator],
            'model' => ['nullable', 'string', 'max:255'],
            // SPEC-LLM-001 REQ-LLM-020: model_manual as alternative to model.
            'model_manual' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'request_params_json' => ['nullable', 'string'],
            'is_enabled' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_external' => ['nullable', 'boolean'],
        ]);

        // SPEC-LLM-001 REQ-LLM-020: at least one of model | model_manual must be non-empty.
        $modelValue = trim((string) ($data['model'] ?? ''));
        $modelManualValue = trim((string) ($data['model_manual'] ?? ''));
        if ($modelValue === '' && $modelManualValue === '') {
            return back()->withErrors([
                'model' => 'A model name is required (select from discovery or enter manually).',
            ])->withInput();
        }
        // SPEC-LLM-001 REQ-LLM-020: discovery-selected `model` takes precedence over
        // `model_manual` when both are non-empty. model_manual is the fallback only.
        $effectiveModel = $modelValue !== '' ? $modelValue : $modelManualValue;

        $params = null;
        if (($data['request_params_json'] ?? '') !== '') {
            $decoded = json_decode((string) $data['request_params_json'], true);
            if (! is_array($decoded)) {
                return back()->withErrors(['request_params_json' => 'Invalid JSON']);
            }
            $params = $decoded;
        }

        $provider = null;
        if (isset($data['id'])) {
            $provider = LlmProvider::query()->find((int) $data['id']);
        }

        $before = $this->sanitizeProviderForAudit($provider?->toArray());

        $payload = [
            'name' => $data['name'],
            'provider_type' => $data['provider_type'],
            'base_url' => ($data['base_url'] ?? '') !== '' ? $data['base_url'] : null,
            'model' => $effectiveModel,
            // Persist user's original manual entry verbatim so edit-mode can repopulate
            // the manual-override field exactly as entered. `model` remains the
            // effective value used at request time (REQ-LLM-020 precedence preserved).
            'model_manual' => $modelManualValue !== '' ? $modelManualValue : null,
            'request_params' => $params,
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_external' => (bool) ($data['is_external'] ?? true),
        ];

        if (($data['api_key'] ?? '') !== '') {
            $payload['api_key'] = $data['api_key'];
        }

        if ($provider === null) {
            $provider = LlmProvider::query()->create($payload);
        } else {
            $provider->fill($payload);
            $provider->save();
        }

        if ($provider->is_default) {
            LlmProvider::query()
                ->where('id', '!=', $provider->id)
                ->update(['is_default' => false]);
        }

        $audit->log(
            request: $request,
            eventType: 'admin.provider.saved',
            project: null,
            entityType: 'llm_provider',
            entityId: $provider->id,
            entityUuid: null,
            payload: [
                'before' => $before,
                'after' => $this->sanitizeProviderForAudit($provider->fresh()?->toArray()),
            ],
        );

        return redirect()->route('admin.index', ['tab' => 'providers'])->with('status', 'Provider saved.');
    }

    /**
     * SPEC-LLM-001 REQ-LLM-011/013/014/019 — model discovery endpoint.
     * Returns JSON: {success, models, loaded, server_type, error?}.
     */
    public function discover(
        DiscoverProviderRequest $request,
        LlmDiscoveryService $svc,
        AuditService $audit,
    ): JsonResponse {
        /** @var array{provider_type: string, base_url: string, api_key?: ?string} $data */
        $data = $request->validated();

        $result = $svc->discoverModels(
            $data['provider_type'],
            $data['base_url'],
            $data['api_key'] ?? null,
        );

        $auditResult = $result->errorCode ?? 'success';

        $audit->log(
            request: $request,
            eventType: 'admin.provider.models_discovered',
            project: null,
            entityType: 'llm_provider',
            entityId: null,
            entityUuid: null,
            payload: [
                'provider_type' => $data['provider_type'],
                'base_url' => BaseUrlAuditSanitizer::sanitize($data['base_url']),
                'api_key' => '[REDACTED]',
                'result' => $auditResult,
                'server_type' => $result->serverType,
                'model_count' => count($result->models),
                'loaded_count' => count($result->loaded),
            ],
        );

        $success = $result->errorCode === null;
        $body = [
            'success' => $success,
            'models' => $result->models,
            'loaded' => $result->loaded,
            'server_type' => $result->serverType,
            'error' => $result->errorCode,
        ];

        // Service-level errors (auth_failed/timeout/connect_failed/redirect_not_allowed)
        // return HTTP 200 with success=false (per teammate API contract); validation
        // errors (handled in DiscoverProviderRequest::failedValidation) return 422.
        return response()->json($body, 200);
    }

    public function test(Request $request, LlmProvider $provider, LlmChatService $llm, AuditService $audit): \Illuminate\Http\RedirectResponse
    {
        // SPEC-LLM-001 REQ-LLM-021: re-validate stored base_url through BaseUrlValidator
        // BEFORE issuing chat probe (defence-in-depth against post-save DNS changes).
        // ValidationException routes to Laravel's standard 422 {errors:{base_url:[...]}}
        // envelope for JSON requests, and to a 302 redirect-with-session-errors for
        // browser form posts — both satisfy REQ-LLM-021's "Laravel-standard envelope".
        if ($provider->base_url !== null && $provider->base_url !== '') {
            $error = (new BaseUrlValidator)($provider->base_url);
            if ($error !== null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'base_url' => [$error],
                ]);
            }
        }

        $before = $this->sanitizeProviderForAudit($provider->toArray());

        try {
            $llm->chat($provider, [
                ['role' => 'system', 'content' => 'You are a connectivity test. Reply with the single word OK.'],
                ['role' => 'user', 'content' => 'OK'],
            ]);

            $provider->forceFill([
                'last_tested_at' => now(),
                'last_test_ok' => true,
                'last_test_error' => null,
            ])->save();

            $audit->log(
                request: $request,
                eventType: 'admin.provider.tested',
                project: null,
                entityType: 'llm_provider',
                entityId: $provider->id,
                entityUuid: null,
                payload: [
                    'before' => $before,
                    'after' => $this->sanitizeProviderForAudit($provider->fresh()?->toArray()),
                    'result' => 'ok',
                ],
            );

            return redirect()->route('admin.index', ['tab' => 'providers'])->with('status', 'Provider test succeeded.');
        } catch (\Throwable $e) {
            $classification = $this->classifyLlmException($e);

            \Illuminate\Support\Facades\Log::warning('LLM provider test failed', [
                'provider_id' => $provider->id,
                'classification' => $classification,
                'message' => $e->getMessage(),
            ]);

            $provider->forceFill([
                'last_tested_at' => now(),
                'last_test_ok' => false,
                'last_test_error' => $classification,
            ])->save();

            $audit->log(
                request: $request,
                eventType: 'admin.provider.tested',
                project: null,
                entityType: 'llm_provider',
                entityId: $provider->id,
                entityUuid: null,
                payload: [
                    'before' => $before,
                    'after' => $this->sanitizeProviderForAudit($provider->fresh()?->toArray()),
                    'result' => $classification,
                ],
            );

            return redirect()
                ->route('admin.index', ['tab' => 'providers'])
                ->withErrors(['provider_test' => 'Provider test failed: '.$classification]);
        }
    }

    private function classifyLlmException(\Throwable $e): string
    {
        $msg = $e->getMessage();

        // Timeout check first — overlaps with connect_failed cURL codes
        if (str_contains($msg, 'timed out') || str_contains($msg, 'cURL error 28')) {
            return 'timeout';
        }

        // TLS / SSL errors
        if (
            str_contains($msg, 'SSL') ||
            str_contains($msg, 'TLS') ||
            str_contains($msg, 'cURL error 35') ||
            str_contains($msg, 'cURL error 60')
        ) {
            return 'tls_error';
        }

        // Connection failures
        if (
            $e instanceof \Illuminate\Http\Client\ConnectionException ||
            str_contains($msg, 'cURL error 6') ||
            str_contains($msg, 'cURL error 7') ||
            str_contains($msg, 'Could not resolve') ||
            str_contains($msg, 'Failed to connect')
        ) {
            return 'connect_failed';
        }

        // HTTP status-based classification from LlmChatService message format
        if (str_contains($msg, 'LLM request failed: 4')) {
            return 'http_4xx';
        }

        if (str_contains($msg, 'LLM request failed: 5')) {
            return 'http_5xx';
        }

        if (str_contains($msg, 'LLM request failed:')) {
            return 'unexpected_response';
        }

        return 'unexpected_response';
    }

    /**
     * Issue D follow-up: delete a provider.
     *
     * Audit payload uses sanitised before-state (api_key stripped, base_url userinfo
     * removed per M2). No after-state since the row is gone.
     */
    public function destroy(LlmProvider $provider, Request $request, AuditService $audit): \Illuminate\Http\RedirectResponse
    {
        $before = $this->sanitizeProviderForAudit($provider->toArray());
        $name = $provider->name;

        \Illuminate\Support\Facades\DB::transaction(function () use ($provider, $before, $request, $audit) {
            $provider->delete();

            $audit->log(
                request: $request,
                eventType: 'admin.provider.deleted',
                project: null,
                entityType: 'llm_provider',
                entityId: $provider->id,
                entityUuid: null,
                payload: [
                    'before' => $before,
                    'after' => null,
                ],
            );
        });

        return redirect()
            ->route('admin.index', ['tab' => 'providers'])
            ->with('status', "Provider '{$name}' deleted.");
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private function sanitizeProviderForAudit(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $hasKey = isset($data['api_key']) && is_string($data['api_key']) && $data['api_key'] !== '';
        unset($data['api_key']);
        $data['has_api_key'] = $hasKey;

        // M2: strip userinfo (user:pass@) from base_url in audit payload (SECURITY).
        // The value persisted to the DB column is unchanged; only the audit copy is sanitised.
        if (isset($data['base_url']) && is_string($data['base_url'])) {
            $data['base_url'] = BaseUrlAuditSanitizer::sanitize($data['base_url']);
        }

        return $data;
    }
}
