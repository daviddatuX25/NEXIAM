<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessment_result_sets', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('administration_id')->constrained('assessment_administrations')->restrictOnDelete();
            $table->foreignId('grading_session_id')->constrained('grading_sessions')->restrictOnDelete();
            $table->json('raw_revision_vector_json');
            $table->json('conversion_revision_vector_json');
            $table->string('status', 16)->default('finalized');
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at');
            $table->timestamps();

            $table->index(['administration_id', 'status'], 'assessment_result_sets_admin_status_idx');
            $table->index(['grading_session_id', 'status'], 'assessment_result_sets_session_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_result_sets');
    }
};
