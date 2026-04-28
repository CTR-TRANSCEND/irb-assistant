<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DocumentChunk;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Services\DocumentExtractionService;
use App\Services\FileEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Unit\Support\DocxTestHelper;

class DocumentExtractionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeMinimalPdfWithText(string $text): string
    {
        $stream = "BT\n/F1 24 Tf\n72 120 Td\n(".$this->escapePdfString($text).") Tj\nET\n";

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> >>',
            4 => '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n".'%'.chr(0xE2).chr(0xE3).chr(0xCF).chr(0xD3)."\n";
        $offsets = [];

        foreach ($objects as $i => $body) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= '0 '.(count($objects) + 1)."\n";
        $pdf .= sprintf("%010d %05d f \n", 0, 65535);
        foreach (array_keys($objects) as $i) {
            $pdf .= sprintf("%010d %05d n \n", $offsets[$i], 0);
        }

        $pdf .= "trailer\n";
        $pdf .= '<< /Size '.(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n";
        $pdf .= "%%EOF\n";

        return $pdf;
    }

    private function escapePdfString(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    public function test_extracts_docx_into_chunks(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'name' => 'Test',
            'status' => 'draft',
        ]);

        $docxPath = sys_get_temp_dir().'/test-docx-'.bin2hex(random_bytes(4)).'.docx';

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
    <w:p><w:r><w:t>Hello world</w:t></w:r></w:p>
    <w:p><w:r><w:t>Second paragraph</w:t></w:r></w:p>
  </w:body>
</w:document>',
        ]);

        $stored = Storage::disk('local')->putFileAs('projects/'.$project->uuid.'/uploads', $docxPath, 'sample.docx');

        $doc = ProjectDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'uploaded_by_user_id' => $user->id,
            'original_filename' => 'sample.docx',
            'storage_disk' => 'local',
            'storage_path' => $stored,
            'sha256' => null,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_ext' => 'docx',
            'size_bytes' => 0,
            'kind' => 'docx',
            'extraction_status' => 'pending',
        ]);

        $svc = app(DocumentExtractionService::class);
        $svc->extract($doc);

        $doc->refresh();
        $this->assertSame('completed', $doc->extraction_status);
        $this->assertGreaterThan(0, DocumentChunk::query()->where('project_document_id', $doc->id)->count());

        $chunk = DocumentChunk::query()
            ->where('project_document_id', $doc->id)
            ->orderBy('chunk_index')
            ->first();

        $this->assertNotNull($chunk);
        $this->assertNotNull($chunk->start_offset);
        $this->assertNotNull($chunk->end_offset);
        $this->assertSame(0, (int) $chunk->start_offset);
        $this->assertSame(mb_strlen((string) $chunk->text), (int) $chunk->end_offset);
    }

    public function test_extracts_encrypted_txt_document_into_chunks(): void
    {
        Storage::fake('local');

        $keyId = 'doc-key';
        $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        putenv('IRB_FILE_ENCRYPTION_KEYS='.$keyId.':'.base64_encode($key));
        putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID='.$keyId);

        try {
            $user = User::factory()->create();
            $project = Project::query()->create([
                'uuid' => (string) Str::uuid(),
                'owner_user_id' => $user->id,
                'name' => 'EncryptedText',
                'status' => 'draft',
            ]);

            Storage::disk('local')->put('projects/'.$project->uuid.'/uploads/notes.txt', "First\n\nSecond\n");

            $doc = ProjectDocument::query()->create([
                'uuid' => (string) Str::uuid(),
                'project_id' => $project->id,
                'uploaded_by_user_id' => $user->id,
                'original_filename' => 'notes.txt',
                'storage_disk' => 'local',
                'storage_path' => 'projects/'.$project->uuid.'/uploads/notes.txt',
                'is_encrypted' => false,
                'encryption_key_id' => null,
                'sha256' => null,
                'mime_type' => 'text/plain',
                'file_ext' => 'txt',
                'size_bytes' => 0,
                'kind' => 'txt',
                'extraction_status' => 'pending',
            ]);

            $encMeta = app(FileEncryptionService::class)->encryptStoredFile('local', (string) $doc->storage_path);
            $doc->update([
                'storage_path' => $encMeta['storage_path'],
                'is_encrypted' => true,
                'encryption_key_id' => $encMeta['encryption_key_id'],
            ]);

            app(DocumentExtractionService::class)->extract($doc);

            $doc->refresh();
            $this->assertSame('completed', $doc->extraction_status);
            $this->assertGreaterThan(0, DocumentChunk::query()->where('project_document_id', $doc->id)->count());
        } finally {
            putenv('IRB_FILE_ENCRYPTION_KEYS');
            putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID');
        }
    }

    public function test_extracts_pdf_into_chunks(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'name' => 'PdfTest',
            'status' => 'draft',
        ]);

        $pdfBytes = $this->makeMinimalPdfWithText('Hello PDF');
        $path = 'projects/'.$project->uuid.'/uploads/sample.pdf';
        Storage::disk('local')->put($path, $pdfBytes);

        $doc = ProjectDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'uploaded_by_user_id' => $user->id,
            'original_filename' => 'sample.pdf',
            'storage_disk' => 'local',
            'storage_path' => $path,
            'sha256' => null,
            'mime_type' => 'application/pdf',
            'file_ext' => 'pdf',
            'size_bytes' => strlen($pdfBytes),
            'kind' => 'pdf',
            'extraction_status' => 'pending',
        ]);

        app(DocumentExtractionService::class)->extract($doc);

        $doc->refresh();
        $this->assertSame('completed', $doc->extraction_status);

        $chunkText = (string) (DocumentChunk::query()
            ->where('project_document_id', $doc->id)
            ->orderBy('chunk_index')
            ->firstOrFail()->text);

        $this->assertStringContainsString('Hello PDF', $chunkText);
    }
}
