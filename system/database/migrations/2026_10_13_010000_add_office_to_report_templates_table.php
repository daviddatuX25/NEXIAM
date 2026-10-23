<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_templates', function (Blueprint $table): void {
            $table->string('office')->nullable()->after('surface_key')->index();
        });
    }

    public function down(): void
    {
        Schema::table('report_templates', function (Blueprint $table): void {
            $table->dropColumn('office');
        });
    }
};
