<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->boolean('allow_multiple_sets')->nullable(false)->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->boolean('allow_multiple_sets')->nullable(false)->default(true)->change();
        });
    }
};
