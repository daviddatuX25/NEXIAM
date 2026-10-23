<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_physical_challenges', function (Blueprint $table): void {
            $table->string('mode', 24)->change();
            $table->string('method', 16)->nullable()->default('pre_shaded');
            $table->string('target_kind', 32)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_version', 64)->nullable();
            $table->char('target_hash', 64)->nullable();
            $table->string('plan_version', 16)->nullable()->default('v1');
            $table->string('plan_seed', 128)->nullable();
            $table->string('renderer_version', 64)->nullable();
            $table->char('renderer_hash', 64)->nullable();
            $table->foreignId('marker_profile_id')->nullable()->constrained('omr_marker_profiles')->restrictOnDelete();
            $table->unsignedInteger('marker_profile_version')->nullable();
            $table->char('marker_profile_hash', 64)->nullable();
            $table->json('page_manifest')->nullable();
            $table->char('page_manifest_hash', 64)->nullable();
            $table->char('sheet_asset_hash', 64)->nullable();
            $table->char('guide_asset_hash', 64)->nullable();
            $table->json('print_metadata')->nullable();
        });

        Schema::table('omr_template_verification_captures', function (Blueprint $table): void {
            $table->foreignId('method_id')->nullable()->constrained('omr_template_verification_methods')->restrictOnDelete();
            $table->string('method', 16)->nullable();
            $table->unsignedSmallInteger('attempt_no')->nullable();
            $table->string('attempt_state', 16)->nullable();
            $table->string('target_kind', 32)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_version', 64)->nullable();
            $table->char('target_hash', 64)->nullable();
            $table->string('plan_version', 16)->nullable();
            $table->char('plan_hash', 64)->nullable();
            $table->string('plan_seed', 128)->nullable();
            $table->char('challenge_hash', 64)->nullable();
            $table->string('renderer_version', 64)->nullable();
            $table->char('renderer_hash', 64)->nullable();
            $table->foreignId('marker_profile_id')->nullable()->constrained('omr_marker_profiles')->restrictOnDelete();
            $table->unsignedInteger('marker_profile_version')->nullable();
            $table->char('marker_profile_hash', 64)->nullable();
            $table->char('page_manifest_hash', 64)->nullable();
            $table->string('evidence_version', 16)->nullable()->default('v1');
            $table->char('input_asset_hash', 64)->nullable();
            $table->char('aligned_asset_hash', 64)->nullable();
            $table->char('overlay_asset_hash', 64)->nullable();
            $table->json('orientation_tried')->nullable();
            $table->unsignedSmallInteger('accepted_orientation')->nullable();
            $table->json('orientation_diagnostics')->nullable();
            $table->json('marker_result')->nullable();
            $table->json('layout_result')->nullable();
            $table->json('gate_result')->nullable();
            $table->string('medium', 24)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_detail', 512)->nullable();
            $table->unsignedBigInteger('superseded_by_attempt_id')->nullable();
            $table->foreignId('superseded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable();
            $table->string('supersede_reason', 512)->nullable();
            $table->foreignId('selected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selected_at')->nullable();
            $table->string('selection_reason', 512)->nullable();
            $table->string('printer', 190)->nullable();
            $table->string('device', 190)->nullable();
            $table->string('paper', 190)->nullable();
            $table->json('measurements')->nullable();
            $table->unsignedBigInteger('lock_version')->nullable()->default(0);

            $table->foreign('superseded_by_attempt_id', 'omr_verification_captures_superseded_by_fk')
                ->references('id')
                ->on('omr_template_verification_captures')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('omr_template_verification_captures', function (Blueprint $table): void {
            $table->dropForeign(['method_id']);
            $table->dropForeign('omr_verification_captures_superseded_by_fk');
            $table->dropForeign(['selected_by_user_id']);
            $table->dropForeign(['superseded_by_user_id']);
            $table->dropForeign(['marker_profile_id']);
            $table->dropColumn([
                'method', 'attempt_no', 'attempt_state', 'target_kind', 'target_id',
                'target_version', 'target_hash', 'plan_version', 'plan_hash', 'plan_seed',
                'challenge_hash', 'renderer_version', 'renderer_hash', 'marker_profile_id',
                'marker_profile_version', 'marker_profile_hash', 'page_manifest_hash',
                'evidence_version', 'input_asset_hash', 'aligned_asset_hash', 'overlay_asset_hash',
                'orientation_tried', 'accepted_orientation', 'orientation_diagnostics',
                'marker_result', 'layout_result', 'gate_result', 'medium', 'failure_code',
                'failure_detail', 'superseded_by_attempt_id', 'superseded_by_user_id',
                'superseded_at', 'supersede_reason', 'selected_by_user_id', 'selected_at',
                'selection_reason', 'printer', 'device', 'paper', 'measurements', 'lock_version',
            ]);
        });

        Schema::table('omr_physical_challenges', function (Blueprint $table): void {
            $table->dropForeign(['marker_profile_id']);
            $table->dropColumn([
                'method', 'target_kind', 'target_id', 'target_version', 'target_hash',
                'plan_version', 'plan_seed', 'renderer_version', 'renderer_hash',
                'marker_profile_id', 'marker_profile_version', 'marker_profile_hash',
                'page_manifest', 'page_manifest_hash', 'sheet_asset_hash', 'guide_asset_hash',
                'print_metadata',
            ]);
        });
    }
};
