<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFieldValue;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ProjectFieldController extends Controller
{
    public function update(
        Request $request,
        Project $project,
        ProjectFieldValue $value,
        AuditService $audit,
    ): \Illuminate\Http\RedirectResponse {
        if ($project->owner_user_id !== $request->user()->id) {
            abort(404);
        }

        if ($value->project_id !== $project->id) {
            abort(404);
        }

        $data = $request->validate([
            'final_value' => ['nullable', 'string', 'max:65535'],
            'confirm' => ['nullable', 'boolean'],
            'tab' => ['nullable', 'string'],
        ]);

        $before = [
            'final_value' => $value->final_value,
            'status' => $value->status,
        ];

        $value->final_value = $data['final_value'] ?? null;
        $value->updated_by_user_id = $request->user()->id;

        if (($data['confirm'] ?? false) === true) {
            $value->status = $value->final_value === null || trim($value->final_value) === '' ? 'missing' : 'confirmed';
            $value->confirmed_at = $value->status === 'confirmed' ? now() : null;
        } else {
            if ($value->final_value !== null && trim($value->final_value) !== '') {
                $value->status = $value->suggested_value === $value->final_value ? 'suggested' : 'edited';
            } else {
                $value->status = 'missing';
            }
        }

        $value->save();

        $audit->log(
            request: $request,
            eventType: 'field.updated',
            project: $project,
            entityType: 'project_field_value',
            entityId: $value->id,
            entityUuid: null,
            payload: [
                'field_key' => $value->field->key ?? null,
                'before' => $before,
                'after' => [
                    'final_value' => $value->final_value,
                    'status' => $value->status,
                ],
            ],
        );

        $tab = (string) ($data['tab'] ?? 'review');
        $tab = in_array($tab, ['documents', 'review', 'questions', 'export', 'activity'], true) ? $tab : 'review';

        return redirect()->route('projects.show', [
            'project' => $project->uuid,
            'tab' => $tab,
            'fv' => $value->id,
        ]);
    }
}
