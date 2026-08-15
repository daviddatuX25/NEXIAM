<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_conversion_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aptitude_area_id')->constrained('aptitude_areas')->cascadeOnDelete();
            $table->unsignedSmallInteger('min_score');
            $table->unsignedSmallInteger('max_score');
            $table->string('output_value', 50);
            $table->timestamps();

            $table->unique(['aptitude_area_id', 'min_score']);
            $table->index(['aptitude_area_id', 'max_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_conversion_rows');
    }
};
