<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aptitude_areas', function (Blueprint $table) {
            $table->text('formula')->nullable()->after('max_items');
        });
    }

    public function down(): void
    {
        Schema::table('aptitude_areas', function (Blueprint $table) {
            $table->dropColumn('formula');
        });
    }
};
