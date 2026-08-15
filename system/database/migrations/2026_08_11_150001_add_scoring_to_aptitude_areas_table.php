<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aptitude_areas', function (Blueprint $table) {
            $table->string('scoring_method', 32)->default('conversion_table')->after('version');
            $table->unsignedSmallInteger('max_items')->nullable()->after('scoring_method');
        });
    }

    public function down(): void
    {
        Schema::table('aptitude_areas', function (Blueprint $table) {
            $table->dropColumn(['scoring_method', 'max_items']);
        });
    }
};
