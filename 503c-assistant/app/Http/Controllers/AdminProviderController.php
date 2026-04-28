<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LlmProvider;
use App\Services\AuditService;
use App\Services\LlmChatService;
use Illuminate\Http\Request;

class AdminProviderController extends Controller
{
    public function store(Request $request, AuditService $audit): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'provider_type' => ['required', 'string', 'in:openai,openai_compat,ollama,lmstudio,glm47'],
            'base_url' => ['nullable', 'url:http,https', 'max:2048'],
            'model' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'request_params_json' => ['nullable', 'string'],
            'is_enabled' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_external' => ['nullable', 'boolean'],
        ]);

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
            'base_url' => $data['base_url'] ?: null,
            'model' => $data['model'] ?: null,
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

    public function test(Request $request, LlmProvider $provider, LlmChatService $llm, AuditService $audit): \Illuminate\Http\RedirectResponse
    {
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

        return $data;
    }
}
