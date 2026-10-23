<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_session_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_snapshot_id')
                ->constrained('exam_session_material_snapshots')
                ->restrictOnDelete();
            $table->unsignedInteger('ordinal');
            $table->unsignedSmallInteger('row_number');
            $table->unsignedSmallInteger('column_number');
            $table->string('seat_label', 32);
            $table->foreignId('booklet_set_id')
                ->constrained('omr_booklet_sets')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['material_snapshot_id', 'ordinal'],
                'exam_session_seats_snapshot_ordinal_unique',
            );
            $table->unique(
                ['material_snapshot_id', 'seat_label'],
                'exam_session_seats_snapshot_label_unique',
            );
            $table->index(
                ['material_snapshot_id', 'booklet_set_id'],
                'exam_session_seats_snapshot_set_idx',
            );
        });

        Schema::create('exam_assignment_seat_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_assignment_id')
                ->constrained('exam_assignments')
                ->restrictOnDelete();
            $table->foreignId('exam_session_seat_id')
                ->constrained('exam_session_seats')
                ->restrictOnDelete();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('assigned_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['exam_assignment_id', 'effective_from'],
                'exam_assignment_seat_allocations_assignment_history_idx',
            );
            $table->index(
                ['exam_session_seat_id', 'effective_from'],
                'exam_assignment_seat_allocations_seat_history_idx',
            );
        });

        Schema::table('exam_submission_records', function (Blueprint $table): void {
            $table->foreign('seat_allocation_id', 'exam_submission_records_seat_allocation_fk')
                ->references('id')
                ->on('exam_assignment_seat_allocations')
                ->restrictOnDelete();
            $table->index('seat_allocation_id', 'exam_submission_records_seat_allocation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exam_submission_records', function (Blueprint $table): void {
            $table->dropForeign('exam_submission_records_seat_allocation_fk');
            $table->dropIndex('exam_submission_records_seat_allocation_idx');
        });

        Schema::dropIfExists('exam_assignment_seat_allocations');
        Schema::dropIfExists('exam_session_seats');
    }
};
