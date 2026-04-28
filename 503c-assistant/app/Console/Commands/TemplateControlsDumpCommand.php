<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TemplateControl;
use App\Models\TemplateVersion;
use App\Services\TemplateService;
use Illuminate\Console\Command;

class TemplateControlsDumpCommand extends Command
{
    protected $signature = 'irb:template-controls-dump {--template-uuid=}';

    protected $description = 'Dump template controls as JSON for active/default template.';

    public function handle(TemplateService $templates): int
    {
        $templateUuidOpt = $this->option('template-uuid');
        $templateUuid = is_string($templateUuidOpt) ? trim($templateUuidOpt) : '';

        $template = null;
        if ($templateUuid !== '') {
            $template = TemplateVersion::query()->where('uuid', $templateUuid)->first();
            if ($template === null) {
                $this->error('Template not found for uuid: '.$templateUuid);

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
