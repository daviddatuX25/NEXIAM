<?php

namespace App\ProgramHead\Queries;

use App\Admission\Support\AttemptProgramFacts;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\Program;
use App\Models\User;
use App\ProgramHead\Support\ProtectedViewGuard;

/**
 * D-080: Tier A candidate summary vs Tier B protected admission-support
 * record. Tier B persists protected-view audit + access notice BEFORE any
 * protected field is serialized (fail-closed). Tier A never receives scores,
 * counselor notes, or engine input/output.
 */
final class ProgramHeadApplicantQueries
{
    public function __construct(
        private readonly ProtectedViewGuard $protectedViewGuard,
        private readonly ConsultationAdmissionSupportQuery $supportQuery,
    ) {}

    /**
     * Tier A summary — minimum necessary visibility.
     *
     * @return array<string, mixed>
     */
    public function summary(AdmissionAttempt $attempt, Program $program): array
    {
        return [
            'tier' => 'A',
            'attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'name' => $attempt->identity?->last_name.', '.$attempt->identity?->first_name,
            'basis' => $this->basis($attempt, $program),
            'lifecycle' => AttemptProgramFacts::lifecycleTag($attempt),
            'advised' => AttemptProgramFacts::isAdvised($attempt),
            'selection_status' => AttemptProgramFacts::currentSelection($attempt)?->status,
            'follow_up_count' => count(AttemptProgramFacts::openFollowUps($attempt)),
            'enrolling' => AttemptProgramFacts::isEnrolling($attempt),
            'enrolled' => AttemptProgramFacts::isEnrolled($attempt),
            'visibility_note' => 'Scores, counselor notes, and other program choices are not shown at this visibility level.',
        ];
    }

    /**
     * Tier B protected record. Access evidence persists before serialization.
     *
     * @return array<string, mixed>
     */
    public function protectedRecord(AdmissionAttempt $attempt, Program $program, User $ph): array
    {
        $tier = $this->protectedViewGuard->tierFor($attempt, $program);

        if ($tier['tier'] !== 'B') {
            return $this->summary($attempt, $program);
        }

        $this->protectedViewGuard->recordProtectedView($attempt, $program, $ph, $tier['access_reason'] ?? '');

        $support = $this->supportQuery->for($attempt);

        return [
            'tier' => 'B',
            'access_reason' => $tier['access_reason'],
            'attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'name' => $attempt->identity?->last_name.', '.$attempt->identity?->first_name,
            'lifecycle' => AttemptProgramFacts::lifecycleTag($attempt),
            'score_profile' => $support['score_profile'],
            'consultation_outcome' => $support['consultation_evidence'],
            'selection' => $support['program_pool'],
            'follow_ups' => $support['follow_ups'],
            'stamp_state' => [
                'enrolling' => AttemptProgramFacts::isEnrolling($attempt),
                'enrolled' => AttemptProgramFacts::isEnrolled($attempt),
            ],
            'as_of' => $support['as_of'],
        ];
    }

    /**
     * @return list<string>
     */
    private function basis(AdmissionAttempt $attempt, Program $program): array
    {
        $basis = [];
        foreach (['program_preference_1_code', 'program_preference_2_code', 'program_preference_3_code'] as $i => $column) {
            if ($attempt->{$column} === $program->code) {
                $basis[] = 'preference #'.($i + 1);
            }
        }

        if (ProgramPoolHas::optionFor($attempt, $program->id)) {
            $basis[] = 'guidance pool';
        }

        return $basis;
    }
}
