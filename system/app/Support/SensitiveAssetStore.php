<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Encrypted-at-rest asset store for sensitive grading material (OMR photos).
 * Files are AES-256-CBC encrypted with a key derived from APP_KEY; the caller
 * records the SHA-256 HMAC of the plaintext so integrity is verifiable on read
 * (founder lock: grading data is the most sensitive; master §8.6 HMAC intent).
 */
final class SensitiveAssetStore
{
    private function key(): string
    {
        return hash('sha256', (string) config('app.key'), true);
    }

    public function hmac(string $bytes): string
    {
        return hash_hmac('sha256', $bytes, (string) config('app.key'));
    }

    /**
     * Encrypt and persist bytes. Returns the stored path on the `sensitive`
     * disk. Path is caller-owned (e.g. `omr/{batch}/{sheet}.img`).
     */
    public function store(string $bytes, string $path): string
    {
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($bytes, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new RuntimeException('Failed to encrypt sensitive asset.');
        }

        Storage::disk('sensitive')->put($path, $iv.$ciphertext);

        return $path;
    }

    /**
     * Decrypt and return the plaintext bytes. When an expected HMAC is given,
     * a mismatch throws — corrupted or tampered assets must never be served
     * silently (integrity failure).
     */
    public function read(string $path, ?string $expectedHmac = null): string
    {
        $payload = Storage::disk('sensitive')->get($path);

        if ($payload === null) {
            throw new RuntimeException('Sensitive asset not found.');
        }

        $iv = substr($payload, 0, 16);
        $ciphertext = substr($payload, 16);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new RuntimeException('Failed to decrypt sensitive asset.');
        }

        if ($expectedHmac !== null && ! hash_equals($expectedHmac, $this->hmac($plaintext))) {
            throw new RuntimeException('Sensitive asset integrity check failed.');
        }

        return $plaintext;
    }

    public function delete(string $path): void
    {
        Storage::disk('sensitive')->delete($path);
    }
}
