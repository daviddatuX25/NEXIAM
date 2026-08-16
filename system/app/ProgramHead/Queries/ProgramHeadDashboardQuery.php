<?php

namespace App\ProgramHead\Queries;

use App\Admission\Support\QuotaReservation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultRelease;

/**
 * D-ROLE-05: program-scoped, active-cycle metric dictionary with source +
 * as_of. No small-cell suppression (authenticated assigned-program staff
 * audience); accessible table alternative is UI's responsibility.
 */
final class ProgramHeadDashboardQuery
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

        $attempts = AdmissionAttempt::query()
            ->when($cycle !== null, fn ($q) => $q->where('academic_cycle_id', $cycle->id))
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->get();

        $programId = $program->id;
        $code = $program->code;

        $preferenceCandidates = $attempts->filter(fn (AdmissionAttempt $a) => in_array($code, [
            $a->program_preference_1_code,
            $a->program_preference_2_code,
            $a->program_preference_3_code,
        ], true))->pluck('id');

        $ids = $preferenceCandidates->all();
        $inPool = $attempts->filter(fn (AdmissionAttempt $a) => ProgramPoolHas::optionFor($a, $programId))->pluck('id');

        $poolCandidateIds = $inPool->all();

        $metrics = [
            'preference_candidates' => $preferenceCandidates->count(),
            'accepted_candidates' => $preferenceCandidates->count(),
            'exam_completed_candidates' => $attempts->filter(
                fn (AdmissionAttempt $a) => $preferenceCandidates->contains($a->id) && $a->examAssignments()->where('status', 'completed')->exists()
            )->count(),
            'result_released_candidates' => ResultRelease::query()
                ->where('is_current', true)
                ->whereIn('admission_attempt_id', $ids)
                ->distinct('admission_attempt_id')
                ->count('admission_attempt_id'),
            'advised_candidates' => ConsultationDecision::query()
                ->whereIn('admission_attempt_id', $ids)
                ->whereIn('decision_type', ['approve', 'modify', 'override'])
                ->distinct('admission_attempt_id')
                ->count('admission_attempt_id'),
            'guidance_pool_candidates' => count($poolCandidateIds),
            'active_reservations' => ProgramSelection::query()
                ->where('program_id', $programId)
                ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
                ->distinct('admission_attempt_id')
                ->count('admission_attempt_id'),
            'open_follow_ups' => ProgramFollowUp::query()
                ->where('program_id', $programId)
                ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
                ->count(),
            'enrolling' => EnrollmentStamp::query()
                ->where('stamp_type', EnrollmentStamp::STAMP_ENROLLING)
                ->where('provisional_program_id', $programId)
                ->count(),
            'enrolled' => EnrollmentStamp::query()
                ->where('stamp_type', EnrollmentStamp::STAMP_ENROLLED)
                ->where('final_program_id', $programId)
                ->count(),
        ];

        $quota = ProgramQuota::query()
            ->when($cycle !== null, fn ($q) => $q->where('academic_cycle_id', $cycle->id))
            ->where('program_id', $programId)
            ->first();

        $committed = $cycle === null
            ? 0
            : QuotaReservation::committedForCycle($cycle->id, $programId);

        $metrics['quota'] = [
            'configured' => $quota !== null,
            'capacity' => $quota?->slots,
            'committed' => $committed,
            'remaining' => $quota === null ? 0 : max($quota->slots - $committed, 0),
            'over_capacity' => $quota === null ? 0 : max($committed - $quota->slots, 0),
        ];

        return [
            'program' => ['code' => $code, 'name' => $program->name],
            'cycle' => $cycle?->code,
            'metrics' => $metrics,
            'source' => 'committed domain records',
            'as_of' => now()->toIso8601String(),
        ];
    }
}
