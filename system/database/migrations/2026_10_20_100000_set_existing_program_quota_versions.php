<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('program_quotas')->where('version', 0)->update(['version' => 1]);
        Schema::table('program_quotas', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('program_quotas', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(0)->change();
        });
    }
};
