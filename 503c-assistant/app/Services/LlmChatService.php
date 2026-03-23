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
     * @param  list<array{role: 'system'|'user'|'assistant', content: string}>  $messages
     */
    private function openAiCompatibleChat(LlmProvider $provider, array $messages): string
    {
        $base = rtrim((string) ($provider->base_url ?: 'https://api.openai.com/v1'), '/');
        $url = $base.'/chat/completions';

        $payload = array_filter([
            'model' => $provider->model,
            'messages' => $messages,
            'temperature' => $provider->request_params['temperature'] ?? 0.2,
            'top_p' => $provider->request_params['top_p'] ?? null,
            'max_tokens' => $provider->request_params['max_tokens'] ?? 1200,
        ], static fn ($v) => $v !== null);

        $req = Http::timeout(60)->acceptJson();
        if ($provider->api_key !== null && $provider->api_key !== '') {
            $req = $req->withToken($provider->api_key);
        }

        /** @var Response $resp */
        $resp = $req->post($url, $payload);

        if (! $resp->successful()) {
            throw new \RuntimeException('LLM request failed: '.$resp->status().' '.$resp->body());
        }

        $json = $resp->json();
        $content = $json['choices'][0]['message']['content'] ?? null;
        if (! is_string($content)) {
            throw new \RuntimeException('Unexpected LLM response shape');
        }

        return $content;
    }
}
