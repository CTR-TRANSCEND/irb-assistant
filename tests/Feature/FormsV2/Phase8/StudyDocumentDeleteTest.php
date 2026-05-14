<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2\Phase8;

use App\Models\ProjectDocument;
use App\Models\Study;
use App\Models\User;
use App\Services\DocumentExtractionService;
use App\Services\FileEncryptionService;
use App\Services\MalwareScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-008 — Study-level document delete (Phase 8 HOTFIX).
 *
 * REQ-P8-003 (delete route present), REQ-P8-004 (ownership enforced),
 * REQ-P8-005 (audit emitted).
 */
class StudyDocumentDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->mockEncryption();
        $this->mockExtraction();
        $this->mockMalwareScanner();

        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Delete Test Study']);
    }

    // ── Happy-path ──────────────────────────────────────────────────────────────

    #[Test]
    public function delete_own_doc_removes_row_and_file(): void
    {
        $doc = $this->uploadDocument('to-delete.pdf');

        $response = $this->actingAs($this->user)
            ->delete(route('studies.documents.destroy', [
                'study_uuid' => $this->study->uuid,
                'doc_uuid' => $doc->uuid,
            ]));

        $response->assertRedirect(route('studies.show', ['uuid' => $this->study->uuid]));
        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('project_documents', ['uuid' => $doc->uuid]);
    }

    // ── Security / rejection tests ──────────────────────────────────────────────

    #[Test]
    public function delete_non_owner_doc_returns_404(): void
    {
        $doc = $this->uploadDocument('protected.pdf');

        $otherUser = User::factory()->create(['is_approved' => true]);

        $response = $this->actingAs($otherUser)
            ->delete(route('studies.documents.destroy', [
                'study_uuid' => $this->study->uuid,
                'doc_uuid' => $doc->uuid,
            ]));

        $response->assertNotFound();
        $this->assertDatabaseHas('project_documents', ['uuid' => $doc->uuid]);
    }

    #[Test]
    public function delete_doc_from_different_study_returns_404(): void
    {
        // Create a second study owned by the same user
        $otherStudy = Study::createForUser($this->user->id, ['application_title' => 'Other Study']);

        // Upload doc to the first study
        $doc = $this->uploadDocument('study1-doc.pdf');

        // Try to delete it via the OTHER study's route
        $response = $this->actingAs($this->user)
            ->delete(route('studies.documents.destroy', [
                'study_uuid' => $otherStudy->uuid,
                'doc_uuid' => $doc->uuid,
            ]));

        $response->assertNotFound();
        $this->assertDatabaseHas('project_documents', ['uuid' => $doc->uuid]);
    }

    #[Test]
    public function audit_event_emitted_on_delete(): void
    {
        $doc = $this->uploadDocument('audit-delete.pdf');

        $this->actingAs($this->user)
            ->delete(route('studies.documents.destroy', [
                'study_uuid' => $this->study->uuid,
                'doc_uuid' => $doc->uuid,
            ]));

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'study.document.deleted',
            'entity_type' => 'study',
            'entity_id' => $this->study->id,
            'actor_user_id' => $this->user->id,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Upload a document to $this->study via the store route and return the persisted row.
     */
    private function uploadDocument(string $filename): ProjectDocument
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $mime = $ext === 'pdf' ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        $file = UploadedFile::fake()->create($filename, 20, $mime);

        $this->actingAs($this->user)
            ->post(
                route('studies.documents.store', ['study_uuid' => $this->study->uuid]),
                ['file' => $file]
            );

        return ProjectDocument::where('study_id', $this->study->id)
            ->where('original_filename', $filename)
            ->firstOrFail();
    }

    private function mockEncryption(): void
    {
        $this->instance(FileEncryptionService::class, new class extends FileEncryptionService
        {
            public function __construct()
            {
                // Skip parent — no sodium key needed
            }

            public function isEnabled(): bool
            {
                return false;
            }

            /** @return array{storage_path: string, encryption_key_id: null} */
            public function encryptStoredFile(string $disk, string $sourcePath, ?string $targetPath = null): array
            {
                return ['storage_path' => $sourcePath, 'encryption_key_id' => null];
            }
        });
    }

    private function mockExtraction(): void
    {
        $this->instance(DocumentExtractionService::class, new class(app(FileEncryptionService::class)) extends DocumentExtractionService
        {
            public function extract(\App\Models\ProjectDocument $document): void
            {
                // No-op
            }
        });
    }

    private function mockMalwareScanner(): void
    {
        $this->instance(MalwareScanService::class, new class extends MalwareScanService
        {
            public function scanFile(string $absolutePath): array
            {
                return [
                    'status' => 'unavailable',
                    'engine' => null,
                    'result' => null,
                    'signature' => null,
                    'error' => null,
                    'scanned_at' => null,
                ];
            }
        });
    }
}
