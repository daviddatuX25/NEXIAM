<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Rename old 'registrar_administrator' to 'test_administrator' first
        // (avoids unique constraint conflict when we rename 'admin' → 'registrar_administrator')
        DB::table('roles')
            ->where('name', 'registrar_administrator')
            ->update(['name' => 'test_administrator', 'display_name' => 'Test Administrator']);

        // Step 2: Rename old 'admin' to 'registrar_administrator'
        DB::table('roles')
            ->where('name', 'admin')
            ->update(['name' => 'registrar_administrator', 'display_name' => 'Registrar Administrator']);
    }

    public function down(): void
    {
        // Reverse step 2: 'registrar_administrator' → 'admin'
        DB::table('roles')
            ->where('name', 'registrar_administrator')
            ->update(['name' => 'admin', 'display_name' => 'Admin']);

        // Reverse step 1: 'test_administrator' → 'registrar_administrator'
        DB::table('roles')
            ->where('name', 'test_administrator')
            ->update(['name' => 'registrar_administrator', 'display_name' => 'Test Administrator']);
    }
};
