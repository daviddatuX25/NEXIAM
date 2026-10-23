<?php

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('capability catalog seeds stable GF capability keys', function (): void {
    seedE2Foundation();

    foreach (CapabilityCatalog::all() as $capability) {
        expect(Permission::query()->where('name', $capability)->exists())->toBeTrue();
    }
});

test('authorizer enforces office scope mismatch', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $allowed = app(CapabilityAuthorizer::class)->decide(
        $user,
        CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW,
        ['office' => 'registrar'],
    );
    $denied = app(CapabilityAuthorizer::class)->decide(
        $user,
        CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW,
        ['office' => 'guidance'],
    );

    expect($allowed->allowed)->toBeTrue()
        ->and($denied->allowed)->toBeFalse()
        ->and($denied->reason)->toBe('scope_mismatch');
});

test('institutional admin owns setup while operational office leads do not by default', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    expect($admin->actorScope()->office)->toBe('admin')
        ->and($admin->hasPermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE))->toBeTrue()
        ->and($admin->hasPermissionTo(CapabilityCatalog::SETUP_PROGRAMS_MANAGE))->toBeTrue()
        ->and($guidance->hasPermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE))->toBeFalse()
        ->and($registrar->hasPermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE))->toBeFalse()
        ->and($guidance->hasPermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE))->toBeTrue()
        ->and($guidance->hasPermissionTo(CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT))->toBeFalse()
        ->and($guidance->actorScope()->office)->toBe('guidance')
        ->and($guidance->actorScope()->activeCycleCode)->toBe('SYN-CYCLE-2026');
});
