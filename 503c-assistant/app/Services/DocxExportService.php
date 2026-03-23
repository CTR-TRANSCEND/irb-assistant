<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Export;
use App\Models\Project;
use App\Models\TemplateControlMapping;
use App\Models\TemplateVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DocxExportService
{
    public function __construct(private FileEncryptionService $fileEncryption)
    {
    }

    public function generate(Project $project, int $actorUserId): Export
    {
        $template = TemplateVersion::query()->where('is_active', true)->orderByDesc('created_at')->first();
        if ($template === null) {
            throw new \RuntimeException('No active template configured');
        }

        $export = Export::query()->create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'template_version_id' => $template->id,
            'created_by_user_id' => $actorUserId,
            'status' => 'generating',
            'storage_disk' => 'local',
        ]);

        $tmpDirAbs = null;

        try {
            $outputPath = $this->generateDocx($project, $template, $export->uuid, $tmpDirAbs);

            $isEncrypted = false;
            $encryptionKeyId = null;
            if ($this->fileEncryption->isEnabled()) {
                $encryptedMeta = $this->fileEncryption->encryptStoredFile('local', $outputPath);
                $outputPath = (string) $encryptedMeta['storage_path'];
                $isEncrypted = true;
                $encryptionKeyId = (string) $encryptedMeta['encryption_key_id'];
            }

            $export->update([
                'status' => 'ready',
                'storage_path' => $outputPath,
                'is_encrypted' => $isEncrypted,
                'encryption_key_id' => $encryptionKeyId,
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $export->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if ($tmpDirAbs !== null && is_dir($tmpDirAbs)) {
                File::deleteDirectory($tmpDirAbs);
            }
        }

        return $export;
    }

    private function generateDocx(Project $project, TemplateVersion $template, string $exportUuid, ?string &$tmpDirAbsOut = null): string
    {
        $templateAbs = Storage::disk($template->storage_disk)->path($template->storage_path);
        $tmpDirRel = 'tmp/export_'.$exportUuid;
        $tmpDirAbs = Storage::disk('local')->path($tmpDirRel);
        $tmpDirAbsOut = $tmpDirAbs;

        if (! is_dir($tmpDirAbs) && ! mkdir($tmpDirAbs, 0700, true) && ! is_dir($tmpDirAbs)) {
            throw new \RuntimeException('Failed to create temp dir');
        }

        $unzip = new Process(['unzip', '-q', $templateAbs, '-d', $tmpDirAbs]);
        $unzip->setTimeout(60);
        $unzip->mustRun();

        // Build (part, control_index) -> value map using mappings.
        $mappings = TemplateControlMapping::query()
            ->where('template_version_id', $template->id)
            ->with(['control', 'field'])
            ->get();

        $fieldValues = $project->fieldValues()->with('field')->get()->keyBy('field_definition_id');
        $partIndexToValue = [];

        foreach ($mappings as $m) {
            if ($m->control === null || $m->field === null) {
                continue;
            }

            $fv = $fieldValues->get($m->field->id);
            $val = $fv?->final_value;
            if ($val === null || trim($val) === '') {
                $val = $fv?->suggested_value;
            }

            $val = trim((string) ($val ?? ''));
            if ($val === '') {
                // Do not overwrite template content when no value is available.
                continue;
            }

            $partIndexToValue[$m->control->part][(int) $m->control->control_index] = $val;
        }

        foreach ($partIndexToValue as $part => $controlIndexToValue) {
            $innerPath = $this->innerPathForPart((string) $part);
            if ($innerPath === null) {
                continue;
            }
            $this->fillSdtPart($tmpDirAbs, (string) $part, $innerPath, $controlIndexToValue);
        }

        $outRel = "exports/{$project->uuid}/{$exportUuid}.docx";
        $outAbs = Storage::disk('local')->path($outRel);
        $outDir = dirname($outAbs);
        if (! is_dir($outDir) && ! mkdir($outDir, 0700, true) && ! is_dir($outDir)) {
            throw new \RuntimeException('Failed to create export directory');
        }

        $zip = new Process(['zip', '-qr', $outAbs, '.']);
        $zip->setWorkingDirectory($tmpDirAbs);
        $zip->setTimeout(60);
        $zip->mustRun();

        return $outRel;
    }

    /**
     * @param array<int, string> $controlIndexToValue
     */
    private function fillSdtPart(string $tmpDirAbs, string $part, string $innerPath, array $controlIndexToValue): void
    {
        if (count($controlIndexToValue) === 0) {
            return;
        }

        $xmlPath = $tmpDirAbs.'/'.$innerPath;
        if (! is_file($xmlPath)) {
            return;
        }

        $xml = file_get_contents($xmlPath);
        if ($xml === false) {
            throw new \RuntimeException('Failed to read '.$innerPath);
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;

        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadXML($xml, LIBXML_NONET);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if (count($xmlErrors) > 0) {
            Log::warning('XML parsing issues in DOCX part', [
                'part' => $part,
                'errors' => array_map(fn ($e) => trim($e->message), array_slice($xmlErrors, 0, 5)),
            ]);
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $sdts = $xpath->query('//w:sdt');
        if ($sdts === false) {
            throw new \RuntimeException('Failed to locate controls in '.$innerPath);
        }

        foreach ($sdts as $i => $sdt) {
            if (! ($sdt instanceof \DOMElement)) {
                continue;
            }

            if (! array_key_exists((int) $i, $controlIndexToValue)) {
                continue;
            }

            $this->setSdtText($xpath, $dom, $sdt, $controlIndexToValue[(int) $i]);
        }

        $dom->formatOutput = false;
        file_put_contents($xmlPath, $dom->saveXML());
    }

    private function innerPathForPart(string $part): ?string
    {
        return match ($part) {
            'document' => 'word/document.xml',
            'endnotes' => 'word/endnotes.xml',
            'footnotes' => 'word/footnotes.xml',
            default => $this->innerPathForNumberedPart($part),
        };
    }

    private function innerPathForNumberedPart(string $part): ?string
    {
        $m = [];
        if (preg_match('/^(header|footer)(\d+)$/', $part, $m) !== 1) {
            return null;
        }

        return 'word/'.$m[1].$m[2].'.xml';
    }

    private function setSdtText(\DOMXPath $xpath, \DOMDocument $dom, \DOMElement $sdt, string $value): void
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        $sdtContent = $xpath->query('.//w:sdtContent', $sdt)?->item(0);
        if (! ($sdtContent instanceof \DOMElement)) {
            return;
        }

        $hasParagraphs = $xpath->query('./w:p', $sdtContent)?->length > 0;
        if ($hasParagraphs) {
            $this->setBlockSdtText($xpath, $dom, $sdtContent, $value);

            return;
        }

        $lines = explode("\n", $value);

        $tNodes = $xpath->query('.//w:t', $sdtContent);
        if ($tNodes === false || $tNodes->length === 0) {
            // Create a minimal run.
            $r = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
            $this->appendTextWithBreaks($dom, $r, $lines);
            $sdtContent->appendChild($r);
            return;
        }

        /** @var \DOMElement $firstT */
        $firstT = $tNodes->item(0);
        $firstT->nodeValue = '';

        // Remove other text nodes.
        for ($i = $tNodes->length - 1; $i >= 1; $i--) {
            $n = $tNodes->item($i);
            if ($n instanceof \DOMNode && $n->parentNode !== null) {
                $n->parentNode->removeChild($n);
            }
        }

        // Add line breaks in the same run if needed.
        if ($firstT->parentNode instanceof \DOMElement) {
            $run = $firstT->parentNode;
            $this->appendTextWithBreaks($dom, $run, $lines);
        }
    }

    private function setBlockSdtText(\DOMXPath $xpath, \DOMDocument $dom, \DOMElement $sdtContent, string $value): void
    {
        $firstParagraph = $xpath->query('./w:p[1]', $sdtContent)?->item(0);

        $paragraphProperties = null;
        $runProperties = null;

        if ($firstParagraph instanceof \DOMElement) {
            $paragraphProperties = $xpath->query('./w:pPr[1]', $firstParagraph)?->item(0);
            $firstRun = $xpath->query('.//w:r[1]', $firstParagraph)?->item(0);
            if ($firstRun instanceof \DOMElement) {
                $runProperties = $xpath->query('./w:rPr[1]', $firstRun)?->item(0);
            }
        }

        while ($sdtContent->firstChild !== null) {
            $sdtContent->removeChild($sdtContent->firstChild);
        }

        $paragraphs = $this->splitIntoParagraphs($value);
        foreach ($paragraphs as $paragraphText) {
            $paragraph = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p');
            if ($paragraphProperties instanceof \DOMNode) {
                $paragraph->appendChild($dom->importNode($paragraphProperties, true));
            }

            $run = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
            if ($runProperties instanceof \DOMNode) {
                $run->appendChild($dom->importNode($runProperties, true));
            }

            $this->appendTextWithBreaks($dom, $run, explode("\n", $paragraphText));
            $paragraph->appendChild($run);
            $sdtContent->appendChild($paragraph);
        }
    }

    private function splitIntoParagraphs(string $value): array
    {
        $paragraphs = [''];
        $currentIndex = 0;
        $offset = 0;

        while (preg_match('/\n+/', $value, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $newlineText = $match[0][0];
            $newlineOffset = $match[0][1];
            $newlineLength = strlen($newlineText);

            $paragraphs[$currentIndex] .= substr($value, $offset, $newlineOffset - $offset);

            if ($newlineLength === 1) {
                $paragraphs[$currentIndex] .= "\n";
            } else {
                $paragraphs[] = '';
                $currentIndex++;

                for ($i = 0; $i < $newlineLength - 2; $i++) {
                    $paragraphs[] = '';
                    $currentIndex++;
                }
            }

            $offset = $newlineOffset + $newlineLength;
        }

        $paragraphs[$currentIndex] .= substr($value, $offset);

        return $paragraphs;
    }

    private function appendTextWithBreaks(\DOMDocument $dom, \DOMElement $run, array $lines): void
    {
        for ($i = $run->childNodes->length - 1; $i >= 0; $i--) {
            $node = $run->childNodes->item($i);
            if (! ($node instanceof \DOMNode)) {
                continue;
            }

            $isRunProperties = $node instanceof \DOMElement
                && $node->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
                && $node->localName === 'rPr';

            if (! $isRunProperties) {
                $run->removeChild($node);
            }
        }

        $lineCount = count($lines);
        for ($i = 0; $i < $lineCount; $i++) {
            if ($i > 0) {
                $run->appendChild($dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:br'));
            }

            $textNode = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
            $textNode->setAttribute('xml:space', 'preserve');
            $textNode->nodeValue = (string) $lines[$i];
            $run->appendChild($textNode);
        }
    }
}
