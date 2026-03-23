<?php

namespace Tests\Feature;

use App\Models\AnalysisRun;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectProviderSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_selected_provider_is_used_for_analysis_when_enabled_and_allowed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'is_active' => true,
            'role' => 'user',
        ]);

        $default = LlmProvider::query()->create([
            'name' => 'default',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://default.test/v1',
            'model' => 'default-model',
            'api_key' => 'x',
            'request_params' => ['temperature' => 0.0, 'max_tokens' => 200],
            'is_enabled' => true,
            'is_default' => true,
            'is_external' => false,
        ]);

        $chosen = LlmProvider::query()->create([
            'name' => 'chosen',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://chosen.test/v1',
            'model' => 'chosen-model',
            'api_key' => 'x',
            'request_params' => ['temperature' => 0.0, 'max_tokens' => 200],
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => false,
        ]);

        Http::fake(function ($request) {
            return Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'schema_version' => 'irb.first_pass.v2',
                        'fields' => [],
                    ])]],
                ],
            ], 200);
        });

        $this->actingAs($user);

        $this->post('/projects', ['name' => 'Study Provider'])->assertRedirect();
        $project = Project::query()->where('owner_user_id', $user->id)->firstOrFail();

        $file = UploadedFile::fake()->createWithContent('outline.txt', "Study title: Demo\n");
        $this->post(route('projects.documents.store', ['project' => $project->uuid]), [
            'documents' => [$file],
        ])->assertRedirect(route('projects.show', ['project' => $project->uuid, 'tab' => 'documents']));

        $this->post(route('projects.provider.update', ['project' => $project->uuid]), [
            'tab' => 'documents',
            'llm_provider_id' => $chosen->id,
        ])->assertRedirect(route('projects.show', ['project' => $project->uuid, 'tab' => 'documents']));

        $this->post(route('projects.analyze', ['project' => $project->uuid]))
            ->assertRedirect(route('projects.show', ['project' => $project->uuid, 'tab' => 'review']));

        $run = AnalysisRun::query()->where('project_id', $project->id)->orderByDesc('id')->firstOrFail();
        $this->assertSame($chosen->id, $run->llm_provider_id);
        $this->assertNotSame($default->id, $run->llm_provider_id);
    }

    public function test_cannot_select_external_provider_when_external_llms_are_disallowed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'is_active' => true,
            'role' => 'user',
        ]);

        $external = LlmProvider::query()->create([
            'name' => 'external',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://external.test/v1',
            'model' => 'external-model',
            'api_key' => 'x',
            'request_params' => ['temperature' => 0.0, 'max_tokens' => 200],
            'is_enabled' => true,
            'is_default' => false,
            'is_external' => true,
        ]);

        $this->actingAs($user);
        $this->post('/projects', ['name' => 'Study Ext'])->assertRedirect();
        $project = Project::query()->where('owner_user_id', $user->id)->firstOrFail();

        $resp = $this->post(route('projects.provider.update', ['project' => $project->uuid]), [
            'tab' => 'documents',
            'llm_provider_id' => $external->id,
        ]);

        $resp->assertSessionHasErrors(['llm_provider_id']);

        $project->refresh();
        $this->assertNull($project->llm_provider_id);
    }
}
