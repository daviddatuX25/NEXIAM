<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->boolean('is_admission_offered')->default(true);
            $table->index(['institution_id', 'is_admission_offered']);
        });

        DB::table('programs')
            ->whereIn('institution_id', DB::table('institutions')->select('id')->where('code', 'SYN-INST-001'))
            ->whereIn('code', ['BSN', 'BSPSY', 'BSCS', 'BSCE', 'BAT', 'BSHM'])
            ->update(['is_admission_offered' => false]);
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropIndex(['institution_id', 'is_admission_offered']);
            $table->dropColumn('is_admission_offered');
        });
    }
};
