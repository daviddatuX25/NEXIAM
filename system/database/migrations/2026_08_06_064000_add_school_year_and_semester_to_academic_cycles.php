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
            $table->string('school_year', 20)->nullable()->after('name');
            $table->string('semester', 8)->nullable()->after('school_year');
            $table->unique(['institution_id', 'school_year', 'semester'], 'academic_cycles_year_semester_unique');
        });

        // Backfill synthetic seed row so staff labels are meaningful.
        DB::table('academic_cycles')
            ->where('code', 'SYN-CYCLE-2026')
            ->whereNull('school_year')
            ->update([
                'school_year' => '2025-2026',
                'semester' => '1',
                'name' => '2025-2026 1st Semester',
            ]);
    }

    public function down(): void
    {
        Schema::table('academic_cycles', function (Blueprint $table) {
            $table->dropUnique('academic_cycles_year_semester_unique');
            $table->dropColumn(['school_year', 'semester']);
        });
    }
};
