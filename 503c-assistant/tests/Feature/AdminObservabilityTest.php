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
        // Provider usage metrics table should render
        $resp->assertSee('Provider Usage', false);
    }

    public function test_admin_can_view_run_detail_without_payloads(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
            'role' => 'admin',
        ]);

        $project = Project::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'owner_user_id' => $admin->id,
            'name' => 'Detail Test Project',
            'status' => 'draft',
        ]);

        $provider = LlmProvider::query()->create([
            'name' => 'detail-provider',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://detail.test/v1',
            'model' => 'detail-model',
            'api_key' => 'x',
            'request_params' => ['temperature' => 0.0, 'max_tokens' => 20],
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => false,
        ]);

        $secret = 'SECRET_DETAIL_PAYLOAD_XYZ';

        $startedAt  = now()->subSeconds(10);
        $finishedAt = $startedAt->copy()->addSeconds(10);

        $run = AnalysisRun::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'project_id' => $project->id,
            'llm_provider_id' => $provider->id,
            'created_by_user_id' => $admin->id,
            'status' => 'succeeded',
            'prompt_version' => 'v2',
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'request_payload' => ['secret' => $secret],
            'response_payload' => ['field_a' => $secret, 'field_b' => $secret],
        ]);

        $this->actingAs($admin);

        $resp = $this->get(route('admin.runs.show', ['runUuid' => $run->uuid]));
        $resp->assertOk();
        // UUID and status visible
        $resp->assertSee($run->uuid, false);
        $resp->assertSee('succeeded', false);
        // Duration heading is rendered (value is a separate element, exact integer varies)
        $resp->assertSee('Duration', false);
        // Field count from response_payload (2 keys)
        $resp->assertSee('2', false);
        // Provider and project visible
        $resp->assertSee('detail-provider', false);
        $resp->assertSee('Detail Test Project', false);
        // Raw payload values must NOT appear
        $resp->assertDontSee($secret, false);
    }

    public function test_non_admin_cannot_view_run_detail(): void
    {
        $regularUser = User::factory()->create([
            'is_active' => true,
            'role' => 'user',
        ]);

        $this->actingAs($regularUser);

        $resp = $this->get(route('admin.runs.show', ['runUuid' => 'nonexistent-uuid']));
        $resp->assertForbidden();
    }
}
