<?php

namespace App\Ai\Connectors;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Write-only credential boundary.
 *
 * The returned handle is opaque and the public API never exposes secret
 * material. A deployment can replace the cache backend with KMS-backed
 * storage without changing ConnectorCatalog or callers.
 */
final class SecretVault
{
    public function put(string $connectorId, string $secret): string
    {
        if ($connectorId === '' || $secret === '') {
            throw new RuntimeException('connector_secret_required');
        }

        $handle = 'xv_'.Str::random(40);
        Cache::forever($this->cacheKey($handle), [
            'connector_id' => $connectorId,
            'ciphertext' => Crypt::encryptString($secret),
            'created_at' => now()->toIso8601String(),
        ]);

        return $handle;
    }

    public function revoke(string $handle): void
    {
        Cache::forget($this->cacheKey($handle));
    }

    public function mask(): string
    {
        return '••••••••';
    }

    public function exists(string $handle): bool
    {
        return Cache::has($this->cacheKey($handle));
    }

    private function cacheKey(string $handle): string
    {
        return 'xiam.secret-vault.'.hash('sha256', $handle);
    }
}
