<?php

namespace Tests\Feature;

use App\Models\Export;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Unit\Support\DocxTestHelper;

class ProjectEndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_analyze_and_export_docx(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
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
            $chunkText = (string) (($chunks[0]['text'] ?? '') ?: '');
            $quote = $chunkText !== '' ? $chunkText : 'Evidence';

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
                                'quote' => $quote,
                            ]],
                        ]],
                    ])]],
                ],
            ], 200);
        });

        $this->actingAs($user);

        $resp = $this->post('/projects', ['name' => 'Study A']);
        $resp->assertRedirect();

        /** @var Project $project */
        $project = Project::query()->where('owner_user_id', $user->id)->firstOrFail();

        $file = UploadedFile::fake()->createWithContent('outline.txt', "Study title: Demo\n");

        $resp2 = $this->post(route('projects.documents.store', ['project' => $project->uuid]), [
            'documents' => [$file],
        ]);
        $resp2->assertRedirect(route('projects.show', ['project' => $project->uuid, 'tab' => 'documents']));

        $resp3 = $this->post(route('projects.analyze', ['project' => $project->uuid]));
        $resp3->assertRedirect(route('projects.show', ['project' => $project->uuid, 'tab' => 'review']));

        $resp4 = $this->post(route('projects.exports.store', ['project' => $project->uuid]));
        $resp4->assertRedirect(route('projects.show', ['project' => $project->uuid, 'tab' => 'export']));

        $export = Export::query()->where('project_id', $project->id)->orderByDesc('created_at')->firstOrFail();
        $this->assertSame('ready', $export->status);
        $this->assertNotNull($export->storage_path);

        $exportAbs = Storage::disk('local')->path((string) $export->storage_path);
        $xml = DocxTestHelper::unzipPrint($exportAbs, 'word/document.xml');
        $this->assertNotSame('', $xml);
        $this->assertStringContainsString('Suggested Value', $xml);
    }

    public function test_export_download_streams_decrypted_docx_when_export_is_encrypted(): void
    {
        Storage::fake('local');

        $keyId = 'download-key';
        $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        putenv('IRB_FILE_ENCRYPTION_KEYS='.$keyId.':'.base64_encode($key));
        putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID='.$keyId);

        try {
            $user = User::factory()->create([
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
                $chunkText = (string) (($chunks[0]['text'] ?? '') ?: '');

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
                                    'quote' => $chunkText !== '' ? $chunkText : 'Evidence',
                                ]],
                            ]],
                        ])]],
                    ],
                ], 200);
            });

            $this->actingAs($user);

            $this->post('/projects', ['name' => 'Study Enc'])->assertRedirect();
            $project = Project::query()->where('owner_user_id', $user->id)->firstOrFail();

            $file = UploadedFile::fake()->createWithContent('outline.txt', "Study title: Demo\n");
            $this->post(route('projects.documents.store', ['project' => $project->uuid]), ['documents' => [$file]])->assertRedirect();
            $this->post(route('projects.analyze', ['project' => $project->uuid]))->assertRedirect();
            $this->post(route('projects.exports.store', ['project' => $project->uuid]))->assertRedirect();

            $export = Export::query()->where('project_id', $project->id)->orderByDesc('created_at')->firstOrFail();
            $this->assertTrue((bool) $export->is_encrypted);
            $this->assertSame($keyId, $export->encryption_key_id);

            $download = $this->get(route('exports.download', ['export' => $export->uuid]));
            $download->assertOk();

            $content = $download->streamedContent();
            $this->assertStringStartsWith('PK', $content);
            $tmpDocx = tempnam(sys_get_temp_dir(), 'enc-download-');
            file_put_contents($tmpDocx, $content);
            $xml = DocxTestHelper::unzipPrint($tmpDocx, 'word/document.xml');
            @unlink($tmpDocx);
            $this->assertStringContainsString('Suggested Value', $xml);
        } finally {
            putenv('IRB_FILE_ENCRYPTION_KEYS');
            putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID');
        }
    }
}
