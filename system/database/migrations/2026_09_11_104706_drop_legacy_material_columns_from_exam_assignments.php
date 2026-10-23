<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $discrepancyExists = DB::table('exam_assignments as assignments')
            ->join('exam_assignment_seat_allocations as allocations', function ($join): void {
                $join->on('allocations.exam_assignment_id', '=', 'assignments.id')
                    ->whereNull('allocations.effective_to');
            })
            ->join('exam_session_seats as seats', 'seats.id', '=', 'allocations.exam_session_seat_id')
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNotNull('assignments.material_snapshot_id')
                        ->whereColumn('assignments.material_snapshot_id', '!=', 'seats.material_snapshot_id');
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('assignments.booklet_set_id')
                        ->whereColumn('assignments.booklet_set_id', '!=', 'seats.booklet_set_id');
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('assignments.seat_label')
                        ->whereColumn('assignments.seat_label', '!=', 'seats.seat_label');
                });
            })
            ->exists();

        if ($discrepancyExists) {
            throw new RuntimeException('Legacy assignment Set evidence conflicts with canonical seat allocations; resolve discrepancies before cutover.');
        }

        Schema::table('exam_assignments', function (Blueprint $table): void {
            $table->dropForeign(['material_snapshot_id']);
            $table->dropForeign(['booklet_set_id']);
            $table->dropIndex('exam_assignments_material_set_idx');
            $table->dropIndex('exam_assignments_legacy_seat_status_idx');
            $table->dropColumn([
                'material_snapshot_id',
                'booklet_set_id',
                'seat_label',
                'legacy_seat_allocation_status',
                'legacy_seat_allocation_exception',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('exam_assignments', function (Blueprint $table): void {
            $table->foreignId('material_snapshot_id')->nullable()->constrained('exam_session_material_snapshots')->restrictOnDelete();
            $table->foreignId('booklet_set_id')->nullable()->constrained('omr_booklet_sets')->restrictOnDelete();
            $table->string('seat_label', 32)->nullable();
            $table->string('legacy_seat_allocation_status', 32)->default('not_attempted');
            $table->text('legacy_seat_allocation_exception')->nullable();
            $table->index(['material_snapshot_id', 'booklet_set_id'], 'exam_assignments_material_set_idx');
            $table->index(['exam_session_id', 'legacy_seat_allocation_status'], 'exam_assignments_legacy_seat_status_idx');
        });
    }
};
