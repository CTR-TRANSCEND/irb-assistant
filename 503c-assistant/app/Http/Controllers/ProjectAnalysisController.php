<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\LlmProvider;
use App\Services\AuditService;
use App\Services\LlmChatService;
use App\Services\ProjectAnalysisService;
use App\Services\ProjectInitializationService;
use App\Services\SettingsService;
use App\Services\TemplateService;
use Illuminate\Http\Request;

class ProjectAnalysisController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        TemplateService $templates,
        ProjectInitializationService $init,
        ProjectAnalysisService $analysis,
        LlmChatService $llm,
        SettingsService $settings,
        AuditService $audit,
    ): \Illuminate\Http\RedirectResponse {
        if ($project->owner_user_id !== $request->user()->id) {
            abort(404);
        }

        $allowExternal = $settings->bool('allow_external_llm', filter_var((string) env('IRB_ALLOW_EXTERNAL_LLM', 'false'), FILTER_VALIDATE_BOOLEAN));

        $provider = null;
        if ($project->llm_provider_id !== null) {
            $provider = LlmProvider::query()
                ->whereKey((int) $project->llm_provider_id)
                ->where('is_enabled', true)
                ->when(! $allowExternal, fn ($q) => $q->where('is_external', false))
                ->first();
        }

        $provider = $provider ?? LlmProvider::query()
            ->where('is_enabled', true)
            ->when(! $allowExternal, fn ($q) => $q->where('is_external', false))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        if ($provider === null) {
            return redirect()
                ->route('projects.show', ['project' => $project->uuid, 'tab' => 'documents'])
                ->with('status', 'No enabled LLM provider available under current system policy. Admin must configure one first.');
        }


        $templates->ensureDefaultTemplateInstalled(uploadedByUserId: $request->user()->id);
        $init->ensureProjectFieldValuesExist($project);

        $analysis->runFirstPass($project, $provider, $request->user()->id, $llm);

        $audit->log(
            request: $request,
            eventType: 'project.analyzed',
            project: $project,
            entityType: 'project',
            entityId: $project->id,
            entityUuid: $project->uuid,
            payload: ['mode' => 'first-pass'],
        );

        return redirect()
            ->route('projects.show', ['project' => $project->uuid, 'tab' => 'review'])
            ->with('status', 'Analysis complete. Review suggestions.');
    }
}
