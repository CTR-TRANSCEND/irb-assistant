<?php

declare(strict_types=1);

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

            // Per-provider metrics: total, succeeded, failed, avg duration (seconds)
            $data['providerMetrics'] = AnalysisRun::query()
                ->selectRaw(
                    'llm_provider_id,'
                    . ' COUNT(*) as total,'
                    . ' SUM(CASE WHEN status = \'succeeded\' THEN 1 ELSE 0 END) as succeeded,'
                    . ' SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed,'
                    . ' AVG(CASE WHEN finished_at IS NOT NULL AND started_at IS NOT NULL'
                    . '     THEN TIMESTAMPDIFF(SECOND, started_at, finished_at) ELSE NULL END) as avg_duration_s,'
                    . ' MAX(created_at) as last_used_at'
                )
                ->groupBy('llm_provider_id')
                ->orderByDesc('total')
                ->get()
                ->keyBy('llm_provider_id');

            // Overall totals via real DB aggregate to avoid 200-row cap inaccuracy.
            $overall = AnalysisRun::query()
                ->selectRaw(
                    "COUNT(*) as total,"
                    . " SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) as succeeded,"
                    . " SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"
                )
                ->first();
            $data['overallStats'] = [
                'total'     => (int) ($overall->total ?? 0),
                'succeeded' => (int) ($overall->succeeded ?? 0),
                'failed'    => (int) ($overall->failed ?? 0),
            ];
        }

        return view('admin.index', [
            'tab' => $tab,
            ...$data,
        ]);
    }

    public function showRun(string $runUuid): View
    {
        $run = AnalysisRun::query()
            ->where('uuid', $runUuid)
            ->with(['project', 'provider', 'createdBy'])
            ->firstOrFail();

        // Compute safe metadata only — never expose raw request/response payloads.
        $durationSeconds = null;
        if ($run->started_at && $run->finished_at) {
            $durationSeconds = (int) $run->started_at->diffInSeconds($run->finished_at);
        }

        // Derive field count from response payload structure without exposing content.
        // The redacted payload has shape: ['batch_count' => N, 'batches' => [['field_keys' => [...], ...], ...]].
        // Summing field_keys across all batches gives the actual field count processed.
        $fieldCount = null;
        if (is_array($run->response_payload) && isset($run->response_payload['batches'])) {
            $fieldCount = collect($run->response_payload['batches'] ?? [])
                ->sum(fn ($b) => count($b['field_keys'] ?? []));
        }

        return view('admin.runs.show', [
            'run'             => $run,
            'durationSeconds' => $durationSeconds,
            'fieldCount'      => $fieldCount,
        ]);
    }
}
