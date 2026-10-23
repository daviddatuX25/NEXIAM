<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('report_templates', 'source_kind')) {
                $table->string('source_kind', 24)->default('docx');
            }

            if (! Schema::hasColumn('report_templates', 'builder_schema_version')) {
                $table->unsignedInteger('builder_schema_version')->nullable();
            }

            if (! Schema::hasColumn('report_templates', 'builder_json')) {
                $table->json('builder_json')->nullable();
            }

            if (! Schema::hasColumn('report_templates', 'compiled_html')) {
                $table->longText('compiled_html')->nullable();
            }

            if (! Schema::hasColumn('report_templates', 'compiler_version')) {
                $table->string('compiler_version', 64)->nullable();
            }

            if (! Schema::hasColumn('report_templates', 'lock_version')) {
                $table->unsignedBigInteger('lock_version')->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('report_templates', function (Blueprint $table): void {
            foreach ([
                'source_kind',
                'builder_schema_version',
                'builder_json',
                'compiled_html',
                'compiler_version',
                'lock_version',
            ] as $column) {
                if (Schema::hasColumn('report_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
