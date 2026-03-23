<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentChunk;
use App\Models\ProjectDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\Process\Process;

class DocumentExtractionService
{
    public function __construct(private FileEncryptionService $fileEncryption)
    {
    }

    public function extract(ProjectDocument $document): void
    {
        $document->update([
            'extraction_status' => 'processing',
            'extraction_error' => null,
        ]);

        $sourcePath = Storage::disk($document->storage_disk)->path($document->storage_path);
        $tempPath = null;

        try {
            DocumentChunk::query()->where('project_document_id', $document->id)->delete();

            if ((bool) $document->is_encrypted) {
                $tempPath = $this->fileEncryption->decryptStoredFileToTemp($document->storage_disk, $document->storage_path);
                $sourcePath = $tempPath;
            }

            $text = match ($document->kind) {
                'txt' => $this->extractTxt($sourcePath),
                'docx' => $this->extractDocx($sourcePath),
                'pdf' => $this->extractPdf($sourcePath),
                default => throw new \RuntimeException('Unsupported document kind: '.$document->kind),
            };

            $this->chunkAndPersist($document, $text);

            $document->update([
                'extraction_status' => 'completed',
                'extracted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $document->update([
                'extraction_status' => 'failed',
                'extraction_error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if (is_string($tempPath) && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function extractTxt(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read txt file');
        }

        return $this->normalizeText($contents);
    }

    private function extractPdf(string $path): string
    {
        $timeout = (int) env('IRB_PDFTOTEXT_TIMEOUT_SECONDS', 20);
        $maxPages = (int) env('IRB_PDF_MAX_PAGES', 200);
        $maxBytes = (int) env('IRB_PDF_MAX_TEXT_BYTES', 5_000_000);

        try {
            $cmd = ['pdftotext', '-q', '-enc', 'UTF-8', '-eol', 'unix'];
            if ($maxPages > 0) {
                $cmd[] = '-f';
                $cmd[] = '1';
                $cmd[] = '-l';
                $cmd[] = (string) $maxPages;
            }
            $cmd[] = $path;
            $cmd[] = '-';

            $process = new Process($cmd);
            $process->setTimeout($timeout);
            $process->run();

            if ($process->isSuccessful()) {
                $text = (string) $process->getOutput();
                if ($maxBytes > 0 && strlen($text) > $maxBytes) {
                    $text = substr($text, 0, $maxBytes);
                }

                $text = $this->normalizeText($text);
                if ($text !== '') {
                    return $text;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('pdftotext failed, falling back to smalot/pdfparser', [
                'error' => $e->getMessage(),
            ]);
        }

        $memoryLimit = (int) env('IRB_PDF_PARSER_MEMORY_MB', 256);
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', $memoryLimit.'M');

        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();
        } finally {
            if (is_string($previousLimit) && $previousLimit !== '') {
                ini_set('memory_limit', $previousLimit);
            }
        }

        if ($maxBytes > 0 && strlen($text) > $maxBytes) {
            $text = substr($text, 0, $maxBytes);
        }

        return $this->normalizeText($text);
    }

    private function extractDocx(string $path): string
    {
        $xml = $this->unzipPrintFile($path, 'word/document.xml');
        if ($xml === '') {
            throw new \RuntimeException('DOCX missing word/document.xml');
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = [];
        foreach ($xpath->query('//w:body//w:p') as $p) {
            $texts = [];
            foreach ($xpath->query('.//w:t', $p) as $t) {
                $texts[] = $t->textContent;
            }
            $para = trim(implode('', $texts));
            if ($para !== '') {
                $paragraphs[] = $para;
            }
        }

        return $this->normalizeText(implode("\n\n", $paragraphs));
    }

    private function unzipPrintFile(string $docxPath, string $innerPath): string
    {
        $process = new Process(['unzip', '-p', $docxPath, $innerPath]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return '';
        }

        return (string) $process->getOutput();
    }

    private function chunkAndPersist(ProjectDocument $document, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $chunks = $this->splitIntoChunks($text, 1400);
        $cursor = 0;
        foreach ($chunks as $i => $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $startOffset = mb_strpos($text, $chunk, $cursor);
            if ($startOffset === false) {
                $startOffset = mb_strpos($text, $chunk);
            }

            $endOffset = $startOffset === false ? null : ($startOffset + mb_strlen($chunk));
            if ($startOffset !== false) {
                $cursor = $endOffset;
            }

            DocumentChunk::query()->create([
                'project_document_id' => $document->id,
                'chunk_index' => (int) $i,
                'page_number' => null,
                'source_locator' => null,
                'heading' => null,
                'text' => $chunk,
                'text_sha256' => hash('sha256', $chunk),
                'start_offset' => $startOffset === false ? null : $startOffset,
                'end_offset' => $endOffset,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function splitIntoChunks(string $text, int $targetChars): array
    {
        $paras = preg_split("/\n{2,}/", $text) ?: [];
        $out = [];
        $buf = '';

        foreach ($paras as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }

            $candidate = $buf === '' ? $p : ($buf."\n\n".$p);
            if (mb_strlen($candidate) <= $targetChars) {
                $buf = $candidate;
                continue;
            }

            if ($buf !== '') {
                $out[] = $buf;
                $buf = '';
            }

            if (mb_strlen($p) <= $targetChars) {
                $buf = $p;
                continue;
            }

            $offset = 0;
            $len = mb_strlen($p);
            while ($offset < $len) {
                $out[] = mb_substr($p, $offset, $targetChars);
                $offset += $targetChars;
            }
        }

        if ($buf !== '') {
            $out[] = $buf;
        }

        return $out;
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
