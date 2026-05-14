<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminProviderController;
use App\Http\Controllers\AdminSettingController;
use App\Http\Controllers\AdminTemplateController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\StudyDocumentController;
use App\Http\Controllers\SubmissionAnalysisController;
use App\Http\Controllers\SubmissionAnswerController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionExportController;
use App\Http\Controllers\WorksheetAssistStateController;
use Illuminate\Support\Facades\Route;

// SPEC-UI-001 REQ-UI-001/REQ-UI-002 + SPEC-IRB-FORMSV2-007 (Phase 7):
// Render branded landing for guests; authenticated users redirect to studies.index
// (the FormsV2 entry point, replacing the legacy projects.index target).
// Outstanding #68: invokable controller — `route:cache` safe.
Route::get('/', HomeController::class)->name('home');

// SPEC-IRB-FORMSV2-004 §E: FormsV2 study + submission routes.
// Landing redirect: authenticated users can go to /studies for the new model.
Route::get('/dashboard', fn () => redirect()->route('studies.index'));

Route::middleware(['auth', 'verified'])->group(function (): void {
    // Study CRUD — resource uses {uuid} as the route model key
    Route::resource('studies', StudyController::class)
        ->parameters(['studies' => 'uuid'])
        ->only(['index', 'create', 'store', 'show', 'destroy']);

    // Submission show + assistance-mode toggle.
    // Security review F3: state-mutating assistance_mode toggle is throttled
    // to prevent audit-log inflation and forceFill churn.
    Route::get('/studies/{uuid}/submissions/{form_code}', [SubmissionController::class, 'show'])
        ->name('submissions.show');
    Route::post('/studies/{uuid}/submissions/{form_code}/assistance-mode', [SubmissionController::class, 'updateAssistanceMode'])
        ->middleware('throttle:60,1')
        ->name('submissions.assistance_mode');

    // Worksheet assist-state upsert (HRP-398 guidance items).
    // SPEC-IRB-FORMSV2-006: throttle:60,1 matches Phase 4 PR-1 mutation routes.
    Route::put('/submissions/{submission_uuid}/worksheet/{item_id}', [WorksheetAssistStateController::class, 'update'])
        ->middleware('throttle:60,1')
        ->name('submissions.worksheet.update');

    // Answer upsert + draft acceptance.
    // Security review F3: throttle:60,1 gives headroom for save-on-blur UX
    // while preventing high-volume flooding of audit_events.
    Route::put('/submissions/{submission_uuid}/answers/{question_key}', [SubmissionAnswerController::class, 'update'])
        ->middleware('throttle:60,1')
        ->name('submissions.answers.update');
    Route::post('/submissions/{submission_uuid}/answers/{question_key}/accept-draft', [SubmissionAnswerController::class, 'acceptDraft'])
        ->middleware('throttle:60,1')
        ->name('submissions.answers.accept_draft');

    // Analysis queue + polling + cancel.
    // Security review F2: status polling endpoint matches legacy throttle:120,1
    // pattern from projects.analyze.status. Security review F3: cancel route
    // is state-mutating, throttled the same as other mutations.
    Route::post('/submissions/{submission_uuid}/analyze', [SubmissionAnalysisController::class, 'analyze'])
        ->middleware('throttle:5,1')
        ->name('submissions.analyze');
    Route::get('/submissions/{submission_uuid}/analyze/status', [SubmissionAnalysisController::class, 'status'])
        ->middleware('throttle:120,1')
        ->name('submissions.analyze.status');
    Route::post('/submissions/{submission_uuid}/analyze/cancel', [SubmissionAnalysisController::class, 'cancel'])
        ->middleware('throttle:60,1')
        ->name('submissions.analyze.cancel');

    // Study-level document upload + delete.
    // SPEC-IRB-FORMSV2-008 REQ-P8-001 / REQ-P8-003
    // Security review F1: throttle:5,1 matches Phase 4 PR-1 pattern for state-mutating file routes.
    Route::post('/studies/{study_uuid}/documents', [StudyDocumentController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('studies.documents.store');
    Route::delete('/studies/{study_uuid}/documents/{doc_uuid}', [StudyDocumentController::class, 'destroy'])
        ->middleware('throttle:5,1')
        ->name('studies.documents.destroy');

    // DOCX export + download.
    // Security review F1: throttle the generate (store) endpoint — generate()
    // shells out to unzip/zip subprocesses and writes to storage/app/tmp/.
    Route::post('/studies/{study_uuid}/submissions/{form_code}/export', [SubmissionExportController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('submissions.exports.store');
    Route::get('/exports/{export_uuid}/download', [SubmissionExportController::class, 'download'])
        ->middleware('throttle:5,1')
        ->name('submissions.exports.download');
});

// /welcome always renders the landing page (no auth-redirect). Lets logged-in users
// view the project overview / Sanford-grant attribution from the in-app nav.
Route::get('/welcome', fn () => view('welcome'))->name('welcome');

// SPEC-IRB-FORMSV2-007 Phase 7: legacy /projects/* routes REMOVED.
// User-facing functionality is served entirely by the FormsV2 study + submission
// routes above (Phase 4 PR-1 + Phase 5 + Phase 6). The legacy ProjectController,
// ProjectFieldController, ProjectAnalysisController, ProjectDocumentController,
// and ExportController have been deleted along with their unit + feature tests.
// The App\Models\Project Eloquent model is retained per LD-P7-5 for read-only
// access via admin audit-log queries.
Route::middleware('auth')->group(function () {

    Route::get('/admin', [AdminController::class, 'index'])->middleware('admin')->name('admin.index');
    Route::get('/admin/runs/{runUuid}', [AdminController::class, 'showRun'])->middleware('admin')->name('admin.runs.show');
    Route::post('/admin/providers', [AdminProviderController::class, 'store'])->middleware('admin')->name('admin.providers.store');
    // SPEC-LLM-001 REQ-LLM-013: model-discovery endpoint inside `web` group so CSRF + sessions apply automatically.
    Route::post('/admin/providers/discover', [AdminProviderController::class, 'discover'])->middleware('admin')->name('admin.providers.discover');
    Route::post('/admin/providers/{provider}/test', [AdminProviderController::class, 'test'])->middleware('admin')->name('admin.providers.test');
    // Issue D follow-up: provider delete support.
    // Note: edit/update reuses the existing store() handler via optional `id` field in the payload.
    Route::delete('/admin/providers/{provider}', [AdminProviderController::class, 'destroy'])->middleware('admin')->name('admin.providers.destroy');
    Route::post('/admin/settings', [AdminSettingController::class, 'store'])->middleware('admin')->name('admin.settings.store');

    // Existing admin user status-toggle (unchanged).
    Route::post('/admin/users/{user}', [AdminUserController::class, 'update'])->middleware('admin')->name('admin.users.update');

    // SPEC-AUTH-001 M4: 5 new admin user-management routes.
    // REQ-AUTH-013: approve a pending user.
    Route::post('/admin/users/{user}/approve', [AdminUserController::class, 'approve'])->middleware('admin')->name('admin.users.approve');
    // REQ-AUTH-014: reject (hard-delete) a pending user.
    Route::delete('/admin/users/{user}/reject', [AdminUserController::class, 'reject'])->middleware('admin')->name('admin.users.reject');
    // REQ-AUTH-015: deactivate an approved non-admin user.
    Route::post('/admin/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->middleware('admin')->name('admin.users.deactivate');
    // REQ-AUTH-016: reactivate a deactivated user.
    Route::post('/admin/users/{user}/activate', [AdminUserController::class, 'activate'])->middleware('admin')->name('admin.users.activate');
    // REQ-AUTH-017: delete (hard-delete) an approved non-admin user.
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->middleware('admin')->name('admin.users.destroy');

    Route::post('/admin/templates', [AdminTemplateController::class, 'store'])->middleware('admin')->name('admin.templates.store');
    Route::get('/admin/templates/{template:uuid}', [AdminTemplateController::class, 'show'])->middleware('admin')->name('admin.templates.show');
    Route::post('/admin/templates/{template:uuid}/activate', [AdminTemplateController::class, 'activate'])->middleware('admin')->name('admin.templates.activate');
    Route::post('/admin/templates/{template:uuid}/mappings', [AdminTemplateController::class, 'saveMappings'])->middleware('admin')->name('admin.templates.mappings');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
