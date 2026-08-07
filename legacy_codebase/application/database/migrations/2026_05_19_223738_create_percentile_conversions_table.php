<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('percentile_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aptitude_area_id')->constrained('aptitude_areas')->cascadeOnDelete();
            $table->unsignedSmallInteger('raw_score');
            $table->string('percentile_output', 20);
            $table->timestamps();

            $table->unique(['aptitude_area_id', 'raw_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('percentile_conversions');
    }
};
