<?php

namespace App\Http\Controllers;

use App\Models\LlmProvider;
use App\Models\AnalysisRun;
use App\Models\AuditEvent;
use App\Models\TemplateControl;
use App\Models\TemplateControlMapping;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(Request $request, SettingsService $settings): View
    {
        $tab = (string) $request->query('tab', 'users');
        $tab = in_array($tab, ['users', 'providers', 'templates', 'settings', 'audit', 'observability'], true) ? $tab : 'users';

        $data = [];
        if ($tab === 'users') {
            $data['users'] = User::query()->orderBy('email')->limit(200)->get();
        } elseif ($tab === 'providers') {
            $data['providers'] = LlmProvider::query()->orderBy('name')->get();
        } elseif ($tab === 'templates') {
            $templates = TemplateVersion::query()->orderByDesc('created_at')->get();
            $templateIds = $templates->pluck('id')->all();

            $controlsByTemplate = TemplateControl::query()
                ->selectRaw('template_version_id, COUNT(*) as c')
                ->whereIn('template_version_id', $templateIds)
                ->groupBy('template_version_id')
                ->pluck('c', 'template_version_id');

            $mappedByTemplate = TemplateControlMapping::query()
                ->selectRaw('template_version_id, COUNT(*) as c')
                ->whereIn('template_version_id', $templateIds)
                ->groupBy('template_version_id')
                ->pluck('c', 'template_version_id');

            $data['templates'] = $templates;
            $data['templateStats'] = [
                'controls' => $controlsByTemplate,
                'mapped' => $mappedByTemplate,
            ];
        } elseif ($tab === 'settings') {
            $data['settings'] = [
                'allow_external_llm' => $settings->bool('allow_external_llm', false),
                'retention_days' => $settings->int('retention_days', (int) env('IRB_RETENTION_DAYS', 14)),
                'max_upload_bytes' => $settings->int('max_upload_bytes', (int) env('IRB_MAX_UPLOAD_BYTES', 104857600)),
                'logging_level' => $settings->string('logging_level', (string) env('LOG_LEVEL', 'debug')),
            ];
        } elseif ($tab === 'audit') {
            $data['auditEvents'] = AuditEvent::query()
                ->orderByDesc('occurred_at')
                ->paginate(100);
        } elseif ($tab === 'observability') {
            $data['providers'] = LlmProvider::query()->orderBy('name')->get();

            $data['analysisRuns'] = AnalysisRun::query()
                ->with(['project', 'provider', 'createdBy'])
                ->orderByDesc('created_at')
                ->limit(200)
                ->get();

            $data['analysisUsageByProvider'] = AnalysisRun::query()
                ->selectRaw('llm_provider_id, COUNT(*) as c')
                ->groupBy('llm_provider_id')
                ->orderByDesc('c')
                ->pluck('c', 'llm_provider_id');
        }

        return view('admin.index', [
            'tab' => $tab,
            ...$data,
        ]);
    }
}
