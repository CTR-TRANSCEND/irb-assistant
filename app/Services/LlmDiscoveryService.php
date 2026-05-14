<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Dto\DiscoveryResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * SPEC-LLM-001 REQ-LLM-001/003/004/005/006/019 — discover available models from
 * an LLM provider via /v1/models or /api/tags. Outbound HTTP only via the Http
 * facade so tests can use Http::fake().
 *
 * @MX:NOTE: 10s primary timeout (REQ-LLM-006); 5s soft cap on the
 *           best-effort lmstudio /api/v0/models probe.
 */
class LlmDiscoveryService
{
    private const TIMEOUT_PRIMARY_SECONDS = 10;

    private const TIMEOUT_LMSTUDIO_LOADED_SECONDS = 5;

    /**
     * @MX:ANCHOR: outbound HTTP boundary; redirect-following disabled per REQ-LLM-019.
     *
     * @MX:REASON: 3xx on the primary endpoint is a hard fail (errorCode='redirect_not_allowed'),
     *             NOT a 404 fallback trigger. Closes the public-host-302-to-private-IP bypass.
     */
    public function discoverModels(string $providerType, string $baseUrl, ?string $apiKey = null): DiscoveryResult
    {
        $base = rtrim($baseUrl, '/');

        // Endpoint priority: openai-compat family tries /v1/models first, ollama
        // tries /api/tags first. On 404 the other is attempted as a fallback.
        // 3xx on the PRIMARY is fatal — no fallback (REQ-LLM-019).
        // Path normalization: if base already includes the OpenAI v1 segment
        // (e.g., https://api.openai.com/v1) append only /models. Same for
        // ollama if base ends with /api. Prevents the v1/v1/models double-segment.
        $openAiPath = str_ends_with($base, '/v1') ? '/models' : '/v1/models';
        $ollamaPath = str_ends_with($base, '/api') ? '/tags' : '/api/tags';

        // SPEC-LLM-001 REQ-LLM-001: server_type is one distinct value per providerType.
        // openai → 'openai', openai_compat → 'openai-compatible',
        // ollama → 'ollama-native', lmstudio → 'lmstudio', glm47 → 'glm47'.
        $primaryServerTypeMap = [
            'openai' => 'openai',
            'openai_compat' => 'openai-compatible',
            'ollama' => 'ollama-native',
            'lmstudio' => 'lmstudio',
            'glm47' => 'glm47',
        ];

        if ($providerType === 'ollama') {
            $primaryPath = $ollamaPath;
            $fallbackPath = $openAiPath;
            $serverTypeOnPrimary = $primaryServerTypeMap['ollama'];
            // Fallback path on /v1/models for an ollama-typed provider means the
            // server speaks OpenAI-compatible API; report as 'openai-compatible'.
            $serverTypeOnFallback = 'openai-compatible';
        } else {
            // openai, openai_compat, lmstudio, glm47
            $primaryPath = $openAiPath;
            $fallbackPath = $ollamaPath;
            $serverTypeOnPrimary = $primaryServerTypeMap[$providerType] ?? 'openai-compatible';
            // Fallback path on /api/tags for any of the OpenAI-family providers
            // means the server is actually ollama-native.
            $serverTypeOnFallback = 'ollama-native';
        }

        try {
            $response = $this->buildClient($apiKey)->get($base.$primaryPath);
        } catch (ConnectionException $e) {
            return new DiscoveryResult([], [], $serverTypeOnPrimary, $this->classifyConnectException($e));
        } catch (\Throwable) {
            return new DiscoveryResult([], [], $serverTypeOnPrimary, 'connect_failed');
        }

        // 3xx on primary → REQ-LLM-019 hard fail, NO fallback attempt.
        $status = $response->status();
        if ($status >= 300 && $status < 400) {
            return new DiscoveryResult([], [], $serverTypeOnPrimary, 'redirect_not_allowed');
        }

        if ($status === 401 || $status === 403) {
            return new DiscoveryResult([], [], $serverTypeOnPrimary, 'auth_failed');
        }

        if ($status === 404) {
            // Fallback path.
            try {
                $fallback = $this->buildClient($apiKey)->get($base.$fallbackPath);
            } catch (ConnectionException $e) {
                return new DiscoveryResult([], [], $serverTypeOnFallback, $this->classifyConnectException($e));
            } catch (\Throwable) {
                return new DiscoveryResult([], [], $serverTypeOnFallback, 'connect_failed');
            }

            $fStatus = $fallback->status();
            if ($fStatus >= 300 && $fStatus < 400) {
                // Per REQ-LLM-019, redirects on the fallback are also rejected.
                return new DiscoveryResult([], [], $serverTypeOnFallback, 'redirect_not_allowed');
            }
            if ($fStatus === 401 || $fStatus === 403) {
                return new DiscoveryResult([], [], $serverTypeOnFallback, 'auth_failed');
            }
            if (! $fallback->successful()) {
                return new DiscoveryResult([], [], $serverTypeOnFallback, 'connect_failed');
            }

            $models = $serverTypeOnFallback === 'ollama-native'
                ? $this->parseOllamaModels($fallback)
                : $this->parseOpenAiModels($fallback);

            return new DiscoveryResult($models, [], $serverTypeOnFallback, null);
        }

        if (! $response->successful()) {
            return new DiscoveryResult([], [], $serverTypeOnPrimary, 'connect_failed');
        }

        $models = $serverTypeOnPrimary === 'ollama-native'
            ? $this->parseOllamaModels($response)
            : $this->parseOpenAiModels($response);

        $loaded = [];
        if ($providerType === 'lmstudio') {
            $loaded = $this->probeLmStudioLoaded($base, $apiKey, $models);
        }

        return new DiscoveryResult($models, $loaded, $serverTypeOnPrimary, null);
    }

    /**
     * @MX:WARN: REQ-LLM-019 — Http::withoutRedirecting() means a 3xx on the primary
     *           endpoint fails immediately and is NOT a 404-style fallback trigger.
     *
     * @MX:REASON: This closes the public-host → 302 → private-IP SSRF bypass.
     */
    private function buildClient(?string $apiKey): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::withoutRedirecting()
            ->timeout(self::TIMEOUT_PRIMARY_SECONDS)
            ->acceptJson();

        if ($apiKey !== null && $apiKey !== '') {
            $client = $client->withToken($apiKey);
        }

        return $client;
    }

    /**
     * @return list<string>
     */
    private function parseOpenAiModels(Response $response): array
    {
        $json = $response->json();
        $models = [];
        if (is_array($json) && isset($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $row) {
                if (is_array($row) && isset($row['id']) && is_string($row['id']) && $row['id'] !== '') {
                    $models[] = $row['id'];
                }
            }
        }

        return array_values(array_unique($models));
    }

    /**
     * @return list<string>
     */
    private function parseOllamaModels(Response $response): array
    {
        $json = $response->json();
        $models = [];
        if (is_array($json) && isset($json['models']) && is_array($json['models'])) {
            foreach ($json['models'] as $row) {
                if (! is_array($row) || ! isset($row['name']) || ! is_string($row['name'])) {
                    continue;
                }
                $name = $row['name'];
                if ($name === '') {
                    continue;
                }
                // Take base name before ":" (tag separator), e.g., "llama3:8b" → "llama3".
                $colon = strpos($name, ':');
                if ($colon !== false) {
                    $name = substr($name, 0, $colon);
                }
                if ($name !== '') {
                    $models[] = $name;
                }
            }
        }

        return array_values(array_unique($models));
    }

    /**
     * REQ-LLM-005: best-effort GET /api/v0/models — failure leaves loaded=[].
     *
     * @param  list<string>  $models
     * @return list<string>
     */
    private function probeLmStudioLoaded(string $base, ?string $apiKey, array $models): array
    {
        try {
            $client = Http::withoutRedirecting()
                ->timeout(self::TIMEOUT_LMSTUDIO_LOADED_SECONDS)
                ->acceptJson();
            if ($apiKey !== null && $apiKey !== '') {
                $client = $client->withToken($apiKey);
            }
            $response = $client->get($base.'/api/v0/models');
            if (! $response->successful()) {
                return [];
            }
            $json = $response->json();
            if (! is_array($json) || ! isset($json['data']) || ! is_array($json['data'])) {
                return [];
            }
            $loaded = [];
            $modelsSet = array_flip($models);
            foreach ($json['data'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = isset($row['id']) && is_string($row['id']) ? $row['id'] : null;
                $state = isset($row['state']) && is_string($row['state']) ? $row['state'] : null;
                if ($id !== null && $state === 'loaded') {
                    $loaded[] = $id;
                }
            }
            // Preserve subset-of-models invariant declared in DTO contract.
            $loaded = array_values(array_filter($loaded, static fn (string $id): bool => isset($modelsSet[$id])));

            return array_values(array_unique($loaded));
        } catch (\Throwable) {
            return [];
        }
    }

    private function classifyConnectException(ConnectionException $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'timed out') || str_contains($msg, 'cURL error 28')) {
            return 'timeout';
        }

        return 'connect_failed';
    }
}
