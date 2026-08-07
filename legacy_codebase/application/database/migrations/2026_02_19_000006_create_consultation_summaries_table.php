<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'draft', 'released'])->default('pending');
            $table->foreignId('recommended_course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->text('counselor_comments')->nullable();
            $table->json('system_notes')->nullable();
            $table->foreignId('counselor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_summaries');
    }
};
