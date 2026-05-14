<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TemplateControl;
use App\Models\TemplateVersion;
use App\Services\TemplateService;
use Illuminate\Console\Command;

class TemplateControlsDumpCommand extends Command
{
    protected $signature = 'irb:template-controls-dump {--template-uuid=} {--form-code=}';

    protected $description = 'Dump template controls as JSON for active/default template.';

    public function handle(TemplateService $templates): int
    {
        $templateUuidOpt = $this->option('template-uuid');
        $templateUuid = is_string($templateUuidOpt) ? trim($templateUuidOpt) : '';

        $formCodeOpt = $this->option('form-code');
        $formCode = is_string($formCodeOpt) ? trim($formCodeOpt) : '';

        $template = null;

        // Outstanding #49 — multi-form rollout: --template-uuid takes highest priority.
        if ($templateUuid !== '') {
            $template = TemplateVersion::query()->where('uuid', $templateUuid)->first();
            if ($template === null) {
                $this->error('Template not found for uuid: '.$templateUuid);

                return self::FAILURE;
            }
        }

        // --form-code resolves the template by name defined in TemplateService::FORM_TEMPLATES.
        if ($template === null && $formCode !== '') {
            if (! isset(TemplateService::FORM_TEMPLATES[$formCode])) {
                $valid = implode(', ', array_keys(TemplateService::FORM_TEMPLATES));
                $this->error('Unknown form-code "'.$formCode.'". Valid values: '.$valid);

                return self::FAILURE;
            }

            $templateName = TemplateService::FORM_TEMPLATES[$formCode]['name'];
            $template = TemplateVersion::query()
                ->where('name', $templateName)
                ->orderByDesc('is_active')
                ->orderByDesc('created_at')
                ->first();

            if ($template === null) {
                $this->error('No template installed for form-code "'.$formCode.'" (name: '.$templateName.')');

                return self::FAILURE;
            }
        }

        if ($template === null) {
            $template = TemplateVersion::query()->where('is_active', true)->orderByDesc('created_at')->first();
        }

        if ($template === null) {
            $template = $templates->ensureDefaultTemplateInstalled();
        }

        $rows = TemplateControl::query()
            ->where('template_version_id', $template->id)
            ->orderBy('part')
            ->orderBy('control_index')
            ->get([
                'part',
                'control_index',
                'signature_sha256',
                'placeholder_text',
                'context_before',
                'context_after',
            ])
            ->map(function (TemplateControl $ctrl): array {
                return [
                    'part' => $ctrl->part,
                    'control_index' => (int) $ctrl->control_index,
                    'signature_sha256' => $ctrl->signature_sha256,
                    'placeholder_text' => $ctrl->placeholder_text,
                    'context_before' => $ctrl->context_before,
                    'context_after' => $ctrl->context_after,
                ];
            })
            ->values()
            ->all();

        $payload = [
            'template_uuid' => $template->uuid,
            'template_sha256' => $template->sha256,
            'controls' => $rows,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (! is_string($json)) {
            $this->error('Failed to encode control dump as JSON.');

            return self::FAILURE;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
