<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->json('layout_blueprint_json')->nullable()->after('print_overlay_json');
            $table->char('layout_blueprint_hash', 64)->nullable()->after('layout_blueprint_json');
            $table->string('layout_solver_version', 48)->nullable()->after('layout_blueprint_hash');
        });
    }

    public function down(): void
    {
        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->dropColumn(['layout_blueprint_json', 'layout_blueprint_hash', 'layout_solver_version']);
        });
    }
};
