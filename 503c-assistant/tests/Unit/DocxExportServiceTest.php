<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\FieldDefinition;
use App\Models\Project;
use App\Models\ProjectFieldValue;
use App\Models\TemplateControl;
use App\Models\TemplateControlMapping;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\DocxExportService;
use App\Services\FileEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Unit\Support\DocxTestHelper;

class DocxExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_docx_and_inserts_value(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'name' => 'ExportTest',
            'status' => 'draft',
        ]);

        $field = FieldDefinition::query()->create([
            'key' => 'title',
            'label' => 'Title',
            'section' => 'HRP-503c',
            'sort_order' => 1,
            'is_required' => true,
            'input_type' => 'text',
            'question_text' => 'Title',
        ]);

        ProjectFieldValue::query()->create([
            'project_id' => $project->id,
            'field_definition_id' => $field->id,
            'final_value' => 'My Study Title',
            'status' => 'confirmed',
        ]);

        $docxPath = sys_get_temp_dir().'/template-'.bin2hex(random_bytes(4)).'.docx';
        DocxTestHelper::makeDocx($docxPath, [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t>Title: </w:t></w:r>
      <w:sdt>
        <w:sdtPr><w:id w:val="1"/></w:sdtPr>
        <w:sdtContent><w:r><w:t>[PLACEHOLDER]</w:t></w:r></w:sdtContent>
      </w:sdt>
    </w:p>
  </w:body>
</w:document>',
        ]);

        $stored = Storage::disk('local')->putFileAs('templates/test', $docxPath, 'template.docx');
        $sha256 = hash_file('sha256', $docxPath);

        $tpl = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Template',
            'sha256' => (string) $sha256,
            'storage_disk' => 'local',
            'storage_path' => $stored,
            'is_active' => true,
            'uploaded_by_user_id' => $user->id,
        ]);

        $ctrl = TemplateControl::query()->create([
            'template_version_id' => $tpl->id,
            'part' => 'document',
            'control_index' => 0,
            'context_before' => 'Title:',
            'context_after' => null,
            'placeholder_text' => '[PLACEHOLDER]',
            'signature_sha256' => hash('sha256', 'sig'),
        ]);

        TemplateControlMapping::query()->create([
            'template_version_id' => $tpl->id,
            'template_control_id' => $ctrl->id,
            'field_definition_id' => $field->id,
            'mapped_by_user_id' => $user->id,
        ]);

        $svc = app(DocxExportService::class);
        $export = $svc->generate($project, $user->id);
        $this->assertSame('ready', $export->status);
        $this->assertNotNull($export->storage_path);

        $outAbs = Storage::disk('local')->path($export->storage_path);
        $xml = DocxTestHelper::unzipPrint($outAbs, 'word/document.xml');
        $this->assertStringContainsString('My Study Title', $xml);
        $this->assertStringNotContainsString('[PLACEHOLDER]', $xml);
    }

    public function test_inserts_multiple_paragraphs_into_block_level_sdt(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'name' => 'ExportBlockSdt',
            'status' => 'draft',
        ]);

        $field = FieldDefinition::query()->create([
            'key' => 'block_text',
            'label' => 'Block Text',
            'section' => 'HRP-503c',
            'sort_order' => 2,
            'is_required' => true,
            'input_type' => 'text',
            'question_text' => 'Block Text',
        ]);

        ProjectFieldValue::query()->create([
            'project_id' => $project->id,
            'field_definition_id' => $field->id,
            'final_value' => "First paragraph\n\nSecond paragraph",
            'status' => 'confirmed',
        ]);

        $docxPath = sys_get_temp_dir().'/template-'.bin2hex(random_bytes(4)).'.docx';
        DocxTestHelper::makeDocx($docxPath, [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:sdt>
      <w:sdtPr><w:id w:val="9"/></w:sdtPr>
      <w:sdtContent>
        <w:p>
          <w:pPr><w:pStyle w:val="Normal"/></w:pPr>
          <w:r>
            <w:rPr><w:b/></w:rPr>
            <w:t>[PLACEHOLDER]</w:t>
          </w:r>
        </w:p>
      </w:sdtContent>
    </w:sdt>
  </w:body>
</w:document>',
        ]);

        $stored = Storage::disk('local')->putFileAs('templates/test', $docxPath, 'template-block.docx');
        $sha256 = hash_file('sha256', $docxPath);

        $tpl = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Block Template',
            'sha256' => (string) $sha256,
            'storage_disk' => 'local',
            'storage_path' => $stored,
            'is_active' => true,
            'uploaded_by_user_id' => $user->id,
        ]);

        $ctrl = TemplateControl::query()->create([
            'template_version_id' => $tpl->id,
            'part' => 'document',
            'control_index' => 0,
            'context_before' => null,
            'context_after' => null,
            'placeholder_text' => '[PLACEHOLDER]',
            'signature_sha256' => hash('sha256', 'sig-block'),
        ]);

        TemplateControlMapping::query()->create([
            'template_version_id' => $tpl->id,
            'template_control_id' => $ctrl->id,
            'field_definition_id' => $field->id,
            'mapped_by_user_id' => $user->id,
        ]);

        $export = app(DocxExportService::class)->generate($project, $user->id);
        $this->assertSame('ready', $export->status);

        $outAbs = Storage::disk('local')->path((string) $export->storage_path);
        $xml = DocxTestHelper::unzipPrint($outAbs, 'word/document.xml');

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml));

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphNodes = $xpath->query('//w:sdt/w:sdtContent/w:p');
        $this->assertNotFalse($paragraphNodes);
        $this->assertSame(2, $paragraphNodes->length);
        $this->assertStringContainsString('First paragraph', $xml);
        $this->assertStringContainsString('Second paragraph', $xml);
    }

    public function test_generates_encrypted_docx_when_file_encryption_is_enabled(): void
    {
        Storage::fake('local');

        $keyId = 'exp-key';
        $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        putenv('IRB_FILE_ENCRYPTION_KEYS='.$keyId.':'.base64_encode($key));
        putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID='.$keyId);

        try {
            $user = User::factory()->create();
            $project = Project::query()->create([
                'uuid' => (string) Str::uuid(),
                'owner_user_id' => $user->id,
                'name' => 'ExportEnc',
                'status' => 'draft',
            ]);

            $field = FieldDefinition::query()->create([
                'key' => 'title2',
                'label' => 'Title2',
                'section' => 'HRP-503c',
                'sort_order' => 2,
                'is_required' => true,
                'input_type' => 'text',
                'question_text' => 'Title2',
            ]);

            ProjectFieldValue::query()->create([
                'project_id' => $project->id,
                'field_definition_id' => $field->id,
                'final_value' => 'Encrypted Title',
                'status' => 'confirmed',
            ]);

            $docxPath = sys_get_temp_dir().'/template-'.bin2hex(random_bytes(4)).'.docx';
            DocxTestHelper::makeDocx($docxPath, [
                '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>',
                '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>',
                'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t>Title: </w:t></w:r>
      <w:sdt>
        <w:sdtPr><w:id w:val="1"/></w:sdtPr>
        <w:sdtContent><w:r><w:t>[PLACEHOLDER]</w:t></w:r></w:sdtContent>
      </w:sdt>
    </w:p>
  </w:body>
</w:document>',
            ]);

            $stored = Storage::disk('local')->putFileAs('templates/test', $docxPath, 'template-enc.docx');
            $sha256 = hash_file('sha256', $docxPath);

            $tpl = TemplateVersion::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Enc Template',
                'sha256' => (string) $sha256,
                'storage_disk' => 'local',
                'storage_path' => $stored,
                'is_active' => true,
                'uploaded_by_user_id' => $user->id,
            ]);

            $ctrl = TemplateControl::query()->create([
                'template_version_id' => $tpl->id,
                'part' => 'document',
                'control_index' => 0,
                'context_before' => 'Title:',
                'context_after' => null,
                'placeholder_text' => '[PLACEHOLDER]',
                'signature_sha256' => hash('sha256', 'sig-enc'),
            ]);

            TemplateControlMapping::query()->create([
                'template_version_id' => $tpl->id,
                'template_control_id' => $ctrl->id,
                'field_definition_id' => $field->id,
                'mapped_by_user_id' => $user->id,
            ]);

            $export = app(DocxExportService::class)->generate($project, $user->id);

            $this->assertSame('ready', $export->status);
            $this->assertTrue((bool) $export->is_encrypted);
            $this->assertSame($keyId, $export->encryption_key_id);
            $this->assertStringEndsWith('.enc', (string) $export->storage_path);

            $tmp = app(FileEncryptionService::class)->decryptStoredFileToTemp('local', (string) $export->storage_path);
            $xml = DocxTestHelper::unzipPrint($tmp, 'word/document.xml');
            @unlink($tmp);

            $this->assertStringContainsString('Encrypted Title', $xml);
        } finally {
            putenv('IRB_FILE_ENCRYPTION_KEYS');
            putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID');
        }
    }
}
