<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LlmProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class LlmChatService
{
    /**
     * @param  list<array{role: 'system'|'user'|'assistant', content: string}>  $messages
     */
    public function chat(LlmProvider $provider, array $messages): string
    {
        if (! $provider->is_enabled) {
            throw new \RuntimeException('Provider is disabled');
        }

        return match ($provider->provider_type) {
            'openai', 'openai_compat', 'lmstudio', 'ollama', 'glm47' => $this->openAiCompatibleChat($provider, $messages),
            default => throw new \RuntimeException('Unsupported provider type: '.$provider->provider_type),
        };
    }

    /**
     * Normalize an OpenAI-compatible base URL to its API root.
     *
     * Accepts any of: `host:port`, `host:port/`, `host:port/v1`, `host:port/v1/`.
     * Returns the form without trailing slash and without `/v1` suffix, so
     * callers can append `/v1/chat/completions` or `/v1/models` deterministically.
     *
     * Mirrors the pattern used by research-pdf-renamer's llm_service.py to make
     * configuration forgiving for users who don't know the OpenAI-compat URL convention.
     */
    public static function normalizeBaseUrl(string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');
        if (str_ends_with($base, '/v1')) {
            $base = substr($base, 0, -3);
        }

        return $base;
    }

    /**
     * @param  list<array{role: 'system'|'user'|'assistant', content: string}>  $messages
     */
    private function openAiCompatibleChat(LlmProvider $provider, array $messages): string
    {
        $base = self::normalizeBaseUrl((string) ($provider->base_url ?: 'https://api.openai.com'));
        $url = $base.'/v1/chat/completions';

        $payload = array_filter([
            'model' => $provider->model,
            'messages' => $messages,
            'temperature' => $provider->request_params['temperature'] ?? 0.2,
            'top_p' => $provider->request_params['top_p'] ?? null,
            // Default 4096: reasoning models (gpt-oss, DeepSeek-R1) burn tokens on
            // chain-of-thought before emitting final JSON; 1200 was too low and caused
            // them to run out before the final content. Override per-provider via
            // request_params.max_tokens for cloud providers where token cost matters.
            'max_tokens' => $provider->request_params['max_tokens'] ?? 4096,
        ], static fn ($v) => $v !== null);

        // @MX:WARN: REQ-LLM-022 — chat-probe redirect-bypass closure. Prevents a
        //           saved public base_url from 302-ing the chat probe into a private IP
        //           after BaseUrlValidator pre-flight (REQ-LLM-021).
        // @MX:REASON: Closes residual SSRF vector ND4 — DNS-rebind / late-redirect.
        // Timeout sized for 20B reasoning models on multi-field batches; Apache vhost
        // ProxyTimeout and FPM request_terminate_timeout must be ≥ this value.
        $timeoutSeconds = (int) config('irb.llm_chat_timeout_seconds', 600);
        $req = Http::withoutRedirecting()->timeout($timeoutSeconds)->acceptJson();
        if ($provider->api_key !== null && $provider->api_key !== '') {
            $req = $req->withToken($provider->api_key);
        }

        /** @var Response $resp */
        $resp = $req->post($url, $payload);

        if (! $resp->successful()) {
            throw new \RuntimeException('LLM request failed: '.$resp->status().' '.$resp->body());
        }

        $json = $resp->json();

        // Some servers (LM Studio at the wrong endpoint, certain reverse proxies) return
        // HTTP 200 with `{"error": "..."}` and no `choices`. Surface the server message
        // instead of throwing the generic "missing choices[0]" error.
        if (is_array($json) && ! isset($json['choices']) && isset($json['error'])) {
            $errMsg = is_string($json['error']) ? $json['error'] : (string) json_encode($json['error']);
            throw new \RuntimeException('LLM request failed: 200 server error: '.$errMsg);
        }

        return $this->extractContent($json);
    }

    /**
     * Extract assistant text from an OpenAI-compatible chat-completions response.
     *
     * Handles three shapes beyond the canonical `choices[0].message.content`:
     *   1. Reasoning models (OpenAI gpt-oss, DeepSeek-R1, some LM Studio builds)
     *      that put visible answer in `message.reasoning_content` or `message.reasoning`
     *      while `content` is null/empty.
     *   2. Tool-calling responses where `content` is null but `tool_calls` is populated;
     *      caller may still want a non-null return for connectivity checks.
     *   3. Legacy completion-style fallback shape `choices[0].text`.
     *
     * Throws RuntimeException with the actual response keys if no shape matches,
     * so future failures self-document instead of opaque "Unexpected LLM response shape".
     *
     * @param  array<mixed>|null  $json
     *
     * @MX:NOTE: REQ-LLM-023 — chat-shape robustness; supports reasoning-model output
     *   (gpt-oss, DeepSeek-R1) where assistant text lives in reasoning_content.
     */
    private function extractContent(?array $json): string
    {
        if (! is_array($json)) {
            throw new \RuntimeException('LLM response was not JSON');
        }

        $choice = $json['choices'][0] ?? null;
        if (! is_array($choice)) {
            throw new \RuntimeException('LLM response missing choices[0]');
        }

        $message = $choice['message'] ?? null;

        if (is_array($message) && isset($message['content']) && is_string($message['content']) && $message['content'] !== '') {
            return $message['content'];
        }

        foreach (['reasoning_content', 'reasoning'] as $key) {
            if (is_array($message) && isset($message[$key]) && is_string($message[$key]) && $message[$key] !== '') {
                return $message[$key];
            }
        }

        if (is_array($message) && ! empty($message['tool_calls'])) {
            return '';
        }

        if (isset($choice['text']) && is_string($choice['text']) && $choice['text'] !== '') {
            return $choice['text'];
        }

        $messageKeys = is_array($message) ? array_keys($message) : [];

        throw new \RuntimeException(
            'Unexpected LLM response shape — choices[0].message keys: ['
                .implode(',', $messageKeys).']; choice keys: ['
                .implode(',', array_keys($choice)).']'
        );
    }
}
