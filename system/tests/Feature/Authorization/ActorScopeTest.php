<?php

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\User;

test('capability catalog seeds stable GF capability keys', function (): void {
    $this->seed();

    foreach (CapabilityCatalog::all() as $capability) {
        expect(\Spatie\Permission\Models\Permission::query()->where('name', $capability)->exists())->toBeTrue();
    }
});

test('authorizer enforces office scope mismatch', function (): void {
    $this->seed();
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

test('guidance counselor receives guidance + shared setup grants via helper role (D-087)', function (): void {
    $this->seed();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    expect($user->hasPermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE))->toBeTrue()
        // D-087: registrar.setup.manage is shared institutional setup (Registrar AND Guidance).
        ->and($user->hasPermissionTo(CapabilityCatalog::REGISTRAR_SETUP_MANAGE))->toBeTrue()
        // D-087: intake authorization is NOT merged into Guidance.
        ->and($user->hasPermissionTo(CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT))->toBeFalse()
        ->and($user->actorScope()->office)->toBe('guidance')
        ->and($user->actorScope()->activeCycleCode)->toBe('SYN-CYCLE-2026');
});
