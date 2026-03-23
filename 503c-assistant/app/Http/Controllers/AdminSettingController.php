<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    public function store(
        Request $request,
        SettingsService $settings,
        AuditService $audit,
    ): \Illuminate\Http\RedirectResponse {
        $data = $request->validate([
            'allow_external_llm' => ['nullable', 'boolean'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'max_upload_bytes' => ['required', 'integer', 'min:1048576', 'max:1073741824'],
            'logging_level' => ['required', 'string', 'in:debug,info,notice,warning,error,critical,alert,emergency'],
        ]);

        $before = [
            'allow_external_llm' => $settings->bool('allow_external_llm', false),
            'retention_days' => $settings->int('retention_days', 14),
            'max_upload_bytes' => $settings->int('max_upload_bytes', 104857600),
            'logging_level' => $settings->string('logging_level', 'debug'),
        ];

        $settings->set('allow_external_llm', (bool) ($data['allow_external_llm'] ?? false), $request->user()->id);
        $settings->set('retention_days', (int) $data['retention_days'], $request->user()->id);
        $settings->set('max_upload_bytes', (int) $data['max_upload_bytes'], $request->user()->id);
        $settings->set('logging_level', (string) $data['logging_level'], $request->user()->id);

        $audit->log(
            request: $request,
            eventType: 'admin.settings.updated',
            project: null,
            entityType: 'system_settings',
            entityId: null,
            entityUuid: null,
            payload: [
                'before' => $before,
                'after' => [
                    'allow_external_llm' => (bool) ($data['allow_external_llm'] ?? false),
                    'retention_days' => (int) $data['retention_days'],
                    'max_upload_bytes' => (int) $data['max_upload_bytes'],
                    'logging_level' => (string) $data['logging_level'],
                ],
            ],
        );

        return redirect()->route('admin.index', ['tab' => 'settings'])->with('status', 'Settings saved.');
    }
}
