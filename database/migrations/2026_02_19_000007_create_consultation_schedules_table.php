<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_schedules', function (Blueprint $table) {
            $table->id();
            $table->date('scheduled_date');
            $table->foreignId('grading_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_schedules');
    }
};
