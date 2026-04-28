<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentChunk;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\AuditService;
use App\Services\DocumentExtractionService;
use App\Services\FileEncryptionService;
use App\Services\MalwareScanService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectDocumentController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        DocumentExtractionService $extractor,
        MalwareScanService $scanner,
        FileEncryptionService $fileEncryption,
        SettingsService $settings,
        AuditService $audit,
    ): \Illuminate\Http\RedirectResponse {
        if ($project->owner_user_id !== $request->user()->id) {
            abort(404);
        }

        $maxBytes = $settings->int('max_upload_bytes', (int) env('IRB_MAX_UPLOAD_BYTES', 104857600));
        $maxKb = (int) ceil($maxBytes / 1024);

        $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:20'],
            'documents.*' => ['file', 'max:'.$maxKb, 'mimes:pdf,docx,txt'],
        ]);

        $files = $request->file('documents', []);
        $skipped = 0;
        $quarantined = 0;
        foreach ($files as $file) {
            if (! $file->isValid()) {
                $skipped++;

                continue;
            }

            $uuid = (string) Str::uuid();
            $ext = strtolower((string) $file->getClientOriginalExtension());
            $mime = (string) $file->getMimeType();
            $kind = $this->detectKind($ext, $mime);

            if (! in_array($kind, ['pdf', 'docx', 'txt'], true)) {
                $skipped++;

                continue;
            }

            $storageDisk = 'local';
            $storageDir = "projects/{$project->uuid}/uploads";
            $storageName = $uuid.($ext !== '' ? ('.'.$ext) : '');
            $storagePath = $file->storeAs($storageDir, $storageName, $storageDisk);

            if ($storagePath === false) {
                throw new \RuntimeException('Failed to store uploaded file');
            }

            $absPath = Storage::disk($storageDisk)->path($storagePath);
            $sha256 = hash_file('sha256', $absPath) ?: null;

            $doc = ProjectDocument::query()->create([
                'uuid' => $uuid,
                'project_id' => $project->id,
                'uploaded_by_user_id' => $request->user()->id,
                'original_filename' => $file->getClientOriginalName(),
                'storage_disk' => $storageDisk,
                'storage_path' => $storagePath,
                'sha256' => $sha256,
                'mime_type' => $mime,
                'file_ext' => $ext,
                'size_bytes' => (int) $file->getSize(),
                'kind' => $kind,
                'extraction_status' => 'pending',
            ]);

            $audit->log(
                request: $request,
                eventType: 'document.uploaded',
                project: $project,
                entityType: 'project_document',
                entityId: $doc->id,
                entityUuid: $doc->uuid,
                payload: [
                    'original_filename' => $doc->original_filename,
                    'kind' => $doc->kind,
                    'size_bytes' => $doc->size_bytes,
                ],
            );

            $scan = $scanner->scanFile($absPath);
            $scanStatus = (string) ($scan['status'] ?? 'unavailable');
            $scanEngine = $scan['engine'] ?? null;
            $scanResult = $scan['result'] ?? null;
            $scanError = $scan['error'] ?? null;
            $scannedAt = $scan['scanned_at'] ?? null;

            if ($scanStatus === 'infected') {
                $quarantinePath = "projects/{$project->uuid}/quarantine/{$storageName}";
                $moved = Storage::disk($storageDisk)->move($storagePath, $quarantinePath);
                $infectionError = is_string($scanError) && $scanError !== '' ? $scanError : null;
                if (! $moved) {
                    $infectionError = trim(($infectionError ? $infectionError.' ' : '').'Failed to move file to quarantine.');
                }

                $doc->update([
                    'scan_status' => 'infected',
                    'scan_engine' => $scanEngine,
                    'scan_result' => $scanResult,
                    'scanned_at' => $scannedAt,
                    'scan_error' => $infectionError,
                    'quarantined_at' => $moved ? now() : null,
                    'quarantine_storage_disk' => $moved ? $storageDisk : null,
                    'quarantine_storage_path' => $moved ? $quarantinePath : null,
                    'storage_path' => $moved ? $quarantinePath : $doc->storage_path,
                    'extraction_status' => 'blocked',
                    'extraction_error' => 'Blocked due to malware detection'.($scanResult ? ': '.$scanResult : '.'),
                ]);

                $audit->log(
                    request: $request,
                    eventType: 'document.quarantined',
                    project: $project,
                    entityType: 'project_document',
                    entityId: $doc->id,
                    entityUuid: $doc->uuid,
                    payload: [
                        'scan_status' => $doc->scan_status,
                        'scan_engine' => $doc->scan_engine,
                        'scan_result' => $doc->scan_result,
                        'quarantine_storage_disk' => $doc->quarantine_storage_disk,
                        'quarantine_storage_path' => $doc->quarantine_storage_path,
                    ],
                );

                $quarantined++;

                continue;
            }

            if ($scanStatus === 'clean') {
                $doc->update([
                    'scan_status' => 'clean',
                    'scan_engine' => $scanEngine,
                    'scan_result' => $scanResult,
                    'scanned_at' => $scannedAt,
                    'scan_error' => null,
                ]);
            } elseif ($scanStatus === 'error') {
                $doc->update([
                    'scan_status' => 'scan_failed',
                    'scan_engine' => $scanEngine,
                    'scan_result' => null,
                    'scanned_at' => $scannedAt,
                    'scan_error' => $scanError,
                ]);
            } else {
                $doc->update([
                    'scan_status' => 'unscanned',
                    'scan_engine' => null,
                    'scan_result' => null,
                    'scanned_at' => null,
                    'scan_error' => null,
                ]);
            }

            if ($fileEncryption->isEnabled()) {
                $encryptedMeta = $fileEncryption->encryptStoredFile($storageDisk, (string) $doc->storage_path);
                $encryptedAbs = Storage::disk($storageDisk)->path((string) $encryptedMeta['storage_path']);

                $doc->update([
                    'storage_path' => $encryptedMeta['storage_path'],
                    'is_encrypted' => true,
                    'encryption_key_id' => $encryptedMeta['encryption_key_id'],
                    'sha256' => hash_file('sha256', $encryptedAbs) ?: $doc->sha256,
                ]);
            }

            // Synchronous extraction by default so the app works without a queue worker.
            $extractor->extract($doc);

            $chunkCount = DocumentChunk::query()->where('project_document_id', $doc->id)->count();
            $doc->refresh();

            $audit->log(
                request: $request,
                eventType: 'document.extracted',
                project: $project,
                entityType: 'project_document',
                entityId: $doc->id,
                entityUuid: $doc->uuid,
                payload: [
                    'scan_status' => $doc->scan_status,
                    'extraction_status' => $doc->extraction_status,
                    'chunk_count' => $chunkCount,
                ],
            );
        }

        if ($skipped > 0) {
            return redirect()
                ->route('projects.show', ['project' => $project->uuid, 'tab' => 'documents'])
                ->with('status', 'Documents uploaded. Some files were skipped due to validation or unsupported type.');
        }

        if ($quarantined > 0) {
            return redirect()
                ->route('projects.show', ['project' => $project->uuid, 'tab' => 'documents'])
                ->with('status', 'Documents uploaded. Some files were quarantined and blocked from extraction.');
        }

        return redirect()
            ->route('projects.show', ['project' => $project->uuid, 'tab' => 'documents'])
            ->with('status', 'Documents uploaded.');
    }

    private function detectKind(string $ext, string $mime): string
    {
        if ($ext === 'pdf' || $mime === 'application/pdf') {
            return 'pdf';
        }

        if ($ext === 'docx' || str_contains($mime, 'officedocument.wordprocessingml.document')) {
            return 'docx';
        }

        if ($ext === 'txt' || str_starts_with($mime, 'text/')) {
            return 'txt';
        }

        return 'unknown';
    }
}
