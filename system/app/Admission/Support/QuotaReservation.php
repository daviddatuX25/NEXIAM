<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\ProgramQuota;
use RuntimeException;

/**
 * FCFS quota reservation guard (D-090 §4). All capacity decisions run under
 * the common lock protocol: attempt root first, then quota rows by ascending
 * program id. Only non-terminal selections and active stamps commit capacity;
 * a single attempt never counts twice for the same program.
 */
final class QuotaReservation
{
    /**
     * Lock quota rows for the given program ids in ascending order.
     *
     * @param  list<int>  $programIds
     */
    public static function lockQuotaRows(AdmissionAttempt $attempt, array $programIds): void
    {
        $ids = array_values(array_unique(array_filter($programIds)));
        sort($ids);

        if ($ids === []) {
            return;
        }

        ProgramQuota::query()
            ->where('academic_cycle_id', $attempt->academic_cycle_id)
            ->whereIn('program_id', $ids)
            ->orderBy('program_id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Distinct attempts committed to a program: non-terminal pre-enrollment
     * selections plus current enrolling/enrolled stamps. The acting attempt's
     * own reservation is excluded so its ack/transfer/idempotent re-select
     * never counts against its own held slot (D-090 commitment dedupe).
     */
    public static function committedAttemptCount(AdmissionAttempt $attempt, int $programId, ?int $excludeAttemptId = null): int
    {
        return self::committedForCycle($attempt->academic_cycle_id, $programId, $excludeAttemptId);
    }

    /**
     * Distinct attempts committed to a program in a cycle.
     */
    public static function committedForCycle(int $cycleId, int $programId, ?int $excludeAttemptId = null): int
    {
        $selectionAttempts = ProgramSelection::query()
            ->where('program_id', $programId)
            ->where('academic_cycle_id', $cycleId)
            ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
            ->when($excludeAttemptId !== null, fn ($q) => $q->where('admission_attempt_id', '!=', $excludeAttemptId))
            ->distinct()
            ->pluck('admission_attempt_id');

        $stampAttempts = EnrollmentStamp::query()
            ->whereIn('stamp_type', [EnrollmentStamp::STAMP_ENROLLING, EnrollmentStamp::STAMP_ENROLLED])
            ->when($excludeAttemptId !== null, fn ($q) => $q->where('admission_attempt_id', '!=', $excludeAttemptId))
            ->where(function ($query) use ($programId) {
                $query->where('provisional_program_id', $programId)
                    ->orWhere('final_program_id', $programId);
            })
            ->distinct()
            ->pluck('admission_attempt_id');

        return $selectionAttempts
            ->merge($stampAttempts)
            ->unique()
            ->count();
    }

    /**
     * Guard a new reservation for the attempt+program. Fails closed when
     * quota is missing, inactive, or full (D-090).
     *
     * @return array{remaining: int, over_capacity: int, configured: bool}
     */
    public static function assertCapacityFor(AdmissionAttempt $attempt, int $programId): array
    {
        $quota = ProgramQuota::query()
            ->where('academic_cycle_id', $attempt->academic_cycle_id)
            ->where('program_id', $programId)
            ->lockForUpdate()
            ->first();

        if ($quota === null) {
            throw new RuntimeException('This program has no configured quota for the current cycle.');
        }

        $committed = self::committedAttemptCount($attempt, $programId, $attempt->id);

        if ($committed >= $quota->slots) {
            throw new RuntimeException('This program has reached its configured quota capacity.');
        }

        $remaining = max($quota->slots - $committed, 0);

        return [
            'remaining' => $remaining,
            'over_capacity' => 0,
            'configured' => true,
        ];
    }

    /**
     * Snapshot of commitment variance after a paper-confirmed final program
     * (D-091): configured capacity unchanged, superseded reservation
     * released, projection exposes remaining/over-capacity.
     *
     * @return array{capacity: int, committed: int, remaining: int, over_capacity: int, configured: bool}
     */
    public static function varianceSnapshot(AdmissionAttempt $attempt, int $programId): array
    {
        $quota = ProgramQuota::query()
            ->where('academic_cycle_id', $attempt->academic_cycle_id)
            ->where('program_id', $programId)
            ->first();

        if ($quota === null) {
            return [
                'capacity' => 0,
                'committed' => self::committedAttemptCount($attempt, $programId),
                'remaining' => 0,
                'over_capacity' => 0,
                'configured' => false,
            ];
        }

        $committed = self::committedAttemptCount($attempt, $programId);

        return [
            'capacity' => $quota->slots,
            'committed' => $committed,
            'remaining' => max($quota->slots - $committed, 0),
            'over_capacity' => max($committed - $quota->slots, 0),
            'configured' => true,
        ];
    }
}
