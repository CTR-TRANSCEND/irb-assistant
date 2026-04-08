<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFieldValue;
use App\Models\LlmProvider;
use App\Services\AuditService;
use App\Services\ProjectInitializationService;
use App\Services\ProjectPurgeService;
use App\Services\TemplateService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $projects = Project::query()
            ->where('owner_user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get();

        return view('projects.index', [
            'projects' => $projects,
        ]);
    }

    public function store(Request $request, AuditService $audit): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $project = Project::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $request->user()->id,
            'name' => $data['name'],
            'status' => 'draft',
        ]);

        $audit->log(
            request: $request,
            eventType: 'project.created',
            project: $project,
            entityType: 'project',
            entityId: $project->id,
            entityUuid: $project->uuid,
            payload: ['name' => $project->name],
        );

        return redirect()->route('projects.show', ['project' => $project->uuid]);
    }

    public function show(
        Request $request,
        Project $project,
        TemplateService $templates,
        ProjectInitializationService $init,
        SettingsService $settings,
    ): View
    {
        if ($project->owner_user_id !== $request->user()->id) {
            abort(404);
        }

        $tab = (string) $request->query('tab', 'documents');
        $tab = in_array($tab, ['documents', 'review', 'questions', 'export', 'activity'], true) ? $tab : 'documents';

        $documents = $project->documents()->orderByDesc('created_at')->get();

        if (in_array($tab, ['review', 'questions', 'export'], true)) {
            $templates->ensureDefaultTemplateInstalled(uploadedByUserId: $request->user()->id);
            $init->ensureProjectFieldValuesExist($project);
        }

        $fieldValues = ProjectFieldValue::query()
            ->where('project_id', $project->id)
            ->with('field')
            ->withCount('evidence')
            ->get()
            ->sortBy(function (ProjectFieldValue $fv): int {
                return (int) ($fv->field?->sort_order ?? 0);
            })
            ->values();

        $stats = [
            'total' => $fieldValues->count(),
            'missing' => $fieldValues->where('status', 'missing')->count(),
            'suggested' => $fieldValues->where('status', 'suggested')->count(),
            'edited' => $fieldValues->where('status', 'edited')->count(),
            'confirmed' => $fieldValues->where('status', 'confirmed')->count(),
        ];

        $selectedFieldValueId = $request->query('fv');
        $selectedFieldValueId = is_string($selectedFieldValueId) && ctype_digit($selectedFieldValueId) ? (int) $selectedFieldValueId : null;

        if ($selectedFieldValueId === null) {
            $selectedFieldValueId = (int) ($fieldValues->first()?->id ?? 0);
        }

        $selectedFieldValue = null;
        if ($selectedFieldValueId > 0) {
            $selectedFieldValue = ProjectFieldValue::query()
                ->where('project_id', $project->id)
                ->where('id', $selectedFieldValueId)
                ->with(['field', 'evidence.chunk.document'])
                ->first();
        }

        $missingFieldValues = collect();
        if ($tab === 'questions') {
            $missingFieldValues = ProjectFieldValue::query()
                ->where('project_id', $project->id)
                ->where('status', 'missing')
                ->with(['field', 'evidence.chunk.document'])
                ->get()
                ->sortBy(function (ProjectFieldValue $fv): int {
                    return (int) ($fv->field?->sort_order ?? 0);
                })
                ->values();
        }

        $exports = $project->exports()->orderByDesc('created_at')->limit(20)->get();

        $auditEvents = $project->auditEvents()->orderByDesc('occurred_at')->limit(100)->get();

        $allowExternal = $settings->bool('allow_external_llm', filter_var((string) env('IRB_ALLOW_EXTERNAL_LLM', 'false'), FILTER_VALIDATE_BOOLEAN));
        $hasEnabledProvider = LlmProvider::query()
            ->where('is_enabled', true)
            ->when(! $allowExternal, fn ($q) => $q->where('is_external', false))
            ->exists();

        $providerOptions = LlmProvider::query()
            ->where('is_enabled', true)
            ->when(! $allowExternal, fn ($q) => $q->where('is_external', false))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('projects.show', [
            'project' => $project,
            'tab' => $tab,
            'documents' => $documents,
            'fieldValues' => $fieldValues,
            'fieldValueStats' => $stats,
            'selectedFieldValue' => $selectedFieldValue,
            'missingFieldValues' => $missingFieldValues,
            'exports' => $exports,
            'auditEvents' => $auditEvents,
            'hasEnabledProvider' => $hasEnabledProvider,
            'providerOptions' => $providerOptions,
        ]);
    }

    public function updateProvider(
        Request $request,
        Project $project,
        SettingsService $settings,
        AuditService $audit,
    ): \Illuminate\Http\RedirectResponse {
        if ($project->owner_user_id !== $request->user()->id) {
            abort(404);
        }

        $data = $request->validate([
            'tab' => ['nullable', 'string'],
            'llm_provider_id' => ['nullable', 'integer', Rule::exists('llm_providers', 'id')],
        ]);

        $allowExternal = $settings->bool('allow_external_llm', filter_var((string) env('IRB_ALLOW_EXTERNAL_LLM', 'false'), FILTER_VALIDATE_BOOLEAN));

        $providerId = $data['llm_provider_id'] ?? null;
        $provider = null;

        if ($providerId !== null) {
            $provider = LlmProvider::query()
                ->whereKey((int) $providerId)
                ->where('is_enabled', true)
                ->when(! $allowExternal, fn ($q) => $q->where('is_external', false))
                ->first();

            if ($provider === null) {
                return back()->withErrors(['llm_provider_id' => 'Selected provider is not enabled or not allowed under current system policy.']);
            }
        }

        $beforeProviderId = $project->llm_provider_id;
        $project->update(['llm_provider_id' => $provider?->id]);

        $audit->log(
            request: $request,
            eventType: 'project.provider.updated',
            project: $project,
            entityType: 'project',
            entityId: $project->id,
            entityUuid: $project->uuid,
            payload: [
                'before_llm_provider_id' => $beforeProviderId,
                'after_llm_provider_id' => $provider?->id,
                'after_llm_provider_name' => $provider?->name,
            ],
        );

        $tab = (string) ($data['tab'] ?? 'documents');
        $tab = in_array($tab, ['documents', 'review', 'questions', 'export', 'activity'], true) ? $tab : 'documents';

        return redirect()
            ->route('projects.show', ['project' => $project->uuid, 'tab' => $tab])
            ->with('status', 'Provider preference saved.');
    }

    public function destroy(
        Request $request,
        Project $project,
        ProjectPurgeService $purge,
        AuditService $audit,
    ): \Illuminate\Http\RedirectResponse {
        if ($project->owner_user_id !== $request->user()->id) {
            abort(404);
        }

        $request->validateWithBag('projectDeletion', [
            'confirm_name' => ['required', 'string'],
            'password' => ['required', 'current_password'],
        ]);

        if (trim((string) $request->input('confirm_name')) !== (string) $project->name) {
            return back()->withErrors(['confirm_name' => 'Project name does not match.'], 'projectDeletion');
        }

        $projectUuid = (string) $project->uuid;
        $projectName = (string) $project->name;

        $counts = $purge->purge($project);

        $audit->log(
            request: $request,
            eventType: 'project.purged',
            project: null,
            entityType: 'project',
            entityId: null,
            entityUuid: $projectUuid,
            payload: [
                'name' => $projectName,
                ...$counts,
            ],
        );

        return redirect()
            ->route('projects.index')
            ->with('status', 'Project permanently deleted.');
    }
}
