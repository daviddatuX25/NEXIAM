<?php

namespace App\Admission\Support;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\ProgramQuota;

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
        return self::committedForCycle($attempt->academic_cycle_id, $programId, $excludeAttemptId, $attempt->practice_fixture_set_id);
    }

    /**
     * Distinct attempts committed across multiple programs in a cycle.
     *
     * Invariant: An attempt contributes at most once per program. Active selections,
     * the latest effective clearance receipt across all programs, and legacy
     * un-reversed stamps are unioned and deduplicated per program.
     *
     * @param  list<int>  $programIds
     * @return array<int, int> Map of program_id => committed_count
     */
    public static function batchCommittedForCycle(int $cycleId, array $programIds, ?string $practiceSetId = null): array
    {
        $programIds = array_values(array_unique(array_filter(array_map('intval', $programIds))));

        if ($programIds === []) {
            return [];
        }

        $result = array_fill_keys($programIds, 0);
        $committedSets = array_fill_keys($programIds, []);

        $attemptIds = AdmissionAttempt::query()->forPracticeSet($practiceSetId)
            ->where('academic_cycle_id', $cycleId)
            ->select('id');

        $stampRows = EnrollmentStamp::query()
            ->whereIn('admission_attempt_id', clone $attemptIds)
            ->orderBy('admission_attempt_id')
            ->orderBy('version')
            ->orderBy('id')
            ->get(['id', 'admission_attempt_id', 'stamp_type', 'provisional_program_id', 'final_program_id']);

        $latestStamps = $stampRows
            ->groupBy('admission_attempt_id')
            ->map(fn ($stamps) => $stamps->last())
            ->filter(fn ($stamp) => $stamp !== null && $stamp->isEffective());

        $attemptFinalProgramMap = [];

        foreach ($latestStamps as $stamp) {
            if ($stamp->stamp_type === EnrollmentStamp::STAMP_ENROLLED) {
                $final = $stamp->final_program_id ?? $stamp->provisional_program_id;

                if ($final !== null) {
                    $attemptFinalProgramMap[(int) $stamp->admission_attempt_id] = (int) $final;
                }
            }

            $stampProg = $stamp->stamp_type === EnrollmentStamp::STAMP_ENROLLED
                ? ($stamp->final_program_id ?? $stamp->provisional_program_id)
                : $stamp->provisional_program_id;

            if ($stampProg !== null && in_array((int) $stampProg, $programIds, true)) {
                $committedSets[(int) $stampProg][(int) $stamp->admission_attempt_id] = true;
            }
        }

        $selections = ProgramSelection::query()
            ->whereIn('admission_attempt_id', clone $attemptIds)
            ->whereIn('program_id', $programIds)
            ->where('academic_cycle_id', $cycleId)
            ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
            ->get(['admission_attempt_id', 'program_id']);

        foreach ($selections as $sel) {
            $aId = (int) $sel->admission_attempt_id;
            $pId = (int) $sel->program_id;

            if (! isset($attemptFinalProgramMap[$aId]) || $attemptFinalProgramMap[$aId] === $pId) {
                $committedSets[$pId][$aId] = true;
            }
        }

        $receiptRows = AdmissionClearanceReceipt::query()
            ->whereIn('admission_attempt_id', clone $attemptIds)
            ->orderBy('id')
            ->get(['id', 'admission_attempt_id', 'program_id', 'status']);

        $latestClearance = $receiptRows
            ->groupBy('admission_attempt_id')
            ->map(fn ($receipts) => $receipts->last())
            ->filter(fn ($receipt) => $receipt !== null && $receipt->status === AdmissionClearanceReceipt::STATUS_CONFIRMED);

        foreach ($latestClearance as $receipt) {
            $aId = (int) $receipt->admission_attempt_id;
            $pId = (int) $receipt->program_id;

            if (in_array($pId, $programIds, true)) {
                if (! isset($attemptFinalProgramMap[$aId]) || $attemptFinalProgramMap[$aId] === $pId) {
                    $committedSets[$pId][$aId] = true;
                }
            }
        }

        foreach ($programIds as $pId) {
            $result[$pId] = count($committedSets[$pId]);
        }

        return $result;
    }

    /**
     * Distinct attempts committed to a program in a cycle.
     *
     * Invariant: An attempt contributes at most once to a program. Active
     * selections, the latest effective clearance receipt across all programs,
     * and legacy un-reversed stamps are unioned and deduplicated by attempt.
     */
    public static function committedForCycle(int $cycleId, int $programId, ?int $excludeAttemptId = null, ?string $practiceSetId = null): int
    {
        $attemptIds = AdmissionAttempt::query()->forPracticeSet($practiceSetId)
            ->where('academic_cycle_id', $cycleId)->select('id');

        // Evaluate latest legacy stamp per attempt
        $stampRows = EnrollmentStamp::query()
            ->whereIn('admission_attempt_id', clone $attemptIds)
            ->when($excludeAttemptId !== null, fn ($q) => $q->where('admission_attempt_id', '!=', $excludeAttemptId))
            ->orderBy('admission_attempt_id')
            ->orderBy('version')
            ->get(['id', 'admission_attempt_id', 'stamp_type', 'provisional_program_id', 'final_program_id']);

        $latestStamps = $stampRows
            ->groupBy('admission_attempt_id')
            ->map(fn ($stamps) => $stamps->last())
            ->filter(fn ($stamp) => $stamp !== null && $stamp->isEffective());

        // Attempts whose final enrollment finalized in a DIFFERENT program release all prior commitments to $programId
        $finalizedInOtherProgram = $latestStamps
            ->filter(function (EnrollmentStamp $stamp) use ($programId) {
                if ($stamp->stamp_type !== EnrollmentStamp::STAMP_ENROLLED) {
                    return false;
                }
                $effective = $stamp->final_program_id ?? $stamp->provisional_program_id;

                return $effective !== null && (int) $effective !== $programId;
            })
            ->keys()
            ->all();

        // 1. Non-terminal program selections for this program and cycle (excluding attempts finalized elsewhere)
        $selectionAttempts = ProgramSelection::query()
            ->whereIn('admission_attempt_id', clone $attemptIds)
            ->where('program_id', $programId)
            ->where('academic_cycle_id', $cycleId)
            ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
            ->when($excludeAttemptId !== null, fn ($q) => $q->where('admission_attempt_id', '!=', $excludeAttemptId))
            ->when($finalizedInOtherProgram !== [], fn ($q) => $q->whereNotIn('admission_attempt_id', $finalizedInOtherProgram))
            ->distinct()
            ->pluck('admission_attempt_id');

        // 2. Only the latest clearance receipt per attempt is effective. Scope
        // history across programs so a stale confirmed receipt cannot reserve
        // its former program after a reasoned reopen and selection transfer.
        $latestClearanceReceipts = AdmissionClearanceReceipt::query()
            ->whereIn('admission_attempt_id', clone $attemptIds)
            ->when($excludeAttemptId !== null, fn ($q) => $q->where('admission_attempt_id', '!=', $excludeAttemptId))
            ->when($finalizedInOtherProgram !== [], fn ($q) => $q->whereNotIn('admission_attempt_id', $finalizedInOtherProgram))
            ->orderBy('id')
            ->get(['id', 'admission_attempt_id', 'program_id', 'status'])
            ->groupBy('admission_attempt_id')
            ->map(fn ($receipts) => $receipts->last())
            ->filter(fn ($receipt) => $receipt !== null
                && $receipt->status === AdmissionClearanceReceipt::STATUS_CONFIRMED
                && (int) $receipt->program_id === $programId)
            ->keys();

        $clearanceAttempts = $latestClearanceReceipts;

        // 3. Legacy stamps whose effective program is $programId
        $legacyAttempts = $latestStamps
            ->filter(function (EnrollmentStamp $stamp) use ($programId) {
                $effective = $stamp->stamp_type === EnrollmentStamp::STAMP_ENROLLED
                    ? ($stamp->final_program_id ?? $stamp->provisional_program_id)
                    : $stamp->provisional_program_id;

                return (int) $effective === $programId;
            })
            ->keys();

        return $selectionAttempts
            ->merge($clearanceAttempts)
            ->merge($legacyAttempts)
            ->unique()
            ->count();
    }

    /**
     * Return planned quota capacity as an advisory reservation snapshot.
     *
     * @return array{remaining: int, over_capacity: int, configured: bool, is_overflow: bool}
     */
    public static function assertCapacityFor(AdmissionAttempt $attempt, int $programId): array
    {
        $quota = ProgramQuota::query()
            ->where('academic_cycle_id', $attempt->academic_cycle_id)
            ->where('program_id', $programId)
            ->lockForUpdate()
            ->first();

        if ($quota === null) {
            return [
                'remaining' => 0,
                'over_capacity' => 0,
                'configured' => false,
                'is_overflow' => false,
            ];
        }

        $committed = self::committedAttemptCount($attempt, $programId, $attempt->id);
        $slots = (int) $quota->slots;
        $remaining = max($slots - $committed, 0);
        $isOverflow = $committed >= $slots;
        $overCapacity = $isOverflow ? ($committed - $slots + 1) : 0;

        if ($isOverflow) {
            app(RecordAuditEvent::class)->handle('admission.quota_overflow', [
                'attempt_id' => $attempt->id,
                'program_id' => $programId,
                'cycle_id' => $attempt->academic_cycle_id,
                'committed' => $committed,
                'capacity' => $slots,
            ], (string) (auth()->id() ?? 'system'));
        }

        return [
            'remaining' => $remaining,
            'over_capacity' => $overCapacity,
            'configured' => true,
            'is_overflow' => $isOverflow,
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
