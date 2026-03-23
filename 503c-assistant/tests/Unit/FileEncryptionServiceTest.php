<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FileEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileEncryptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_encrypt_decrypt_roundtrip_for_small_file(): void
    {
        Storage::fake('local');

        $keyId = 'k1';
        $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        putenv('IRB_FILE_ENCRYPTION_KEYS='.$keyId.':'.base64_encode($key));
        putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID='.$keyId);

        try {
            Storage::disk('local')->put('tmp/plain.txt', "alpha\nbeta\n");

            $svc = app(FileEncryptionService::class);
            $meta = $svc->encryptStoredFile('local', 'tmp/plain.txt');

            $this->assertSame('tmp/plain.txt.enc', $meta['storage_path']);
            $this->assertSame($keyId, $meta['encryption_key_id']);
            $this->assertFalse(Storage::disk('local')->exists('tmp/plain.txt'));
            $this->assertTrue(Storage::disk('local')->exists('tmp/plain.txt.enc'));

            $tmpPath = $svc->decryptStoredFileToTemp('local', 'tmp/plain.txt.enc');
            $plain = file_get_contents($tmpPath);
            @unlink($tmpPath);

            $this->assertSame("alpha\nbeta\n", $plain);
        } finally {
            putenv('IRB_FILE_ENCRYPTION_KEYS');
            putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID');
        }
    }

    public function test_decrypt_fails_when_ciphertext_is_tampered(): void
    {
        Storage::fake('local');

        $keyId = 'k1';
        $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        putenv('IRB_FILE_ENCRYPTION_KEYS='.$keyId.':'.base64_encode($key));
        putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID='.$keyId);

        try {
            Storage::disk('local')->put('tmp/plain.txt', 'sensitive payload');

            $svc = app(FileEncryptionService::class);
            $svc->encryptStoredFile('local', 'tmp/plain.txt');

            $encAbs = Storage::disk('local')->path('tmp/plain.txt.enc');
            $bytes = file_get_contents($encAbs);
            $this->assertIsString($bytes);
            $offset = max(0, strlen($bytes) - 3);
            $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x01);
            file_put_contents($encAbs, $bytes);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('failed authentication');
            $svc->decryptStoredFileToTemp('local', 'tmp/plain.txt.enc');
        } finally {
            putenv('IRB_FILE_ENCRYPTION_KEYS');
            putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID');
        }
    }

    public function test_decrypt_fails_when_key_id_is_missing_from_keyring(): void
    {
        Storage::fake('local');

        $keyId = 'k1';
        $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        putenv('IRB_FILE_ENCRYPTION_KEYS='.$keyId.':'.base64_encode($key));
        putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID='.$keyId);

        try {
            Storage::disk('local')->put('tmp/plain.txt', 'payload');

            $svc = app(FileEncryptionService::class);
            $svc->encryptStoredFile('local', 'tmp/plain.txt');

            $otherKey = sodium_crypto_secretstream_xchacha20poly1305_keygen();
            putenv('IRB_FILE_ENCRYPTION_KEYS=other:'.base64_encode($otherKey));
            putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID=other');

            $svc2 = app(FileEncryptionService::class);
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('not available in configured keyring');
            $svc2->decryptStoredFileToTemp('local', 'tmp/plain.txt.enc');
        } finally {
            putenv('IRB_FILE_ENCRYPTION_KEYS');
            putenv('IRB_FILE_ENCRYPTION_ACTIVE_KEY_ID');
        }
    }
}
