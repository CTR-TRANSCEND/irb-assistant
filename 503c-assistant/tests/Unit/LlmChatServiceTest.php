<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\LlmProvider;
use App\Services\LlmChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private LlmChatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LlmChatService;
    }

    private function makeProvider(array $overrides = []): LlmProvider
    {
        return LlmProvider::query()->create(array_merge([
            'name' => 'test-provider',
            'provider_type' => 'openai',
            'base_url' => null,
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'request_params' => ['temperature' => 0.5],
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
        ], $overrides));
    }

    private function messages(): array
    {
        return [
            ['role' => 'user', 'content' => 'Hello'],
        ];
    }

    // ------------------------------------------------------------------
    // 1. Successful response returns content string
    // ------------------------------------------------------------------

    public function test_chat_returns_content_from_successful_response(): void
    {
        $provider = $this->makeProvider([
            'base_url' => 'https://api.example.test/v1',
        ]);

        Http::fake([
            'api.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Hello from LLM']],
                ],
            ], 200),
        ]);

        $result = $this->service->chat($provider, $this->messages());

        $this->assertSame('Hello from LLM', $result);
    }

    // ------------------------------------------------------------------
    // 2. Disabled provider throws RuntimeException
    // ------------------------------------------------------------------

    public function test_chat_throws_when_provider_disabled(): void
    {
        $provider = $this->makeProvider([
            'name' => 'disabled-provider',
            'is_enabled' => false,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provider is disabled');

        $this->service->chat($provider, $this->messages());
    }

    // ------------------------------------------------------------------
    // 3. Unsupported provider type throws RuntimeException
    // ------------------------------------------------------------------

    public function test_chat_throws_for_unsupported_provider_type(): void
    {
        $provider = $this->makeProvider([
            'name' => 'unsupported-provider',
            'provider_type' => 'anthropic',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported provider type: anthropic');

        $this->service->chat($provider, $this->messages());
    }

    // ------------------------------------------------------------------
    // 4. HTTP 500 error throws RuntimeException
    // ------------------------------------------------------------------

    public function test_chat_throws_on_http_error(): void
    {
        $provider = $this->makeProvider([
            'name' => 'error-provider',
            'base_url' => 'https://api.example.test/v1',
        ]);

        Http::fake([
            'api.example.test/*' => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LLM request failed: 500');

        $this->service->chat($provider, $this->messages());
    }

    // ------------------------------------------------------------------
    // 5. Malformed response (no choices) throws RuntimeException
    // ------------------------------------------------------------------

    public function test_chat_throws_on_malformed_response(): void
    {
        $provider = $this->makeProvider([
            'name' => 'malformed-provider',
            'base_url' => 'https://api.example.test/v1',
        ]);

        Http::fake([
            'api.example.test/*' => Http::response(['result' => 'ok'], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected LLM response shape');

        $this->service->chat($provider, $this->messages());
    }

    // ------------------------------------------------------------------
    // 6. Correct payload is sent (model, messages, temperature)
    // ------------------------------------------------------------------

    public function test_chat_sends_correct_payload(): void
    {
        $provider = $this->makeProvider([
            'name' => 'payload-provider',
            'base_url' => 'https://api.example.test/v1',
            'model' => 'gpt-4o-mini',
            'request_params' => ['temperature' => 0.7, 'max_tokens' => 500],
        ]);

        Http::fake([
            'api.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], 200),
        ]);

        $messages = [['role' => 'user', 'content' => 'Test message']];
        $this->service->chat($provider, $messages);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($messages) {
            $body = $request->data();

            return $body['model'] === 'gpt-4o-mini'
                && $body['messages'] === $messages
                && $body['temperature'] === 0.7
                && $body['max_tokens'] === 500;
        });
    }

    // ------------------------------------------------------------------
    // 7. Custom base_url is used in URL construction
    // ------------------------------------------------------------------

    public function test_chat_uses_custom_base_url(): void
    {
        $provider = $this->makeProvider([
            'name' => 'custom-url-provider',
            'base_url' => 'https://api.example.test/custom/v1',
        ]);

        Http::fake([
            'api.example.test/custom/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'custom url response']],
                ],
            ], 200),
        ]);

        $result = $this->service->chat($provider, $this->messages());

        $this->assertSame('custom url response', $result);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), 'api.example.test/custom/v1/chat/completions');
        });
    }

    // ------------------------------------------------------------------
    // 8. No Authorization header when api_key is null/empty
    // ------------------------------------------------------------------

    public function test_chat_omits_auth_header_when_no_api_key(): void
    {
        $provider = $this->makeProvider([
            'name' => 'no-key-provider',
            'base_url' => 'https://api.example.test/v1',
            'api_key' => null,
        ]);

        Http::fake([
            'api.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'no auth response']],
                ],
            ], 200),
        ]);

        $this->service->chat($provider, $this->messages());

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return ! $request->hasHeader('Authorization');
        });
    }

    // ------------------------------------------------------------------
    // 8b. No Authorization header when api_key is empty string
    // ------------------------------------------------------------------

    public function test_chat_omits_auth_header_when_api_key_is_empty_string(): void
    {
        $provider = $this->makeProvider([
            'name' => 'empty-key-provider',
            'base_url' => 'https://api.example.test/v1',
            'api_key' => '',
        ]);

        Http::fake([
            'api.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'no auth response']],
                ],
            ], 200),
        ]);

        $this->service->chat($provider, $this->messages());

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return ! $request->hasHeader('Authorization');
        });
    }

    // ------------------------------------------------------------------
    // 9. All supported provider types route correctly
    // ------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('supportedProviderTypeProvider')]
    public function test_chat_handles_all_provider_types(string $providerType): void
    {
        static $counter = 0;
        $counter++;

        $provider = $this->makeProvider([
            'name' => "provider-type-{$providerType}-{$counter}",
            'provider_type' => $providerType,
            'base_url' => 'https://api.example.test/v1',
        ]);

        Http::fake([
            'api.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => "response from {$providerType}"]],
                ],
            ], 200),
        ]);

        $result = $this->service->chat($provider, $this->messages());

        $this->assertSame("response from {$providerType}", $result);
    }

    public static function supportedProviderTypeProvider(): array
    {
        return [
            'openai' => ['openai'],
            'openai_compat' => ['openai_compat'],
            'lmstudio' => ['lmstudio'],
            'ollama' => ['ollama'],
            'glm47' => ['glm47'],
        ];
    }

    // ------------------------------------------------------------------
    // Extra: trailing slash in base_url is stripped
    // ------------------------------------------------------------------

    public function test_chat_strips_trailing_slash_from_base_url(): void
    {
        $provider = $this->makeProvider([
            'name' => 'trailing-slash-provider',
            'base_url' => 'https://api.example.test/v1/',
        ]);

        Http::fake([
            'api.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'clean url response']],
                ],
            ], 200),
        ]);

        $this->service->chat($provider, $this->messages());

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            // Should be /v1/chat/completions, not /v1//chat/completions
            return $request->url() === 'https://api.example.test/v1/chat/completions';
        });
    }

    // ------------------------------------------------------------------
    // Extra: default OpenAI base URL used when base_url is null
    // ------------------------------------------------------------------

    public function test_chat_uses_default_openai_base_url_when_not_set(): void
    {
        $provider = $this->makeProvider([
            'name' => 'default-url-provider',
            'base_url' => null,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'openai default']],
                ],
            ], 200),
        ]);

        $result = $this->service->chat($provider, $this->messages());

        $this->assertSame('openai default', $result);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), 'api.openai.com/v1/chat/completions');
        });
    }

    // ------------------------------------------------------------------
    // Extra: default request_params applied when not specified
    // ------------------------------------------------------------------

    public function test_chat_applies_default_temperature_and_max_tokens_when_not_in_request_params(): void
    {
        $provider = $this->makeProvider([
            'name' => 'default-params-provider',
            'base_url' => 'https://api.example.test/v1',
            'request_params' => [],
        ]);

        Http::fake([
            'api.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'defaults ok']],
                ],
            ], 200),
        ]);

        $this->service->chat($provider, $this->messages());

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $body = $request->data();

            return $body['temperature'] === 0.2
                && $body['max_tokens'] === 1200;
        });
    }
}
