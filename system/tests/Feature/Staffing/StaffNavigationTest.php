<?php

use App\Models\User;
use App\Support\Navigation\StaffNavigationBuilder;

test('staff navigation marks home exact so personnel is not double-active', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $items = app(StaffNavigationBuilder::class)->for($hr);
    $home = collect($items)->firstWhere('href', '/staff');
    $personnel = collect($items)->firstWhere('href', '/staff/personnel');

    expect($home)->not->toBeNull()
        ->and($home['match'])->toBe('exact')
        ->and($personnel)->not->toBeNull()
        ->and($personnel['match'])->toBe('prefix');
});

test('staff navigation only includes personnel for hr capability', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $hrHrefs = collect(app(StaffNavigationBuilder::class)->for($hr))->pluck('href');
    $clerkHrefs = collect(app(StaffNavigationBuilder::class)->for($clerk))->pluck('href');

    expect($hrHrefs)->toContain('/staff/personnel')
        ->and($clerkHrefs)->not->toContain('/staff/personnel')
        ->and($clerkHrefs)->toContain('/admissions');
});
