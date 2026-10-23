<?php

namespace App\Scheduling\Queries;

use App\Assessment\Support\IndividualScoringReadiness;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Program;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

final class SchedulingWaitlistQuery
{
    public function __construct(private readonly IndividualScoringReadiness $scoringReadiness) {}

    /**
     * Returns readiness counts without projecting or paginating applicant records.
     *
     * @return array{ready: int, not_ready: int}
     */
    public function countsForDomain(
        string $domain,
        int $institutionId,
        ?int $cycleId,
        ?int $practiceSetId = null,
        bool $enforceWorkspace = true,
    ): array {
        if ($cycleId === null || $cycleId <= 0) {
            return ['ready' => 0, 'not_ready' => 0];
        }

        $baseQuery = AdmissionAttempt::query()
            ->where('academic_cycle_id', $cycleId)
            ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId));

        if ($practiceSetId !== null) {
            $baseQuery->forPracticeSet((string) $practiceSetId);
        } elseif ($enforceWorkspace) {
            $baseQuery->forWorkspace();
        }

        if ($domain === 'exam') {
            $readyQuery = (clone $baseQuery)
                ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
                ->whereDoesntHave('examAssignments', fn ($query) => $query->blockingScheduling());
            $notReadyQuery = (clone $baseQuery)
                ->whereIn('status', [AdmissionAttempt::STATUS_PENDING, AdmissionAttempt::STATUS_NEEDS_CORRECTION, 'submitted']);
            $readyCount = (int) $readyQuery->count();
            $notReadyCount = (int) $notReadyQuery->count();
        } else {
            $poolQuery = (clone $baseQuery)
                ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
                ->whereDoesntHave('consultationAssignments', fn ($query) => $query->active())
                ->whereDoesntHave('consultationDecisions', fn ($query) => $query->whereIn('decision_type', ['approve', 'modify', 'override']));

            $scoringCandidates = (clone $poolQuery)
                ->whereHas('examAssignments', fn ($query) => $query->active())
                ->get();
            $scoring = $this->scoringReadiness->forAttempts($scoringCandidates);
            $readyCount = $scoringCandidates->filter(
                fn (AdmissionAttempt $attempt): bool => $scoring[$attempt->id]['ready'],
            )->count();
            $notReadyCount = max(0, $poolQuery->count() - $readyCount);
        }

        return [
            'ready' => $readyCount,
            'not_ready' => $notReadyCount,
        ];
    }

    /**
     * Resolves the scheduling waitlist / candidate pool for either exam scheduling or consultation scheduling.
     *
     * @param  array{
     *     tab?: string|null,
     *     readiness?: string|null,
     *     pool_search?: string|null,
     *     pool_program?: string|null,
     *     pool_blocker?: string|null,
     *     pool_sort?: string|null,
     *     focus_attempt_id?: int|string|null,
     *     pool_page?: int|string|null,
     * }  $filters
     * @return array{
     *     readiness: 'ready'|'not_ready',
     *     counts: array{ready: int, not_ready: int, blockers: array<string, int>},
     *     filters: array{pool_search: string, pool_program: string, pool_blocker: string, pool_sort: string},
     *     focus_attempt_id: int|null,
     *     applicants: array{
     *         data: list<array{
     *             attempt_id: int,
     *             reference_code: string,
     *             full_name: string,
     *             email: string|null,
     *             strand: string|null,
     *             first_choice_program_code: string|null,
     *             first_choice_program_name: string|null,
     *             accepted_at: string|null,
     *             submitted_at: string|null,
     *             readiness_bucket: 'ready'|'not_ready',
     *             stage_code: string,
     *             stage_label: string,
     *             stage_tone: 'emerald'|'primary'|'amber'|'destructive'|'slate',
     *             blocking_office: string|null,
     *             detail_hint: string|null,
     *             can_select_for_assignment: bool,
     *         }>,
     *         current_page: int,
     *         last_page: int,
     *         per_page: int,
     *         total: int,
     *         from: int|null,
     *         to: int|null,
     *         prev_page_url: string|null,
     *         next_page_url: string|null,
     *     }
     * }
     */
    public function forDomain(
        string $domain,
        int $institutionId,
        ?int $cycleId,
        array $filters = [],
        ?int $practiceSetId = null,
        bool $enforceWorkspace = false,
    ): array {
        $readiness = ($filters['readiness'] ?? null) === 'not_ready' ? 'not_ready' : 'ready';
        $poolSearch = trim((string) ($filters['pool_search'] ?? ''));
        $poolProgram = trim((string) ($filters['pool_program'] ?? ''));
        $poolBlocker = trim((string) ($filters['pool_blocker'] ?? ''));
        $poolSort = in_array($filters['pool_sort'] ?? null, ['fcfs', 'alphabetical', 'program'], true)
            ? (string) $filters['pool_sort']
            : 'fcfs';
        $focusAttemptId = isset($filters['focus_attempt_id']) && is_numeric($filters['focus_attempt_id']) && (int) $filters['focus_attempt_id'] > 0
            ? (int) $filters['focus_attempt_id']
            : null;

        if ($cycleId === null || $cycleId <= 0) {
            return [
                'readiness' => $readiness,
                'counts' => [
                    'ready' => 0,
                    'not_ready' => 0,
                    'blockers' => [],
                ],
                'filters' => [
                    'pool_search' => $poolSearch,
                    'pool_program' => $poolProgram,
                    'pool_blocker' => $poolBlocker,
                    'pool_sort' => $poolSort,
                ],
                'focus_attempt_id' => $focusAttemptId,
                'applicants' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 25,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                    'prev_page_url' => null,
                    'next_page_url' => null,
                ],
            ];
        }

        $baseQuery = AdmissionAttempt::query()
            ->where('academic_cycle_id', $cycleId)
            ->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId));

        if ($practiceSetId !== null) {
            $baseQuery->forPracticeSet((string) $practiceSetId);
        } elseif ($enforceWorkspace) {
            $baseQuery->forWorkspace();
        }

        if ($domain === 'exam') {
            return $this->resolveExamWaitlist(
                $baseQuery,
                $institutionId,
                $readiness,
                $poolSearch,
                $poolProgram,
                $poolBlocker,
                $poolSort,
                $focusAttemptId,
                $filters,
            );
        }

        return $this->resolveConsultationWaitlist(
            $baseQuery,
            $institutionId,
            $readiness,
            $poolSearch,
            $poolProgram,
            $poolBlocker,
            $poolSort,
            $focusAttemptId,
            $filters,
        );
    }

    /**
     * Resolves waitlist and pipeline blockers for Exam Scheduling.
     */
    private function resolveExamWaitlist(
        EloquentBuilder $baseQuery,
        int $institutionId,
        string $readiness,
        string $poolSearch,
        string $poolProgram,
        string $poolBlocker,
        string $poolSort,
        ?int $focusAttemptId,
        array $filters,
    ): array {
        // Ready bucket: accepted and no active exam assignments
        $readyBase = (clone $baseQuery)
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->whereDoesntHave('examAssignments', fn ($q) => $q->blockingScheduling());

        // Not Ready bucket: pending, needs_correction, submitted
        $notReadyBase = (clone $baseQuery)
            ->whereIn('status', [AdmissionAttempt::STATUS_PENDING, AdmissionAttempt::STATUS_NEEDS_CORRECTION, 'submitted']);

        // Blocker counts for Not Ready bucket
        $hasReconsideredCol = Schema::hasColumn('admission_attempts', 'reconsidered_at');

        $pendingQuery = (clone $notReadyBase)->where('status', AdmissionAttempt::STATUS_PENDING);
        if ($hasReconsideredCol) {
            $pendingReviewCount = (clone $pendingQuery)->whereNull('reconsidered_at')->count();
            $reconsideredPendingCount = (clone $pendingQuery)->whereNotNull('reconsidered_at')->count();
        } else {
            $pendingReviewCount = (clone $pendingQuery)->whereRaw("LOWER(COALESCE(correction_notes, '')) NOT LIKE '%reconsidered:%'")->count();
            $reconsideredPendingCount = (clone $pendingQuery)->whereRaw("LOWER(COALESCE(correction_notes, '')) LIKE '%reconsidered:%'")->count();
        }

        $needsCorrectionCount = (clone $notReadyBase)
            ->where('status', AdmissionAttempt::STATUS_NEEDS_CORRECTION)
            ->count();

        $readyCount = (clone $readyBase)->count();
        $notReadyCount = (clone $notReadyBase)->count();

        $blockers = [
            'pending_registrar_review' => $pendingReviewCount,
            'needs_applicant_correction' => $needsCorrectionCount,
            'reconsidered_pending' => $reconsideredPendingCount,
        ];

        // Pick active query according to selected readiness
        $activeQuery = $readiness === 'ready' ? (clone $readyBase) : (clone $notReadyBase);

        // Filter by blocker if narrowed on not_ready
        if ($readiness === 'not_ready' && $poolBlocker !== '') {
            if ($poolBlocker === 'needs_applicant_correction') {
                $activeQuery->where('status', AdmissionAttempt::STATUS_NEEDS_CORRECTION);
            } elseif ($poolBlocker === 'reconsidered_pending') {
                $activeQuery->where('status', AdmissionAttempt::STATUS_PENDING);
                if ($hasReconsideredCol) {
                    $activeQuery->whereNotNull('reconsidered_at');
                } else {
                    $activeQuery->whereRaw("LOWER(COALESCE(correction_notes, '')) LIKE '%reconsidered:%'");
                }
            } elseif ($poolBlocker === 'pending_registrar_review') {
                $activeQuery->where('status', AdmissionAttempt::STATUS_PENDING);
                if ($hasReconsideredCol) {
                    $activeQuery->whereNull('reconsidered_at');
                } else {
                    $activeQuery->whereRaw("LOWER(COALESCE(correction_notes, '')) NOT LIKE '%reconsidered:%'");
                }
            }
        }

        $paginator = $this->paginateAndOrder(
            $activeQuery,
            $institutionId,
            $poolSearch,
            $poolProgram,
            $poolSort,
            $focusAttemptId,
            $filters,
        );

        $programsByCode = Program::query()
            ->where('institution_id', $institutionId)
            ->pluck('name', 'code')
            ->all();

        $data = [];
        foreach ($paginator->items() as $attempt) {
            /** @var AdmissionAttempt $attempt */
            $firstChoiceCode = $attempt->preferred_program_code ?: $attempt->program_preference_1_code;
            $fullName = $attempt->identity?->fullName()
                ?: trim(($attempt->payload['first_name'] ?? '').' '.($attempt->payload['last_name'] ?? ''));

            if ($readiness === 'ready') {
                $latestPriorAssignment = $attempt->examAssignments
                    ->sortByDesc('id')
                    ->first(fn (ExamAssignment $a) => ! $a->isActive() || in_array($a->attendanceRecord?->outcome, [
                        ExamAttendanceRecord::OUTCOME_ABSENT,
                        ExamAttendanceRecord::OUTCOME_VOID,
                    ], true));

                $priorOutcome = $latestPriorAssignment?->attendanceRecord?->outcome;
                $priorSessionCode = $latestPriorAssignment?->examSession?->code;

                if ($priorOutcome === ExamAttendanceRecord::OUTCOME_ABSENT) {
                    $stageCode = 'ready_absent_reschedule';
                    $stageLabel = 'Ready · Absent (Reschedule)';
                    $stageTone = 'amber';
                    $detailHint = $priorSessionCode
                        ? "Marked absent in {$priorSessionCode} — available for reassignment"
                        : 'Marked absent in prior exam session — available for reassignment';
                } elseif ($priorOutcome === ExamAttendanceRecord::OUTCOME_VOID) {
                    $stageCode = 'ready_void_reschedule';
                    $stageLabel = 'Ready · Voided Sitting (Reschedule)';
                    $stageTone = 'amber';
                    $detailHint = $priorSessionCode
                        ? "Voided sitting in {$priorSessionCode} — available for reassignment"
                        : 'Voided sitting in prior exam session — available for reassignment';
                } elseif ($latestPriorAssignment !== null) {
                    $stageCode = 'ready_reschedule';
                    $stageLabel = 'Ready · Reschedule Needed';
                    $stageTone = 'primary';
                    $detailHint = 'Prior exam assignment cancelled or removed';
                } else {
                    $stageCode = 'ready_first_time';
                    $stageLabel = 'Ready · NOA Issued';
                    $stageTone = 'emerald';
                    $detailHint = 'Notice of Admission issued';
                }
                $blockingOffice = 'Guidance / Testing Office';
                $canSelect = true;
            } else {
                $canSelect = false;
                $isReconsidered = $hasReconsideredCol
                    ? ($attempt->reconsidered_at !== null)
                    : (is_string($attempt->correction_notes) && stripos($attempt->correction_notes, 'reconsidered:') !== false);

                if ($attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION) {
                    $stageCode = 'needs_applicant_correction';
                    $stageLabel = 'Not Ready · Needs Applicant Correction';
                    $stageTone = 'destructive';
                    $blockingOffice = 'Applicant / Registrar';
                    $detailHint = 'Applicant correction required';
                } elseif ($isReconsidered) {
                    $stageCode = 'reconsidered_pending';
                    $stageLabel = 'Not Ready · Reconsidered (Pending Review)';
                    $stageTone = 'amber';
                    $blockingOffice = 'Registrar Office';
                    $detailHint = 'Reconsidered application awaiting Registrar re-acceptance';
                } else {
                    $stageCode = 'pending_registrar_review';
                    $stageLabel = 'Not Ready · Pending Registrar Review';
                    $stageTone = 'amber';
                    $blockingOffice = 'Registrar Office';
                    $detailHint = 'Under initial review by Registrar';
                }
            }

            $data[] = [
                'attempt_id' => (int) $attempt->id,
                'reference_code' => (string) $attempt->reference_code,
                'full_name' => $fullName,
                'email' => $attempt->contact_email ?? $attempt->identity?->email,
                'strand' => $attempt->strand,
                'first_choice_program_code' => $firstChoiceCode,
                'first_choice_program_name' => $firstChoiceCode ? ($programsByCode[$firstChoiceCode] ?? null) : null,
                'accepted_at' => $attempt->accepted_at?->toIso8601String(),
                'submitted_at' => $attempt->created_at?->toIso8601String(),
                'readiness_bucket' => $readiness,
                'stage_code' => $stageCode,
                'stage_label' => $stageLabel,
                'stage_tone' => $stageTone,
                'blocking_office' => $blockingOffice,
                'detail_hint' => $detailHint,
                'can_select_for_assignment' => $canSelect,
            ];
        }

        return [
            'readiness' => $readiness,
            'counts' => [
                'ready' => $readyCount,
                'not_ready' => $notReadyCount,
                'blockers' => $blockers,
            ],
            'filters' => [
                'pool_search' => $poolSearch,
                'pool_program' => $poolProgram,
                'pool_blocker' => $poolBlocker,
                'pool_sort' => $poolSort,
            ],
            'focus_attempt_id' => $focusAttemptId,
            'applicants' => [
                'data' => $data,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
        ];
    }

    /**
     * Resolves waitlist and upstream pipeline blockers for Consultation Scheduling.
     */
    private function resolveConsultationWaitlist(
        EloquentBuilder $baseQuery,
        int $institutionId,
        string $readiness,
        string $poolSearch,
        string $poolProgram,
        string $poolBlocker,
        string $poolSort,
        ?int $focusAttemptId,
        array $filters,
    ): array {
        // Base accepted attempts with no active consultation assignment and no terminal decision
        $consultationPoolBase = (clone $baseQuery)
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->whereDoesntHave('consultationAssignments', fn ($q) => $q->active())
            ->whereDoesntHave('consultationDecisions', fn ($q) => $q->whereIn('decision_type', ['approve', 'modify', 'override']));

        // Ready means complete current individual scoring; document preparation and printing are separate facts.
        $scoringCandidates = (clone $consultationPoolBase)
            ->with('examAssignments.examSession', 'examAssignments.attendanceRecord', 'examAssignments.submissionRecord')
            ->whereHas('examAssignments', fn ($q) => $q->active())
            ->get();
        $scoring = $this->scoringReadiness->forAttempts($scoringCandidates);
        $readyIds = $scoringCandidates
            ->filter(fn (AdmissionAttempt $attempt): bool => $scoring[$attempt->id]['ready'])
            ->modelKeys();
        $readyBase = (clone $consultationPoolBase)->whereIn('id', $readyIds);

        // Not Ready is the accepted scheduling pool minus individually complete scoring.
        $notReadyBase = (clone $consultationPoolBase)->whereNotIn('id', $readyIds);

        // Individual blocker counts
        $awaitingExamSlotCount = (clone $consultationPoolBase)
            ->whereDoesntHave('examAssignments', fn ($q) => $q->active())
            ->count();

        $examScheduledCount = (clone $consultationPoolBase)
            ->whereHas('examAssignments', fn ($q) => $q->active()->whereHas(
                'examSession',
                fn ($s) => $s->whereIn('status', [ExamSession::STATUS_DRAFT, ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS])
            ))->count();

        $examAbsentCount = (clone $consultationPoolBase)
            ->whereHas('examAssignments', fn ($q) => $q->active()->whereHas(
                'examSession',
                fn ($s) => $s->where('status', ExamSession::STATUS_COMPLETED)
            )->where(function ($sub): void {
                $sub->whereDoesntHave('attendanceRecord', fn ($a) => $a->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT))
                    ->orWhereDoesntHave('submissionRecord');
            }))->count();

        $awaitingScoringIds = $scoringCandidates
            ->filter(fn (AdmissionAttempt $attempt): bool => ! $scoring[$attempt->id]['ready'])
            ->filter(function (AdmissionAttempt $attempt): bool {
                $assignment = $attempt->examAssignments->first(fn (ExamAssignment $item) => $item->isActive());

                return $assignment !== null
                    && $assignment->examSession?->status === ExamSession::STATUS_COMPLETED
                    && $assignment->attendanceRecord?->outcome === ExamAttendanceRecord::OUTCOME_PRESENT
                    && $assignment->submissionRecord !== null;
            })
            ->modelKeys();
        $awaitingScoringCount = count($awaitingScoringIds);

        $readyCount = (clone $readyBase)->count();
        $notReadyCount = (clone $notReadyBase)->count();

        $blockers = [
            'awaiting_exam_slot' => $awaitingExamSlotCount,
            'exam_scheduled_or_in_progress' => $examScheduledCount,
            'exam_absent_or_unsubmitted' => $examAbsentCount,
            'awaiting_exam_scoring' => $awaitingScoringCount,
        ];

        // Narrow active query
        if ($readiness === 'ready') {
            $activeQuery = (clone $readyBase);
        } else {
            $activeQuery = (clone $consultationPoolBase);
            if ($poolBlocker === 'awaiting_exam_slot') {
                $activeQuery->whereDoesntHave('examAssignments', fn ($q) => $q->active());
            } elseif ($poolBlocker === 'exam_scheduled_or_in_progress') {
                $activeQuery->whereHas('examAssignments', fn ($q) => $q->active()->whereHas(
                    'examSession',
                    fn ($s) => $s->whereIn('status', [ExamSession::STATUS_DRAFT, ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS])
                ));
            } elseif ($poolBlocker === 'exam_absent_or_unsubmitted') {
                $activeQuery->whereHas('examAssignments', fn ($q) => $q->active()->whereHas(
                    'examSession',
                    fn ($s) => $s->where('status', ExamSession::STATUS_COMPLETED)
                )->where(function ($sub): void {
                    $sub->whereDoesntHave('attendanceRecord', fn ($a) => $a->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT))
                        ->orWhereDoesntHave('submissionRecord');
                }));
            } elseif ($poolBlocker === 'awaiting_exam_scoring') {
                $activeQuery->whereIn('id', $awaitingScoringIds);
            } else {
                $activeQuery = (clone $notReadyBase);
            }
        }

        $paginator = $this->paginateAndOrder(
            $activeQuery,
            $institutionId,
            $poolSearch,
            $poolProgram,
            $poolSort,
            $focusAttemptId,
            $filters,
        );

        $programsByCode = Program::query()
            ->where('institution_id', $institutionId)
            ->pluck('name', 'code')
            ->all();

        $data = [];
        foreach ($paginator->items() as $attempt) {
            /** @var AdmissionAttempt $attempt */
            $firstChoiceCode = $attempt->preferred_program_code ?: $attempt->program_preference_1_code;
            $fullName = $attempt->identity?->fullName()
                ?: trim(($attempt->payload['first_name'] ?? '').' '.($attempt->payload['last_name'] ?? ''));

            if ($readiness === 'ready') {
                $stageCode = 'ready_individual_scoring';
                $stageLabel = 'Ready · Scoring Complete';
                $stageTone = 'emerald';
                $blockingOffice = null;
                $detailHint = 'Individual scoring is complete';
                $canSelect = true;
            } else {
                $canSelect = false;
                $activeExam = $attempt->examAssignments->first(fn (ExamAssignment $a) => $a->isActive());

                if ($activeExam === null) {
                    $stageCode = 'awaiting_exam_slot';
                    $stageLabel = 'Not Ready · Awaiting Exam Slot';
                    $stageTone = 'slate';
                    $blockingOffice = 'Guidance / Testing';
                    $detailHint = 'No entrance examination schedule assigned';
                } else {
                    $session = $activeExam->examSession;
                    $status = $session?->status;

                    if (in_array($status, [ExamSession::STATUS_DRAFT, ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true)) {
                        $stageCode = 'exam_scheduled_or_in_progress';
                        $stageLabel = 'Not Ready · Exam Scheduled';
                        $stageTone = 'amber';
                        $blockingOffice = 'Testing / Test Administration';
                        $detailHint = 'Exam scheduled or in progress ('.$session->code.')';
                    } elseif ($status === ExamSession::STATUS_COMPLETED && (
                        $activeExam->attendanceRecord?->outcome !== ExamAttendanceRecord::OUTCOME_PRESENT || $activeExam->submissionRecord === null
                    )) {
                        $stageCode = 'exam_absent_or_unsubmitted';
                        $stageLabel = 'Not Ready · Exam Absent / Unsubmitted';
                        $stageTone = 'destructive';
                        $blockingOffice = 'Testing Office';
                        $detailHint = 'Examinee absent or answer sheet unsubmitted';
                    } else {
                        $stageCode = 'awaiting_exam_scoring';
                        $stageLabel = 'Not Ready · Individual Scoring Incomplete';
                        $stageTone = 'amber';
                        $blockingOffice = 'Guidance Scoring';
                        $detailHint = 'Individual score evidence is incomplete or stale';
                    }
                }
            }

            $data[] = [
                'attempt_id' => (int) $attempt->id,
                'reference_code' => (string) $attempt->reference_code,
                'full_name' => $fullName,
                'email' => $attempt->contact_email ?? $attempt->identity?->email,
                'strand' => $attempt->strand,
                'first_choice_program_code' => $firstChoiceCode,
                'first_choice_program_name' => $firstChoiceCode ? ($programsByCode[$firstChoiceCode] ?? null) : null,
                'accepted_at' => $attempt->accepted_at?->toIso8601String(),
                'submitted_at' => $attempt->created_at?->toIso8601String(),
                'readiness_bucket' => $readiness,
                'stage_code' => $stageCode,
                'stage_label' => $stageLabel,
                'stage_tone' => $stageTone,
                'blocking_office' => $blockingOffice,
                'detail_hint' => $detailHint,
                'can_select_for_assignment' => $canSelect,
            ];
        }

        return [
            'readiness' => $readiness,
            'counts' => [
                'ready' => $readyCount,
                'not_ready' => $notReadyCount,
                'blockers' => $blockers,
            ],
            'filters' => [
                'pool_search' => $poolSearch,
                'pool_program' => $poolProgram,
                'pool_blocker' => $poolBlocker,
                'pool_sort' => $poolSort,
            ],
            'focus_attempt_id' => $focusAttemptId,
            'applicants' => [
                'data' => $data,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
        ];
    }

    /**
     * Applies search, program, sort, and deterministic pagination with focus calculation.
     */
    private function paginateAndOrder(
        EloquentBuilder $query,
        int $institutionId,
        string $poolSearch,
        string $poolProgram,
        string $poolSort,
        ?int $focusAttemptId,
        array $filters,
    ): LengthAwarePaginator {
        if ($poolSearch !== '') {
            TokenizedInnerMatchScope::apply(
                $query,
                $poolSearch,
                [
                    'admission_attempts.reference_code',
                    'admission_attempts.contact_email',
                    'admission_attempts.strand',
                    'admission_attempts.last_school_enrolled',
                    'admission_attempts.preferred_program_code',
                    'admission_attempts.program_preference_1_code',
                    'admission_attempts.program_preference_2_code',
                    'admission_attempts.program_preference_3_code',
                    function (EloquentBuilder $sub, string $likePattern): void {
                        $sub->orWhereHas('identity', function (EloquentBuilder $iq) use ($likePattern): void {
                            $iq->where(function (EloquentBuilder $nameSub) use ($likePattern): void {
                                TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.first_name', $likePattern);
                                TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.middle_name', $likePattern);
                                TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.last_name', $likePattern);
                                TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.suffix', $likePattern);
                            });
                        });
                    },
                ],
            );
        }

        if ($poolProgram !== '') {
            $query->whereRaw('COALESCE(admission_attempts.preferred_program_code, admission_attempts.program_preference_1_code) = ?', [$poolProgram]);
        }

        if ($poolSort === 'alphabetical') {
            $query->orderByRaw('(SELECT LOWER(COALESCE(last_name, \'\')) || \' \' || LOWER(COALESCE(first_name, \'\')) FROM applicant_identities WHERE applicant_identities.id = admission_attempts.applicant_identity_id) ASC, admission_attempts.id ASC');
        } elseif ($poolSort === 'program') {
            $query->orderByRaw('COALESCE(admission_attempts.preferred_program_code, admission_attempts.program_preference_1_code, \'\') ASC')->orderBy('admission_attempts.id', 'asc');
        } else {
            $query->orderBy('admission_attempts.accepted_at', 'asc')->orderBy('admission_attempts.id', 'asc');
        }

        $targetPage = max(1, (int) ($filters['pool_page'] ?? 1));
        if ($focusAttemptId !== null && $focusAttemptId > 0) {
            $allIds = (clone $query)->pluck('admission_attempts.id')->all();
            $idx = array_search($focusAttemptId, $allIds, true);
            if ($idx !== false) {
                $targetPage = intdiv((int) $idx, 25) + 1;
            }
        }

        return $query
            ->with([
                'identity',
                'academicCycle',
                'examAssignments.examSession.gradingSession',
                'examAssignments.attendanceRecord',
                'examAssignments.submissionRecord',
                'resultDocuments',
            ])
            ->paginate(25, ['*'], 'pool_page', $targetPage);
    }
}
