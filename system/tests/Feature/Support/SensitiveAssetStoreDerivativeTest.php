<?php

use App\Support\SensitiveAssetStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    Cache::purge('array');
    Storage::fake('sensitive');
});

test('sensitive derivatives reuse authenticated cached output', function (): void {
    $store = app(SensitiveAssetStore::class);
    $source = 'verified source bytes';
    $path = $store->store($source, 'omr/captures/source.bin');
    $hmac = $store->hmac($source);
    $calls = 0;
    $transform = function (string $bytes) use (&$calls): string {
        $calls++;

        return strtoupper($bytes);
    };

    expect($store->rememberDerivative($path, $hmac, $transform))->toBe(strtoupper($source))
        ->and($store->rememberDerivative($path, $hmac, $transform))->toBe(strtoupper($source))
        ->and($calls)->toBe(1)
        ->and(Storage::disk('sensitive')->exists($path.'.thumbnail.v1'))->toBeTrue();
});

test('sensitive derivatives are bound to the exact path and source HMAC', function (): void {
    $store = app(SensitiveAssetStore::class);
    $source = 'same source bytes';
    $sourcePath = $store->store($source, 'omr/captures/first.bin');
    $otherPath = $store->store($source, 'omr/captures/second.bin');
    $hmac = $store->hmac($source);
    $firstDerivative = $store->rememberDerivative($sourcePath, $hmac, fn (string $bytes): string => 'first:'.$bytes);
    Storage::disk('sensitive')->copy($sourcePath.'.thumbnail.v1', $otherPath.'.thumbnail.v1');

    $calls = 0;
    $otherDerivative = $store->rememberDerivative($otherPath, $hmac, function (string $bytes) use (&$calls): string {
        $calls++;

        return 'second:'.$bytes;
    });

    expect($firstDerivative)->toBe('first:'.$source)
        ->and($otherDerivative)->toBe('second:'.$source)
        ->and($calls)->toBe(1)
        ->and(fn () => $store->rememberDerivative($sourcePath, str_repeat('0', 64), fn (string $bytes): string => 'wrong:'.$bytes))
        ->toThrow(RuntimeException::class);
});

test('tampered derivative is discarded and rebuilt from the verified source', function (): void {
    $store = app(SensitiveAssetStore::class);
    $source = 'authentic source';
    $path = $store->store($source, 'omr/captures/tampered.bin');
    $hmac = $store->hmac($source);
    $calls = 0;
    $transform = function (string $bytes) use (&$calls): string {
        $calls++;

        return 'thumbnail:'.$bytes;
    };

    $store->rememberDerivative($path, $hmac, $transform);
    Storage::disk('sensitive')->put($path.'.thumbnail.v1', 'NSD1'.str_repeat('x', 48));

    expect($store->rememberDerivative($path, $hmac, $transform))->toBe('thumbnail:'.$source)
        ->and($calls)->toBe(2);
});

test('oversized derivative transform output is rejected without storing a sidecar', function (): void {
    $store = app(SensitiveAssetStore::class);
    $source = 'verified source';
    $path = $store->store($source, 'omr/captures/oversized-output.bin');
    $sidecar = $path.'.thumbnail.v1';

    expect(fn () => $store->rememberDerivative(
        $path,
        $store->hmac($source),
        fn (string $bytes): string => str_repeat('x', 1_048_577),
    ))->toThrow(RuntimeException::class)
        ->and(Storage::disk('sensitive')->exists($sidecar))->toBeFalse();
});

test('oversized tampered sidecar is discarded and regenerated from verified source', function (): void {
    $store = app(SensitiveAssetStore::class);
    $source = 'verified source for oversized sidecar';
    $path = $store->store($source, 'omr/captures/oversized-sidecar.bin');
    $hmac = $store->hmac($source);
    $sidecar = $path.'.thumbnail.v1';
    $calls = 0;
    $transform = function (string $bytes) use (&$calls): string {
        $calls++;

        return 'thumbnail:'.$bytes;
    };

    Storage::disk('sensitive')->put($sidecar, 'NSD1'.str_repeat('x', 1_048_617));

    expect($store->rememberDerivative($path, $hmac, $transform))->toBe('thumbnail:'.$source)
        ->and($calls)->toBe(1)
        ->and(Storage::disk('sensitive')->size($sidecar))->toBeLessThanOrEqual(1_048_620);
});

test('delete and successful source overwrite remove derivative sidecars', function (): void {
    $store = app(SensitiveAssetStore::class);
    $first = 'before overwrite';
    $path = $store->store($first, 'omr/captures/lifecycle.bin');
    $store->rememberDerivative($path, $store->hmac($first), fn (string $bytes): string => 'thumb:'.$bytes);
    expect(Storage::disk('sensitive')->exists($path.'.thumbnail.v1'))->toBeTrue();

    $second = 'after overwrite';
    $store->store($second, $path);
    expect(Storage::disk('sensitive')->exists($path.'.thumbnail.v1'))->toBeFalse();

    $store->rememberDerivative($path, $store->hmac($second), fn (string $bytes): string => 'thumb:'.$bytes);
    $store->delete($path);
    expect(Storage::disk('sensitive')->exists($path))->toBeFalse()
        ->and(Storage::disk('sensitive')->exists($path.'.thumbnail.v1'))->toBeFalse();
});

test('derivative generation and source lifecycle operations share the source lock', function (): void {
    $store = app(SensitiveAssetStore::class);
    $source = 'source before overwrite';
    $path = $store->store($source, 'omr/captures/shared-lock.bin');
    $lockKey = 'sensitive-derivative:'.hash('sha256', $path);
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')
        ->times(3)
        ->with(10, Mockery::type(Closure::class))
        ->andReturnUsing(static fn (int $seconds, Closure $callback): mixed => $callback());
    Cache::shouldReceive('lock')->with($lockKey, 120)->times(3)->andReturn($lock);

    $store->rememberDerivative($path, $store->hmac($source), fn (string $bytes): string => 'thumb:'.$bytes);
    $store->store('source after overwrite', $path);
    $store->delete($path);

    expect(Storage::disk('sensitive')->exists($path))->toBeFalse()
        ->and(Storage::disk('sensitive')->exists($path.'.thumbnail.v1'))->toBeFalse();
});

test('missing and bad-HMAC sources never produce a derivative', function (): void {
    $store = app(SensitiveAssetStore::class);
    $calls = 0;
    $transform = function (string $bytes) use (&$calls): string {
        $calls++;

        return 'thumbnail:'.$bytes;
    };

    expect(fn () => $store->rememberDerivative('omr/missing.bin', str_repeat('a', 64), $transform))
        ->toThrow(RuntimeException::class);

    $source = 'source with an incorrect expected HMAC';
    $path = $store->store($source, 'omr/captures/bad-hmac.bin');
    expect(fn () => $store->rememberDerivative($path, str_repeat('b', 64), $transform))
        ->toThrow(RuntimeException::class)
        ->and($calls)->toBe(0)
        ->and(Storage::disk('sensitive')->exists($path.'.thumbnail.v1'))->toBeFalse();
});

test('legacy CBC source storage and reading still round-trip unchanged', function (): void {
    $store = app(SensitiveAssetStore::class);
    $source = "legacy CBC payload\0with binary bytes";
    $path = $store->store($source, 'omr/captures/legacy.bin');
    $payload = Storage::disk('sensitive')->get($path);

    expect(strlen($payload))->toBeGreaterThan(16)
        ->and($store->read($path))->toBe($source)
        ->and($store->read($path, $store->hmac($source)))->toBe($source);
});
