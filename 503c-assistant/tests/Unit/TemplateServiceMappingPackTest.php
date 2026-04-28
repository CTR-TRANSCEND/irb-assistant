<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\FieldDefinition;
use App\Models\TemplateControl;
use App\Models\TemplateControlMapping;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TemplateServiceMappingPackTest extends TestCase
{
    use RefreshDatabase;

    private const BUNDLED_TEMPLATE_SHA256 = '470fe073cfb6f3572095e4a323e2ff00b2ffadb1fbec1017abc3f89db5db059f';

    private const BUNDLED_TITLE_SIG_SHA256 = '47b5f3636c4d4b2c3709ef790d994c8b12ab2090fec9627dde90b308e11b14d8';

    public function test_bundled_pack_applies_curated_mapping_and_is_idempotent(): void
    {
        $template = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c',
            'sha256' => self::BUNDLED_TEMPLATE_SHA256,
            'storage_disk' => 'local',
            'storage_path' => 'templates/hrp503c/test-pack-1.docx',
            'is_active' => false,
            'uploaded_by_user_id' => null,
        ]);

        $field = FieldDefinition::query()->create([
            'key' => 'hrp503c.study.title',
            'label' => 'Study Title',
            'section' => 'HRP-503c: Study Identification',
            'sort_order' => 10,
            'is_required' => true,
            'input_type' => 'text',
            'question_text' => 'What is the full study title?',
        ]);

        $control = TemplateControl::query()->create([
            'template_version_id' => $template->id,
            'part' => 'document',
            'control_index' => 0,
            'context_before' => 'TITLE:',
            'context_after' => null,
            'placeholder_text' => '[INSERT TITLE]',
            'signature_sha256' => self::BUNDLED_TITLE_SIG_SHA256,
        ]);

        $service = app(TemplateService::class);

        $created = $service->applyBundledMappingPack($template);
        $this->assertSame(1, $created);

        $this->assertDatabaseHas('template_control_mappings', [
            'template_version_id' => $template->id,
            'template_control_id' => $control->id,
            'field_definition_id' => $field->id,
            'mapped_by_user_id' => null,
        ]);

        $createdAgain = $service->applyBundledMappingPack($template);
        $this->assertSame(0, $createdAgain);
        $this->assertSame(1, TemplateControlMapping::query()->count());
    }

    public function test_bundled_pack_does_not_override_manual_mapping(): void
    {
        $user = User::factory()->create();

        $template = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HRP-503c',
            'sha256' => self::BUNDLED_TEMPLATE_SHA256,
            'storage_disk' => 'local',
            'storage_path' => 'templates/hrp503c/test-pack-2.docx',
            'is_active' => false,
            'uploaded_by_user_id' => $user->id,
        ]);

        $curatedField = FieldDefinition::query()->create([
            'key' => 'hrp503c.study.title',
            'label' => 'Study Title',
            'section' => 'HRP-503c: Study Identification',
            'sort_order' => 10,
            'is_required' => true,
            'input_type' => 'text',
            'question_text' => 'What is the full study title?',
        ]);

        $manualField = FieldDefinition::query()->create([
            'key' => 'manual.title.field',
            'label' => 'Manual Title',
            'section' => 'HRP-503c',
            'sort_order' => 11,
            'is_required' => false,
            'input_type' => 'text',
            'question_text' => 'Manual Title',
        ]);

        $control = TemplateControl::query()->create([
            'template_version_id' => $template->id,
            'part' => 'document',
            'control_index' => 0,
            'context_before' => 'TITLE:',
            'context_after' => null,
            'placeholder_text' => '[INSERT TITLE]',
            'signature_sha256' => self::BUNDLED_TITLE_SIG_SHA256,
        ]);

        TemplateControlMapping::query()->create([
            'template_version_id' => $template->id,
            'template_control_id' => $control->id,
            'field_definition_id' => $manualField->id,
            'mapped_by_user_id' => $user->id,
        ]);

        $service = app(TemplateService::class);

        $created = $service->applyBundledMappingPack($template);
        $this->assertSame(0, $created);

        $mapping = TemplateControlMapping::query()
            ->where('template_control_id', $control->id)
            ->first();

        $this->assertNotNull($mapping);
        $this->assertSame($manualField->id, $mapping->field_definition_id);
        $this->assertSame($user->id, $mapping->mapped_by_user_id);

        $this->assertNotSame($curatedField->id, $mapping->field_definition_id);
    }
}
