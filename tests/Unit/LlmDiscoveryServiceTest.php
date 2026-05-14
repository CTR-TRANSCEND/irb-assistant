<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Dto\DiscoveryResult;
use App\Services\LlmDiscoveryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-LLM-001 — LlmDiscoveryService unit coverage.
 *
 * Covers acceptance scenarios:
 *   S3, S4, S9, S15, S16, S21
 *   plus connection-timeout sanity test for REQ-LLM-006/REQ-LLM-019.
 *
 * Strategy: Use Http::fake() to mock outbound HTTP. Invoke the real service via
 * the container (app(LlmDiscoveryService::class)) so the production code path
 * is exercised end-to-end (no service mocks).
 *
 * Traceability:
 *   REQ-LLM-001, REQ-LLM-003, REQ-LLM-004, REQ-LLM-005, REQ-LLM-006,
 *   REQ-LLM-014, REQ-LLM-019
 */
final class LlmDiscoveryServiceTest extends TestCase
{
    private LlmDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LlmDiscoveryService::class);
    }

    // ─── S3: openai-compat /v1/models happy path (REQ-LLM-001/003) ──────────────

    #[Test]
    public function s3_openai_compat_returns_models(): void
    {
        Http::fake([
            'api.openai.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'gpt-4o'],
                    ['id' => 'gpt-4o-mini'],
                    ['id' => 'gpt-3.5-turbo'],
                ],
            ], 200),
        ]);

        $result = $this->service->discoverModels('openai', 'https://api.openai.com/v1', 'sk-test');

        $this->assertInstanceOf(DiscoveryResult::class, $result);
        $this->assertNull($result->errorCode);
        $this->assertContains('gpt-4o', $result->models);
        $this->assertContains('gpt-4o-mini', $result->models);
        $this->assertContains('gpt-3.5-turbo', $result->models);
        $this->assertSame([], $result->loaded);
        // SPEC-LLM-001 REQ-LLM-001: openai → 'openai' (distinct from 'openai-compatible').
        $this->assertSame('openai', $result->serverType);

        // Verify the bearer token was sent (REQ-LLM-004 auth pass-through).
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/models'
                && $request->header('Authorization') === ['Bearer sk-test'];
        });
    }

    // ─── S4: LM Studio with /api/v0/models loaded[] (REQ-LLM-005) ───────────────

    #[Test]
    public function s4_lmstudio_with_loaded(): void
    {
        Http::fake([
            'http://100.64.10.5:1234/v1/models' => Http::response([
                'data' => [
                    ['id' => 'qwen2.5-coder-32b'],
                    ['id' => 'gemma-2-9b'],
                ],
            ], 200),
            'http://100.64.10.5:1234/api/v0/models' => Http::response([
                'data' => [
                    ['id' => 'qwen2.5-coder-32b', 'state' => 'loaded'],
                    ['id' => 'gemma-2-9b', 'state' => 'not-loaded'],
                ],
            ], 200),
        ]);

        $result = $this->service->discoverModels('lmstudio', 'http://100.64.10.5:1234', '');

        $this->assertNull($result->errorCode);
        $this->assertCount(2, $result->models);
        $this->assertContains('qwen2.5-coder-32b', $result->models);
        $this->assertContains('gemma-2-9b', $result->models);
        $this->assertSame(['qwen2.5-coder-32b'], $result->loaded);
        // SPEC-LLM-001 REQ-LLM-001: lmstudio → 'lmstudio'.
        $this->assertSame('lmstudio', $result->serverType);
    }

    // ─── S9: 401 → auth_failed; api_key NOT echoed (REQ-LLM-014) ────────────────

    #[Test]
    public function s9_bad_api_key_auth_failed(): void
    {
        Http::fake([
            'api.openai.com/v1/models' => Http::response([
                'error' => ['message' => 'Invalid API key'],
            ], 401),
        ]);

        $result = $this->service->discoverModels('openai', 'https://api.openai.com/v1', 'sk-bogus');

        $this->assertSame('auth_failed', $result->errorCode);
        $this->assertSame([], $result->models);
        $this->assertSame([], $result->loaded);

        // The api_key MUST NOT be embedded in any returned property.
        $serialised = json_encode([
            'models' => $result->models,
            'loaded' => $result->loaded,
            'serverType' => $result->serverType,
            'errorCode' => $result->errorCode,
        ]);
        $this->assertIsString($serialised);
        $this->assertStringNotContainsString('sk-bogus', $serialised);
    }

    // ─── S15: glm47 happy path (REQ-LLM-001/003) ────────────────────────────────

    #[Test]
    public function s15_glm47_happy_path(): void
    {
        Http::fake([
            'api.z.ai/api/coding/paas/v4/v1/models' => Http::response([
                'data' => [
                    ['id' => 'glm-4.7'],
                    ['id' => 'glm-4.5'],
                ],
            ], 200),
        ]);

        $result = $this->service->discoverModels(
            'glm47',
            'https://api.z.ai/api/coding/paas/v4',
            'test-glm-key',
        );

        $this->assertNull($result->errorCode);
        $this->assertCount(2, $result->models);
        $this->assertContains('glm-4.7', $result->models);
        $this->assertContains('glm-4.5', $result->models);
        $this->assertSame([], $result->loaded);
        // SPEC-LLM-001 REQ-LLM-001: glm47 → 'glm47'.
        $this->assertSame('glm47', $result->serverType);

        Http::assertSent(function ($request): bool {
            return str_ends_with($request->url(), '/v1/models')
                && $request->header('Authorization') === ['Bearer test-glm-key'];
        });
    }

    // ─── REQ-LLM-001: openai_compat → 'openai-compatible' ───────────────────────

    #[Test]
    public function openai_compat_returns_distinct_server_type(): void
    {
        Http::fake([
            'compat.example.com/v1/models' => Http::response([
                'data' => [['id' => 'mistral-7b']],
            ], 200),
        ]);

        $result = $this->service->discoverModels(
            'openai_compat',
            'http://compat.example.com',
            null,
        );

        $this->assertNull($result->errorCode);
        $this->assertSame('openai-compatible', $result->serverType);
    }

    // ─── REQ-LLM-001: ollama → 'ollama-native' ──────────────────────────────────

    #[Test]
    public function ollama_returns_distinct_server_type(): void
    {
        Http::fake([
            'ollama.example.com/api/tags' => Http::response([
                'models' => [['name' => 'llama3:latest']],
            ], 200),
        ]);

        $result = $this->service->discoverModels(
            'ollama',
            'http://ollama.example.com',
            null,
        );

        $this->assertNull($result->errorCode);
        $this->assertSame('ollama-native', $result->serverType);
    }

    // ─── S16: 30x on primary → hard fail, NO fallback (REQ-LLM-019) ─────────────

    #[Test]
    public function s16_redirect_on_primary_hard_fails_no_fallback(): void
    {
        Http::fake([
            'redirect.example.com/v1/models' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1:6379/',
            ]),
            // Configure the would-be fallback so that if it gets called, the
            // assertSentCount(1) below will fail.
            'redirect.example.com/api/tags' => Http::response([
                'models' => [['name' => 'evil-fallback']],
            ], 200),
        ]);

        $result = $this->service->discoverModels(
            'openai',
            'https://redirect.example.com',
            null,
        );

        $this->assertSame('redirect_not_allowed', $result->errorCode);
        $this->assertSame([], $result->models);
        $this->assertSame([], $result->loaded);

        // Exactly ONE outbound request — the primary probe; no fallback follow-up.
        Http::assertSentCount(1);
        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), '/api/tags');
        });
        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), '127.0.0.1');
        });
    }

    // ─── S21: malformed JSON on /api/v0/models does not fail discovery (REQ-LLM-005)

    #[Test]
    public function s21_lmstudio_v0_models_malformed_json(): void
    {
        Http::fake([
            'http://100.64.10.5:1234/v1/models' => Http::response([
                'data' => [
                    ['id' => 'qwen2.5-coder-32b'],
                ],
            ], 200),
            'http://100.64.10.5:1234/api/v0/models' => Http::response(
                'not-valid-json{{{',
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $result = $this->service->discoverModels('lmstudio', 'http://100.64.10.5:1234', '');

        $this->assertNull($result->errorCode);
        $this->assertSame(['qwen2.5-coder-32b'], $result->models);
        $this->assertSame([], $result->loaded);
    }

    // ─── Sanity: timeout → errorCode 'timeout' (REQ-LLM-006) ────────────────────

    #[Test]
    public function timeout_returns_timeout_error(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('cURL error 28: Operation timed out after 10000 ms');
        });

        $result = $this->service->discoverModels(
            'openai',
            'https://api.openai.com/v1',
            null,
        );

        $this->assertSame('timeout', $result->errorCode);
        $this->assertSame([], $result->models);
    }

    // ─── Sanity: connect_failed for non-timeout connection error ────────────────

    #[Test]
    public function generic_connect_failure_returns_connect_failed(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('cURL error 7: Failed to connect to host');
        });

        $result = $this->service->discoverModels(
            'openai',
            'https://api.openai.com/v1',
            null,
        );

        $this->assertSame('connect_failed', $result->errorCode);
    }

    // ─── Sanity: 404 on primary triggers fallback path (REQ-LLM-001) ────────────

    #[Test]
    public function fallback_to_api_tags_on_primary_404(): void
    {
        Http::fake([
            'host.example.com/v1/models' => Http::response('', 404),
            'host.example.com/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llama3:latest'],
                    ['name' => 'qwen:latest'],
                ],
            ], 200),
        ]);

        $result = $this->service->discoverModels(
            'openai_compat',
            'http://host.example.com',
            null,
        );

        $this->assertNull($result->errorCode);
        // Ollama parser strips the tag suffix; both models become base names.
        $this->assertContains('llama3', $result->models);
        $this->assertContains('qwen', $result->models);
    }
}
