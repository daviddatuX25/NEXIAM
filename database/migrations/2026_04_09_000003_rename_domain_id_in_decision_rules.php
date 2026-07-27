<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decision_rules', function (Blueprint $table) {
            $table->dropForeign(['domain_id']);
            $table->dropIndex(['domain_id']);
            $table->renameColumn('domain_id', 'aptitude_area_id');
            $table->foreign('aptitude_area_id')
                ->references('id')->on('aptitude_areas')
                ->nullOnDelete();
            $table->index('aptitude_area_id');
        });
    }

    public function down(): void
    {
        Schema::table('decision_rules', function (Blueprint $table) {
            $table->dropForeign(['aptitude_area_id']);
            $table->dropIndex(['aptitude_area_id']);
            $table->renameColumn('aptitude_area_id', 'domain_id');
            $table->foreign('domain_id')
                ->references('id')->on('exam_domains')
                ->nullOnDelete();
            $table->index('domain_id');
        });
    }
};
