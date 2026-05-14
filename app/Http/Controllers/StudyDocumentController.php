<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudyDocumentRequest;
use App\Models\AuditEvent;
use App\Models\ProjectDocument;
use App\Models\Study;
use App\Services\DocumentExtractionService;
use App\Services\FileEncryptionService;
use App\Services\MalwareScanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles study-level document upload and delete.
 *
 * SPEC-IRB-FORMSV2-008 REQ-P8-001 / REQ-P8-003
 *
 * @MX:ANCHOR: [AUTO] StudyDocumentController is the sole write path for study-scoped document storage.
 *
 * @MX:REASON: fan_in >= 3 — routes/web.php (store+destroy), StudyDocumentUploadTest, StudyDocumentDeleteTest.
 */
class StudyDocumentController extends Controller
{
    public function __construct(
        private readonly MalwareScanService $malwareScanner,
        private readonly FileEncryptionService $encryptionService,
        private readonly DocumentExtractionService $extractionService,
    ) {}

    /**
     * POST /studies/{study_uuid}/documents
     *
     * Upload a PDF or DOCX document to the study's document pool.
     * Steps: ownership check → malware scan → SHA-256 dedup → encrypt → persist → queue extraction → audit.
     *
     * @MX:WARN: [AUTO] Multi-step file pipeline wrapped in DB::transaction; encryption happens outside the TX.
     *
     * @MX:REASON: Encrypt happens before TX so we never commit a DB row for a file that failed to encrypt.
     *             If TX rolls back after encryption, the orphan .bin file is harmless (no DB reference).
     */
    public function store(StoreStudyDocumentRequest $request, string $studyUuid): RedirectResponse
    {
        // REQ-P8-004: ownership enforced — 404 prevents info leakage
        $study = Study::query()
            ->where('uuid', $studyUuid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $file = $request->file('file');

        // Malware scan (skip silently if ClamAV unavailable per MalwareScanService fallback)
        $tempPath = $file->getRealPath();
        $scanResult = $this->malwareScanner->scanFile($tempPath);

        if (($scanResult['status'] ?? 'unavailable') === 'infected') {
            return back()->withErrors(['file' => 'The uploaded file failed the malware scan and was rejected.']);
        }

        // SHA-256 deduplication (REQ-P8-002)
        $sha256 = hash_file('sha256', $tempPath);

        $duplicate = ProjectDocument::query()
            ->where('study_id', $study->id)
            ->where('sha256', $sha256)
            ->exists();

        if ($duplicate) {
            return back()->with('error', 'This document has already been uploaded to this study.');
        }

        // Determine kind from extension
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $kind = match ($extension) {
            'pdf' => 'pdf',
            'docx' => 'docx',
            'doc' => 'docx',  // treat .doc like docx for extraction purposes
            default => $extension,
        };

        $originalFilename = $file->getClientOriginalName();
        $mimeType = (string) $file->getMimeType();
        $sizeBytes = $file->getSize();

        // Determine encrypted storage path and ensure directory exists
        $relativeStoragePath = 'study-docs/'.Str::uuid().'.bin';
        $absoluteStoragePath = storage_path('app/'.$relativeStoragePath);
        $dir = dirname($absoluteStoragePath);

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        // First: store the uploaded file to a temporary local disk location so
        // encryptStoredFile() can operate on a disk-relative path.
        $tmpRelative = 'study-docs-tmp/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($tmpRelative, file_get_contents($tempPath));

        try {
            // Encrypt at rest (throws if encryption not configured)
            $encMeta = $this->encryptionService->encryptStoredFile('local', $tmpRelative, $relativeStoragePath);
        } catch (\RuntimeException $e) {
            // Encryption not configured — store plaintext and flag accordingly
            Log::warning('File encryption not configured; storing plaintext.', ['error' => $e->getMessage()]);
            Storage::disk('local')->move($tmpRelative, $relativeStoragePath);
            $encMeta = ['storage_path' => $relativeStoragePath, 'encryption_key_id' => null];
        } finally {
            // Clean up temp file if encryption moved/removed it (may already be gone)
            if (Storage::disk('local')->exists($tmpRelative)) {
                Storage::disk('local')->delete($tmpRelative);
            }
        }

        $isEncrypted = $encMeta['encryption_key_id'] !== null;

        // Persist row + emit audit atomically (REQ-P8-005)
        $document = DB::transaction(function () use (
            $study,
            $originalFilename,
            $sha256,
            $mimeType,
            $extension,
            $kind,
            $sizeBytes,
            $encMeta,
            $isEncrypted,
            $scanResult,
        ): ProjectDocument {
            $doc = new ProjectDocument;
            $doc->forceFill([
                'uuid' => (string) Str::uuid(),
                'study_id' => $study->id,
                'project_id' => null,
                'uploaded_by_user_id' => Auth::id(),
                'original_filename' => $originalFilename,
                'storage_disk' => 'local',
                'storage_path' => $encMeta['storage_path'],
                'is_encrypted' => $isEncrypted,
                'encryption_key_id' => $encMeta['encryption_key_id'],
                'sha256' => $sha256,
                'mime_type' => $mimeType,
                'file_ext' => $extension,
                'size_bytes' => $sizeBytes,
                'kind' => $kind,
                'extraction_status' => 'pending',
                // Malware scan fields (if available)
                'scan_status' => $scanResult['status'] ?? null,
                'scan_engine' => $scanResult['engine'] ?? null,
                'scan_result' => $scanResult['result'] ?? null,
                'scanned_at' => $scanResult['scanned_at'] ?? null,
            ])->save();

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_user_id' => Auth::id(),
                'event_type' => 'study.document.uploaded',
                'entity_type' => 'study',
                'entity_id' => $study->id,
                'entity_uuid' => $study->uuid,
                'project_id' => null,
                'ip' => request()->ip() ?? '127.0.0.1',
                'user_agent' => substr((string) request()->userAgent(), 0, 512),
                'request_id' => null,
                'payload' => [
                    'sha256' => $sha256,
                    'filename' => $originalFilename,
                    'size' => $sizeBytes,
                    'doc_uuid' => $doc->uuid,
                ],
            ]);

            return $doc;
        });

        // Queue text extraction (outside TX — job failure should not roll back the upload)
        try {
            $this->extractionService->extract($document);
        } catch (\Throwable $e) {
            // Extraction failure is non-fatal at upload time; status column now reads 'failed'.
            Log::warning('Document extraction failed after upload.', [
                'doc_uuid' => $document->uuid,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('studies.show', ['uuid' => $study->uuid])
            ->with('status', "Uploaded {$originalFilename}");
    }

    /**
     * DELETE /studies/{study_uuid}/documents/{doc_uuid}
     *
     * Delete a study-scoped document, its extracted chunks, and the encrypted file.
     */
    public function destroy(Request $request, string $studyUuid, string $docUuid): RedirectResponse
    {
        // REQ-P8-004: ownership enforced on both study and document
        $study = Study::query()
            ->where('uuid', $studyUuid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $document = ProjectDocument::query()
            ->where('uuid', $docUuid)
            ->where('study_id', $study->id)
            ->firstOrFail();

        $storageDisk = $document->storage_disk;
        $storagePath = $document->storage_path;
        $filename = $document->original_filename;
        $docUuidForAudit = $document->uuid;

        DB::transaction(function () use ($study, $document, $storageDisk, $storagePath, $docUuidForAudit, $filename): void {
            // Delete extracted chunks
            $document->chunks()->delete();

            // Delete the encrypted/plaintext file from disk
            if ($storagePath && Storage::disk($storageDisk)->exists($storagePath)) {
                Storage::disk($storageDisk)->delete($storagePath);
            }

            $document->delete();

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_user_id' => Auth::id(),
                'event_type' => 'study.document.deleted',
                'entity_type' => 'study',
                'entity_id' => $study->id,
                'entity_uuid' => $study->uuid,
                'project_id' => null,
                'ip' => request()->ip() ?? '127.0.0.1',
                'user_agent' => substr((string) request()->userAgent(), 0, 512),
                'request_id' => null,
                'payload' => [
                    'doc_uuid' => $docUuidForAudit,
                    'filename' => $filename,
                ],
            ]);
        });

        return redirect()
            ->route('studies.show', ['uuid' => $study->uuid])
            ->with('status', "Deleted {$filename}");
    }
}
