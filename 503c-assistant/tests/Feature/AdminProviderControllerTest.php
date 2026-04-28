<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LlmProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminProviderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_provider(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.providers.store'), [
            'name' => 'Test Provider',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://api.example.com/v1',
            'model' => 'gpt-4',
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
        ]);

        $response->assertRedirect(route('admin.index', ['tab' => 'providers']));

        $this->assertDatabaseHas('llm_providers', [
            'name' => 'Test Provider',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://api.example.com/v1',
            'model' => 'gpt-4',
        ]);
    }

    public function test_admin_can_create_provider_with_api_key(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.providers.store'), [
            'name' => 'Keyed Provider',
            'provider_type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test-key-abc123',
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
        ]);

        $response->assertRedirect(route('admin.index', ['tab' => 'providers']));

        $provider = LlmProvider::query()->where('name', 'Keyed Provider')->firstOrFail();

        // The api_key is stored encrypted; verify it decrypts correctly
        $this->assertSame('sk-test-key-abc123', $provider->api_key);
    }

    public function test_validation_rejects_invalid_provider_type(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.providers.store'), [
            'name' => 'Bad Provider',
            'provider_type' => 'invalid_type',
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => false,
        ]);

        $response->assertSessionHasErrors(['provider_type']);

        $this->assertDatabaseMissing('llm_providers', [
            'name' => 'Bad Provider',
        ]);
    }

    public function test_non_admin_cannot_create_provider(): void
    {
        $regularUser = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        $response = $this->actingAs($regularUser)->post(route('admin.providers.store'), [
            'name' => 'Unauthorized Provider',
            'provider_type' => 'openai_compat',
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => false,
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('llm_providers', [
            'name' => 'Unauthorized Provider',
        ]);
    }

    public function test_store_rejects_non_http_base_url(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        // file:// scheme must be rejected
        $response = $this->actingAs($admin)->post(route('admin.providers.store'), [
            'name' => 'Evil Provider',
            'provider_type' => 'openai_compat',
            'base_url' => 'file:///etc/passwd',
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['base_url']);

        // gopher:// scheme must be rejected
        $response2 = $this->actingAs($admin)->post(route('admin.providers.store'), [
            'name' => 'Evil Provider 2',
            'provider_type' => 'openai_compat',
            'base_url' => 'gopher://x',
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
        ]);

        $response2->assertRedirect();
        $response2->assertSessionHasErrors(['base_url']);

        // Non-URL string must be rejected
        $response3 = $this->actingAs($admin)->post(route('admin.providers.store'), [
            'name' => 'Evil Provider 3',
            'provider_type' => 'openai_compat',
            'base_url' => 'not a url at all',
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
        ]);

        $response3->assertRedirect();
        $response3->assertSessionHasErrors(['base_url']);
    }

    public function test_store_accepts_https_base_url(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.providers.store'), [
            'name' => 'OpenAI Provider',
            'provider_type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
        ]);

        $response->assertRedirect(route('admin.index', ['tab' => 'providers']));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('llm_providers', [
            'name' => 'OpenAI Provider',
            'base_url' => 'https://api.openai.com/v1',
        ]);
    }

    public function test_test_failure_persists_classification_not_body(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $provider = LlmProvider::query()->create([
            'name' => 'Test Provider For Classification',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://api.example.com/v1',
            'model' => 'gpt-4',
            'is_enabled' => true,
            'is_external' => true,
        ]);

        // Fake a 500 response whose body contains a sentinel string that must NOT leak
        Http::fake([
            '*' => Http::response('SECRET-LEAK-MARKER internal server error', 500),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.providers.test', $provider));

        $response->assertRedirect();

        // (a) last_test_error must NOT contain the raw body sentinel
        $provider->refresh();
        $this->assertStringNotContainsString('SECRET-LEAK-MARKER', (string) $provider->last_test_error);

        // (b) last_test_error must be a known classification label
        $validClassifications = ['http_4xx', 'http_5xx', 'connect_failed', 'timeout', 'tls_error', 'unexpected_response'];
        $this->assertContains($provider->last_test_error, $validClassifications);

        // (c) the session errors must not leak the sentinel string either
        $errors = $response->getSession()->get('errors');
        $errorBag = $errors?->getBag('default');
        $allMessages = $errorBag ? implode(' ', $errorBag->all()) : '';
        $this->assertStringNotContainsString('SECRET-LEAK-MARKER', $allMessages);
    }
}
