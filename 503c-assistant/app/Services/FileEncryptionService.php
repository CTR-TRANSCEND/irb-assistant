<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class FileEncryptionService
{
    private const MAGIC = 'IRBENC01';

    private array $keys;

    private ?string $activeKeyId;

    public function __construct()
    {
        $this->keys = $this->parseKeyring((string) env('IRB_FILE_ENCRYPTION_KEYS', ''));

        $active = trim((string) env('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID', ''));
        $this->activeKeyId = $active !== '' ? $active : null;
    }

    public function isEnabled(): bool
    {
        return $this->activeKeyId !== null && array_key_exists($this->activeKeyId, $this->keys);
    }

    public function encryptStoredFile(string $disk, string $sourcePath, ?string $targetPath = null): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('File encryption is not configured.');
        }

        $keyId = (string) $this->activeKeyId;
        $key = $this->keys[$keyId] ?? null;
        if (! is_string($key)) {
            throw new \RuntimeException('Active file encryption key is missing.');
        }

        $targetPath = $targetPath ?? ($sourcePath.'.enc');

        $sourceAbs = Storage::disk($disk)->path($sourcePath);
        $targetAbs = Storage::disk($disk)->path($targetPath);

        $dir = dirname($targetAbs);
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Failed to create destination directory for encrypted file.');
        }

        $tmpTargetAbs = $targetAbs.'.tmp.'.bin2hex(random_bytes(8));

        try {
            $this->encryptAbsoluteFile($sourceAbs, $tmpTargetAbs, $keyId, $key);

            if (! rename($tmpTargetAbs, $targetAbs)) {
                throw new \RuntimeException('Failed to finalize encrypted file.');
            }

            if ($sourcePath !== $targetPath && is_file($sourceAbs) && ! unlink($sourceAbs)) {
                throw new \RuntimeException('Encrypted file written but source file cleanup failed.');
            }
        } finally {
            if (is_file($tmpTargetAbs)) {
                @unlink($tmpTargetAbs);
            }
        }

        return [
            'storage_path' => $targetPath,
            'encryption_key_id' => $keyId,
        ];
    }

    public function decryptStoredFileToTemp(string $disk, string $storagePath): string
    {
        $sourceAbs = Storage::disk($disk)->path($storagePath);
        $tempAbs = sys_get_temp_dir().'/irb-dec-'.bin2hex(random_bytes(10));

        try {
            $this->decryptAbsoluteFile($sourceAbs, $tempAbs);
        } catch (\Throwable $e) {
            if (is_file($tempAbs)) {
                @unlink($tempAbs);
            }

            throw $e;
        }

        return $tempAbs;
    }

    public function decryptStoredFileToStream(string $disk, string $storagePath, $outputStream): void
    {
        if (! is_resource($outputStream)) {
            throw new \InvalidArgumentException('Output stream is not a valid resource.');
        }

        $sourceAbs = Storage::disk($disk)->path($storagePath);
        $this->decryptAbsoluteFile($sourceAbs, null, $outputStream);
    }

    private function encryptAbsoluteFile(string $sourceAbs, string $targetAbs, string $keyId, string $key): void
    {
        $in = fopen($sourceAbs, 'rb');
        if ($in === false) {
            throw new \RuntimeException('Failed to open source file for encryption.');
        }

        $out = fopen($targetAbs, 'wb');
        if ($out === false) {
            fclose($in);
            throw new \RuntimeException('Failed to open target file for encryption.');
        }

        try {
            $stateAndHeader = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            $state = $stateAndHeader[0];
            $header = $stateAndHeader[1];

            $this->writeAll($out, self::MAGIC);
            $this->writeAll($out, pack('n', strlen($keyId)));
            $this->writeAll($out, $keyId);
            $this->writeAll($out, $header);

            $chunk = fread($in, 65536);
            if ($chunk === false) {
                throw new \RuntimeException('Failed to read plaintext chunk for encryption.');
            }

            if ($chunk === '') {
                $ciphertext = sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state,
                    '',
                    '',
                    SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                );
                $this->writeAll($out, pack('N', strlen($ciphertext)));
                $this->writeAll($out, $ciphertext);
            } else {
                while (true) {
                    $next = fread($in, 65536);
                    if ($next === false) {
                        throw new \RuntimeException('Failed to read plaintext chunk for encryption.');
                    }

                    $isFinal = $next === '';
                    $tag = $isFinal
                        ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                        : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;

                    $ciphertext = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                    $this->writeAll($out, pack('N', strlen($ciphertext)));
                    $this->writeAll($out, $ciphertext);

                    if ($isFinal) {
                        break;
                    }

                    $chunk = $next;
                }
            }

            if (! fflush($out)) {
                throw new \RuntimeException('Failed to flush encrypted output file.');
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    private function decryptAbsoluteFile(string $sourceAbs, ?string $targetAbs = null, $streamOut = null): void
    {
        $in = fopen($sourceAbs, 'rb');
        if ($in === false) {
            throw new \RuntimeException('Failed to open encrypted file for decryption.');
        }

        $out = $streamOut;
        $closeOut = false;

        if ($out === null) {
            if ($targetAbs === null) {
                fclose($in);
                throw new \InvalidArgumentException('Target path or output stream is required for decryption.');
            }

            $out = fopen($targetAbs, 'wb');
            if ($out === false) {
                fclose($in);
                throw new \RuntimeException('Failed to open destination file for decryption.');
            }

            $closeOut = true;
        }

        try {
            $magic = $this->readExact($in, strlen(self::MAGIC));
            if ($magic !== self::MAGIC) {
                throw new \RuntimeException('Encrypted file format is invalid.');
            }

            $keyIdLenRaw = $this->readExact($in, 2);
            $keyIdLen = unpack('nlen', $keyIdLenRaw);
            $len = (int) ($keyIdLen['len'] ?? 0);
            if ($len <= 0 || $len > 512) {
                throw new \RuntimeException('Encrypted file key id is invalid.');
            }

            $keyId = $this->readExact($in, $len);
            $key = $this->keys[$keyId] ?? null;
            if (! is_string($key)) {
                throw new \RuntimeException('Encrypted file key id is not available in configured keyring: '.$keyId);
            }

            $header = $this->readExact($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);

            $sawFinal = false;

            while (! feof($in)) {
                $lenRaw = fread($in, 4);
                if ($lenRaw === false) {
                    throw new \RuntimeException('Failed reading encrypted frame length.');
                }

                if ($lenRaw === '') {
                    break;
                }

                if (strlen($lenRaw) !== 4) {
                    throw new \RuntimeException('Encrypted file frame length is truncated.');
                }

                $frame = unpack('Nlen', $lenRaw);
                $cipherLen = (int) ($frame['len'] ?? 0);
                if ($cipherLen <= 0) {
                    throw new \RuntimeException('Encrypted file frame length is invalid.');
                }

                $ciphertext = $this->readExact($in, $cipherLen);
                $pulled = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $ciphertext);
                if ($pulled === false) {
                    throw new \RuntimeException('Encrypted file failed authentication (tampered or wrong key).');
                }

                $plaintext = $pulled[0];
                $tag = $pulled[1];

                $this->writeAll($out, $plaintext);

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $sawFinal = true;

                    $peek = fread($in, 1);
                    if ($peek === false) {
                        throw new \RuntimeException('Failed to validate encrypted stream tail.');
                    }
                    if ($peek !== '') {
                        throw new \RuntimeException('Encrypted file has trailing data after final frame.');
                    }

                    break;
                }
            }

            if (! $sawFinal) {
                throw new \RuntimeException('Encrypted file is truncated or missing final frame.');
            }

            if (! fflush($out)) {
                throw new \RuntimeException('Failed flushing decrypted output.');
            }
        } finally {
            fclose($in);
            if ($closeOut && is_resource($out)) {
                fclose($out);
            }
        }
    }

    private function readExact($stream, int $len): string
    {
        $buf = '';
        while (strlen($buf) < $len) {
            $chunk = fread($stream, $len - strlen($buf));
            if ($chunk === false) {
                throw new \RuntimeException('Failed reading encrypted data.');
            }

            if ($chunk === '') {
                throw new \RuntimeException('Unexpected end of encrypted file.');
            }

            $buf .= $chunk;
        }

        return $buf;
    }

    private function writeAll($stream, string $data): void
    {
        $offset = 0;
        $len = strlen($data);

        while ($offset < $len) {
            $written = fwrite($stream, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('Failed writing encrypted data.');
            }

            $offset += $written;
        }
    }

    private function parseKeyring(string $raw): array
    {
        $out = [];
        $pairs = array_filter(array_map('trim', explode(',', $raw)), static fn (string $s): bool => $s !== '');

        foreach ($pairs as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $keyId = trim($parts[0]);
            $keyB64 = trim($parts[1]);
            if ($keyId === '' || $keyB64 === '') {
                continue;
            }

            $key = base64_decode($keyB64, true);
            if (! is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
                continue;
            }

            $out[$keyId] = $key;
        }

        return $out;
    }
}
