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
 * SPEC-IRB-FORMSV2-008 — Study-level document upload (Phase 8 HOTFIX).
 *
 * REQ-P8-001 (route present), REQ-P8-002 (validation), REQ-P8-004 (ownership),
 * REQ-P8-005 (audit).
 */
class StudyDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Study $study;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Stub encryption service so tests don't need a real sodium key
        $this->mockEncryption();

        // Stub extraction service so tests don't shell out to pdftotext/unzip
        $this->mockExtraction();

        // Stub malware scanner — returns 'unavailable' (no ClamAV in CI)
        $this->mockMalwareScanner();

        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'Upload Test Study']);
    }

    // ── Happy-path ──────────────────────────────────────────────────────────────

    #[Test]
    public function pdf_upload_creates_row_and_file(): void
    {
        $file = UploadedFile::fake()->create('protocol.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post(
                route('studies.documents.store', ['study_uuid' => $this->study->uuid]),
                ['file' => $file]
            );

        $response->assertRedirect(route('studies.show', ['uuid' => $this->study->uuid]));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('project_documents', [
            'study_id' => $this->study->id,
            'original_filename' => 'protocol.pdf',
            'kind' => 'pdf',
        ]);
    }

    #[Test]
    public function docx_upload_creates_row_and_file(): void
    {
        $file = UploadedFile::fake()->create('consent.docx', 50,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->actingAs($this->user)
            ->post(
                route('studies.documents.store', ['study_uuid' => $this->study->uuid]),
                ['file' => $file]
            );

        $response->assertRedirect(route('studies.show', ['uuid' => $this->study->uuid]));

        $this->assertDatabaseHas('project_documents', [
            'study_id' => $this->study->id,
            'original_filename' => 'consent.docx',
            'kind' => 'docx',
        ]);
    }

    // ── Security / rejection tests ──────────────────────────────────────────────

    #[Test]
    public function non_owner_upload_returns_404(): void
    {
        $otherUser = User::factory()->create(['is_approved' => true]);

        $file = UploadedFile::fake()->create('protocol.pdf', 10, 'application/pdf');

        $response = $this->actingAs($otherUser)
            ->post(
                route('studies.documents.store', ['study_uuid' => $this->study->uuid]),
                ['file' => $file]
            );

        $response->assertNotFound();
    }

    #[Test]
    public function oversized_file_returns_422(): void
    {
        // 102401 KB > 100 MB limit — Laravel validation rejects before controller.
        // Use Accept: application/json so FormRequest returns 422 JSON instead of redirect.
        $file = UploadedFile::fake()->create('huge.pdf', 102401, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(
                route('studies.documents.store', ['study_uuid' => $this->study->uuid]),
                ['file' => $file]
            );

        $response->assertStatus(422);
        $response->assertInvalid(['file']);
    }

    #[Test]
    public function wrong_mime_returns_422(): void
    {
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

        $response = $this->actingAs($this->user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(
                route('studies.documents.store', ['study_uuid' => $this->study->uuid]),
                ['file' => $file]
            );

        $response->assertStatus(422);
        $response->assertInvalid(['file']);
    }

    #[Test]
    public function duplicate_sha256_returns_redirect_with_error_flash(): void
    {
        $file = UploadedFile::fake()->create('protocol.pdf', 100, 'application/pdf');

        // First upload succeeds
        $this->actingAs($this->user)
            ->post(
                route('studies.documents.store', ['study_uuid' => $this->study->uuid]),
                ['file' => $file]
            );

        // Second upload of identical file content → SHA-256 duplicate
        $duplicate = UploadedFile::fake()->create('protocol.pdf', 100, 'application/pdf');

        // Make the second file have the same sha256 as whatever was stored
        $existingDoc = ProjectDocument::where('study_id', $this->study->id)->first();

        // Insert a matching sha256 stub so we can test without identical file bytes
        ProjectDocument::query()->where('id', $existingDoc->id)->update([
            'sha256' => hash('sha256', (string) $duplicate->getContent()),
        ]);

        $response = $this->actingAs($this->user)
            ->post(
                route('studies.documents.store', ['study_uuid' => $this->study->uuid]),
                ['file' => $duplicate]
            );

        // Should redirect back with an error flash (not crash)
        $response->assertRedirect();
        // Either the session has 'error' (duplicate) or validation error
        $this->assertTrue(
            $response->getSession()->has('error') ||
            count($response->getSession()->get('errors', collect())->all()) > 0,
            'Expected an error flash for duplicate upload'
        );
    }

    #[Test]
    public function throttle_5_per_minute_enforced(): void
    {
        // Verify that the 'throttle:5,1' middleware is applied to the route.
        $route = app('router')->getRoutes()->getByName('studies.documents.store');

        $this->assertNotNull($route, 'Route studies.documents.store must exist');

        $middlewares = collect($route->middleware());
        $hasThrottle = $middlewares->contains(fn ($m) => str_contains((string) $m, 'throttle:5'));

        $this->assertTrue($hasThrottle, 'Route must carry throttle:5,1 middleware');
    }

    #[Test]
    public function audit_event_emitted_on_upload(): void
    {
        $file = UploadedFile::fake()->create('audit-test.pdf', 20, 'application/pdf');

        $this->actingAs($this->user)
            ->post(
                route('studies.documents.store', ['study_uuid' => $this->study->uuid]),
                ['file' => $file]
            );

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'study.document.uploaded',
            'entity_type' => 'study',
            'entity_id' => $this->study->id,
            'actor_user_id' => $this->user->id,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Bind a fake FileEncryptionService that writes the source file as-is.
     * This avoids needing a real sodium key in tests.
     */
    private function mockEncryption(): void
    {
        $this->instance(FileEncryptionService::class, new class extends FileEncryptionService
        {
            public function __construct()
            {
                // Skip parent constructor — no key needed in test
            }

            public function isEnabled(): bool
            {
                return false; // triggers plaintext fallback path in controller
            }

            /** @return array{storage_path: string, encryption_key_id: null} */
            public function encryptStoredFile(string $disk, string $sourcePath, ?string $targetPath = null): array
            {
                // No-op: controller's catch branch handles isEnabled()=false
                return ['storage_path' => $sourcePath, 'encryption_key_id' => null];
            }
        });
    }

    /**
     * Bind a fake DocumentExtractionService that does nothing.
     * Prevents pdftotext / unzip shells in unit tests.
     */
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

    /**
     * Bind a fake MalwareScanService that always returns 'unavailable'.
     */
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
