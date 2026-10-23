<?php

declare(strict_types=1);

use App\Models\Foundation\Institution;
use App\Testing\Seeders\TestKitBrowserQaBootstrap;
use RuntimeException;

test('qa bootstrap rejects a live database name before opening a connection', function (): void {
    expect(fn () => app(TestKitBrowserQaBootstrap::class)->assertQaTargetConfiguration(
        true,
        'pgsql',
        'nexiam',
        null,
        '',
    ))->toThrow(RuntimeException::class, 'isolated nexiam_test_kit_acceptance_*');
});

test('qa bootstrap requires an empty database url before opening a connection', function (): void {
    expect(fn () => app(TestKitBrowserQaBootstrap::class)->assertQaTargetConfiguration(
        true,
        'pgsql',
        'nexiam_test_kit_acceptance_guard',
        'postgres://live/override',
        '',
    ))->toThrow(RuntimeException::class, 'empty DB_URL');
});

test('qa bootstrap rejects a resolved database that differs from its configured target', function (): void {
    expect(fn () => app(TestKitBrowserQaBootstrap::class)->assertResolvedQaDatabase(
        'nexiam_test_kit_acceptance_guard',
        'nexiam',
    ))->toThrow(RuntimeException::class, 'current_database()');
});

test('qa seed boundary leaves existing foundation rows untouched', function (): void {
    $institution = Institution::query()->create([
        'code' => 'QA-EXISTING-ROW',
        'name' => 'Existing unrelated institution',
    ]);

    expect(fn () => app(TestKitBrowserQaBootstrap::class)->assertFreshFoundation())
        ->toThrow(RuntimeException::class, 'existing Institution rows were found');

    expect(Institution::query()->count())->toBe(1)
        ->and($institution->fresh()->name)->toBe('Existing unrelated institution');
});
