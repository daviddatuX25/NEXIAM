<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_pending_captures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('client_capture_id')->unique();
            $table->string('status', 32)->default('received');
            $table->string('capture_mode', 8)->default('auto');
            $table->unsignedSmallInteger('page_no')->default(1);
            $table->unsignedSmallInteger('page_count')->default(1);

            $table->string('qr_state', 32)->nullable();
            $table->text('qr_payload')->nullable();
            $table->char('qr_payload_hash', 64)->nullable();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->foreignId('blank_instance_id')->nullable()->constrained('omr_blank_instances')->restrictOnDelete();
            $table->foreignId('blank_instance_page_id')->nullable()->constrained('omr_blank_instance_pages')->restrictOnDelete();

            $table->string('asset_path', 512);
            $table->char('asset_hmac', 64);
            $table->char('asset_hmac_key_id', 100)->nullable();
            $table->char('asset_key_id', 100)->nullable();
            $table->string('asset_format_version', 20)->default('v1');
            $table->string('asset_state', 20)->default('live');
            $table->string('original_mime', 100);
            $table->unsignedBigInteger('original_bytes');
            $table->char('input_hash', 64);

            $table->string('aligned_mime', 100)->nullable();
            $table->unsignedBigInteger('aligned_bytes')->nullable();
            $table->unsignedInteger('aligned_width')->nullable();
            $table->unsignedInteger('aligned_height')->nullable();
            $table->char('aligned_hash', 64)->nullable();
            $table->string('aligned_asset_path', 512)->nullable();
            $table->char('aligned_asset_hmac', 64)->nullable();
            $table->char('aligned_asset_hmac_key_id', 100)->nullable();
            $table->char('aligned_asset_key_id', 100)->nullable();

            $table->json('evidence_json')->nullable();
            $table->json('resolution_json')->nullable();
            $table->string('analysis_code', 100)->nullable();
            $table->text('analysis_detail')->nullable();
            $table->string('detected_applicant_number', 100)->nullable();
            $table->decimal('detected_applicant_confidence', 4, 3)->nullable();
            $table->string('detected_birthdate', 32)->nullable();

            $table->foreignId('grading_session_id')->nullable()->constrained('grading_sessions')->restrictOnDelete();
            $table->foreignId('admission_attempt_id')->nullable()->constrained('admission_attempts')->restrictOnDelete();
            $table->foreignId('adopted_sheet_id')->nullable()->constrained('omr_capture_sheets')->nullOnDelete();

            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('adopted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('adopted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at', 'id']);
            $table->index(['input_hash', 'status']);
            $table->index(['grading_session_id', 'status']);
            $table->index(['institution_id', 'academic_cycle_id', 'status', 'id']);
            $table->index(['blank_instance_page_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_pending_captures');
    }
};
