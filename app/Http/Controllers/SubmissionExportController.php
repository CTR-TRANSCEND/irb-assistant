<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Export;
use App\Models\Study;
use App\Services\SubmissionDocxExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Generates and streams DOCX exports for HRP-503 / HRP-503c submissions.
 *
 * SPEC-IRB-FORMSV2-004 §A.5
 * REQ-IRB-FORMSV2-064: HRP-398 is guidance-only; rejected with 422.
 */
class SubmissionExportController extends Controller
{
    /**
     * POST /studies/{study_uuid}/submissions/{form_code}/export
     *
     * REQ-IRB-FORMSV2-064 positive allowlist: only HRP-503 and HRP-503c
     * are exportable. HRP-398 (or any other code) is rejected with 422.
     */
    public function store(Request $request, string $study_uuid, string $form_code): JsonResponse|RedirectResponse
    {
        // REQ-IRB-FORMSV2-064: positive allowlist — only HRP-503 and HRP-503c
        $exportable = ['HRP-503', 'HRP-503c'];
        if (! in_array($form_code, $exportable, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'HRP-398 is guidance-only; not exportable',
                ], 422);
            }

            return redirect()
                ->route('submissions.show', ['uuid' => $study_uuid, 'form_code' => $form_code])
                ->with('error', 'HRP-398 is guidance-only; not exportable.');
        }

        $study = Study::query()->where('uuid', $study_uuid)->firstOrFail();

        if ($study->user_id !== $request->user()->id) {
            abort(404);
        }

        $submission = $study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', $form_code))
            ->with(['formDefinition', 'answers'])
            ->firstOrFail();

        $export = app(SubmissionDocxExportService::class)->generate($submission, $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json([
                'export_uuid' => $export->uuid,
                'download_url' => route('submissions.exports.download', ['export_uuid' => $export->uuid]),
                'status' => $export->status,
            ]);
        }

        // Outstanding #75 (Batch C CRITICAL): the previous redirect to
        // studies.show with a "click Download" flash never rendered a download
        // link — DOCX was generated but unreachable. Redirect directly to the
        // download URL so the user's browser streams the file immediately.
        return redirect()->route('submissions.exports.download', ['export_uuid' => $export->uuid]);
    }

    /**
     * GET /exports/{export_uuid}
     *
     * Streams the DOCX file. Ownership verified via export → submission → study.
     * Filename: sanitized {title}-{form_code}.docx
     */
    public function download(Request $request, string $export_uuid): BinaryFileResponse
    {
        $export = Export::query()
            ->where('uuid', $export_uuid)
            ->with(['submission.study', 'submission.formDefinition'])
            ->firstOrFail();

        // Ownership check: trace export → submission → study → user
        $ownerId = $export->submission?->study?->user_id
            ?? $export->submission?->user_id
            ?? null;

        if ($ownerId !== $request->user()->id) {
            abort(404);
        }

        if ($export->status !== 'ready' || $export->storage_path === null) {
            abort(404, 'Export is not ready.');
        }

        $title = $export->submission?->study?->application_title
            ?? $export->submission?->study?->nickname
            ?? 'export';
        $formCode = $export->submission?->formDefinition?->form_code ?? 'form';

        // Sanitize filename: keep only safe filename characters
        $safeTitle = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $title) ?? 'export';
        $safeTitle = trim($safeTitle);
        $safeFormCode = preg_replace('/[^a-zA-Z0-9\-]/', '', $formCode) ?? $formCode;
        $filename = "{$safeTitle}-{$safeFormCode}.docx";

        $disk = $export->storage_disk ?? 'local';
        $absPath = \Illuminate\Support\Facades\Storage::disk($disk)->path($export->storage_path);

        if (! is_file($absPath)) {
            abort(404, 'Export file not found on disk.');
        }

        return response()->download($absPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
