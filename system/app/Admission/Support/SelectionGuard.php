<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramDecline;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use RuntimeException;

/**
 * Shared predicates for program-selection commands (D-090 §4). Called from
 * inside each action's transaction, after the attempt root lock.
 */
final class SelectionGuard
{
    public static function assertSelectable(AdmissionAttempt $attempt, string $context): void
    {
        if (! AttemptProgramFacts::isAccepted($attempt)) {
            throw new RuntimeException('Application must be accepted before a program can be selected.');
        }

        if (! AttemptProgramFacts::isResultReleased($attempt)) {
            throw new RuntimeException('The result must be released before a program can be selected.');
        }

        if (! AttemptProgramFacts::isAdvised($attempt)) {
            throw new RuntimeException('A confirmed consultation decision is required before selecting a program.');
        }

        if (AttemptProgramFacts::isEnrolling($attempt) || AttemptProgramFacts::isEnrolled($attempt)) {
            throw new RuntimeException($context.' is not allowed while enrollment processing is in progress.');
        }
    }

    /**
     * @throws RuntimeException when the program is not a current-pool option or
     *                          an effective decline exists against attempt/program/pool.
     */
    public static function assertOptionEffective(
        AdmissionAttempt $attempt,
        ProgramPool $pool,
        int $programId,
    ): void {
        $option = $pool->options()->where('program_id', $programId)->first();

        if ($option === null) {
            throw new RuntimeException('That program is not available in the current Guidance pool.');
        }

        $declined = ProgramDecline::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('program_id', $programId)
            ->where('program_pool_id', $pool->id)
            ->exists();

        if ($declined) {
            throw new RuntimeException('That program is not available: an assigned Program Head decline is recorded for this pool.');
        }
    }

    /**
     * @throws RuntimeException when a non-terminal selection exists and must be
     *                          terminalized first (transfer/cancel paths).
     */
    public static function lockStandingSelection(AdmissionAttempt $attempt): ?ProgramSelection
    {
        return ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Terminalize the old selection and release its reservation atomically.
     */
    public static function terminalize(
        ProgramSelection $selection,
        string $terminalStatus,
        ?string $reason,
    ): void {
        $selection->forceFill([
            'status' => $terminalStatus,
            'terminal_reason' => $reason,
            'updated_at' => now(),
        ])->save();
    }
}
