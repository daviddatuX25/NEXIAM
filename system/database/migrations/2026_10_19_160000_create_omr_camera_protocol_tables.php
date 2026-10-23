<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_camera_pairings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_batch_id')->nullable()->constrained('omr_capture_batches')->restrictOnDelete();
            $table->foreignId('diagnostic_run_id')->nullable()->constrained('omr_diagnostic_runs')->restrictOnDelete();
            $table->string('workbench_id', 180);
            $table->string('workbench_key', 200);
            $table->unsignedBigInteger('controller_generation')->default(1);
            $table->char('controller_token_hash', 64)->nullable();
            $table->timestamp('controller_heartbeat_at')->nullable();
            $table->uuid('node_id')->nullable();
            $table->char('node_token_hash', 64)->nullable();
            $table->unsignedBigInteger('node_generation')->default(0);
            $table->timestamp('node_heartbeat_at')->nullable();
            $table->char('pair_code_hash', 64)->nullable();
            $table->timestamp('pair_code_expires_at')->nullable();
            $table->timestamp('pair_code_consumed_at')->nullable();
            $table->timestamp('absolute_expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 160)->nullable();
            $table->json('config_json');
            $table->unsignedInteger('config_revision')->default(1);
            $table->unsignedBigInteger('source_generation')->default(1);
            $table->unsignedBigInteger('target_generation')->default(1);
            $table->string('active_lease_key', 200)->nullable()->unique();
            $table->unsignedBigInteger('observation_sequence')->default(0);
            $table->json('observations_json')->nullable();
            $table->json('signals_json')->nullable();
            $table->json('last_ack_json')->nullable();
            $table->timestamps();
            $table->index(['operator_id', 'workbench_key', 'revoked_at', 'absolute_expires_at'], 'omr_camera_pairing_scope_active_idx');
            $table->index(['pair_code_hash', 'pair_code_expires_at', 'pair_code_consumed_at'], 'omr_camera_pairing_code_idx');
            $table->unique(['id', 'workbench_key'], 'omr_camera_pairing_workbench_tuple_unique');
        });

        Schema::create('omr_camera_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pairing_id')->nullable()->constrained('omr_camera_pairings')->cascadeOnDelete();
            $table->uuid('operation_id');
            $table->uuid('command_id')->unique();
            $table->string('issuer_role', 16);
            $table->unsignedBigInteger('sequence')->nullable();
            $table->string('kind', 32);
            $table->string('workbench_key', 200);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->char('receipt_key', 64)->nullable()->unique();
            $table->json('payload_json');
            $table->char('payload_fingerprint', 64);
            $table->json('target_json')->nullable();
            $table->unsignedInteger('config_revision')->nullable();
            $table->unsignedBigInteger('source_generation')->nullable();
            $table->unsignedBigInteger('controller_generation')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('execution_expires_at')->nullable();
            $table->string('device_outcome', 24)->nullable();
            $table->json('device_outcome_json')->nullable();
            $table->string('domain_outcome', 24)->nullable();
            $table->json('domain_receipt_json')->nullable();
            $table->uuid('finalization_lease_token')->nullable();
            $table->timestamp('finalization_lease_expires_at')->nullable();
            $table->timestamp('finalization_absolute_expires_at')->nullable();
            $table->unsignedBigInteger('finalization_lease_generation')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['pairing_id', 'sequence'], 'omr_camera_command_pair_sequence_unique');
            $table->unique(['pairing_id', 'command_id'], 'omr_camera_command_pair_command_unique');
            $table->index(['actor_id', 'workbench_key', 'operation_id'], 'omr_camera_command_operation_idx');
            $table->index(['pairing_id', 'issued_at', 'sequence'], 'omr_camera_command_poll_idx');
            $table->index(['domain_outcome', 'completed_at'], 'omr_camera_command_receipt_idx');
        });

        Schema::create('omr_camera_frames', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('pairing_id')->constrained('omr_camera_pairings')->cascadeOnDelete();
            $table->foreignId('command_id')->unique()->constrained('omr_camera_commands')->restrictOnDelete();
            $table->unsignedBigInteger('node_generation');
            $table->json('target_json');
            $table->string('target_key', 512);
            $table->unsignedInteger('config_revision');
            $table->unsignedBigInteger('source_generation');
            $table->char('input_sha256', 64);
            $table->unsignedBigInteger('byte_count');
            $table->string('mime_type', 127);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedSmallInteger('orientation')->default(1);
            $table->json('client_quad_json')->nullable();
            $table->string('asset_disk', 32)->nullable();
            $table->string('asset_path', 512)->nullable();
            $table->char('asset_hmac', 64)->nullable();
            $table->timestamp('asset_deleted_at')->nullable();
            $table->string('preview_mime', 127)->nullable();
            $table->string('state', 24)->default('acquired');
            $table->timestamp('acquired_at');
            $table->timestamp('upload_expires_at');
            $table->timestamp('staged_at')->nullable();
            $table->char('finalization_fingerprint', 64)->nullable();
            $table->uuid('finalization_lease_token')->nullable();
            $table->timestamp('finalization_lease_expires_at')->nullable();
            $table->timestamp('finalization_absolute_expires_at')->nullable();
            $table->json('domain_receipt_json')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['pairing_id', 'state', 'upload_expires_at'], 'omr_camera_frame_cleanup_idx');
            $table->index(['state', 'finalization_lease_expires_at'], 'omr_camera_frame_finalization_lease_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_camera_frames');
        Schema::dropIfExists('omr_camera_commands');
        Schema::dropIfExists('omr_camera_pairings');
    }
};
