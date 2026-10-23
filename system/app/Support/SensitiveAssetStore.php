<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Encrypted-at-rest asset store for sensitive grading material (OMR photos).
 * Files are AES-256-CBC encrypted with a key derived from APP_KEY; the caller
 * records the SHA-256 HMAC of the plaintext so integrity is verifiable on read
 * (founder lock: grading data is the most sensitive; master §8.6 HMAC intent).
 */
final class SensitiveAssetStore
{
    private const MAX_DERIVATIVE_BYTES = 1_048_576;

    private const DERIVATIVE_ENVELOPE_OVERHEAD = 4
        + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
        + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;

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
        return $this->sourceLock($path)->block(10, function () use ($bytes, $path): string {
            $iv = random_bytes(16);
            $ciphertext = openssl_encrypt($bytes, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv);

            if ($ciphertext === false) {
                throw new RuntimeException('Failed to encrypt sensitive asset.');
            }

            Storage::disk('sensitive')->put($path, $iv.$ciphertext);
            Storage::disk('sensitive')->delete($this->derivativePath($path));

            return $path;
        });
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

    public function rememberDerivative(string $sourcePath, string $expectedSourceHmac, Closure $transform): string
    {
        $this->validateDerivativeContext($sourcePath, $expectedSourceHmac);
        $sidecarPath = $this->derivativePath($sourcePath);

        return $this->sourceLock($sourcePath)->block(10, function () use ($sourcePath, $expectedSourceHmac, $transform, $sidecarPath): string {
            $disk = Storage::disk('sensitive');

            if ($disk->exists($sidecarPath)) {
                try {
                    $envelopeBytes = $disk->size($sidecarPath);
                    if ($envelopeBytes <= self::MAX_DERIVATIVE_BYTES + self::DERIVATIVE_ENVELOPE_OVERHEAD) {
                        $envelope = $disk->get($sidecarPath);
                        if (is_string($envelope)) {
                            return $this->decryptDerivative($envelope, $sourcePath, $expectedSourceHmac);
                        }
                    }
                } catch (Throwable) {
                    // An unauthenticated cache entry is disposable; source read below remains authoritative.
                }

                $disk->delete($sidecarPath);
            }

            $source = $this->read($sourcePath, $expectedSourceHmac);
            $derivative = $transform($source);
            if (! is_string($derivative)) {
                throw new RuntimeException('Sensitive derivative transform must return a string.');
            }

            if (strlen($derivative) > self::MAX_DERIVATIVE_BYTES) {
                throw new RuntimeException('Sensitive derivative exceeds the maximum allowed size.');
            }

            $envelope = $this->encryptDerivative($derivative, $sourcePath, $expectedSourceHmac);
            if (! $disk->put($sidecarPath, $envelope)) {
                throw new RuntimeException('Failed to store sensitive derivative.');
            }

            return $derivative;
        });
    }

    public function delete(string $path): void
    {
        $this->sourceLock($path)->block(10, function () use ($path): void {
            Storage::disk('sensitive')->delete($path);
            Storage::disk('sensitive')->delete($this->derivativePath($path));
        });
    }

    private function sourceLock(string $sourcePath): Lock
    {
        return Cache::lock('sensitive-derivative:'.hash('sha256', $sourcePath), 120);
    }

    private function derivativePath(string $sourcePath): string
    {
        return $sourcePath.'.thumbnail.v1';
    }

    private function validateDerivativeContext(string $sourcePath, string $expectedSourceHmac): void
    {
        $segments = explode('/', $sourcePath);
        if ($sourcePath === ''
            || str_starts_with($sourcePath, '/')
            || str_contains($sourcePath, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $sourcePath) === 1
            || in_array('', $segments, true)
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
            || str_ends_with($sourcePath, '.thumbnail.v1')) {
            throw new RuntimeException('Sensitive derivative source path is invalid.');
        }

        if (preg_match('/\A[0-9a-f]{64}\z/', $expectedSourceHmac) !== 1) {
            throw new RuntimeException('Sensitive derivative source HMAC is invalid.');
        }
    }

    private function derivativeKey(): string
    {
        return hash_hkdf('sha256', (string) config('app.key'), SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, 'nexiam-sensitive-derivative-v1');
    }

    private function derivativeAad(string $sourcePath, string $expectedSourceHmac): string
    {
        return "NEXIAM-SENSITIVE-DERIVATIVE-V1\0".$sourcePath."\0".$expectedSourceHmac;
    }

    private function encryptDerivative(string $plaintext, string $sourcePath, string $expectedSourceHmac): string
    {
        if (strlen($plaintext) > self::MAX_DERIVATIVE_BYTES) {
            throw new RuntimeException('Sensitive derivative exceeds the maximum allowed size.');
        }

        $magic = 'NSD1';
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $this->derivativeAad($sourcePath, $expectedSourceHmac),
            $nonce,
            $this->derivativeKey(),
        );

        return $magic.$nonce.$ciphertext;
    }

    private function decryptDerivative(string $envelope, string $sourcePath, string $expectedSourceHmac): string
    {
        $minimumLength = self::DERIVATIVE_ENVELOPE_OVERHEAD;
        $maximumLength = self::MAX_DERIVATIVE_BYTES + self::DERIVATIVE_ENVELOPE_OVERHEAD;
        if (strlen($envelope) < $minimumLength || strlen($envelope) > $maximumLength || substr($envelope, 0, 4) !== 'NSD1') {
            throw new RuntimeException('Sensitive derivative envelope is invalid.');
        }

        $nonce = substr($envelope, 4, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = substr($envelope, 4 + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            $this->derivativeAad($sourcePath, $expectedSourceHmac),
            $nonce,
            $this->derivativeKey(),
        );

        if ($plaintext === false) {
            throw new RuntimeException('Sensitive derivative authentication failed.');
        }

        return $plaintext;
    }
}
