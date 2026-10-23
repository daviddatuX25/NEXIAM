<?php

use App\Foundation\Actions\RecordAuditEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $roles = ['guidance_clerk', 'guidance_support_staff'];
        $capabilities = ['guidance.scoring.manual', 'guidance.results.prepare'];

        DB::transaction(function () use ($roles, $capabilities): void {
            $roleRows = DB::table('roles')
                ->where('guard_name', 'web')
                ->whereIn('name', $roles)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'name']);

            foreach ($roleRows as $role) {
                $permissionRows = DB::table('permissions')
                    ->where('guard_name', 'web')
                    ->whereIn('name', $capabilities)
                    ->orderBy('id')
                    ->get(['id', 'name']);

                if ($permissionRows->isEmpty()) {
                    continue;
                }

                $permissionIds = $permissionRows->pluck('id');
                $pivot = DB::table('role_has_permissions')
                    ->where('role_id', $role->id)
                    ->whereIn('permission_id', $permissionIds)
                    ->orderBy('permission_id')
                    ->lockForUpdate()
                    ->get(['permission_id']);

                if ($pivot->isEmpty()) {
                    continue;
                }

                $removed = $permissionRows
                    ->whereIn('id', $pivot->pluck('permission_id'))
                    ->pluck('name')
                    ->values()
                    ->all();

                DB::table('role_has_permissions')
                    ->where('role_id', $role->id)
                    ->whereIn('permission_id', $pivot->pluck('permission_id'))
                    ->delete();

                app(RecordAuditEvent::class)->handle('staff.guidance_default_scoring_grants_removed', [
                    'role' => $role->name,
                    'removed_capabilities' => $removed,
                    'before' => ['capabilities' => $removed],
                    'after' => ['capabilities' => []],
                ]);
            }
        });

        DB::afterCommit(static function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    public function down(): void
    {
        // Intentionally forward-only: unsafe default grants must not be restored.
    }
};
