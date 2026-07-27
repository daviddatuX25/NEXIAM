<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_sheet_templates', function (Blueprint $table) {
            $table->string('watermark_text', 50)->nullable()->after('docx_path');
        });
    }

    public function down(): void
    {
        Schema::table('result_sheet_templates', function (Blueprint $table) {
            $table->dropColumn('watermark_text');
        });
    }
};
