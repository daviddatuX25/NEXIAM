<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_cycles', function (Blueprint $table): void {
            $table->boolean('allow_guidance_all_in_one_intake')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('academic_cycles', function (Blueprint $table): void {
            $table->dropColumn('allow_guidance_all_in_one_intake');
        });
    }
};
