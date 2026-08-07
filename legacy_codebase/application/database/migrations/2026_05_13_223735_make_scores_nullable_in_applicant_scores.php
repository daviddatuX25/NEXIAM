<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applicant_scores', function (Blueprint $table) {
            $table->unsignedSmallInteger('raw_score')->nullable()->change();
            $table->unsignedSmallInteger('max_score')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicant_scores', function (Blueprint $table) {
            $table->unsignedSmallInteger('raw_score')->nullable(false)->change();
            $table->unsignedSmallInteger('max_score')->nullable(false)->change();
        });
    }
};
