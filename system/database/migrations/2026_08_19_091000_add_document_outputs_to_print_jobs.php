<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->json('output_manifest')->nullable()->after('per_item_manifest');
        });

        Schema::table('print_job_items', function (Blueprint $table) {
            $table->string('docx_path')->nullable()->after('file_checksum');
            $table->string('docx_checksum', 64)->nullable()->after('docx_path');
        });
    }

    public function down(): void
    {
        Schema::table('print_job_items', function (Blueprint $table) {
            $table->dropColumn(['docx_path', 'docx_checksum']);
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn('output_manifest');
        });
    }
};
