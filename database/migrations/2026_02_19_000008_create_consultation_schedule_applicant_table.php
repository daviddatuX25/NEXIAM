<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_schedule_applicant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['consultation_schedule_id', 'applicant_id'], 'cons_sched_app_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_schedule_applicant');
    }
};
