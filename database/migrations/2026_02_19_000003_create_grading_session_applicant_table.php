<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_session_applicant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('result_printed_at')->nullable();
            $table->timestamps();

            $table->unique(['grading_session_id', 'applicant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_session_applicant');
    }
};
