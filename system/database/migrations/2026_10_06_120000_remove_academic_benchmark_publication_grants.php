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
            || ! Schema::hasTable('staff_profiles')) {
            return;
        }

        $publicationPermissionId = DB::table('permissions')
            ->where('guard_name', 'web')
            ->where('name', CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH)
            ->value('id');

        if ($publicationPermissionId === null) {
            return;
        }

        DB::table('model_has_permissions')
            ->where('model_type', 'App\\Models\\User')
            ->where('permission_id', $publicationPermissionId)
            ->whereIn('model_id', DB::table('staff_profiles')
                ->select('user_id')
                ->where('office', 'program'))
            ->delete();
    }

    public function down(): void {}
};
