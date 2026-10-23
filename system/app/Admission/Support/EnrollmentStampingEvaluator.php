<?php

namespace App\Admission\Support;

use App\Examination\Support\PreflightResult;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\ProgramQuota;

final class EnrollmentStampingEvaluator
{
    public function evaluateEnrolling(AdmissionAttempt $attempt, ?int $programId = null): PreflightResult
    {
        $accepted = AttemptProgramFacts::isAccepted($attempt);
        $examCompleted = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->whereHas('examSession', fn ($query) => $query->where('status', ExamSession::STATUS_COMPLETED))
            ->exists();
        $resultReleased = AttemptProgramFacts::isResultReady($attempt)
            && AttemptProgramFacts::isResultReleased($attempt);
        $consultationAdvised = AttemptProgramFacts::isAdvised($attempt);
        $selection = AttemptProgramFacts::currentSelection($attempt);
        $programId ??= $selection === null ? null : (int) $selection->program_id;
        $quota = $programId === null
            ? null
            : ProgramQuota::query()
                ->where('academic_cycle_id', $attempt->academic_cycle_id)
                ->where('program_id', $programId)
                ->first();
        $committed = $programId === null
            ? 0
            : QuotaReservation::committedAttemptCount($attempt, (int) $programId, (int) $attempt->id);
        $quotaHasSpace = $quota !== null && (int) $quota->slots > $committed;
        $noActiveStamp = AttemptProgramFacts::activeStamp($attempt) === null;

        $checks = [
            [
                'key' => 'attempt_accepted',
                'label' => 'Admission status',
                'status' => $accepted ? 'pass' : 'fail',
                'detail' => $accepted ? 'Attempt is accepted' : 'Attempt must be accepted',
            ],
            [
                'key' => 'exam_completed',
                'label' => 'Exam session',
                'status' => $examCompleted ? 'pass' : 'fail',
                'detail' => $examCompleted ? 'Exam session is completed' : 'Exam session must be completed',
            ],
            [
                'key' => 'result_released',
                'label' => 'Exam result',
                'status' => $resultReleased ? 'pass' : 'fail',
                'detail' => $resultReleased ? 'Finalized result is released' : 'Exam result must be finalized and released',
            ],
            [
                'key' => 'consultation_advised',
                'label' => 'Consultation decision',
                'status' => $consultationAdvised ? 'pass' : 'fail',
                'detail' => $consultationAdvised ? 'Consultation advised this applicant' : 'Consultation must advise this applicant',
            ],
            [
                'key' => 'program_quota_available',
                'label' => 'Program quota',
                'status' => $quotaHasSpace ? 'pass' : 'fail',
                'detail' => $quotaHasSpace ? 'Program quota has space' : 'A configured program quota with available space is required',
            ],
            [
                'key' => 'no_active_enrollment_stamp',
                'label' => 'Enrollment stamp',
                'status' => $noActiveStamp ? 'pass' : 'fail',
                'detail' => $noActiveStamp ? 'No active enrollment stamp exists' : 'An active enrollment stamp already exists',
            ],
        ];

        return new PreflightResult(
            outcome: $accepted && $examCompleted && $resultReleased && $consultationAdvised && $quotaHasSpace && $noActiveStamp ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'status',
                'label' => 'Status',
                'before' => 'advised',
                'after' => 'enrolling',
            ]],
        );
    }

    public function evaluateEnrolled(AdmissionAttempt $attempt, string $documentRef): PreflightResult
    {
        $activeStamp = AttemptProgramFacts::activeStamp($attempt);
        $activeEnrollingStamp = $activeStamp !== null && $activeStamp->stamp_type === EnrollmentStamp::STAMP_ENROLLING;
        $documentRefProvided = trim($documentRef) !== '';

        $checks = [
            [
                'key' => 'active_enrolling_stamp',
                'label' => 'Enrollment processing',
                'status' => $activeEnrollingStamp ? 'pass' : 'fail',
                'detail' => $activeEnrollingStamp ? 'An active enrolling stamp exists' : 'An active enrolling stamp is required',
            ],
            [
                'key' => 'document_reference_provided',
                'label' => 'Signed document reference',
                'status' => $documentRefProvided ? 'pass' : 'fail',
                'detail' => $documentRefProvided ? 'A signed document reference was provided' : 'A signed document reference is required',
            ],
        ];

        return new PreflightResult(
            outcome: $activeEnrollingStamp && $documentRefProvided ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'status',
                'label' => 'Status',
                'before' => 'enrolling',
                'after' => 'enrolled',
            ]],
        );
    }

    public function evaluateReverse(AdmissionAttempt $attempt, string $reason): PreflightResult
    {
        $activeStamp = AttemptProgramFacts::activeStamp($attempt);
        $activeEnrolledStamp = $activeStamp !== null && $activeStamp->stamp_type === EnrollmentStamp::STAMP_ENROLLED;
        $reasonProvided = trim($reason) !== '';

        $checks = [
            [
                'key' => 'active_enrolled_stamp',
                'label' => 'Enrollment status',
                'status' => $activeEnrolledStamp ? 'pass' : 'fail',
                'detail' => $activeEnrolledStamp ? 'An active enrolled stamp exists' : 'An active enrolled stamp is required',
            ],
            [
                'key' => 'reversal_reason_provided',
                'label' => 'Reversal reason',
                'status' => $reasonProvided ? 'pass' : 'fail',
                'detail' => $reasonProvided ? 'A reversal reason was provided' : 'A reversal reason is required',
            ],
        ];

        return new PreflightResult(
            outcome: $activeEnrolledStamp && $reasonProvided ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'status',
                'label' => 'Status',
                'before' => 'enrolled',
                'after' => 'advised (Quota returned)',
            ]],
        );
    }
}
