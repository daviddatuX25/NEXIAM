<?php

use App\Authorization\CapabilityCatalog;
use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('capabilities:sync restores a capability row that drifted out of the database', function (): void {
    seedE2Foundation();

    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    expect($staff->hasPermissionTo('notify.read.own'))->toBeTrue();

    // Simulate a database that predates the capability (permission row missing).
    Permission::query()->where('name', 'notify.read.own')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => $staff->fresh()->hasPermissionTo('notify.read.own'))
        ->toThrow(PermissionDoesNotExist::class);

    $authState = $staff->staffProfile->authenticationState;
    $staffSession = [
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $authState->session_version,
    ];

    $this->actingAs($staff)
        ->withSession($staffSession)
        ->get(route('notifications.index'))
        ->assertForbidden();

    $this->artisan('capabilities:sync')
        ->assertExitCode(0);

    expect(Permission::query()->where('name', 'notify.read.own')->exists())->toBeTrue()
        ->and($staff->fresh()->hasPermissionTo('notify.read.own'))->toBeTrue();

    $this->actingAs($staff)
        ->withSession($staffSession)
        ->get(route('notifications.index'))
        ->assertOk();
});

test('capabilities:sync purges forbidden registrar support grants and preserves allowed grants', function (): void {
    seedE2Foundation();

    $role = Role::query()->where('name', 'registrar_support_staff')->firstOrFail();
    $forbidden = [
        CapabilityCatalog::REGISTRAR_APPLICATIONS_DISMISS,
        CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT,
        CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_REOPEN,
        CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
        CapabilityCatalog::KNOWLEDGE_SOURCES_MANAGE,
    ];
    $role->givePermissionTo($forbidden);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->artisan('capabilities:sync')->assertExitCode(0);

    $synced = $role->fresh()->permissions()->pluck('name')->all();
    expect($synced)
        ->not->toContain(...$forbidden)
        ->toContain(CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT)
        ->toContain(CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_CONFIRM);
});
