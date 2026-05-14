<?php

namespace Tests\Feature;

use App\Models\FieldDefinition;
use App\Models\TemplateControl;
use App\Models\TemplateControlMapping;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTemplateMappingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_mappings_for_a_part_and_preserve_filters_in_redirect(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $tpl = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c',
            'sha256' => hash('sha256', 'tpl-1'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/hrp503c/test.docx',
            'is_active' => false,
            'uploaded_by_user_id' => $admin->id,
        ]);

        $field = FieldDefinition::query()->create([
            'key' => 'test.field',
            'label' => 'Test Field',
            'sort_order' => 1,
        ]);

        $ctrl = TemplateControl::query()->create([
            'template_version_id' => $tpl->id,
            'part' => 'endnotes',
            'control_index' => 1,
            'context_before' => 'Before',
            'context_after' => 'After',
            'placeholder_text' => 'Placeholder',
            'signature_sha256' => hash('sha256', 'sig-1'),
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.templates.mappings', [
                'template' => $tpl->uuid,
                'only_fillable' => '1',
                'only_unmapped' => '1',
            ]), [
                'part' => 'endnotes',
                'mapping' => [
                    'endnotes' => [
                        '1' => (string) $field->id,
                    ],
                ],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.templates.show', [
                'template' => $tpl->uuid,
                'part' => 'endnotes',
                'only_fillable' => '1',
                'only_unmapped' => '1',
            ]));

        $this->assertDatabaseHas('template_control_mappings', [
            'template_version_id' => $tpl->id,
            'template_control_id' => $ctrl->id,
            'field_definition_id' => $field->id,
        ]);

        $response2 = $this
            ->actingAs($admin)
            ->post(route('admin.templates.mappings', [
                'template' => $tpl->uuid,
            ]), [
                'part' => 'endnotes',
                'mapping' => [
                    'endnotes' => [
                        '1' => '',
                    ],
                ],
            ]);

        $response2
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.templates.show', [
                'template' => $tpl->uuid,
                'part' => 'endnotes',
            ]));

        $this->assertDatabaseMissing('template_control_mappings', [
            'template_control_id' => $ctrl->id,
        ]);

        $this->assertSame(0, TemplateControlMapping::query()->count());
    }

    public function test_non_admin_cannot_save_mappings(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        $tpl = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c',
            'sha256' => hash('sha256', 'tpl-2'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/hrp503c/test2.docx',
            'is_active' => false,
            'uploaded_by_user_id' => $user->id,
        ]);

        $field = FieldDefinition::query()->create([
            'key' => 'test.field2',
            'label' => 'Test Field 2',
            'sort_order' => 1,
        ]);

        TemplateControl::query()->create([
            'template_version_id' => $tpl->id,
            'part' => 'document',
            'control_index' => 1,
            'signature_sha256' => hash('sha256', 'sig-2'),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('admin.templates.mappings', ['template' => $tpl->uuid]), [
                'part' => 'document',
                'mapping' => [
                    'document' => [
                        '1' => (string) $field->id,
                    ],
                ],
            ]);

        $response->assertForbidden();
    }
}
