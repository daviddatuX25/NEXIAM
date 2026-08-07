<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_cycles', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('closes_on');
            $table->index(['institution_id', 'is_active']);
        });

        DB::table('academic_cycles')
            ->where('code', 'SYN-CYCLE-2026')
            ->update(['is_active' => true]);
    }

    public function down(): void
    {
        Schema::table('academic_cycles', function (Blueprint $table) {
            $table->dropIndex(['institution_id', 'is_active']);
            $table->dropColumn('is_active');
        });
    }
};
