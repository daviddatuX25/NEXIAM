<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('exam_session_material_snapshots')
                ->orderBy('id')
                ->get()
                ->each(fn (object $snapshot): ?bool => $this->backfillSnapshot($snapshot));
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Published seating backfill is append-only. Restore the pre-migration database instead of rolling it down.',
        );
    }

    private function backfillSnapshot(object $snapshot): ?bool
    {
        $profileId = $snapshot->seating_profile_id;
        $profile = $profileId === null
            ? null
            : DB::table('room_seating_profiles')->where('id', $profileId)->first();
        $arrangement = $profileId === null
            ? null
            : DB::table('seating_arrangements')
                ->where('legacy_room_seating_profile_id', $profileId)
                ->first();

        if ($profile === null || $arrangement === null) {
            $this->markSnapshotUnresolved($snapshot->id, 'missing_profile_or_arrangement_evidence');

            return null;
        }

        DB::table('exam_sessions')
            ->where('id', $snapshot->exam_session_id)
            ->whereNull('seating_arrangement_id')
            ->update([
                'seating_arrangement_id' => $arrangement->id,
                'updated_at' => now(),
            ]);

        $materialSets = DB::table('exam_session_material_sets')
            ->where('snapshot_id', $snapshot->id)
            ->get()
            ->keyBy('set_code');
        $requiredSetCodes = $this->requiredSetCodes((string) $profile->booklet_pattern);

        foreach ($requiredSetCodes as $setCode) {
            if (! $materialSets->has($setCode)) {
                $this->markSnapshotUnresolved($snapshot->id, 'missing_snapshot_set_'.$setCode);

                return null;
            }
        }

        $manifest = [];
        $rows = (int) $profile->rows;
        $columns = (int) $profile->columns;
        $publishedAt = $snapshot->published_at ?? now();

        for ($row = 0; $row < $rows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $ordinal = ($row * $columns) + $column + 1;
                $setCode = $this->bookletCode(
                    (string) $profile->booklet_pattern,
                    $row,
                    $column,
                    $ordinal - 1,
                );
                $seatLabel = $this->rowLabel($row).($column + 1);
                $materialSet = $materialSets->get($setCode);

                DB::table('exam_session_seats')->insertOrIgnore([
                    'material_snapshot_id' => $snapshot->id,
                    'ordinal' => $ordinal,
                    'row_number' => $row + 1,
                    'column_number' => $column + 1,
                    'seat_label' => $seatLabel,
                    'booklet_set_id' => $materialSet->booklet_set_id,
                    'created_at' => $publishedAt,
                    'updated_at' => $publishedAt,
                ]);

                $manifest[] = [
                    'ordinal' => $ordinal,
                    'row_number' => $row + 1,
                    'column_number' => $column + 1,
                    'seat_label' => $seatLabel,
                    'booklet_set_id' => (int) $materialSet->booklet_set_id,
                ];
            }
        }

        DB::table('exam_session_material_snapshots')
            ->where('id', $snapshot->id)
            ->update([
                'seating_arrangement_id' => $arrangement->id,
                'arrangement_lock_version' => 1,
                'arrangement_rows' => $rows,
                'arrangement_columns' => $columns,
                'arrangement_booklet_pattern' => (string) $profile->booklet_pattern,
                'seat_manifest_hash' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)),
                'legacy_backfill_status' => 'backfilled',
                'legacy_exception_reason' => null,
                'updated_at' => now(),
            ]);

        $assignments = DB::table('exam_assignments')
            ->where('exam_session_id', $snapshot->exam_session_id)
            ->where(function ($query) use ($snapshot): void {
                $query
                    ->whereNull('material_snapshot_id')
                    ->orWhere('material_snapshot_id', $snapshot->id);
            })
            ->orderBy('id')
            ->get();

        foreach ($assignments as $assignment) {
            $this->backfillAssignment($assignment, $snapshot, $publishedAt);
        }

        foreach ($assignments as $assignment) {
            $this->backfillSubmission($assignment);
        }

        return true;
    }

    private function backfillAssignment(object $assignment, object $snapshot, mixed $publishedAt): void
    {
        if ((string) $assignment->status !== 'assigned') {
            DB::table('exam_assignments')
                ->where('id', $assignment->id)
                ->update([
                    'legacy_seat_allocation_status' => 'not_applicable',
                    'legacy_seat_allocation_exception' => 'assignment_not_active',
                    'updated_at' => now(),
                ]);

            return;
        }

        $seatLabel = trim((string) ($assignment->seat_label ?? ''));
        $seat = $seatLabel === '' || $assignment->booklet_set_id === null
            ? null
            : DB::table('exam_session_seats')
                ->where('material_snapshot_id', $snapshot->id)
                ->where('seat_label', $seatLabel)
                ->where('booklet_set_id', $assignment->booklet_set_id)
                ->first();

        if ($seat === null) {
            DB::table('exam_assignments')
                ->where('id', $assignment->id)
                ->update([
                    'legacy_seat_allocation_status' => 'unresolved',
                    'legacy_seat_allocation_exception' => 'missing_exact_seat_or_set_evidence',
                    'updated_at' => now(),
                ]);

            return;
        }

        $activeAllocation = DB::table('exam_assignment_seat_allocations')
            ->where('exam_assignment_id', $assignment->id)
            ->whereNull('effective_to')
            ->first();
        $seatAlreadyAllocated = DB::table('exam_assignment_seat_allocations')
            ->where('exam_session_seat_id', $seat->id)
            ->whereNull('effective_to')
            ->where('exam_assignment_id', '<>', $assignment->id)
            ->exists();

        if ($seatAlreadyAllocated) {
            DB::table('exam_assignments')
                ->where('id', $assignment->id)
                ->update([
                    'legacy_seat_allocation_status' => 'unresolved',
                    'legacy_seat_allocation_exception' => 'seat_already_allocated_by_legacy_evidence',
                    'updated_at' => now(),
                ]);

            return;
        }

        if ($activeAllocation === null) {
            DB::table('exam_assignment_seat_allocations')->insert([
                'exam_assignment_id' => $assignment->id,
                'exam_session_seat_id' => $seat->id,
                'effective_from' => $publishedAt,
                'effective_to' => null,
                'reason' => 'legacy_backfill:verified_profile_assignment_facts',
                'assigned_by_user_id' => null,
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ]);
        }

        DB::table('exam_assignments')
            ->where('id', $assignment->id)
            ->update([
                'material_snapshot_id' => $snapshot->id,
                'legacy_seat_allocation_status' => 'backfilled',
                'legacy_seat_allocation_exception' => null,
                'updated_at' => now(),
            ]);
    }

    private function backfillSubmission(object $assignment): void
    {
        $submission = DB::table('exam_submission_records')
            ->where('exam_assignment_id', $assignment->id)
            ->first();

        if ($submission === null) {
            return;
        }

        $allocation = DB::table('exam_assignment_seat_allocations')
            ->where('exam_assignment_id', $assignment->id)
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($allocation === null) {
            DB::table('exam_submission_records')
                ->where('id', $submission->id)
                ->update([
                    'legacy_seat_allocation_status' => 'unresolved',
                    'legacy_seat_allocation_exception' => 'submission_has_no_provable_active_allocation',
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('exam_submission_records')
            ->where('id', $submission->id)
            ->update([
                'seat_allocation_id' => $allocation->id,
                'legacy_seat_allocation_status' => 'backfilled',
                'legacy_seat_allocation_exception' => null,
                'updated_at' => now(),
            ]);
    }

    private function markSnapshotUnresolved(int $snapshotId, string $reason): void
    {
        DB::table('exam_session_material_snapshots')
            ->where('id', $snapshotId)
            ->update([
                'legacy_backfill_status' => 'unresolved',
                'legacy_exception_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return list<string>
     */
    private function requiredSetCodes(string $pattern): array
    {
        return match ($pattern) {
            'uniform' => ['A'],
            'checkerboard', 'linear_alternating' => ['A', 'B'],
            'latin_four' => ['A', 'B', 'C', 'D'],
            default => [],
        };
    }

    private function bookletCode(string $pattern, int $row, int $column, int $index): string
    {
        return match ($pattern) {
            'uniform' => 'A',
            'checkerboard' => ($row + $column) % 2 === 0 ? 'A' : 'B',
            'latin_four' => ['A', 'B', 'C', 'D'][($row + $column) % 4],
            'linear_alternating' => $index % 2 === 0 ? 'A' : 'B',
            default => throw new RuntimeException('Unknown legacy seating pattern: '.$pattern),
        };
    }

    private function rowLabel(int $zeroBasedRow): string
    {
        $label = '';

        for ($value = $zeroBasedRow + 1; $value > 0; $value = intdiv($value - 1, 26)) {
            $label = chr(65 + (($value - 1) % 26)).$label;
        }

        return $label;
    }
};
