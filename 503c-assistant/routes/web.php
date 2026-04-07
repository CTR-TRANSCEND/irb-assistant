<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminProviderController;
use App\Http\Controllers\AdminSettingController;
use App\Http\Controllers\AdminTemplateController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\ProjectFieldController;
use App\Http\Controllers\ProjectAnalysisController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('projects.index');
    }

    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project:uuid}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects/{project:uuid}/provider', [ProjectController::class, 'updateProvider'])->name('projects.provider.update');
    Route::delete('/projects/{project:uuid}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::post('/projects/{project:uuid}/documents', [ProjectDocumentController::class, 'store'])->name('projects.documents.store');
    Route::post('/projects/{project:uuid}/analyze', [ProjectAnalysisController::class, 'store'])->name('projects.analyze');
    Route::post('/projects/{project:uuid}/fields/{value}', [ProjectFieldController::class, 'update'])->name('projects.fields.update');
    Route::post('/projects/{project:uuid}/export', [ExportController::class, 'store'])->name('projects.exports.store');
    Route::get('/exports/{export:uuid}', [ExportController::class, 'download'])->middleware('throttle:10,1')->name('exports.download');

    Route::get('/admin', [AdminController::class, 'index'])->middleware('admin')->name('admin.index');
    Route::get('/admin/runs/{runUuid}', [AdminController::class, 'showRun'])->middleware('admin')->name('admin.runs.show');
    Route::post('/admin/providers', [AdminProviderController::class, 'store'])->middleware('admin')->name('admin.providers.store');
    Route::post('/admin/providers/{provider}/test', [AdminProviderController::class, 'test'])->middleware('admin')->name('admin.providers.test');
    Route::post('/admin/settings', [AdminSettingController::class, 'store'])->middleware('admin')->name('admin.settings.store');
    Route::post('/admin/users/{user}', [AdminUserController::class, 'update'])->middleware('admin')->name('admin.users.update');

    Route::post('/admin/templates', [AdminTemplateController::class, 'store'])->middleware('admin')->name('admin.templates.store');
    Route::get('/admin/templates/{template:uuid}', [AdminTemplateController::class, 'show'])->middleware('admin')->name('admin.templates.show');
    Route::post('/admin/templates/{template:uuid}/activate', [AdminTemplateController::class, 'activate'])->middleware('admin')->name('admin.templates.activate');
    Route::post('/admin/templates/{template:uuid}/mappings', [AdminTemplateController::class, 'saveMappings'])->middleware('admin')->name('admin.templates.mappings');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
