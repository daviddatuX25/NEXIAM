<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('result_sheet_templates', function (Blueprint $table) {
            $table->string('mode', 10)->default('html')->after('name');
            $table->string('paper_size', 20)->default('a4')->after('mode');
            $table->string('orientation', 10)->default('portrait')->after('paper_size');
            $table->string('logical_unit', 20)->default('full')->after('orientation');
            $table->string('docx_path')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('result_sheet_templates', function (Blueprint $table) {
            $table->dropColumn(['mode', 'paper_size', 'orientation', 'logical_unit', 'docx_path']);
        });
    }
};
