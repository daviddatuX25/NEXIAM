<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-input verification captures (OpenSpec 35.21, AD-5/AD-10). One row per
 * digital matrix variant or physical capture submission; assets are real FK
 * references to the immutable private asset store. NO applicant or score FK.
 * Capture rows are retained per evidence policy; runs cannot be deleted
 * while captures exist (deletion order: captures -> challenges -> run).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_template_verification_captures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('omr_template_verification_runs')->restrictOnDelete();
            $table->foreignId('challenge_id')->nullable()->constrained('omr_physical_challenges')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->unsignedSmallInteger('page_no');
            $table->string('mode', 24);
            $table->string('variant', 32)->nullable();
            $table->string('expected', 16)->nullable();
            $table->string('actual', 24)->nullable();
            $table->string('status', 8);
            $table->string('error_code', 64)->nullable();
            $table->char('input_hash', 64);
            $table->foreignId('input_asset_id')->nullable()->constrained('omr_template_assets')->restrictOnDelete();
            $table->foreignId('aligned_asset_id')->nullable()->constrained('omr_template_assets')->restrictOnDelete();
            $table->foreignId('overlay_asset_id')->nullable()->constrained('omr_template_assets')->restrictOnDelete();
            $table->char('evidence_hash', 64);
            $table->char('expected_vector_hash', 64)->nullable();
            $table->char('detected_vector_hash', 64)->nullable();
            $table->boolean('vector_match')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('captured_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['run_id', 'attempt'], 'omr_verification_captures_run_attempt_idx');
            $table->index(['run_id', 'variant'], 'omr_verification_captures_run_variant_idx');
            $table->index(['challenge_id', 'page_no'], 'omr_verification_captures_challenge_page_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_template_verification_captures
                    ADD CONSTRAINT omr_verification_captures_mode_check
                        CHECK (mode IN ('digital', 'quick_rehearsal', 'full_qualification')),
                    ADD CONSTRAINT omr_verification_captures_attempt_check
                        CHECK (attempt >= 1),
                    ADD CONSTRAINT omr_verification_captures_page_no_check
                        CHECK (page_no >= 1),
                    ADD CONSTRAINT omr_verification_captures_expected_check
                        CHECK (expected IN ('pass', 'typed_rejection')),
                    ADD CONSTRAINT omr_verification_captures_actual_check
                        CHECK (actual IN ('pass', 'typed_rejection', 'answer_vector_mismatch', 'failed')),
                    ADD CONSTRAINT omr_verification_captures_status_check
                        CHECK (status IN ('pass', 'fail'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_template_verification_captures');
    }
};
