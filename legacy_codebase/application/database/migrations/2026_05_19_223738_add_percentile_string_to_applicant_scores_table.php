<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_scores', function (Blueprint $table) {
            $table->string('percentile_string', 20)->nullable()->after('normalized_score');
        });
    }

    public function down(): void
    {
        Schema::table('applicant_scores', function (Blueprint $table) {
            $table->dropColumn('percentile_string');
        });
    }
};
