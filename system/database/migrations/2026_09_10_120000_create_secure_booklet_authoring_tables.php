<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->foreignId('template_version_id')
                ->nullable()
                ->after('booklet_id')
                ->constrained('omr_answer_sheet_template_versions')
                ->restrictOnDelete();
            $table->string('authoring_mode', 32)->nullable()->after('status');
            $table->unsignedInteger('lock_version')->default(1)->after('authoring_mode');
            $table->char('content_hash', 64)->nullable()->after('answer_key_hash');
            $table->foreignId('finalized_by_user_id')
                ->nullable()
                ->after('activated_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('finalized_by_user_id');

            $table->index(['template_version_id', 'status'], 'omr_booklet_versions_template_status_idx');
            $table->index(['authoring_mode', 'status'], 'omr_booklet_versions_mode_status_idx');
        });

        Schema::create('omr_booklet_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booklet_version_id')
                ->constrained('omr_question_booklet_versions')
                ->cascadeOnDelete();
            $table->foreignId('aptitude_area_id')->constrained('aptitude_areas')->restrictOnDelete();
            $table->unsignedSmallInteger('authored_position');
            $table->text('encrypted_content');
            $table->string('correct_option_code', 32);
            $table->char('content_hash', 64);
            $table->timestamps();

            $table->unique(['booklet_version_id', 'aptitude_area_id', 'authored_position'], 'omr_booklet_items_authored_position_unique');
            $table->index(['booklet_version_id', 'aptitude_area_id']);
            $table->index(['booklet_version_id', 'content_hash']);
        });

        Schema::create('omr_booklet_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booklet_version_id')
                ->constrained('omr_question_booklet_versions')
                ->cascadeOnDelete();
            $table->string('set_code', 1);
            $table->string('status', 16)->default('draft');
            $table->string('generation_version', 32)->default('booklet-set-v1');
            $table->string('generation_seed', 128)->nullable();
            $table->char('source_content_hash', 64)->nullable();
            $table->char('order_hash', 64)->nullable();
            $table->char('answer_key_hash', 64)->nullable();
            $table->json('diagnostics_json')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['booklet_version_id', 'set_code'], 'omr_booklet_sets_version_code_unique');
            $table->index(['booklet_version_id', 'status']);
        });

        Schema::create('omr_booklet_set_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booklet_set_id')
                ->constrained('omr_booklet_sets')
                ->cascadeOnDelete();
            $table->foreignId('booklet_item_id')
                ->nullable()
                ->constrained('omr_booklet_items')
                ->nullOnDelete();
            $table->foreignId('aptitude_area_id')->constrained('aptitude_areas')->restrictOnDelete();
            $table->unsignedSmallInteger('area_position');
            $table->unsignedSmallInteger('global_position');
            $table->string('correct_option_code', 32);
            $table->char('content_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['booklet_set_id', 'global_position'], 'omr_booklet_set_items_global_position_unique');
            $table->unique(['booklet_set_id', 'aptitude_area_id', 'area_position'], 'omr_booklet_set_items_area_position_unique');
            $table->index(['booklet_set_id', 'aptitude_area_id']);
        });

        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->foreignId('booklet_version_id')
                ->nullable()
                ->after('room_id')
                ->constrained('omr_question_booklet_versions')
                ->restrictOnDelete();
            $table->foreignId('seating_profile_id')
                ->nullable()
                ->after('booklet_version_id')
                ->constrained('room_seating_profiles')
                ->restrictOnDelete();
            $table->index(['booklet_version_id', 'status'], 'exam_sessions_booklet_status_idx');
        });

        Schema::create('exam_session_material_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_session_id')
                ->constrained('exam_sessions')
                ->restrictOnDelete();
            $table->foreignId('template_version_id')
                ->constrained('omr_answer_sheet_template_versions')
                ->restrictOnDelete();
            $table->foreignId('booklet_version_id')
                ->constrained('omr_question_booklet_versions')
                ->restrictOnDelete();
            $table->foreignId('seating_profile_id')
                ->nullable()
                ->constrained('room_seating_profiles')
                ->restrictOnDelete();
            $table->string('scoring_policy_identity', 128);
            $table->char('material_hash', 64);
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['booklet_version_id', 'template_version_id']);
            $table->unique('exam_session_id', 'exam_session_material_snapshots_exam_session_id_unique');
        });

        Schema::create('exam_session_material_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('exam_session_material_snapshots')
                ->restrictOnDelete();
            $table->foreignId('booklet_set_id')
                ->constrained('omr_booklet_sets')
                ->restrictOnDelete();
            $table->string('set_code', 1);
            $table->char('order_hash', 64);
            $table->char('answer_key_hash', 64);
            $table->json('answer_key_json');
            $table->timestamps();

            $table->unique(['snapshot_id', 'set_code'], 'exam_session_material_sets_snapshot_code_unique');
            $table->index(['snapshot_id', 'booklet_set_id']);
        });

        Schema::table('exam_assignments', function (Blueprint $table): void {
            $table->foreignId('material_snapshot_id')
                ->nullable()
                ->after('exam_session_id')
                ->constrained('exam_session_material_snapshots')
                ->restrictOnDelete();
            $table->foreignId('booklet_set_id')
                ->nullable()
                ->after('material_snapshot_id')
                ->constrained('omr_booklet_sets')
                ->restrictOnDelete();
            $table->index(['material_snapshot_id', 'booklet_set_id'], 'exam_assignments_material_set_idx');
        });

        Schema::table('omr_capture_sheets', function (Blueprint $table): void {
            $table->foreignId('booklet_set_id')
                ->nullable()
                ->after('admission_attempt_id')
                ->constrained('omr_booklet_sets')
                ->restrictOnDelete();
            $table->string('observed_set_code', 1)->nullable()->after('booklet_set_id');
            $table->text('set_resolution_reason')->nullable()->after('observed_set_code');
            $table->foreignId('set_resolved_by_user_id')
                ->nullable()
                ->after('set_resolution_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('set_resolved_at')->nullable()->after('set_resolved_by_user_id');
            $table->index(['booklet_set_id', 'status'], 'omr_capture_sheets_booklet_set_status_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE omr_question_booklet_versions ADD CONSTRAINT omr_booklet_versions_authoring_mode_check CHECK (authoring_mode IS NULL OR authoring_mode IN ('stored_questions', 'answer_keys_only'))");
            DB::statement("ALTER TABLE omr_booklet_sets ADD CONSTRAINT omr_booklet_sets_code_check CHECK (set_code IN ('A', 'B', 'C', 'D'))");
            DB::statement("ALTER TABLE omr_booklet_sets ADD CONSTRAINT omr_booklet_sets_status_check CHECK (status IN ('draft', 'finalized'))");
            DB::statement('ALTER TABLE omr_booklet_set_items ADD CONSTRAINT omr_booklet_set_items_position_check CHECK (area_position > 0 AND global_position > 0)');
        }
    }

    public function down(): void
    {
        Schema::table('omr_capture_sheets', function (Blueprint $table): void {
            $table->dropForeign(['booklet_set_id']);
            $table->dropForeign(['set_resolved_by_user_id']);
            $table->dropIndex('omr_capture_sheets_booklet_set_status_idx');
            $table->dropColumn(['booklet_set_id', 'observed_set_code', 'set_resolution_reason', 'set_resolved_by_user_id', 'set_resolved_at']);
        });

        Schema::table('exam_assignments', function (Blueprint $table): void {
            $table->dropForeign(['material_snapshot_id']);
            $table->dropForeign(['booklet_set_id']);
            $table->dropIndex('exam_assignments_material_set_idx');
            $table->dropColumn(['material_snapshot_id', 'booklet_set_id']);
        });

        Schema::dropIfExists('exam_session_material_sets');
        Schema::dropIfExists('exam_session_material_snapshots');

        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->dropForeign(['booklet_version_id']);
            $table->dropForeign(['seating_profile_id']);
            $table->dropIndex('exam_sessions_booklet_status_idx');
            $table->dropColumn(['booklet_version_id', 'seating_profile_id']);
        });

        Schema::dropIfExists('omr_booklet_set_items');
        Schema::dropIfExists('omr_booklet_sets');
        Schema::dropIfExists('omr_booklet_items');

        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->dropForeign(['template_version_id']);
            $table->dropForeign(['finalized_by_user_id']);
            $table->dropIndex('omr_booklet_versions_template_status_idx');
            $table->dropIndex('omr_booklet_versions_mode_status_idx');
            $table->dropColumn(['template_version_id', 'authoring_mode', 'lock_version', 'content_hash', 'finalized_by_user_id', 'finalized_at']);
        });
    }
};
