<?php

namespace App\Admission\Support;

use App\Consultation\Support\AttemptResultReadiness;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramSelection;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultRelease;

/**
 * Read-only derivation of admission-support predicates from committed domain
 * records (D-089–D-093). Never persisted; projections only.
 */
final class AttemptProgramFacts
{
    public static function isAccepted(AdmissionAttempt $attempt): bool
    {
        return $attempt->status === AdmissionAttempt::STATUS_ACCEPTED;
    }

    public static function isResultReleased(AdmissionAttempt $attempt): bool
    {
        return ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('is_current', true)
            ->exists();
    }

    public static function isResultReady(AdmissionAttempt $attempt): bool
    {
        return AttemptResultReadiness::isReady($attempt);
    }

    public static function isAdvised(AdmissionAttempt $attempt): bool
    {
        $latest = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();

        return $latest !== null && in_array($latest->decision_type, ['approve', 'modify', 'override'], true);
    }

    /**
     * Current non-terminal selection for the attempt, if any.
     */
    public static function currentSelection(AdmissionAttempt $attempt): ?ProgramSelection
    {
        return ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Most recent enrollment stamp row. A reversal row means no active stamp.
     */
    public static function activeStamp(AdmissionAttempt $attempt): ?EnrollmentStamp
    {
        $latest = EnrollmentStamp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();

        if ($latest === null || ! $latest->isActive()) {
            return null;
        }

        return $latest;
    }

    public static function isEnrolling(AdmissionAttempt $attempt): bool
    {
        $stamp = self::activeStamp($attempt);

        return $stamp !== null && $stamp->stamp_type === EnrollmentStamp::STAMP_ENROLLING;
    }

    public static function isEnrolled(AdmissionAttempt $attempt): bool
    {
        $stamp = self::activeStamp($attempt);

        return $stamp !== null && $stamp->stamp_type === EnrollmentStamp::STAMP_ENROLLED;
    }

    /**
     * @return list<ProgramFollowUp>
     */
    public static function openFollowUps(AdmissionAttempt $attempt): array
    {
        return ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->orderBy('id')
            ->get()
            ->all();
    }

    public static function hasOpenFollowUp(AdmissionAttempt $attempt): bool
    {
        return self::openFollowUps($attempt) !== [];
    }

    public static function hasOpenFollowUpForProgram(AdmissionAttempt $attempt, int $programId): bool
    {
        return ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('program_id', $programId)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->exists();
    }

    /**
     * Current clearance is derived from the latest immutable confirmation or revocation receipt.
     */
    public static function isClearanceConfirmed(AdmissionAttempt $attempt): bool
    {
        $latestReceipt = AdmissionClearanceReceipt::query()
            ->where('admission_attempt_id', $attempt->id)
            ->latest('id')
            ->first();

        return $latestReceipt?->status === AdmissionClearanceReceipt::STATUS_CONFIRMED;
    }

    public static function lifecycleTag(AdmissionAttempt $attempt): string
    {
        if (self::isEnrolled($attempt)) {
            return 'enrolled';
        }

        if (self::isEnrolling($attempt)) {
            return 'enrolling';
        }

        $selection = self::currentSelection($attempt);

        if ($selection !== null && $selection->status === ProgramSelection::STATUS_ACTIVE) {
            return 'selected';
        }

        if ($selection !== null) {
            return 'selection_pending_ack';
        }

        if (self::isAdvised($attempt)) {
            return 'advised';
        }

        if (self::isResultReleased($attempt)) {
            return 'result_released';
        }

        return 'accepted';
    }
}
