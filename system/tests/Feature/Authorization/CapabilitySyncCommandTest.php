<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

test('capabilities:sync restores a capability row that drifted out of the database', function (): void {
    seedE2Foundation();

    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    expect($staff->hasPermissionTo('notify.read.own'))->toBeTrue();

    // Simulate a database that predates the capability (permission row missing).
    Permission::query()->where('name', 'notify.read.own')->delete();
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => $staff->fresh()->hasPermissionTo('notify.read.own'))
        ->toThrow(Spatie\Permission\Exceptions\PermissionDoesNotExist::class);

    $this->actingAs($staff)
        ->get(route('notifications.index'))
        ->assertForbidden();

    $this->artisan('capabilities:sync')
        ->assertExitCode(0);

    expect(Permission::query()->where('name', 'notify.read.own')->exists())->toBeTrue()
        ->and($staff->fresh()->hasPermissionTo('notify.read.own'))->toBeTrue();

    $this->actingAs($staff)
        ->get(route('notifications.index'))
        ->assertOk();
});

test('capabilities:sync is idempotent and keeps every role grant set aligned', function (): void {
    seedE2Foundation();

    $this->artisan('capabilities:sync')->assertExitCode(0);

    foreach (\App\Authorization\CapabilityCatalog::roleHelperGrants() as $roleName => $capabilities) {
        $role = \Spatie\Permission\Models\Role::query()->where('name', $roleName)->firstOrFail();
        expect($role->permissions()->pluck('name')->sort()->all())
            ->toBe(collect($capabilities)->sort()->all());
    }
});
