<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Export;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\DocxExportService;
use App\Services\FileEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        DocxExportService $exports,
        AuditService $audit,
    ): \Illuminate\Http\RedirectResponse {
        if ($project->owner_user_id !== $request->user()->id) {
            abort(404);
        }

        $export = $exports->generate($project, $request->user()->id);

        $audit->log(
            request: $request,
            eventType: 'export.generated',
            project: $project,
            entityType: 'export',
            entityId: $export->id,
            entityUuid: $export->uuid,
            payload: ['status' => $export->status],
        );

        return redirect()
            ->route('projects.show', ['project' => $project->uuid, 'tab' => 'export'])
            ->with('status', 'Export generated.');
    }

    public function download(Request $request, Export $export): \Symfony\Component\HttpFoundation\Response
    {
        $project = $export->project;
        if ($project === null || $project->owner_user_id !== $request->user()->id) {
            abort(404);
        }

        if ($export->storage_path === null || $export->status !== 'ready') {
            abort(404);
        }

        $audit = app(AuditService::class);
        $audit->log(
            request: $request,
            eventType: 'export.downloaded',
            project: $project,
            entityType: 'export',
            entityId: $export->id,
            entityUuid: $export->uuid,
            payload: [],
        );

        $filename = $project->name.'-HRP-503c.docx';
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'HRP-503c.docx';

        if ((bool) $export->is_encrypted) {
            $fileEncryption = app(FileEncryptionService::class);

            return response()->streamDownload(function () use ($fileEncryption, $export): void {
                $out = fopen('php://output', 'wb');
                if ($out === false) {
                    throw new \RuntimeException('Failed to open output stream for download.');
                }

                try {
                    $fileEncryption->decryptStoredFileToStream((string) $export->storage_disk, (string) $export->storage_path, $out);
                } finally {
                    fclose($out);
                }
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
        }

        return response()->download(
            Storage::disk((string) $export->storage_disk)->path((string) $export->storage_path),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        );
    }
}
