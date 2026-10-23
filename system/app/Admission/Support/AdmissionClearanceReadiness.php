<?php

namespace App\Admission\Support;

use App\Admission\Queries\AttemptRequirementChecklistQuery;
use App\Consultation\Support\AttemptResultReadiness;
use App\Models\Admission\AdmissionAttempt;

final class AdmissionClearanceReadiness
{
    /**
     * Evaluate admission clearance readiness for an attempt.
     *
     * @return array{
     *     is_ready: bool,
     *     checks: array<string, bool>,
     *     blocking_reasons: list<string>,
     *     program_id: ?int,
     *     evidence_vector: array<string, mixed>
     * }
     */
    public static function evaluate(AdmissionAttempt $attempt): array
    {
        $isAccepted = $attempt->status === AdmissionAttempt::STATUS_ACCEPTED;
        $isScored = AttemptResultReadiness::isReady($attempt);
        $isAdvised = AttemptProgramFacts::isAdvised($attempt);
        $documentsReady = AttemptRequirementChecklistQuery::allMandatoryVerified($attempt);

        $hasPendingFollowUps = AttemptProgramFacts::hasOpenFollowUp($attempt);
        $followUpsClear = ! $hasPendingFollowUps;

        $selectedProgramId = $attempt->program_id
            ?? $attempt->enrollmentStamp?->provisional_program_id
            ?? AttemptProgramFacts::currentSelection($attempt)?->program_id
            ?? null;

        $checks = [
            'accepted' => $isAccepted,
            'is_accepted' => $isAccepted,
            'scored' => $isScored,
            'is_scored' => $isScored,
            'advised' => $isAdvised,
            'is_advised' => $isAdvised,
            'documents_ready' => $documentsReady,
            'follow_ups_clear' => $followUpsClear,
            'program_selected' => $selectedProgramId !== null,
        ];

        $blockingReasons = [];
        if (! $isAccepted) {
            $blockingReasons[] = 'Admission attempt is not accepted.';
        }
        if (! $isScored) {
            $blockingReasons[] = 'Applicant does not have a prepared, finalized assessment result.';
        }
        if (! $isAdvised) {
            $blockingReasons[] = 'Applicant does not have an approved program advising decision.';
        }
        if (! $documentsReady) {
            $blockingReasons[] = 'Mandatory intake documents have not all been verified.';
        }
        if (! $followUpsClear) {
            $blockingReasons[] = 'Applicant has open academic follow-up requirements.';
        }
        if ($selectedProgramId === null) {
            $blockingReasons[] = 'No admission program is selected or designated for this applicant.';
        }

        $isReady = $isAccepted
            && $isScored
            && $isAdvised
            && $documentsReady
            && $followUpsClear
            && ($selectedProgramId !== null);

        $evidenceVector = [
            'attempt_status' => (string) $attempt->status,
            'attempt_version' => (int) ($attempt->version ?? 1),
            'is_accepted' => $isAccepted,
            'is_scored' => $isScored,
            'is_advised' => $isAdvised,
            'documents_ready' => $documentsReady,
            'follow_ups_clear' => $followUpsClear,
            'program_id' => $selectedProgramId,
            'evaluated_at' => now()->toIso8601String(),
        ];

        return [
            'is_ready' => $isReady,
            'checks' => $checks,
            'blocking_reasons' => $blockingReasons,
            'program_id' => $selectedProgramId,
            'evidence_vector' => $evidenceVector,
        ];
    }

    /**
     * Instance convenience method.
     *
     * @return array{
     *     is_ready: bool,
     *     checks: array<string, bool>,
     *     blocking_reasons: list<string>,
     *     program_id: ?int,
     *     evidence_vector: array<string, mixed>
     * }
     */
    public function handle(AdmissionAttempt $attempt): array
    {
        return self::evaluate($attempt);
    }
}
