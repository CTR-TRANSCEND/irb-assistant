<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LlmProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
