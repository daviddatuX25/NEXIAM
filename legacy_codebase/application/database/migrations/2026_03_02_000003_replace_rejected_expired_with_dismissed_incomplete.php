<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // Add new enum values so we can migrate data
            DB::statement("ALTER TABLE applications MODIFY COLUMN status ENUM('pending','accepted','rejected','expired','dismissed','incomplete_documents') DEFAULT 'pending'");
        }

        // Migrate existing data: rejected and expired → dismissed
        DB::table('applications')
            ->whereIn('status', ['rejected', 'expired'])
            ->update(['status' => 'dismissed']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE applications MODIFY COLUMN status ENUM('pending','accepted','dismissed','incomplete_documents') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE applications MODIFY COLUMN status ENUM('pending','accepted','rejected','expired','dismissed','incomplete_documents') DEFAULT 'pending'");
        }
        DB::table('applications')->where('status', 'incomplete_documents')->update(['status' => 'rejected']);
        DB::table('applications')->where('status', 'dismissed')->update(['status' => 'rejected']);
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE applications MODIFY COLUMN status ENUM('pending','accepted','rejected','expired') DEFAULT 'pending'");
        }
    }
};
