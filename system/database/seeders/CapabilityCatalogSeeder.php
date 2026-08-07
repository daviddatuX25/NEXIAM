<?php

namespace Database\Seeders;

use App\Authorization\CapabilityCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CapabilityCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (CapabilityCatalog::all() as $capability) {
            Permission::findOrCreate($capability, 'web');
        }

        foreach (CapabilityCatalog::roleHelperGrants() as $roleName => $capabilities) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($capabilities);
        }
    }
}
