<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('aptitude_area_id')->nullable();
            $table->unsignedSmallInteger('raw_score');
            $table->unsignedSmallInteger('max_score');
            $table->decimal('normalized_score', 5, 2)->nullable();
            $table->foreignId('scored_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('scored_at');
            $table->timestamps();

            $table->unique(['grading_session_id', 'applicant_id', 'aptitude_area_id'], 'app_scores_gs_app_area_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_scores');
    }
};
