<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DocumentChunk;
use App\Models\FieldDefinition;
use App\Models\FieldEvidence;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectFieldValue;
use App\Models\AnalysisRun;
use App\Models\TemplateControl;
use App\Models\TemplateControlMapping;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\LlmChatService;
use App\Services\ProjectAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_writes_suggestion_and_evidence(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'name' => 'A',
            'status' => 'draft',
        ]);

        $field = FieldDefinition::query()->create([
            'key' => 'title',
            'label' => 'Title',
            'section' => 'HRP-503c',
            'sort_order' => 1,
            'is_required' => true,
            'input_type' => 'text',
            'question_text' => 'Title',
        ]);

        $value = ProjectFieldValue::query()->create([
            'project_id' => $project->id,
            'field_definition_id' => $field->id,
            'status' => 'missing',
        ]);

        $tpl = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'T',
            'sha256' => hash('sha256', 't'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/none.docx',
            'is_active' => true,
            'uploaded_by_user_id' => $user->id,
        ]);

        $ctrl = TemplateControl::query()->create([
            'template_version_id' => $tpl->id,
            'part' => 'document',
            'control_index' => 0,
            'context_before' => 'Title:',
            'context_after' => null,
            'placeholder_text' => '',
            'signature_sha256' => hash('sha256', 'x'),
        ]);

        TemplateControlMapping::query()->create([
            'template_version_id' => $tpl->id,
            'template_control_id' => $ctrl->id,
            'field_definition_id' => $field->id,
            'mapped_by_user_id' => $user->id,
        ]);

        $doc = ProjectDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'uploaded_by_user_id' => $user->id,
            'original_filename' => 'outline.txt',
            'storage_disk' => 'local',
            'storage_path' => 'projects/x/y.txt',
            'sha256' => null,
            'mime_type' => 'text/plain',
            'file_ext' => 'txt',
            'size_bytes' => 1,
            'kind' => 'txt',
            'extraction_status' => 'completed',
        ]);

        $chunk = DocumentChunk::query()->create([
            'project_document_id' => $doc->id,
            'chunk_index' => 0,
            'page_number' => null,
            'source_locator' => null,
            'heading' => null,
            'text' => 'Study title: My Suggested Title',
            'text_sha256' => hash('sha256', 'Study title: My Suggested Title'),
            'start_offset' => null,
            'end_offset' => null,
        ]);

        $provider = LlmProvider::query()->create([
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

        Http::fake([
            'example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'fields' => [[
                            'field_key' => 'title',
                            'value' => 'My Suggested Title',
                            'confidence' => 0.7,
                            'evidence' => [[
                                'chunk_id' => $chunk->id,
                                'quote' => 'Study title: My Suggested Title',
                            ]],
                        ]],
                    ])]],
                ],
            ], 200),
        ]);

        putenv('IRB_ANALYSIS_BATCH_SIZE=50');
        putenv('IRB_MAX_CHUNKS_SENT=5');

        $svc = app(ProjectAnalysisService::class);
        $svc->runFirstPass($project, $provider, $user->id, app(LlmChatService::class));

        $value->refresh();
        $this->assertSame('suggested', $value->status);
        $this->assertSame('My Suggested Title', $value->suggested_value);
        $this->assertNotNull($value->analysis_run_id);
        $this->assertDatabaseHas('field_evidence', [
            'project_field_value_id' => $value->id,
            'document_chunk_id' => $chunk->id,
        ]);

        $evidence = FieldEvidence::query()->where('project_field_value_id', $value->id)->first();
        $this->assertNotNull($evidence);
        $this->assertNotNull($evidence->start_offset);
        $this->assertNotNull($evidence->end_offset);
        $this->assertSame(0, (int) $evidence->start_offset);
        $this->assertSame(mb_strlen('Study title: My Suggested Title'), (int) $evidence->end_offset);

        $run = AnalysisRun::query()->where('project_id', $project->id)->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertIsArray($run->request_payload);
        $this->assertArrayHasKey('chunk_meta', $run->request_payload);
        $this->assertArrayNotHasKey('chunks', $run->request_payload);
        $this->assertIsArray($run->response_payload);
        $this->assertArrayHasKey('batch_count', $run->response_payload);
        $this->assertNotNull($run->request_payload_enc);
        $this->assertNotNull($run->response_payload_enc);

        $reqRaw = json_decode(Crypt::decryptString((string) $run->request_payload_enc), true);
        $this->assertIsArray($reqRaw);
        $this->assertArrayHasKey('chunks', $reqRaw);
        $this->assertSame('Study title: My Suggested Title', $reqRaw['chunks'][0]['text'] ?? null);

        $respRaw = json_decode(Crypt::decryptString((string) $run->response_payload_enc), true);
        $this->assertIsArray($respRaw);
        $this->assertSame('Study title: My Suggested Title', $respRaw['batches'][0]['raw']['fields'][0]['evidence'][0]['quote'] ?? null);
    }

    public function test_analysis_ignores_suggestion_when_evidence_quote_does_not_match_chunk(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'name' => 'A',
            'status' => 'draft',
        ]);

        $field = FieldDefinition::query()->create([
            'key' => 'title',
            'label' => 'Title',
            'section' => 'HRP-503c',
            'sort_order' => 1,
            'is_required' => true,
            'input_type' => 'text',
            'question_text' => 'Title',
        ]);

        $value = ProjectFieldValue::query()->create([
            'project_id' => $project->id,
            'field_definition_id' => $field->id,
            'status' => 'missing',
        ]);

        $tpl = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'T',
            'sha256' => hash('sha256', 't'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/none.docx',
            'is_active' => true,
            'uploaded_by_user_id' => $user->id,
        ]);

        $ctrl = TemplateControl::query()->create([
            'template_version_id' => $tpl->id,
            'part' => 'document',
            'control_index' => 0,
            'context_before' => 'Title:',
            'context_after' => null,
            'placeholder_text' => '',
            'signature_sha256' => hash('sha256', 'x'),
        ]);

        TemplateControlMapping::query()->create([
            'template_version_id' => $tpl->id,
            'template_control_id' => $ctrl->id,
            'field_definition_id' => $field->id,
            'mapped_by_user_id' => $user->id,
        ]);

        $doc = ProjectDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'uploaded_by_user_id' => $user->id,
            'original_filename' => 'outline.txt',
            'storage_disk' => 'local',
            'storage_path' => 'projects/x/y.txt',
            'sha256' => null,
            'mime_type' => 'text/plain',
            'file_ext' => 'txt',
            'size_bytes' => 1,
            'kind' => 'txt',
            'extraction_status' => 'completed',
        ]);

        $chunk = DocumentChunk::query()->create([
            'project_document_id' => $doc->id,
            'chunk_index' => 0,
            'page_number' => null,
            'source_locator' => null,
            'heading' => null,
            'text' => 'Study title: My Suggested Title',
            'text_sha256' => hash('sha256', 'Study title: My Suggested Title'),
            'start_offset' => null,
            'end_offset' => null,
        ]);

        $provider = LlmProvider::query()->create([
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

        Http::fake([
            'example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'fields' => [[
                            'field_key' => 'title',
                            'value' => 'My Suggested Title',
                            'confidence' => 0.7,
                            'evidence' => [[
                                'chunk_id' => $chunk->id,
                                'quote' => 'This quote is not in the chunk',
                            ]],
                        ]],
                    ])]],
                ],
            ], 200),
        ]);

        putenv('IRB_ANALYSIS_BATCH_SIZE=50');
        putenv('IRB_MAX_CHUNKS_SENT=5');

        $svc = app(ProjectAnalysisService::class);
        $svc->runFirstPass($project, $provider, $user->id, app(LlmChatService::class));

        $value->refresh();
        $this->assertSame('missing', $value->status);
        $this->assertNull($value->suggested_value);
        $this->assertNull($value->analysis_run_id);
        $this->assertDatabaseMissing('field_evidence', [
            'project_field_value_id' => $value->id,
        ]);
    }
}
