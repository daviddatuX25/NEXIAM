<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_pending_capture_sets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('production_batch_id')->nullable()->constrained('omr_capture_batches')->restrictOnDelete();
            $table->foreignId('diagnostic_run_id')->nullable()->constrained('omr_diagnostic_runs')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $table->foreignId('template_version_id')->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->foreignId('issuance_id')->nullable()->constrained('omr_qr_disabled_issuances')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('registration_mode', 24);
            $table->string('copy_policy', 24);
            $table->json('expected_pages_json');
            $table->unsignedSmallInteger('page_count');
            $table->unsignedSmallInteger('active_page_no')->default(1);
            $table->char('pages_hash', 64);
            $table->char('native_bundle_hash', 64);
            $table->char('artifact_hash', 64);
            $table->uuid('idempotency_key');
            $table->char('payload_fingerprint', 64);
            $table->unsignedBigInteger('revision')->default(1);
            $table->string('status', 24)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['production_batch_id', 'created_by_user_id', 'idempotency_key'], 'omr_capture_set_production_replay_unique');
            $table->unique(['diagnostic_run_id', 'created_by_user_id', 'idempotency_key'], 'omr_capture_set_diagnostic_replay_unique');
            $table->index(['production_batch_id', 'status', 'expires_at'], 'omr_capture_set_production_active_idx');
            $table->index(['diagnostic_run_id', 'status', 'expires_at'], 'omr_capture_set_diagnostic_active_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_pending_capture_sets
                    ADD CONSTRAINT omr_capture_set_context_check CHECK (
                        (production_batch_id IS NOT NULL AND diagnostic_run_id IS NULL AND issuance_id IS NOT NULL AND copy_policy IN ('unique_instance', 'reusable_master'))
                        OR (production_batch_id IS NULL AND diagnostic_run_id IS NOT NULL AND issuance_id IS NULL AND copy_policy = 'diagnostic_only')
                    ),
                    ADD CONSTRAINT omr_capture_set_page_check CHECK (page_count BETWEEN 1 AND 4 AND active_page_no BETWEEN 1 AND page_count),
                    ADD CONSTRAINT omr_capture_set_revision_check CHECK (revision > 0),
                    ADD CONSTRAINT omr_capture_set_status_check CHECK (status IN ('pending', 'ready', 'expired', 'cancelled'))
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_pending_capture_sets');
    }
};
