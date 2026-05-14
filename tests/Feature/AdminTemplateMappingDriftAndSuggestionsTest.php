<?php

namespace Tests\Feature;

use App\Models\FieldDefinition;
use App\Models\TemplateControl;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTemplateMappingDriftAndSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_part_drift_details_and_mapping_suggestions(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $base = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c Base',
            'sha256' => hash('sha256', 'tpl-base'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/hrp503c/base.docx',
            'is_active' => true,
            'uploaded_by_user_id' => $admin->id,
        ]);

        $target = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c Target',
            'sha256' => hash('sha256', 'tpl-target'),
            'storage_disk' => 'local',
            'storage_path' => 'templates/hrp503c/target.docx',
            'is_active' => false,
            'uploaded_by_user_id' => $admin->id,
        ]);

        $field1 = FieldDefinition::query()->create([
            'key' => 'pi.name',
            'label' => 'Principal Investigator Name',
            'section' => 'Study Team',
            'question_text' => 'Who is the principal investigator?',
            'sort_order' => 1,
        ]);

        $field2 = FieldDefinition::query()->create([
            'key' => 'study.title',
            'label' => 'Study title',
            'section' => 'Study Overview',
            'question_text' => 'What is the study title?',
            'sort_order' => 2,
        ]);

        TemplateControl::query()->create([
            'template_version_id' => $base->id,
            'part' => 'document',
            'control_index' => 1,
            'context_before' => 'PRINCIPAL INVESTIGATOR:',
            'context_after' => null,
            'placeholder_text' => 'Name of PI',
            'signature_sha256' => hash('sha256', 'sig-a'),
        ]);

        TemplateControl::query()->create([
            'template_version_id' => $base->id,
            'part' => 'document',
            'control_index' => 2,
            'context_before' => 'DEPARTMENT:',
            'context_after' => null,
            'placeholder_text' => 'Department',
            'signature_sha256' => hash('sha256', 'sig-b'),
        ]);

        TemplateControl::query()->create([
            'template_version_id' => $target->id,
            'part' => 'document',
            'control_index' => 1,
            'context_before' => 'PRINCIPAL INVESTIGATOR:',
            'context_after' => null,
            'placeholder_text' => 'Principal Investigator Name',
            'signature_sha256' => hash('sha256', 'sig-a'),
        ]);

        TemplateControl::query()->create([
            'template_version_id' => $target->id,
            'part' => 'document',
            'control_index' => 2,
            'context_before' => 'STUDY TITLE:',
            'context_after' => null,
            'placeholder_text' => 'Study title',
            'signature_sha256' => hash('sha256', 'sig-c'),
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.templates.show', ['template' => $target->uuid, 'part' => 'document']));

        $response
            ->assertOk()
            ->assertSee('Drift Details')
            ->assertSee('Matched:')
            ->assertSee('New:')
            ->assertSee('Missing:')
            ->assertSee('Suggestions')
            ->assertSee($field2->key);
    }
}
