<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnalysisRun;
use App\Models\AuditEvent;
use App\Models\DocumentChunk;
use App\Models\Export;
use App\Models\FieldEvidence;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectFieldValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectPurgeService
{
    public function purge(Project $project): array
    {
        $counts = [
            'documents_deleted' => 0,
            'document_files_deleted' => 0,
            'exports_deleted' => 0,
            'export_files_deleted' => 0,
            'analysis_runs_deleted' => 0,
            'field_values_deleted' => 0,
            'evidence_deleted' => 0,
            'chunks_deleted' => 0,
            'audit_events_redacted' => 0,
        ];

        // Collect file paths before transaction so we can delete them after commit.
        $filesToDelete = [];

        $exports = Export::query()->where('project_id', $project->id)->get();
        foreach ($exports as $ex) {
            $disk = (string) ($ex->storage_disk ?? 'local');
            $path = $ex->storage_path;
            if (is_string($path) && $path !== '') {
                $filesToDelete[] = ['disk' => $disk, 'path' => $path];
            }
        }

        $documents = ProjectDocument::query()->where('project_id', $project->id)->get();
        foreach ($documents as $doc) {
            $disk = (string) ($doc->storage_disk ?? 'local');
            $path = $doc->storage_path;
            if (is_string($path) && $path !== '') {
                $filesToDelete[] = ['disk' => $disk, 'path' => $path];
            }

            $qDisk = $doc->quarantine_storage_disk;
            $qPath = $doc->quarantine_storage_path;
            if (is_string($qDisk) && $qDisk !== '' && is_string($qPath) && $qPath !== '') {
                $filesToDelete[] = ['disk' => $qDisk, 'path' => $qPath];
            }
        }

        // All DB operations in a single transaction.
        DB::transaction(function () use ($project, &$counts) {
            $docIds = ProjectDocument::query()->where('project_id', $project->id)->pluck('id')->all();
            $fieldValueIds = ProjectFieldValue::query()->where('project_id', $project->id)->pluck('id')->all();

            if (count($fieldValueIds) > 0) {
                $counts['evidence_deleted'] = FieldEvidence::query()
                    ->whereIn('project_field_value_id', $fieldValueIds)
                    ->delete();
            }

            if (count($docIds) > 0) {
                $counts['chunks_deleted'] = DocumentChunk::query()
                    ->whereIn('project_document_id', $docIds)
                    ->delete();
            }

            $counts['field_values_deleted'] = ProjectFieldValue::query()
                ->where('project_id', $project->id)
                ->delete();

            $counts['analysis_runs_deleted'] = AnalysisRun::query()
                ->where('project_id', $project->id)
                ->delete();

            $counts['exports_deleted'] = Export::query()
                ->where('project_id', $project->id)
                ->delete();

            $counts['documents_deleted'] = ProjectDocument::query()
                ->where('project_id', $project->id)
                ->delete();

            $counts['audit_events_redacted'] = AuditEvent::query()
                ->where('project_id', $project->id)
                ->update([
                    'project_id' => null,
                    'payload' => json_encode([
                        'redacted' => true,
                        'reason' => 'project purged',
                    ]),
                ]);

            $project->delete();
        });

        // Delete files after transaction commits successfully.
        foreach ($filesToDelete as $file) {
            if (Storage::disk($file['disk'])->exists($file['path'])) {
                Storage::disk($file['disk'])->delete($file['path']);
                if (str_starts_with($file['path'], 'exports/')) {
                    $counts['export_files_deleted']++;
                } else {
                    $counts['document_files_deleted']++;
                }
            }
        }

        return $counts;
    }
}
