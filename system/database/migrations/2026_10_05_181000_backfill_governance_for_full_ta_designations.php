<?php

use App\Authorization\CapabilityCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')
            || ! Schema::hasTable('model_has_permissions')
            || ! Schema::hasTable('model_has_roles')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('staff_profiles')) {
            return;
        }

        $legacyCapabilities = CapabilityCatalog::legacyTestAdministratorCapabilities();
        $governanceCapabilities = array_values(array_diff(
            CapabilityCatalog::delegatedTestAdministratorCapabilities(),
            $legacyCapabilities,
        ));
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [...$legacyCapabilities, ...$governanceCapabilities])
            ->pluck('id', 'name');
        if (array_diff([...$legacyCapabilities, ...$governanceCapabilities], $permissionIds->keys()->all()) !== []) {
            return;
        }

        $eligibleIds = DB::table('staff_profiles')
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'staff_profiles.user_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('staff_profiles.office', 'guidance')
            ->where('staff_profiles.status', 'active')
            ->whereIn('roles.name', ['guidance_clerk', 'guidance_support_staff'])
            ->distinct()
            ->pluck('staff_profiles.user_id');

        foreach ($eligibleIds as $userId) {
            $directIds = DB::table('model_has_permissions')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $userId)
                ->whereIn('permission_id', array_map(static fn (string $name): int => (int) $permissionIds[$name], $legacyCapabilities))
                ->distinct()
                ->pluck('permission_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $requiredLegacyIds = array_map(static fn (string $name): int => (int) $permissionIds[$name], $legacyCapabilities);
            sort($directIds);
            sort($requiredLegacyIds);
            if ($directIds !== $requiredLegacyIds) {
                continue;
            }

            foreach ($governanceCapabilities as $capability) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => (int) $permissionIds[$capability],
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            }
        }
    }

    public function down(): void {}
};
