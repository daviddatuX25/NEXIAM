<?php

namespace App\ProgramHead\Queries;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\QuotaReservation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Support\Search\TokenizedInnerMatchScope;
use App\Testing\PracticeWorkspace;
use Illuminate\Support\Carbon;

/**
 * D-080/D-ROLE-05: assigned-program candidate lists. Identified rows require
 * Registrar acceptance; submitted/pre-accept rows stay aggregate-only.
 * Current confirmed clearance is the terminal program-facing milestone;
 * legacy enrollment stamps are never active candidate states.
 */
final class ProgramHeadCandidateListsQuery
{
    /**
     * @return array<string, mixed>
     */
    public function for(
        Program $program,
        ?string $cycleCode,
        ?string $search = null,
        ?string $sort = null,
        ?string $dir = null,
        ?string $strand = null,
    ): array {
        $search = $search ?? request()->query('candidate_search', request()->query('search'));
        $sort = $sort ?? request()->query('candidate_sort', request()->query('sort'));
        $dir = strtolower((string) ($dir ?? request()->query('candidate_dir', request()->query('dir', 'asc')))) === 'desc' ? 'desc' : 'asc';
        $strand = $strand ?? request()->query('strand');
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

        // Potential: preference 1-3 OR active pool option, not committed elsewhere.
        $potentialIds = AdmissionAttempt::query()->forWorkspace()
            ->whereIn('id', $accepted)
            ->whereDoesntHave('latestClearanceReceipt', fn ($q) => $q->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED))
            ->whereDoesntHave('enrollmentStamps')
            ->where(function ($q) use ($programCode, $programId) {
                $q->whereIn('program_preference_1_code', [$programCode])
                    ->orWhereIn('program_preference_2_code', [$programCode])
                    ->orWhereIn('program_preference_3_code', [$programCode])
                    ->orWhereHas('programPools.options', fn ($o) => $o->where('program_id', $programId));
            })
            ->whereDoesntHave('programSelections', function ($q) use ($programId) {
                $q->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
                    ->where('program_id', '!=', $programId);
            })
            ->pluck('id')
            ->all();

        $potential = $this->rows($potentialIds, fn (AdmissionAttempt $a) => [
            'basis' => $this->basis($a, $programCode, $programId),
            'stage' => AttemptProgramFacts::lifecycleTag($a),
        ]);

        // Upcoming: active selection for this program with no open follow-up or confirmed clearance.
        $upcomingIds = ProgramSelection::query()
            ->where('program_id', $programId)
            ->where('status', ProgramSelection::STATUS_ACTIVE)
            ->whereIn('admission_attempt_id', $accepted)
            ->whereDoesntHave('attempt.latestClearanceReceipt', fn ($q) => $q->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED))
            ->whereDoesntHave('attempt.enrollmentStamps')
            ->pluck('admission_attempt_id')
            ->all();

        $upcoming = $this->rows($upcomingIds, fn (AdmissionAttempt $a) => ['stage' => 'upcoming']);

        // Follow-up queue: open targeted follow-ups for this program.
        $followUpRows = [];
        $followUps = ProgramFollowUp::query()
            ->whereIn('admission_attempt_id', $accepted)
            ->where('program_id', $programId)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->with('attempt.identity')
            ->orderBy('opened_at')
            ->get();

        $followUpAttemptIds = $followUps->pluck('admission_attempt_id')->filter()->unique()->values()->all();
        $followUpResults = [];
        foreach ($followUpAttemptIds as $attemptId) {
            $attempt = $followUps->firstWhere('admission_attempt_id', $attemptId)?->attempt;
            $followUpResults[$attemptId] = $attempt === null ? false : $this->hasFinalizedResult($attempt);
        }

        foreach ($followUps as $f) {
            $followUpRows[] = [
                'id' => $f->id,
                'attempt_id' => $f->admission_attempt_id,
                'reference_code' => $f->attempt?->reference_code,
                'name' => $f->attempt?->identity?->last_name.', '.$f->attempt?->identity?->first_name,
                'strand' => $f->attempt?->strand,
                'last_school' => $f->attempt?->last_school_enrolled,
                'gwa' => $f->attempt?->gwa !== null ? (float) $f->attempt->gwa : null,
                'score' => null,
                'result_available' => $followUpResults[$f->admission_attempt_id] ?? false,
                'status' => $f->status,
                'reason' => $f->reason,
                'opened_at' => self::isoOrNull($f->opened_at),
            ];
        }

        $readyForEnrollmentIds = AdmissionAttempt::query()->forWorkspace()
            ->whereIn('id', $accepted)
            ->whereHas('latestClearanceReceipt', fn ($q) => $q
                ->where('program_id', $programId)
                ->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED))
            ->pluck('id')
            ->all();
        $readyForEnrollment = $this->rows($readyForEnrollmentIds, fn (AdmissionAttempt $attempt) => [
            'stage' => 'ready_for_enrollment',
            'clearance_receipt_number' => $attempt->latestClearanceReceipt?->receipt_number,
            'cleared_at' => self::isoOrNull($attempt->latestClearanceReceipt?->confirmed_at),
        ]);
        $potential = $this->filterAndSortRows($potential, $search, $strand, $sort ?? 'name', $dir);
        $upcoming = $this->filterAndSortRows($upcoming, $search, $strand, $sort ?? 'name', $dir);
        $followUpRows = $this->filterAndSortRows($followUpRows, $search, $strand, $sort ?? 'opened_at', $dir);
        $readyForEnrollment = $this->filterAndSortRows($readyForEnrollment, $search, $strand, $sort ?? 'name', $dir);
        // Quota panel.
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
            'potential' => $potential,
            'upcoming' => $upcoming,
            'follow_ups' => $followUpRows,
            'ready_for_enrollment' => $readyForEnrollment,
            'pending_review_count' => PendingProgramReviewQuery::countFor($program, $cycleId),
            'pending_referrals' => $followUpRows,
            'qualified_pool' => $potential,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'dir' => $dir,
                'strand' => $strand,
            ],
            'quota' => [
                'configured' => $quota !== null,
                'capacity' => $quota?->slots,
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

        $attempts = AdmissionAttempt::query()->forWorkspace()
            ->whereIn('id', $ids)
            ->with(['identity', 'latestClearanceReceipt'])
            ->orderBy('id')
            ->get();
        $resultAvailability = [];
        foreach ($attempts as $attempt) {
            $resultAvailability[$attempt->id] = $this->hasFinalizedResult($attempt);
        }

        return $attempts
            ->map(fn (AdmissionAttempt $a) => [
                'attempt_id' => $a->id,
                'reference_code' => $a->reference_code,
                'name' => $a->identity?->last_name.', '.$a->identity?->first_name,
                'strand' => $a->strand,
                'last_school' => $a->last_school_enrolled,
                'gwa' => $a->gwa !== null ? (float) $a->gwa : null,
                'score' => null,
                'result_available' => $resultAvailability[$a->id] ?? false,
                'opened_at' => self::isoOrNull($a->created_at),
                ...$tag($a),
            ])
            ->values()
            ->all();
    }

    private function hasFinalizedResult(AdmissionAttempt $attempt): bool
    {
        $assignment = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->with('examSession')
            ->orderByDesc('id')
            ->first();
        if ($assignment === null || $assignment->examSession === null) {
            return false;
        }

        $administrations = AssessmentAdministration::query()
            ->active()
            ->where('exam_assignment_id', $assignment->id)
            ->where('admission_attempt_id', $attempt->id)
            ->orderBy('id')
            ->get(['id']);
        if ($administrations->count() !== 1) {
            return false;
        }

        $gradingSession = GradingSession::query()
            ->where('exam_session_id', $assignment->exam_session_id)
            ->first();
        if ($gradingSession === null) {
            return false;
        }

        $resultSets = AssessmentResultSet::query()
            ->where('administration_id', $administrations->first()->id)
            ->where('exam_session_id', $assignment->exam_session_id)
            ->where('grading_session_id', $gradingSession->id)
            ->where('status', AssessmentResultSet::STATUS_FINALIZED)
            ->orderBy('id')
            ->get(['id']);

        return $resultSets->count() === 1;
    }

    /**
     * Filters by search tokens across candidate identity & facts, and sorts by key.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterAndSortRows(array $rows, ?string $search, ?string $strand, ?string $sort, string $dir): array
    {
        if ($strand !== null && $strand !== '' && strtolower($strand) !== 'all') {
            $rows = array_values(array_filter($rows, fn ($r) => strcasecmp((string) ($r['strand'] ?? ''), $strand) === 0));
        }

        $tokens = TokenizedInnerMatchScope::tokenize($search);
        if ($tokens !== []) {
            $rows = array_values(array_filter($rows, function ($r) use ($tokens) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $r['name'] ?? null,
                    $r['reference_code'] ?? null,
                    $r['strand'] ?? null,
                    $r['last_school'] ?? null,
                    $r['reason'] ?? null,
                ])));
                foreach ($tokens as $token) {
                    if (! str_contains($haystack, mb_strtolower($token))) {
                        return false;
                    }
                }

                return true;
            }));
        }

        if ($sort !== null && $sort !== '') {
            usort($rows, function ($a, $b) use ($sort, $dir) {
                $valA = match ($sort) {
                    'name' => $a['name'] ?? '',
                    'reference_code' => $a['reference_code'] ?? '',
                    'gwa' => $a['gwa'] ?? null,
                    'score' => $a['score'] ?? null,
                    'opened_at' => $a['opened_at'] ?? '',
                    default => $a['name'] ?? '',
                };
                $valB = match ($sort) {
                    'name' => $b['name'] ?? '',
                    'reference_code' => $b['reference_code'] ?? '',
                    'gwa' => $b['gwa'] ?? null,
                    'score' => $b['score'] ?? null,
                    'opened_at' => $b['opened_at'] ?? '',
                    default => $b['name'] ?? '',
                };

                if ($valA === null && $valB === null) {
                    return 0;
                }
                if ($valA === null) {
                    return $dir === 'asc' ? 1 : -1;
                }
                if ($valB === null) {
                    return $dir === 'asc' ? -1 : 1;
                }

                $cmp = (is_numeric($valA) && is_numeric($valB))
                    ? ($valA <=> $valB)
                    : strcasecmp((string) $valA, (string) $valB);

                return $dir === 'asc' ? $cmp : -$cmp;
            });
        }

        return array_values($rows);
    }

    /**
     * @return list<string>
     */
    private function basis(AdmissionAttempt $attempt, string $programCode, int $programId): array
    {
        $basis = [];
        foreach (['program_preference_1_code', 'program_preference_2_code', 'program_preference_3_code'] as $i => $column) {
            if ($attempt->{$column} === $programCode) {
                $basis[] = 'preference #'.($i + 1);
            }
        }

        $inPool = ProgramPool::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereHas('options', fn ($o) => $o->where('program_id', $programId))
            ->exists();

        if ($inPool) {
            $basis[] = 'guidance pool';
        }

        return $basis;
    }

    private static function isoOrNull(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }
}
