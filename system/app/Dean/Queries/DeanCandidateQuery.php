<?php

namespace App\Dean\Queries;

use App\Admission\Support\QuotaReservation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Testing\PracticeWorkspace;
use Illuminate\Support\Carbon;

/**
 * DeanCandidateQuery: Scoped candidate projection for Academic Department Heads and Deans.
 *
 * Confirmed-clearance lifecycle buckets:
 * - acknowledged_reservation: Active program selections acknowledged by applicant, awaiting clearance confirmation.
 * - ready_for_enrollment: Current confirmed AdmissionClearanceReceipt pinned to the program.
 * - pending_follow_up: Referrals requiring academic or paper follow-up resolution.
 */
final class DeanCandidateQuery
{
    /**
     * @return array<string, mixed>
     */
    public function for(Program $program, ?string $cycleCode): array
    {
        $cycle = $cycleCode === null ? null : AcademicCycle::query()
            ->where('code', $cycleCode)
            ->where('is_active', true)
            ->first();

        $cycleId = $cycle?->id;
        $programId = $program->id;
        $programCode = $program->code;

        $accepted = AdmissionAttempt::query()->forWorkspace()
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->pluck('id')
            ->all();

        // 1. Acknowledged Reservations: active selection without current clearance or legacy enrollment history.
        $acknowledgedSelectionAttemptIds = ProgramSelection::query()
            ->where('program_id', $programId)
            ->where('status', ProgramSelection::STATUS_ACTIVE)
            ->whereIn('admission_attempt_id', $accepted)
            ->whereDoesntHave('attempt.latestClearanceReceipt', fn ($q) => $q->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED))
            ->whereDoesntHave('attempt.enrollmentStamps')
            ->pluck('admission_attempt_id')
            ->all();

        $acknowledgedReservations = $this->rows($acknowledgedSelectionAttemptIds, fn (AdmissionAttempt $a) => [
            'bucket' => 'acknowledged_reservation',
            'stage' => 'acknowledged_reservation',
        ]);

        // 2. Ready for enrollment: the latest receipt is confirmed for this program.
        $readyForEnrollmentAttemptIds = AdmissionAttempt::query()
            ->whereIn('id', $accepted)
            ->whereHas('latestClearanceReceipt', fn ($q) => $q
                ->where('program_id', $programId)
                ->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED))
            ->pluck('id')
            ->all();

        $readyForEnrollment = $this->rows($readyForEnrollmentAttemptIds, fn (AdmissionAttempt $a) => [
            'bucket' => 'ready_for_enrollment',
            'stage' => 'ready_for_enrollment',
            'clearance_receipt_number' => $a->latestClearanceReceipt?->receipt_number,
            'cleared_at' => self::isoOrNull($a->latestClearanceReceipt?->confirmed_at),
        ]);

        // 4. Pending Follow-up: open targeted follow-ups for this program
        $pendingFollowUpRows = [];
        $followUps = ProgramFollowUp::query()
            ->whereIn('admission_attempt_id', $accepted)
            ->where('program_id', $programId)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->with('attempt.identity')
            ->orderBy('opened_at')
            ->get();

        foreach ($followUps as $f) {
            $pendingFollowUpRows[] = [
                'id' => $f->id,
                'attempt_id' => $f->admission_attempt_id,
                'reference_code' => $f->attempt?->reference_code,
                'name' => $f->attempt?->identity?->last_name.', '.$f->attempt?->identity?->first_name,
                'strand' => $f->attempt?->strand,
                'gwa' => $f->attempt?->gwa,
                'status' => $f->status,
                'reason' => $f->reason,
                'opened_at' => self::isoOrNull($f->opened_at),
                'bucket' => 'pending_follow_up',
            ];
        }

        // Quota metrics using QuotaReservation::committedForCycle
        $quota = ProgramQuota::query()
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->where('program_id', $programId)
            ->first();

        $committed = $cycleId === null
            ? 0
            : QuotaReservation::committedForCycle($cycleId, $programId, practiceSetId: app(PracticeWorkspace::class)->currentId());

        return [
            'program' => ['code' => $programCode, 'name' => $program->name],
            'cycle' => $cycle?->code,
            'ready_for_enrollment' => $readyForEnrollment,
            'pending_follow_up' => $pendingFollowUpRows,
            'quota' => [
                'configured' => $quota !== null,
                'capacity' => $quota?->slots,
                'committed' => $committed,
                'remaining' => $quota === null ? 0 : max($quota->slots - $committed, 0),
                'over_capacity' => $quota === null ? 0 : max($committed - $quota->slots, 0),
            ],
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @param  callable(AdmissionAttempt): array<string, mixed>  $tag
     * @return list<array<string, mixed>>
     */
    private function rows(array $ids, callable $tag): array
    {
        if ($ids === []) {
            return [];
        }

        return AdmissionAttempt::query()->forWorkspace()
            ->whereIn('id', $ids)
            ->with(['identity', 'latestClearanceReceipt'])
            ->orderBy('id')
            ->get()
            ->map(fn (AdmissionAttempt $a) => [
                'attempt_id' => $a->id,
                'reference_code' => $a->reference_code,
                'name' => $a->identity?->last_name.', '.$a->identity?->first_name,
                ...$tag($a),
            ])
            ->values()
            ->all();
    }

    private static function isoOrNull(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }
}
