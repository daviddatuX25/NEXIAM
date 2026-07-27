<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_session_id')->nullable()->constrained()->nullOnDelete();
            $table->json('applicant_ids');
            $table->unsignedInteger('copies')->default(1);
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('pdf_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
