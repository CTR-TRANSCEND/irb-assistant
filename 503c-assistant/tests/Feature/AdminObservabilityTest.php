<?php

namespace Tests\Feature;

use App\Models\AnalysisRun;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_observability_without_leaking_payloads(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'is_active' => true,
            'role' => 'user',
        ]);

        $project = Project::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'owner_user_id' => $user->id,
            'name' => 'Study Obs',
            'status' => 'draft',
        ]);

        $provider = LlmProvider::query()->create([
            'name' => 'obs-provider',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://obs.test/v1',
            'model' => 'obs-model',
            'api_key' => 'x',
            'request_params' => ['temperature' => 0.0, 'max_tokens' => 20],
            'is_enabled' => true,
            'is_default' => true,
            'is_external' => false,
        ]);

        $secret = 'SHOULD_NOT_LEAK_123';

        $run = AnalysisRun::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'project_id' => $project->id,
            'llm_provider_id' => $provider->id,
            'created_by_user_id' => $admin->id,
            'status' => 'completed',
            'prompt_version' => 'test',
            'started_at' => now()->subSeconds(2),
            'finished_at' => now(),
            'request_payload' => ['secret' => $secret],
            'response_payload' => ['secret' => $secret],
        ]);

        $this->actingAs($admin);

        $resp = $this->get(route('admin.index', ['tab' => 'observability']));
        $resp->assertOk();
        $resp->assertSee('Analysis Runs', false);
        $resp->assertSee(substr($run->uuid, 0, 8), false);
        $resp->assertSee('obs-provider', false);
        $resp->assertDontSee($secret, false);
    }
}
