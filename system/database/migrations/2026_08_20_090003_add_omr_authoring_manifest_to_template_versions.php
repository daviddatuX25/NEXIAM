<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('omr_answer_sheet_template_versions', 'authoring_manifest')) {
            Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
                $table->json('authoring_manifest')->nullable()->after('layout_solver_version');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('omr_answer_sheet_template_versions', 'authoring_manifest')) {
            Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
                $table->dropColumn('authoring_manifest');
            });
        }
    }
};
