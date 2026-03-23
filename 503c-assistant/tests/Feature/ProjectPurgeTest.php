<?php

namespace Tests\Feature;

use App\Models\AnalysisRun;
use App\Models\AuditEvent;
use App\Models\Export;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectFieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_a_project_and_purge_sensitive_data_while_retaining_redacted_audit_events(): void
    {
        Storage::fake('local');

        $user = \App\Models\User::factory()->create([
            'is_active' => true,
            'role' => 'user',
        ]);

        LlmProvider::query()->create([
            'name' => 'test',
            'provider_type' => 'openai_compat',
            'base_url' => 'https://example.test/v1',
            'model' => 'test-model',
            'api_key' => 'x',
            'request_params' => ['temperature' => 0.0, 'max_tokens' => 200],
            'is_enabled' => true,
            'is_default' => true,
            'is_external' => false,
        ]);

        Http::fake(function ($request) {
            $data = $request->data();
            $messages = $data['messages'] ?? [];
            $userMsg = $messages[1]['content'] ?? null;
            if (! is_string($userMsg)) {
                return Http::response(['error' => 'bad request'], 400);
            }

            $prompt = json_decode($userMsg, true);
            if (! is_array($prompt)) {
                return Http::response(['error' => 'bad prompt'], 400);
            }

            $fieldKey = (string) (($prompt['expected_field_keys'][0] ?? '') ?: ($prompt['fields'][0]['field_key'] ?? ''));
            $chunks = $prompt['chunks'] ?? [];
            $chunkId = $chunks[0]['chunk_id'] ?? null;
            $chunkText = (string) (($chunks[0]['text'] ?? '') ?: 'Evidence');

            return Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'schema_version' => 'irb.first_pass.v2',
                        'fields' => [[
                            'field_key' => $fieldKey,
                            'value' => 'Suggested Value',
                            'confidence' => 0.7,
                            'evidence' => [[
                                'chunk_id' => $chunkId,
                                'quote' => $chunkText,
                            ]],
                        ]],
                    ])]],
                ],
            ], 200);
        });

        $this->actingAs($user);

        $this->post('/projects', ['name' => 'Study Purge'])->assertRedirect();

        $project = Project::query()->where('owner_user_id', $user->id)->firstOrFail();
        $projectId = $project->id;
        $projectUuid = $project->uuid;
        $projectName = $project->name;

        $file = UploadedFile::fake()->createWithContent('outline.txt', "Study title: Demo\n");
        $this->post(route('projects.documents.store', ['project' => $project->uuid]), ['documents' => [$file]])
            ->assertRedirect(route('projects.show', ['project' => $project->uuid, 'tab' => 'documents']));

        $this->post(route('projects.analyze', ['project' => $project->uuid]))
            ->assertRedirect(route('projects.show', ['project' => $project->uuid, 'tab' => 'review']));

        $this->post(route('projects.exports.store', ['project' => $project->uuid]))
            ->assertRedirect(route('projects.show', ['project' => $project->uuid, 'tab' => 'export']));

        $this->assertGreaterThan(0, ProjectDocument::query()->where('project_id', $projectId)->count());
        $this->assertGreaterThan(0, ProjectFieldValue::query()->where('project_id', $projectId)->count());
        $this->assertGreaterThan(0, AnalysisRun::query()->where('project_id', $projectId)->count());
        $this->assertGreaterThan(0, Export::query()->where('project_id', $projectId)->count());
        $this->assertGreaterThan(0, AuditEvent::query()->where('project_id', $projectId)->count());

        $this->delete(route('projects.destroy', ['project' => $projectUuid]), [
            'confirm_name' => $projectName,
            'password' => 'password',
        ])->assertRedirect(route('projects.index'));

        $this->assertNull(Project::query()->where('id', $projectId)->first());
        $this->assertSame(0, ProjectDocument::query()->where('project_id', $projectId)->count());
        $this->assertSame(0, ProjectFieldValue::query()->where('project_id', $projectId)->count());
        $this->assertSame(0, AnalysisRun::query()->where('project_id', $projectId)->count());
        $this->assertSame(0, Export::query()->where('project_id', $projectId)->count());

        $created = AuditEvent::query()
            ->where('event_type', 'project.created')
            ->where('entity_uuid', $projectUuid)
            ->firstOrFail();

        $this->assertNull($created->project_id);
        $this->assertSame(['redacted' => true, 'reason' => 'project purged'], $created->payload);

        $purged = AuditEvent::query()
            ->where('event_type', 'project.purged')
            ->where('entity_uuid', $projectUuid)
            ->firstOrFail();

        $this->assertNull($purged->project_id);
        $this->assertSame($projectName, $purged->payload['name'] ?? null);
    }
}
