<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['season_id']);
            $table->dropIndex(['season_id']);
            $table->renameColumn('season_id', 'academic_year_id');
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['season_id']);
            $table->dropIndex(['season_id']);
            $table->renameColumn('season_id', 'academic_year_id');
        });

        Schema::rename('seasons', 'academic_years');

        Schema::table('applications', function (Blueprint $table) {
            $table->index('academic_year_id');
            $table->foreign('academic_year_id')
                ->references('id')->on('academic_years')
                ->nullOnDelete();
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->index('academic_year_id');
            $table->foreign('academic_year_id')
                ->references('id')->on('academic_years')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropIndex(['academic_year_id']);
            $table->renameColumn('academic_year_id', 'season_id');
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropIndex(['academic_year_id']);
            $table->renameColumn('academic_year_id', 'season_id');
        });

        Schema::rename('academic_years', 'seasons');

        Schema::table('applications', function (Blueprint $table) {
            $table->index('season_id');
            $table->foreign('season_id')
                ->references('id')->on('seasons')
                ->nullOnDelete();
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->index('season_id');
            $table->foreign('season_id')
                ->references('id')->on('seasons')
                ->nullOnDelete();
        });
    }
};
