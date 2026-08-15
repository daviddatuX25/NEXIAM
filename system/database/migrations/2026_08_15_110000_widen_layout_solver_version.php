<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->string('layout_solver_version', 48)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->string('layout_solver_version', 24)->nullable()->change();
        });
    }
};
