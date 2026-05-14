<?php

declare(strict_types=1);

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

        $startedAt = now()->subSeconds(10);
        $finishedAt = $startedAt->copy()->addSeconds(10);

        // Seed a payload matching the shape produced by ProjectAnalysisService::redactResponsePayload().
        // batches[0].field_keys has exactly 7 entries so the field count must render as "7", not "2".
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
            'response_payload' => [
                'batch_count' => 1,
                'batches' => [
                    [
                        'batch' => 1,
                        'rows' => 7,
                        'field_keys' => [
                            'field_one',
                            'field_two',
                            'field_three',
                            'field_four',
                            'field_five',
                            'field_six',
                            'field_seven',
                        ],
                        'evidence_count' => 14,
                    ],
                ],
            ],
        ]);

        $this->actingAs($admin);

        $resp = $this->get(route('admin.runs.show', ['runUuid' => $run->uuid]));
        $resp->assertOk();
        // UUID and status visible
        $resp->assertSee($run->uuid, false);
        $resp->assertSee('succeeded', false);
        // Duration heading is rendered (value is a separate element, exact integer varies)
        $resp->assertSee('Duration', false);
        // Field count must be 7 (sum of field_keys across batches), not 2 (count of top-level keys).
        $resp->assertSee('7', false);
        $resp->assertDontSee('>2<', false);
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

    public function test_overall_stats_reflect_true_total_not_capped_at_200(): void
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
            'name' => 'Stats Test',
            'status' => 'draft',
        ]);

        $provider = LlmProvider::query()->create([
            'name' => 'stats-provider',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://stats.test/v1',
            'model' => 'stats-model',
            'api_key' => 'x',
            'request_params' => ['temperature' => 0.0, 'max_tokens' => 20],
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => false,
        ]);

        $base = [
            'project_id' => $project->id,
            'llm_provider_id' => $provider->id,
            'created_by_user_id' => $admin->id,
            'prompt_version' => 'v1',
            'request_payload' => null,
            'response_payload' => null,
        ];

        // Seed 250 runs: 200 succeeded + 50 failed.
        // Using insert() in a single call is fast and avoids model overhead for 250 rows.
        $rows = [];
        $now = now();
        for ($i = 0; $i < 250; $i++) {
            $rows[] = array_merge($base, [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'status' => $i < 200 ? 'succeeded' : 'failed',
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ]);
        }

        // Split into chunks to avoid parameter limit on large inserts.
        foreach (array_chunk($rows, 50) as $chunk) {
            \DB::table('analysis_runs')->insert($chunk);
        }

        $this->actingAs($admin);

        $resp = $this->get(route('admin.index', ['tab' => 'observability']));
        $resp->assertOk();

        // The banner must show the actual total (250), not 200 (the in-memory cap).
        $resp->assertSee('250', false);
    }
}
