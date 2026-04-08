<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FieldDefinition;
use App\Models\Project;
use App\Models\ProjectFieldValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFieldControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createProjectWithField(User $user): array
    {
        $project = Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $field = FieldDefinition::factory()->create();

        $fieldValue = ProjectFieldValue::factory()->create([
            'project_id' => $project->id,
            'field_definition_id' => $field->id,
            'suggested_value' => 'Original Suggestion',
            'final_value' => null,
            'status' => 'missing',
        ]);

        return [$project, $fieldValue];
    }

    public function test_user_can_update_field_value(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        /** @var Project $project */
        /** @var ProjectFieldValue $fieldValue */
        [$project, $fieldValue] = $this->createProjectWithField($user);

        $response = $this->actingAs($user)->post(
            route('projects.fields.update', [
                'project' => $project->uuid,
                'value' => $fieldValue->id,
            ]),
            [
                'final_value' => 'Updated Value',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('project_field_values', [
            'id' => $fieldValue->id,
            'final_value' => 'Updated Value',
        ]);
    }

    public function test_user_cannot_update_other_users_field(): void
    {
        $userA = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        $userB = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        /** @var Project $projectA */
        /** @var ProjectFieldValue $fieldValue */
        [$projectA, $fieldValue] = $this->createProjectWithField($userA);

        $response = $this->actingAs($userB)->post(
            route('projects.fields.update', [
                'project' => $projectA->uuid,
                'value' => $fieldValue->id,
            ]),
            [
                'final_value' => 'Unauthorized Update',
            ]
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('project_field_values', [
            'id' => $fieldValue->id,
            'final_value' => null,
        ]);
    }

    public function test_status_transitions_to_edited(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        /** @var Project $project */
        /** @var ProjectFieldValue $fieldValue */
        [$project, $fieldValue] = $this->createProjectWithField($user);

        // final_value differs from suggested_value → status becomes 'edited'
        $response = $this->actingAs($user)->post(
            route('projects.fields.update', [
                'project' => $project->uuid,
                'value' => $fieldValue->id,
            ]),
            [
                'final_value' => 'Edited Value',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('project_field_values', [
            'id' => $fieldValue->id,
            'final_value' => 'Edited Value',
            'status' => 'edited',
        ]);
    }

    public function test_status_transitions_to_confirmed(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        /** @var Project $project */
        /** @var ProjectFieldValue $fieldValue */
        [$project, $fieldValue] = $this->createProjectWithField($user);

        $response = $this->actingAs($user)->post(
            route('projects.fields.update', [
                'project' => $project->uuid,
                'value' => $fieldValue->id,
            ]),
            [
                'final_value' => 'Confirmed Value',
                'confirm' => true,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('project_field_values', [
            'id' => $fieldValue->id,
            'final_value' => 'Confirmed Value',
            'status' => 'confirmed',
        ]);
    }

    public function test_validates_max_length(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        /** @var Project $project */
        /** @var ProjectFieldValue $fieldValue */
        [$project, $fieldValue] = $this->createProjectWithField($user);

        $oversizedValue = str_repeat('a', 65536); // 65536 chars > max:65535

        $response = $this->actingAs($user)->post(
            route('projects.fields.update', [
                'project' => $project->uuid,
                'value' => $fieldValue->id,
            ]),
            [
                'final_value' => $oversizedValue,
            ]
        );

        $response->assertSessionHasErrors(['final_value']);
    }
}
