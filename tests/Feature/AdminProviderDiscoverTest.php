<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\LlmProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-LLM-001 — End-to-end Feature coverage for the discover endpoint and
 * the test endpoint's stored-base_url re-validation.
 *
 * Covers acceptance scenarios:
 *   S7 (store path 422), S8 (non-admin 403 + no audit), S10 (loading-state markup),
 *   S14 (test-endpoint re-validates stored base_url with Laravel envelope),
 *   S20 (audit userinfo stripped + api_key=[REDACTED]),
 *   S22 (chat-probe 30x rejection — REQ-LLM-022),
 *   S23 (no-JS Alpine fallback markup).
 *
 * Strategy: Use Http::fake() to mock outbound LLM calls. Use RefreshDatabase
 * for isolation. Tests posting JSON to the discover endpoint use json() so
 * Laravel's standard 422 envelope is asserted via assertJsonValidationErrors.
 *
 * Traceability:
 *   REQ-LLM-008, REQ-LLM-012, REQ-LLM-013, REQ-LLM-014,
 *   REQ-LLM-015, REQ-LLM-016, REQ-LLM-018, REQ-LLM-021, REQ-LLM-022
 */
final class AdminProviderDiscoverTest extends TestCase
{
    use RefreshDatabase;

    private const DISCOVER_URI = '/admin/providers/discover';

    private const ADMIN_INDEX_URI = '/admin?tab=providers';

    protected function setUp(): void
    {
        parent::setUp();
        // Default: env loopback flag OFF so all tests start with strict SSRF.
        putenv('IRB_ALLOW_LLM_LOOPBACK');
        unset($_ENV['IRB_ALLOW_LLM_LOOPBACK'], $_SERVER['IRB_ALLOW_LLM_LOOPBACK']);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function nonAdmin(): User
    {
        return User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);
    }

    // ─── S7: store path rejects RFC1918 with 422 + base_url error (REQ-LLM-012) ─

    #[Test]
    public function s7_store_with_rfc1918_returns_422(): void
    {
        Http::fake();

        $response = $this->actingAs($this->admin())->post(route('admin.providers.store'), [
            'name' => 'Bad Provider',
            'provider_type' => 'openai_compat',
            'base_url' => 'http://192.168.1.5/',
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
        ]);

        // Browser POST → controller redirect with session error bag.
        $response->assertSessionHasErrors(['base_url']);

        $this->assertDatabaseMissing('llm_providers', [
            'name' => 'Bad Provider',
        ]);

        // No outbound HTTP during validation (REQ-LLM-010).
        Http::assertNothingSent();
    }

    #[Test]
    public function s7_store_with_oversize_url_returns_422(): void
    {
        Http::fake();

        $oversize = 'https://example.com/'.str_repeat('a', 2050);
        $this->assertGreaterThan(2048, strlen($oversize));

        $response = $this->actingAs($this->admin())->post(route('admin.providers.store'), [
            'name' => 'Oversize URL',
            'provider_type' => 'openai_compat',
            'base_url' => $oversize,
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
        ]);

        $response->assertSessionHasErrors(['base_url']);

        $this->assertDatabaseMissing('llm_providers', [
            'name' => 'Oversize URL',
        ]);

        Http::assertNothingSent();
    }

    // ─── S8: non-admin → 403, no audit row (REQ-LLM-013/014) ────────────────────

    #[Test]
    public function s8_non_admin_returns_403_no_audit(): void
    {
        Http::fake();

        $auditCountBefore = AuditEvent::query()->count();

        $response = $this->actingAs($this->nonAdmin())->postJson(self::DISCOVER_URI, [
            'provider_type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
        ]);

        $response->assertForbidden();

        // Per REQ-LLM-014: audit-row guarantee applies AFTER successful admin
        // authentication. Non-admin requests rejected by the admin middleware
        // do NOT generate audit rows.
        $this->assertSame(
            $auditCountBefore,
            AuditEvent::query()->count(),
            'No audit row should be written for non-admin requests rejected by middleware.',
        );

        Http::assertNothingSent();
    }

    // ─── S10: loading-state literal text rendered server-side (REQ-LLM-016) ─────

    #[Test]
    public function s10_admin_index_renders_loading_state_literals(): void
    {
        $response = $this->actingAs($this->admin())->get(self::ADMIN_INDEX_URI);

        $response->assertOk();

        // The literal strings used by Alpine's x-text expression must be present
        // in the server-rendered HTML so the no-JS fallback shows reasonable text
        // and the JS swap is exact (per REQ-LLM-016 ASCII-period invariant).
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('Discovering...', $body);
        $this->assertStringContainsString('Discover models', $body);
        // ASCII three-period ellipsis, NOT Unicode "…".
        $this->assertStringNotContainsString("Discovering\u{2026}", $body);
    }

    // ─── S14: test-endpoint re-validates stored base_url (REQ-LLM-018/021) ──────

    #[Test]
    public function s14_test_endpoint_revalidates_stored_base_url_with_envelope(): void
    {
        Http::fake();

        // Bypass FormRequest by direct DB insert: simulate a row whose base_url
        // was written before the validator existed (or by a different code path).
        $providerId = DB::table('llm_providers')->insertGetId([
            'name' => 'Legacy Private Provider',
            'provider_type' => 'openai_compat',
            'base_url' => 'http://10.0.0.5:8080/',
            'model' => 'gpt-4',
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $provider = LlmProvider::query()->findOrFail($providerId);

        $response = $this->actingAs($this->admin())->postJson(
            route('admin.providers.test', $provider),
        );

        // Per REQ-LLM-021, the test endpoint MUST re-validate via Validator+
        // ValidationException so the response carries Laravel's standard envelope.
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['base_url']);

        // The chat probe MUST NOT be issued.
        Http::assertNothingSent();
    }

    // ─── S20: audit userinfo stripped (REQ-LLM-014) ─────────────────────────────

    #[Test]
    public function s20_audit_base_url_userinfo_stripped(): void
    {
        Http::fake();

        // Use an RFC1918 host literal so validation deterministically fails
        // without depending on external DNS for hypothetical hostnames.
        $response = $this->actingAs($this->admin())->postJson(self::DISCOVER_URI, [
            'provider_type' => 'openai',
            'base_url' => 'http://alice:secret@10.0.0.5/v1',
            'api_key' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['base_url']);

        $audit = AuditEvent::query()
            ->where('event_type', 'admin.provider.models_discovered')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit, 'expected an audit row for validation_failed discover request');

        $payload = $audit->payload ?? [];
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('base_url', $payload);

        $loggedBaseUrl = (string) $payload['base_url'];
        $this->assertStringNotContainsString('alice', $loggedBaseUrl);
        $this->assertStringNotContainsString('secret', $loggedBaseUrl);
        $this->assertStringNotContainsString('alice:secret', $loggedBaseUrl);
        $this->assertStringContainsString('10.0.0.5', $loggedBaseUrl);

        // The full audit row (including the JSON-serialised payload column)
        // must not contain the userinfo substring anywhere.
        $rawRow = (string) DB::table('audit_events')->where('id', $audit->id)->value('payload');
        $this->assertStringNotContainsString('alice:secret', $rawRow);
    }

    #[Test]
    public function s20_audit_api_key_redacted(): void
    {
        Http::fake();

        // Force validation_failed via RFC1918 host so an audit row is written
        // by DiscoverProviderRequest::failedValidation() (REQ-LLM-014).
        $response = $this->actingAs($this->admin())->postJson(self::DISCOVER_URI, [
            'provider_type' => 'openai',
            'base_url' => 'http://10.0.0.5/v1',
            'api_key' => 'sk-secret123',
        ]);

        $response->assertStatus(422);

        $audit = AuditEvent::query()
            ->where('event_type', 'admin.provider.models_discovered')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit, 'expected an audit row for validation_failed discover request');

        $payload = $audit->payload ?? [];
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('api_key', $payload);

        // Binary criterion: literal '[REDACTED]'. Both "absent" and any value
        // other than '[REDACTED]' are non-conforming.
        $this->assertSame('[REDACTED]', $payload['api_key']);

        // Defence in depth: the secret must not appear anywhere in the row.
        $rawRow = (string) DB::table('audit_events')->where('id', $audit->id)->value('payload');
        $this->assertStringNotContainsString('sk-secret123', $rawRow);

        // Nor should it appear in the response body.
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString('sk-secret123', $body);
    }

    #[Test]
    public function s9_response_does_not_echo_api_key_on_auth_failure(): void
    {
        // Service-level auth_failed path (NOT validation_failed). Mock the
        // upstream 401 response and assert the api_key is not echoed.
        Http::fake([
            'api.openai.com/v1/models' => Http::response([
                'error' => ['message' => 'Invalid API key'],
            ], 401),
        ]);

        $response = $this->actingAs($this->admin())->postJson(self::DISCOVER_URI, [
            'provider_type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-bogus-feature',
        ]);

        // Service-level errors return 200 with success=false (per controller
        // contract enumerated by team-lead); the caller distinguishes via
        // body.success. A non-2xx response is also acceptable as long as the
        // api_key is not echoed.
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString('sk-bogus-feature', $body);

        // Audit row should be written and api_key redacted.
        $audit = AuditEvent::query()
            ->where('event_type', 'admin.provider.models_discovered')
            ->latest('id')
            ->first();

        if ($audit !== null) {
            $payload = $audit->payload ?? [];
            $this->assertIsArray($payload);
            if (array_key_exists('api_key', $payload)) {
                $this->assertSame('[REDACTED]', $payload['api_key']);
            }
        }
    }

    // ─── S22: chat-probe rejects 30x (REQ-LLM-022) ──────────────────────────────

    #[Test]
    public function s22_chat_probe_redirect_rejected(): void
    {
        // Provider with a public, validator-passing base_url.
        $providerId = DB::table('llm_providers')->insertGetId([
            'name' => 'Public Chat Provider',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://chat.example.com/v1',
            'model' => 'gpt-4',
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $provider = LlmProvider::query()->findOrFail($providerId);

        // Mock the chat-probe endpoint to return a 302 redirecting to a
        // private IP. The redirect MUST NOT be followed (REQ-LLM-022).
        Http::fake([
            'chat.example.com/*' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1:6379/',
            ]),
            // Sentinel: if Http::fake catches a request to 127.0.0.1 we'll
            // see it in assertSentCount(1) failure.
            '127.0.0.1/*' => Http::response('should-not-be-called', 200),
        ]);

        $response = $this->actingAs($this->admin())->post(
            route('admin.providers.test', $provider),
        );

        // Existing failure-path convention is a redirect with session error,
        // NOT a 2xx success.
        $this->assertNotSame(200, $response->status(), 'chat-probe must not return success on 30x');

        // Exactly one outbound HTTP request — the primary chat-probe; the
        // 302 was not followed and no fallback was issued.
        Http::assertSentCount(1);
        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), '127.0.0.1');
        });

        // Defence in depth: the private IP must not appear in the response body.
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString('127.0.0.1', $body);
    }

    // ─── S23: no-JS Alpine fallback markup (REQ-LLM-015) ────────────────────────

    #[Test]
    public function s23_no_js_alpine_fallback_markup(): void
    {
        $response = $this->actingAs($this->admin())->get(self::ADMIN_INDEX_URI);

        $response->assertOk();
        $body = $response->getContent();
        $this->assertIsString($body);

        // Server renders <details ... open> so manual-override is visible
        // without JS. Alpine collapses it on init when JS runs (REQ-LLM-015).
        $this->assertMatchesRegularExpression(
            '/<details\b[^>]*\bopen\b[^>]*>/',
            $body,
            'manual-override <details> must render with the open attribute server-side',
        );

        // The manual-override input must be present in the DOM.
        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*\bname=("|\')model_manual\1[^>]*>/',
            $body,
            'manual-override input[name="model_manual"] must be present',
        );
    }
}
