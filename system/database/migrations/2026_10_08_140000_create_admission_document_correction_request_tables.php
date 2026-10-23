<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_document_correction_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->foreignId('requirement_set_id')->constrained('admission_attempt_requirement_sets')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $table->foreignUuid('practice_fixture_set_id')->nullable()->constrained('practice_fixture_sets')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('expected_version');
            $table->unsignedInteger('recorded_version');
            $table->string('idempotency_key', 128);
            $table->text('overall_note')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['admission_attempt_id', 'idempotency_key'], 'admission_doc_request_attempt_key_index');
            $table->index(['admission_attempt_id', 'recorded_at', 'id']);
            $table->index(['institution_id', 'academic_cycle_id']);
            $table->index('practice_fixture_set_id');
        });

        Schema::create('admission_document_correction_request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_correction_request_id')->constrained('admission_document_correction_requests')->restrictOnDelete();
            $table->foreignId('requirement_item_id')->constrained('admission_attempt_requirement_items')->restrictOnDelete();
            $table->foreignId('expected_latest_verification_id')->nullable()
                ->constrained('admission_attempt_document_verifications')->restrictOnDelete();
            $table->string('label', 255);
            $table->string('observed_status', 32);
            $table->text('applicant_instruction');
            $table->timestamps();

            $table->unique(['document_correction_request_id', 'requirement_item_id'], 'admission_doc_request_item_unique');
            $table->index(['requirement_item_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_document_correction_request_items');
        Schema::dropIfExists('admission_document_correction_requests');
    }
};
