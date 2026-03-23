<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Symfony\Component\Process\Process;

class DocxTestHelper
{
    /**
     * Create a minimal .docx by zipping provided parts.
     *
     * @param array<string, string> $files relative path => contents
     */
    public static function makeDocx(string $targetPath, array $files): void
    {
        $dir = sys_get_temp_dir().'/docx_'.bin2hex(random_bytes(6));
        if (! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Failed to create temp dir');
        }

        try {
            foreach ($files as $rel => $contents) {
                $abs = $dir.'/'.ltrim($rel, '/');
                $parent = dirname($abs);
                if (! is_dir($parent) && ! mkdir($parent, 0700, true) && ! is_dir($parent)) {
                    throw new \RuntimeException('Failed to create parent dir');
                }
                file_put_contents($abs, $contents);
            }

            $proc = new Process(['zip', '-qr', $targetPath, '.']);
            $proc->setWorkingDirectory($dir);
            $proc->setTimeout(30);
            $proc->mustRun();
        } finally {
            self::deleteDir($dir);
        }
    }

    public static function unzipPrint(string $docxPath, string $innerPath): string
    {
        $proc = new Process(['unzip', '-p', $docxPath, $innerPath]);
        $proc->setTimeout(30);
        $proc->run();

        return $proc->isSuccessful() ? (string) $proc->getOutput() : '';
    }

    private static function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                self::deleteDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
