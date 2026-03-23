<?php

namespace Tests\Feature;

use App\Models\FieldDefinition;
use App\Models\Project;
use App\Models\ProjectFieldValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTabsRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_show_tabs_render_successfully(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'role' => 'user',
        ]);

        $project = Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $field = FieldDefinition::factory()->create([
            'key' => 'study_title',
            'label' => 'Study Title',
            'sort_order' => 1,
        ]);

        ProjectFieldValue::factory()->create([
            'project_id' => $project->id,
            'field_definition_id' => $field->id,
            'status' => 'missing',
            'suggested_value' => null,
            'confidence' => null,
        ]);

        $this->actingAs($user);

        $tabs = ['documents', 'review', 'questions', 'export', 'activity'];

        foreach ($tabs as $tab) {
            $response = $this->get(route('projects.show', ['project' => $project->uuid, 'tab' => $tab]));
            $response->assertOk();
        }
    }
}
