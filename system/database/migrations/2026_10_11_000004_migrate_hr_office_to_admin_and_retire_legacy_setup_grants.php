<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_profiles')) {
            DB::table('staff_profiles')->where('office', 'hr')->update(['office' => 'admin']);
        }

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', 'registrar.setup.manage')->value('id');

        if ($permissionId === null) {
            return;
        }

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
        }

        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
        }
    }

    public function down(): void
    {
        // Retired permission grants and office classifications are not safely reconstructable.
    }
};
