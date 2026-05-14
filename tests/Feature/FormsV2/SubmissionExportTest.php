<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Models\Export;
use App\Models\Study;
use App\Models\Submission;
use App\Models\User;
use App\Services\SubmissionDocxExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-004 §H.7
 * Covers SubmissionExportController::store() and ::download().
 *
 * REQ-IRB-FORMSV2-064: Only HRP-503 and HRP-503c are exportable.
 * REQ-IRB-FORMSV2-060: HRP-398 rejected with 422.
 * REQ-IRB-FORMSV2-069: Filename uses {title}-{form_code}.docx.
 */
class SubmissionExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Export Study']);
    }

    // ── store — HRP-398 rejected ───────────────────────────────────────────────

    #[Test]
    public function store_rejects_hrp398_with_422(): void
    {
        $this->actingAs($this->user)
            ->postJson(
                route('submissions.exports.store', [
                    'study_uuid' => $this->study->uuid,
                    'form_code' => 'HRP-398',
                ]),
            )
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'HRP-398 is guidance-only; not exportable']);
    }

    #[Test]
    public function store_hrp398_web_request_redirects_with_error_flash(): void
    {
        // Outstanding #75 (Batch C C1): non-XHR posts must redirect, not
        // return raw 422 JSON in the browser. The error flash surfaces via
        // the global session('error') → toast handler.
        $this->actingAs($this->user)
            ->post(
                route('submissions.exports.store', [
                    'study_uuid' => $this->study->uuid,
                    'form_code' => 'HRP-398',
                ]),
            )
            ->assertRedirect(route('submissions.show', [
                'uuid' => $this->study->uuid,
                'form_code' => 'HRP-398',
            ]))
            ->assertSessionHas('error', 'HRP-398 is guidance-only; not exportable.');
    }

    // ── store — HRP-503c accepted (mocked service) ────────────────────────────

    #[Test]
    public function store_hrp503c_creates_export_and_redirects_to_download(): void
    {
        $submission = $this->getSubmissionByFormCode('HRP-503c');

        // Write a fake export file on the faked disk
        $fakePath = 'exports/test-export.docx';
        Storage::disk('local')->put($fakePath, 'fake docx content');

        $exportUuid = (string) Str::uuid();

        // Mock SubmissionDocxExportService to avoid real file manipulation
        $this->mock(SubmissionDocxExportService::class, function ($mock) use ($submission, $exportUuid, $fakePath): void {
            $mock->shouldReceive('generate')
                ->once()
                ->with(\Mockery::on(fn ($s) => $s->id === $submission->id), \Mockery::type('int'))
                ->andReturn(Export::query()->create([
                    'uuid' => $exportUuid,
                    'project_id' => null,
                    'submission_id' => $submission->id,
                    'status' => 'ready',
                    'storage_disk' => 'local',
                    'storage_path' => $fakePath,
                    'is_encrypted' => false,
                    'created_by_user_id' => $this->user->id,
                ]));
        });

        // Outstanding #75 (Batch C C1): non-XHR must redirect to the download
        // URL so the browser streams the DOCX immediately. Previously the
        // controller redirected to studies.show with a flash that included no
        // download link → DOCX generated but unreachable.
        $this->actingAs($this->user)
            ->post(route('submissions.exports.store', [
                'study_uuid' => $this->study->uuid,
                'form_code' => 'HRP-503c',
            ]))
            ->assertRedirect(route('submissions.exports.download', ['export_uuid' => $exportUuid]));

        $this->assertDatabaseHas('exports', [
            'uuid' => $exportUuid,
            'submission_id' => $submission->id,
            'status' => 'ready',
        ]);
    }

    #[Test]
    public function store_returns_404_for_non_owner(): void
    {
        $other = User::factory()->create(['is_approved' => true]);
        $otherStudy = Study::createForUser($other->id, ['application_title' => 'Other']);

        $this->actingAs($this->user)
            ->post(route('submissions.exports.store', [
                'study_uuid' => $otherStudy->uuid,
                'form_code' => 'HRP-503c',
            ]))
            ->assertNotFound();
    }

    // ── download ──────────────────────────────────────────────────────────────

    #[Test]
    public function download_streams_export_file_for_owner(): void
    {
        $submission = $this->getSubmissionByFormCode('HRP-503c');

        $fakePath = 'exports/my-export.docx';
        Storage::disk('local')->put($fakePath, 'fake docx content bytes');

        $exportUuid = (string) Str::uuid();
        Export::query()->create([
            'uuid' => $exportUuid,
            'project_id' => null,
            'submission_id' => $submission->id,
            'status' => 'ready',
            'storage_disk' => 'local',
            'storage_path' => $fakePath,
            'is_encrypted' => false,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('submissions.exports.download', ['export_uuid' => $exportUuid]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    #[Test]
    public function download_filename_contains_study_title_and_form_code(): void
    {
        $submission = $this->getSubmissionByFormCode('HRP-503c');

        $fakePath = 'exports/my-export.docx';
        Storage::disk('local')->put($fakePath, 'fake content');

        $exportUuid = (string) Str::uuid();
        Export::query()->create([
            'uuid' => $exportUuid,
            'project_id' => null,
            'submission_id' => $submission->id,
            'status' => 'ready',
            'storage_disk' => 'local',
            'storage_path' => $fakePath,
            'is_encrypted' => false,
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('submissions.exports.download', ['export_uuid' => $exportUuid]));

        $response->assertOk();
        $contentDisposition = $response->headers->get('Content-Disposition') ?? '';
        $this->assertStringContainsString('Export Study', $contentDisposition);
        $this->assertStringContainsString('HRP-503c', $contentDisposition);
        $this->assertStringContainsString('.docx', $contentDisposition);
    }

    #[Test]
    public function download_returns_404_for_non_owner(): void
    {
        $other = User::factory()->create(['is_approved' => true]);
        $otherStudy = Study::createForUser($other->id, ['application_title' => 'Other']);
        $otherSub = $otherStudy->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503c'))
            ->first();

        $fakePath = 'exports/other-export.docx';
        Storage::disk('local')->put($fakePath, 'content');

        $exportUuid = (string) Str::uuid();
        Export::query()->create([
            'uuid' => $exportUuid,
            'project_id' => null,
            'submission_id' => $otherSub->id,
            'status' => 'ready',
            'storage_disk' => 'local',
            'storage_path' => $fakePath,
            'is_encrypted' => false,
            'created_by_user_id' => $other->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('submissions.exports.download', ['export_uuid' => $exportUuid]))
            ->assertNotFound();
    }

    #[Test]
    public function download_returns_404_if_export_not_ready(): void
    {
        $submission = $this->getSubmissionByFormCode('HRP-503c');

        $exportUuid = (string) Str::uuid();
        Export::query()->create([
            'uuid' => $exportUuid,
            'project_id' => null,
            'submission_id' => $submission->id,
            'status' => 'generating', // not ready
            'storage_disk' => 'local',
            'storage_path' => null,
            'is_encrypted' => false,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('submissions.exports.download', ['export_uuid' => $exportUuid]))
            ->assertNotFound();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getSubmissionByFormCode(string $formCode): Submission
    {
        return $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', $formCode))
            ->with(['formDefinition', 'study'])
            ->firstOrFail();
    }
}
