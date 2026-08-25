<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical challenge packs (OpenSpec 35.21). Cut E creates the table, model,
 * and indexes; challenge creation/rendering lands in Cut F (35.27+).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_physical_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('omr_template_verification_runs')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->string('mode', 16);
            $table->string('challenge_code', 32)->unique();
            $table->char('plan_hash', 64);
            $table->char('expected_vector_hash', 64);
            $table->foreignId('sheet_asset_id')->nullable()->constrained('omr_template_assets')->restrictOnDelete();
            $table->foreignId('guide_asset_id')->nullable()->constrained('omr_template_assets')->restrictOnDelete();
            $table->char('qr_payload_hash', 64)->nullable();
            $table->string('status', 24)->default('created');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['run_id', 'attempt'], 'omr_physical_challenges_run_attempt_idx');
            $table->index(['run_id', 'status'], 'omr_physical_challenges_run_status_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_physical_challenges
                    ADD CONSTRAINT omr_physical_challenges_mode_check
                        CHECK (mode IN ('quick', 'full')),
                    ADD CONSTRAINT omr_physical_challenges_attempt_check
                        CHECK (attempt >= 1),
                    ADD CONSTRAINT omr_physical_challenges_status_check
                        CHECK (status IN ('created', 'printed', 'awaiting_capture', 'analyzing_capture', 'passed', 'failed', 'superseded')),
                    ADD CONSTRAINT omr_physical_challenges_code_format_check
                        CHECK (challenge_code ~ '^CHAL-[0-9A-F]{10}$')
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_physical_challenges');
    }
};
