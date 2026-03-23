<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FieldDefinition;
use App\Models\Project;
use App\Models\ProjectFieldValue;
use App\Models\TemplateControlMapping;
use App\Models\TemplateVersion;

class ProjectInitializationService
{
    public function ensureProjectFieldValuesExist(Project $project): void
    {
        $fieldIds = [];

        $template = TemplateVersion::query()->where('is_active', true)->orderByDesc('created_at')->first();
        if ($template !== null) {
            $mapped = TemplateControlMapping::query()
                ->where('template_version_id', $template->id)
                ->pluck('field_definition_id')
                ->unique()
                ->values()
                ->all();

            if (count($mapped) > 0) {
                $fieldIds = $mapped;
            } else {
                $fieldIds = FieldDefinition::query()
                    ->where('key', 'like', 'ctrl\_%')
                    ->pluck('id')
                    ->all();
            }
        }

        if (count($fieldIds) === 0) {
            $fieldIds = FieldDefinition::query()->pluck('id')->all();
        }

        if (count($fieldIds) === 0) {
            return;
        }

        $existing = ProjectFieldValue::query()
            ->where('project_id', $project->id)
            ->pluck('field_definition_id')
            ->all();

        $existingMap = array_fill_keys($existing, true);
        foreach ($fieldIds as $fieldId) {
            if (isset($existingMap[$fieldId])) {
                continue;
            }

            ProjectFieldValue::query()->create([
                'project_id' => $project->id,
                'field_definition_id' => (int) $fieldId,
                'status' => 'missing',
            ]);
        }
    }
}
