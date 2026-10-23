<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_sheet_templates', function (Blueprint $table): void {
            $table->string('source_kind', 24)->default('legacy_html')->after('mode');
            $table->unsignedInteger('builder_schema_version')->nullable()->after('source_kind');
            $table->json('builder_json')->nullable()->after('builder_schema_version');
            $table->longText('compiled_html')->nullable()->after('builder_json');
            $table->string('compiler_version', 64)->nullable()->after('compiled_html');
            $table->unsignedBigInteger('lock_version')->default(1)->after('compiler_version');
        });
    }

    public function down(): void
    {
        Schema::table('result_sheet_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'source_kind',
                'builder_schema_version',
                'builder_json',
                'compiled_html',
                'compiler_version',
                'lock_version',
            ]);
        });
    }
};
