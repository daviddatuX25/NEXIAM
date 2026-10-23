<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->foreignId('seating_arrangement_id')
                ->nullable()
                ->after('seating_profile_id')
                ->constrained('seating_arrangements')
                ->restrictOnDelete();
            $table->index(
                ['seating_arrangement_id', 'status'],
                'exam_sessions_seating_arrangement_status_idx',
            );
        });

        Schema::table('exam_session_material_snapshots', function (Blueprint $table): void {
            $table->unsignedInteger('revision')->default(1)->after('exam_session_id');
            $table->boolean('is_current')->default(true)->after('revision');
            $table->timestamp('superseded_at')->nullable()->after('published_at');
            $table->foreignId('seating_arrangement_id')
                ->nullable()
                ->after('seating_profile_id')
                ->constrained('seating_arrangements')
                ->restrictOnDelete();
            $table->unsignedInteger('arrangement_lock_version')->nullable()->after('seating_arrangement_id');
            $table->unsignedSmallInteger('arrangement_rows')->nullable()->after('arrangement_lock_version');
            $table->unsignedSmallInteger('arrangement_columns')->nullable()->after('arrangement_rows');
            $table->string('arrangement_booklet_pattern', 32)->nullable()->after('arrangement_columns');
            $table->char('seat_manifest_hash', 64)->nullable()->after('arrangement_booklet_pattern');
            $table->string('legacy_backfill_status', 24)->default('not_attempted')->after('seat_manifest_hash');
            $table->text('legacy_exception_reason')->nullable()->after('legacy_backfill_status');

            $table->dropUnique('exam_session_material_snapshots_exam_session_id_unique');
            $table->unique(
                ['exam_session_id', 'revision'],
                'exam_session_material_snapshots_session_revision_unique',
            );
            $table->index(
                ['exam_session_id', 'is_current'],
                'exam_session_material_snapshots_session_current_idx',
            );
            $table->index(
                ['seating_arrangement_id', 'arrangement_lock_version'],
                'exam_session_material_snapshots_arrangement_provenance_idx',
            );
        });

        Schema::table('exam_assignments', function (Blueprint $table): void {
            $table->string('legacy_seat_allocation_status', 24)
                ->default('not_attempted')
                ->after('seat_label');
            $table->text('legacy_seat_allocation_exception')->nullable()->after('legacy_seat_allocation_status');
            $table->index(
                ['exam_session_id', 'legacy_seat_allocation_status'],
                'exam_assignments_legacy_seat_status_idx',
            );
        });

        Schema::table('exam_submission_records', function (Blueprint $table): void {
            $table->unsignedBigInteger('seat_allocation_id')->nullable()->after('exam_assignment_id');
            $table->string('legacy_seat_allocation_status', 24)
                ->default('not_attempted')
                ->after('seat_allocation_id');
            $table->text('legacy_seat_allocation_exception')->nullable()->after('legacy_seat_allocation_status');
        });
    }

    public function down(): void
    {
        Schema::table('exam_submission_records', function (Blueprint $table): void {
            $table->dropColumn([
                'seat_allocation_id',
                'legacy_seat_allocation_status',
                'legacy_seat_allocation_exception',
            ]);
        });

        Schema::table('exam_assignments', function (Blueprint $table): void {
            $table->dropIndex('exam_assignments_legacy_seat_status_idx');
            $table->dropColumn([
                'legacy_seat_allocation_status',
                'legacy_seat_allocation_exception',
            ]);
        });

        Schema::table('exam_session_material_snapshots', function (Blueprint $table): void {
            $table->dropForeign(['seating_arrangement_id']);
            $table->dropIndex('exam_session_material_snapshots_arrangement_provenance_idx');
            $table->dropIndex('exam_session_material_snapshots_session_current_idx');
            $table->dropUnique('exam_session_material_snapshots_session_revision_unique');
            $table->dropColumn([
                'revision',
                'is_current',
                'superseded_at',
                'seating_arrangement_id',
                'arrangement_lock_version',
                'arrangement_rows',
                'arrangement_columns',
                'arrangement_booklet_pattern',
                'seat_manifest_hash',
                'legacy_backfill_status',
                'legacy_exception_reason',
            ]);
            $table->unique('exam_session_id', 'exam_session_material_snapshots_exam_session_id_unique');
        });

        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->dropForeign(['seating_arrangement_id']);
            $table->dropIndex('exam_sessions_seating_arrangement_status_idx');
            $table->dropColumn('seating_arrangement_id');
        });
    }
};
