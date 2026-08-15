<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_capture_sheets', function (Blueprint $table): void {
            $table->string('detected_birthdate', 10)->nullable()->after('detected_applicant_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('omr_capture_sheets', function (Blueprint $table): void {
            $table->dropColumn('detected_birthdate');
        });
    }
};
