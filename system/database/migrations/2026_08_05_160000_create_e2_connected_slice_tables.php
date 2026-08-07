<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('normalized_key', 64);
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->date('birthdate');
            $table->string('sex', 16);
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'normalized_key']);
        });

        Schema::create('admission_reference_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique('academic_cycle_id');
        });

        Schema::create('admission_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_identity_id')->constrained('applicant_identities')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('application_window_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_code')->unique();
            $table->string('status')->default('pending');
            $table->string('preferred_program_code')->nullable();
            $table->string('applicant_type')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->text('correction_notes')->nullable();
            $table->text('dismissal_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['applicant_identity_id', 'academic_cycle_id']);
            $table->index(['institution_id', 'status']);
            $table->index(['academic_cycle_id', 'status']);
        });

        Schema::create('applicant_access_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_identity_id')->constrained('applicant_identities')->restrictOnDelete();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->string('purpose');
            $table->string('token_hash', 64);
            $table->string('request_key')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['applicant_identity_id', 'purpose']);
            $table->index(['expires_at', 'consumed_at']);
        });

        Schema::create('applicant_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_identity_id')->unique()->constrained('applicant_identities')->restrictOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('status')->default('draft');
            $table->timestamp('starts_at');
            $table->timestamp('effective_end_at');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['academic_cycle_id', 'code']);
            $table->index(['academic_cycle_id', 'status']);
        });

        Schema::create('exam_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->string('seat_label')->nullable();
            $table->string('status')->default('assigned');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['exam_session_id', 'admission_attempt_id']);
            $table->index(['admission_attempt_id', 'status']);
        });

        Schema::create('proctor_duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->string('idempotency_key')->unique();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['exam_session_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('exam_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_assignment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome');
            $table->string('idempotency_key')->unique();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::create('exam_submission_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_assignment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key')->unique();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        Schema::create('grading_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status')->default('open');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('opened_at');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('score_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->foreignId('aptitude_area_id')->constrained()->restrictOnDelete();
            $table->decimal('score', 8, 2);
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->unique(['grading_session_id', 'admission_attempt_id', 'aptitude_area_id'], 'score_records_grad_attempt_area_unique');
        });

        Schema::create('consultation_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->foreignId('counselor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('decision_type');
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['admission_attempt_id', 'decision_type']);
        });

        Schema::create('result_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->string('template_version');
            $table->string('checksum', 64);
            $table->string('status')->default('draft');
            $table->string('storage_path')->nullable();
            $table->string('preview_path')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['admission_attempt_id', 'status']);
        });

        Schema::create('result_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->foreignId('result_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('released_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('release_version');
            $table->boolean('is_current')->default(true);
            $table->timestamp('released_at');
            $table->timestamp('unreleased_at')->nullable();
            $table->text('unrelease_reason')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['admission_attempt_id', 'is_current']);
        });

        Schema::create('notification_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel')->default('mail');
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->string('idempotency_key')->unique();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');
        Schema::dropIfExists('result_releases');
        Schema::dropIfExists('result_documents');
        Schema::dropIfExists('consultation_decisions');
        Schema::dropIfExists('score_records');
        Schema::dropIfExists('grading_sessions');
        Schema::dropIfExists('exam_submission_records');
        Schema::dropIfExists('exam_attendance_records');
        Schema::dropIfExists('proctor_duties');
        Schema::dropIfExists('exam_assignments');
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('applicant_accounts');
        Schema::dropIfExists('applicant_access_challenges');
        Schema::dropIfExists('admission_attempts');
        Schema::dropIfExists('admission_reference_counters');
        Schema::dropIfExists('applicant_identities');
    }
};
