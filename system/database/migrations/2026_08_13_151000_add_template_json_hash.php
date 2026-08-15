<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Geometry identity independent of the version number, used by intake
        // duplicate detection (the full bundle hash includes the version).
        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->char('template_json_hash', 64)->nullable()->after('config_hash');
        });
    }

    public function down(): void
    {
        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->dropColumn('template_json_hash');
        });
    }
};
