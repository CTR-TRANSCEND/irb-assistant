<?php

namespace Tests\Unit;

use App\Models\FieldDefinition;
use App\Models\Project;
use App\Models\ProjectFieldValue;
use App\Models\TemplateControl;
use App\Models\TemplateControlMapping;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\ProjectInitializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectInitializationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_field_values_only_for_mapped_fields_when_active_template_has_mappings(): void
    {
        $user = User::factory()->create();

        $project = Project::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'name' => 'P1',
            'status' => 'draft',
        ]);

        $tpl = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c',
            'sha256' => hash('sha256', 'tpl-init-1'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/hrp503c/test.docx',
            'is_active' => true,
            'uploaded_by_user_id' => $user->id,
        ]);

        $mappedField = FieldDefinition::query()->create([
            'key' => 'ctrl_doc_000',
            'label' => 'Mapped',
            'section' => 'HRP-503c',
            'sort_order' => 1,
        ]);

        $unmappedField = FieldDefinition::query()->create([
            'key' => 'hrp503c.study.title',
            'label' => 'Unmapped',
            'section' => 'HRP-503c',
            'sort_order' => 2,
        ]);

        $ctrl = TemplateControl::query()->create([
            'template_version_id' => $tpl->id,
            'part' => 'document',
            'control_index' => 0,
            'signature_sha256' => hash('sha256', 'sig-init-1'),
        ]);

        TemplateControlMapping::query()->create([
            'template_version_id' => $tpl->id,
            'template_control_id' => $ctrl->id,
            'field_definition_id' => $mappedField->id,
            'mapped_by_user_id' => $user->id,
        ]);

        $svc = new ProjectInitializationService;
        $svc->ensureProjectFieldValuesExist($project);

        $this->assertDatabaseHas('project_field_values', [
            'project_id' => $project->id,
            'field_definition_id' => $mappedField->id,
            'status' => 'missing',
        ]);

        $this->assertDatabaseMissing('project_field_values', [
            'project_id' => $project->id,
            'field_definition_id' => $unmappedField->id,
        ]);

        $this->assertSame(1, ProjectFieldValue::query()->where('project_id', $project->id)->count());
    }

    public function test_falls_back_to_ctrl_fields_when_active_template_has_no_mappings(): void
    {
        $user = User::factory()->create();

        $project = Project::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'name' => 'P2',
            'status' => 'draft',
        ]);

        TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c',
            'sha256' => hash('sha256', 'tpl-init-2'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/hrp503c/test2.docx',
            'is_active' => true,
            'uploaded_by_user_id' => $user->id,
        ]);

        $ctrlField = FieldDefinition::query()->create([
            'key' => 'ctrl_doc_001',
            'label' => 'Ctrl Field',
            'section' => 'HRP-503c',
            'sort_order' => 1,
        ]);

        $curatedField = FieldDefinition::query()->create([
            'key' => 'hrp503c.design.purpose',
            'label' => 'Curated Field',
            'section' => 'HRP-503c: Study Design',
            'sort_order' => 2,
        ]);

        $svc = new ProjectInitializationService;
        $svc->ensureProjectFieldValuesExist($project);

        $this->assertDatabaseHas('project_field_values', [
            'project_id' => $project->id,
            'field_definition_id' => $ctrlField->id,
        ]);

        $this->assertDatabaseMissing('project_field_values', [
            'project_id' => $project->id,
            'field_definition_id' => $curatedField->id,
        ]);

        $this->assertSame(1, ProjectFieldValue::query()->where('project_id', $project->id)->count());
    }
}
