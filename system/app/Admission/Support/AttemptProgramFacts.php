<?php

namespace App\Admission\Support;

use App\Consultation\Support\AttemptResultReadiness;
use App\Models\Admission\AdmissionAttempt;
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
        return ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('decision_type', ['approve', 'modify', 'override'])
            ->exists();
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

    /**
     * Canonical primary lifecycle tag for projections.
     */
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
