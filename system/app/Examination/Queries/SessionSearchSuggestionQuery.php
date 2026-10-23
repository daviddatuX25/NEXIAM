<?php

namespace App\Examination\Queries;

use App\Admission\Support\ApplicantReference;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SessionSearchSuggestionQuery
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  array{q: string, academic_cycle_id: int|string, domain_context: string, tab?: string|null, status?: string|null, date?: string|null, room_id?: int|string|null, exam_session_id?: int|string|null, grading_session_id?: int|string|null, lookup_scope?: string|null, destination_kind?: string|null}  $filters
     * @return array{q: string, academic_cycle_id: int, domain_context: string, matches_truncated: bool, items: list<array<string, mixed>>}
     */
    public function handle(User $actor, array $filters, ?string $requestId = null): array
    {
        $principal = User::query()->find($actor->getKey());
        if ($principal === null) {
            throw new AuthorizationException('Search access is unavailable.');
        }

        $scope = $principal->actorScope();
        if ($scope->institutionId === null) {
            throw new AuthorizationException('Search access is unavailable.');
        }

        $domainContext = $filters['domain_context'];
        if (! in_array($domainContext, ['monitoring', 'grading', 'scheduling', 'consultation_scheduling'], true)) {
            throw new AuthorizationException('Search access is unavailable.');
        }

        try {
            $institution = Institution::current();
        } catch (ModelNotFoundException) {
            throw new AuthorizationException('Search access is unavailable.');
        }
        if ($scope->institutionId !== (int) $institution->getKey()) {
            throw new AuthorizationException('Search access is unavailable.');
        }

        if ($domainContext === 'scheduling' || $domainContext === 'consultation_scheduling') {
            $cycle = AcademicCycle::query()
                ->whereKey($filters['academic_cycle_id'])
                ->where('institution_id', $institution->getKey())
                ->first();
            if ($cycle === null) {
                throw new AuthorizationException('Search access is unavailable.');
            }
        } else {
            $cycles = AcademicCycle::query()
                ->where('institution_id', $institution->getKey())
                ->where('is_active', true)
                ->limit(2)
                ->get();
            if ($cycles->count() !== 1) {
                throw new AuthorizationException('Search access is unavailable.');
            }
            $cycle = $cycles->first();
            if ($cycle === null
                || $scope->activeCycleCode !== $cycle->code
                || (int) $filters['academic_cycle_id'] !== (int) $cycle->getKey()) {
                throw new AuthorizationException('Search access is unavailable.');
            }
        }

        $capability = match ($domainContext) {
            'monitoring', 'scheduling' => CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            'grading' => CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
            'consultation_scheduling' => CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
        };

        $capabilityContext = [
            'institution_id' => (int) $institution->getKey(),
            'office' => 'guidance',
        ];
        if ($domainContext !== 'scheduling' && $domainContext !== 'consultation_scheduling') {
            $capabilityContext['cycle'] = $cycle->code;
        }
        if (! $this->authorizer->allows($principal, $capability, $capabilityContext)) {
            throw new AuthorizationException('Search access is unavailable.');
        }
        $tokens = preg_split('/\s+/u', trim($filters['q']), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($domainContext === 'consultation_scheduling') {
            $items = [
                ...$this->consultationApplicantItems($cycle, $institution, $tokens, $filters['q'], $filters),
                ...$this->consultationSessionItems($cycle, $tokens, $filters),
            ];
        } else {
            $parents = $this->parentSessions($cycle, $domainContext, $filters);
            $items = $this->examineeItems($parents, $tokens, $filters['q'], $domainContext, $cycle, $institution, $filters['destination_kind'] ?? null);
            $items = [...$items, ...$this->sessionItems($parents, $tokens, $domainContext)];
            if ($domainContext === 'scheduling') {
                $items = [...$items, ...$this->examWaitlistApplicantItems($cycle, $institution, $tokens, $filters['q'])];
            }
        }

        usort($items, static function (SessionSearchSuggestion $left, SessionSearchSuggestion $right): int {
            $leftRank = self::rank($left);
            $rightRank = self::rank($right);

            return [$leftRank, $left->examSessionId, $left->assignmentId ?? 0, $left->attemptId ?? 0, $left->key]
                <=> [$rightRank, $right->examSessionId, $right->assignmentId ?? 0, $right->attemptId ?? 0, $right->key];
        });

        $items = array_slice($items, 0, 9);
        $matchesTruncated = count($items) > 8;
        $projected = array_map(static fn (SessionSearchSuggestion $item): array => $item->toArray(), array_slice($items, 0, 8));

        $this->recordAuditEvent->handle('exam.session_search_suggestions_viewed', [
            'purpose' => match ($domainContext) {
                'monitoring' => 'monitoring_lookup',
                'scheduling' => 'scheduling_lookup',
                'consultation_scheduling' => 'consultation_scheduling_lookup',
                default => 'grading_lookup',
            },
            'institution_id' => (int) $institution->getKey(),
            'academic_cycle_id' => (int) $cycle->getKey(),
            'domain_context' => $domainContext,
            'request_id' => $requestId,
            'resource_ids' => array_values(array_unique(array_filter(array_map(
                static fn (SessionSearchSuggestion $item): ?int => $item->examSessionId > 0 ? $item->examSessionId : null,
                array_slice($items, 0, 8),
            )))),
            'assignment_ids' => array_values(array_filter(array_map(
                static fn (SessionSearchSuggestion $item): ?int => $item->assignmentId,
                array_slice($items, 0, 8),
            ))),
            'attempt_ids' => array_values(array_filter(array_map(
                static fn (SessionSearchSuggestion $item): ?int => $item->attemptId,
                array_slice($items, 0, 8),
            ))),
            'authorized_result_count' => count($projected),
            'matches_truncated' => $matchesTruncated,
        ], (string) $principal->getKey());

        return [
            'q' => $filters['q'],
            'academic_cycle_id' => (int) $cycle->getKey(),
            'domain_context' => $domainContext,
            'matches_truncated' => $matchesTruncated,
            'items' => $projected,
        ];
    }

    /** @return EloquentBuilder<ExamSession> */
    private function parentSessions(
        AcademicCycle $cycle,
        string $domainContext,
        array $filters,
    ): EloquentBuilder {
        $query = ExamSession::query()
            ->forWorkspace()
            ->where('academic_cycle_id', $cycle->getKey());

        if ($domainContext === 'scheduling') {
            $allStatuses = [
                ExamSession::STATUS_DRAFT,
                ExamSession::STATUS_PUBLISHED,
                ExamSession::STATUS_IN_PROGRESS,
                ExamSession::STATUS_COMPLETED,
                ExamSession::STATUS_CANCELLED,
            ];
            if (($filters['status'] ?? null) === 'active' || trim((string) ($filters['status'] ?? '')) === '') {
                $query->where('status', ExamSession::STATUS_DRAFT);
            } elseif (in_array($filters['status'] ?? null, $allStatuses, true)) {
                $query->where('status', $filters['status']);
            } else {
                $query->whereIn('status', $allStatuses);
            }
        } elseif ($domainContext === 'monitoring') {
            $statuses = ($filters['tab'] ?? 'active') === 'history'
                ? [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_CANCELLED]
                : [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS];
            $query->whereIn('status', $statuses);
        } else {
            $gradingStatus = trim((string) ($filters['status'] ?? ''));
            if ($gradingStatus === '' && (isset($filters['exam_session_id']) || isset($filters['grading_session_id']))) {
                $gradingStatus = 'all';
            } elseif ($gradingStatus === '' && ($filters['tab'] ?? null) === 'history') {
                $gradingStatus = GradingSession::STATUS_FINALIZED;
            }
            if ($gradingStatus === '' || $gradingStatus === 'active') {
                $query->where('status', ExamSession::STATUS_COMPLETED)
                    ->where(function (EloquentBuilder $unfinished): void {
                        $unfinished->whereDoesntHave('gradingSession')
                            ->orWhereHas('gradingSession', fn (EloquentBuilder $grading): EloquentBuilder => $grading->whereIn('status', [GradingSession::STATUS_OPEN, GradingSession::STATUS_REVIEW]));
                    });
            } elseif ($gradingStatus === 'repair_required') {
                $query->where('status', ExamSession::STATUS_COMPLETED)->whereDoesntHave('gradingSession');
            } elseif ($gradingStatus === 'all') {
                $query->where('status', ExamSession::STATUS_COMPLETED);
            } else {
                $query->where('status', ExamSession::STATUS_COMPLETED)
                    ->whereHas('gradingSession', fn (EloquentBuilder $grading): EloquentBuilder => $grading->where('status', $gradingStatus));
            }
            $examSessionId = $filters['exam_session_id'] ?? null;
            if ($examSessionId === null && isset($filters['grading_session_id'])) {
                $examSessionId = ExamSession::query()
                    ->whereHas('gradingSession', fn (EloquentBuilder $grading): EloquentBuilder => $grading->whereKey((int) $filters['grading_session_id']))
                    ->value('id');
            }
            if (($filters['lookup_scope'] ?? null) === 'current_session' && $examSessionId !== null) {
                $query->whereKey((int) $examSessionId);
            }
        }

        if (isset($filters['date']) && $filters['date'] !== '') {
            $query->whereDate('starts_at', $filters['date']);
        }
        if (isset($filters['room_id']) && $filters['room_id'] !== '') {
            $query->where('room_id', (int) $filters['room_id']);
        }

        return $query;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<SessionSearchSuggestion>
     */
    private function examineeItems(
        EloquentBuilder $parents,
        array $tokens,
        string $rawQuery,
        string $domainContext,
        AcademicCycle $cycle,
        Institution $institution,
        ?string $destinationKind,
    ): array {
        $parentIds = (clone $parents)->select('exam_sessions.id');
        $attempts = AdmissionAttempt::query()->forWorkspace()
            ->where('institution_id', $institution->getKey())
            ->where('academic_cycle_id', $cycle->getKey())
            ->select('admission_attempts.id');
        $identities = ApplicantIdentity::query()->forWorkspace()
            ->where('institution_id', $institution->getKey())
            ->select('applicant_identities.id');
        $query = DB::table('exam_assignments as ea')
            ->join('admission_attempts as aa', 'aa.id', '=', 'ea.admission_attempt_id')
            ->join('applicant_identities as ai', 'ai.id', '=', 'aa.applicant_identity_id')
            ->join('exam_sessions as es', 'es.id', '=', 'ea.exam_session_id')
            ->leftJoin('rooms as room', 'room.id', '=', 'es.room_id')
            ->whereIn('ea.exam_session_id', $parentIds)
            ->whereIn('aa.id', $attempts->whereColumn('admission_attempts.id', 'ea.admission_attempt_id'))
            ->whereIn('ai.id', $identities->whereColumn('applicant_identities.id', 'aa.applicant_identity_id'))
            ->whereIn('ea.status', ExamAssignment::ACTIVE_STATUSES);

        if ($domainContext === 'grading') {
            $query->join('grading_sessions as gs', 'gs.exam_session_id', '=', 'es.id');
            $query->whereExists(function (Builder $attendance): void {
                $attendance->selectRaw('1')->from('exam_attendance_records as ear')
                    ->whereColumn('ear.exam_assignment_id', 'ea.id')
                    ->where('ear.outcome', 'present');
            })->whereExists(function (Builder $submission): void {
                $submission->selectRaw('1')->from('exam_submission_records as esr')
                    ->whereColumn('esr.exam_assignment_id', 'ea.id');
            });
        } else {
            $query->leftJoin('grading_sessions as gs', 'gs.exam_session_id', '=', 'es.id');
        }

        $escapedTokens = array_map(self::likeValue(...), $tokens);
        $query->where(function (Builder $match) use ($escapedTokens, $tokens): void {
            $match->where(function (Builder $reference) use ($escapedTokens): void {
                foreach ($escapedTokens as $token) {
                    $reference->whereRaw('LOWER(aa.reference_code) LIKE LOWER(?)', ['%'.$token.'%']);
                }
            });
            foreach ($tokens as $token) {
                if (ApplicantReference::extractSequence($token) !== null) {
                    $match->orWhereIn(DB::raw('UPPER(aa.reference_code)'), ApplicantReference::candidateCodes($token));
                }
            }
            $match->orWhere(function (Builder $name) use ($escapedTokens): void {
                foreach ($escapedTokens as $token) {
                    $name->where(function (Builder $field) use ($token): void {
                        $field->whereRaw('LOWER(ai.first_name) LIKE LOWER(?)', ['%'.$token.'%'])
                            ->orWhereRaw('LOWER(ai.middle_name) LIKE LOWER(?)', ['%'.$token.'%'])
                            ->orWhereRaw('LOWER(ai.last_name) LIKE LOWER(?)', ['%'.$token.'%']);
                    });
                }
            });
        });
        $query->select([
            'ea.id as assignment_id', 'aa.id as attempt_id', 'aa.reference_code',
            'ai.first_name', 'ai.middle_name', 'ai.last_name',
            'es.id as exam_session_id', 'es.code as session_code', 'es.status as session_status',
            'es.starts_at', 'es.effective_end_at as ends_at', 'gs.id as grading_session_id',
            DB::raw("COALESCE(room.name, room.code, '') as room_label"),
        ])->selectRaw('CASE WHEN lower(aa.reference_code) = lower(?) THEN 0 WHEN '.implode(' AND ', array_fill(0, count($escapedTokens), 'LOWER(aa.reference_code) LIKE LOWER(?)')).' THEN 1 ELSE 2 END as match_rank', [$rawQuery, ...array_map(static fn (string $token): string => '%'.$token.'%', $escapedTokens)])
            ->orderBy('match_rank')->orderBy('es.id')->orderBy('ea.id')->limit(9);
        $rows = $query->get();
        $items = [];
        foreach ($rows as $row) {
            $name = trim(implode(' ', array_filter([$row->first_name, $row->middle_name, $row->last_name])));
            $referenceExact = strcasecmp((string) $row->reference_code, $rawQuery) === 0;
            $referenceMatch = $referenceExact || collect($tokens)->every(fn (string $token): bool => stripos((string) $row->reference_code, $token) !== false);
            $matchedFields = $referenceExact ? ['exact_reference_code'] : ($referenceMatch ? ['reference_code'] : []);
            if ($this->nameMatches($row, $tokens)) {
                $matchedFields[] = 'name';
            }
            $session = (int) $row->exam_session_id;
            $assignment = (int) $row->assignment_id;
            $attempt = (int) $row->attempt_id;
            $gradingSessionId = $row->grading_session_id === null ? null : (int) $row->grading_session_id;
            if ($domainContext === 'scheduling') {
                $url = route('exams.sessions.show', ['session' => $session]).'?focus_assignment_id='.$assignment;
                $destination = [
                    'url' => $url,
                    'route_name' => 'exams.sessions.show',
                    'focus_kind' => 'assignment',
                    'focus_id' => $assignment,
                ];
            } elseif ($domainContext === 'monitoring') {
                $url = route('exams.sessions.monitoring', ['session' => $session]).'?focus_assignment_id='.$assignment;
                $destination = [
                    'url' => $url,
                    'focus_kind' => 'assignment',
                    'focus_id' => $assignment,
                ];
            } else {
                $rosterUrl = '/scoring/sessions/'.$session.'?focus_attempt_id='.$attempt;
                $scoreUrl = '/scoring/sessions/'.$session.'/attempts/'.$attempt.'/scores';
                $destination = [
                    'url' => $destinationKind === 'score_entry' ? $scoreUrl : $rosterUrl,
                    'focus_kind' => 'attempt',
                    'focus_id' => $attempt,
                    'score_url' => $scoreUrl,
                    'roster_url' => $rosterUrl,
                ];
            }
            $items[] = new SessionSearchSuggestion(
                key: 'examinee:'.$assignment,
                kind: 'examinee',
                assignmentId: $assignment,
                attemptId: $attempt,
                referenceCode: (string) $row->reference_code,
                name: $name,
                examSessionId: $session,
                sessionCode: (string) $row->session_code,
                sessionStatus: (string) $row->session_status,
                roomLabel: (string) $row->room_label,
                startsAt: self::formatDate($row->starts_at),
                endsAt: self::formatDate($row->ends_at),
                gradingSessionId: $gradingSessionId,
                matchedFields: $matchedFields,
                destination: $destination,
                pipelineState: $domainContext === 'scheduling' ? 'scheduled' : null,
                pipelineBadgeLabel: $domainContext === 'scheduling' ? 'Scheduled · '.(string) $row->session_code : null,
                pipelineBadgeTone: $domainContext === 'scheduling' ? 'primary' : null,
                targetTab: $domainContext === 'scheduling' ? 'sessions' : null,
                readinessBucket: null,
            );
        }

        return $items;
    }

    /**
     * Searches unassigned waitlist and pipeline applicants for Exam Scheduling.
     *
     * @param  list<string>  $tokens
     * @return list<SessionSearchSuggestion>
     */
    private function examWaitlistApplicantItems(
        AcademicCycle $cycle,
        Institution $institution,
        array $tokens,
        string $rawQuery,
    ): array {
        if ($tokens === []) {
            return [];
        }

        $query = AdmissionAttempt::query()
            ->forWorkspace()
            ->where('institution_id', $institution->getKey())
            ->where('academic_cycle_id', $cycle->getKey())
            ->whereIn('status', [
                AdmissionAttempt::STATUS_ACCEPTED,
                AdmissionAttempt::STATUS_PENDING,
                AdmissionAttempt::STATUS_NEEDS_CORRECTION,
                'submitted',
            ])
            ->whereDoesntHave('examAssignments', fn (EloquentBuilder $q) => $q->blockingScheduling());

        TokenizedInnerMatchScope::apply($query, $rawQuery, [
            'admission_attempts.reference_code',
            function (EloquentBuilder $sub, string $pattern): void {
                $sub->orWhereHas('identity', function (EloquentBuilder $iq) use ($pattern): void {
                    $iq->where(function (EloquentBuilder $nameSub) use ($pattern): void {
                        TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.first_name', $pattern);
                        TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.middle_name', $pattern);
                        TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.last_name', $pattern);
                    });
                });
            },
        ]);

        $attempts = $query->with('identity')->limit(9)->get();
        $items = [];

        foreach ($attempts as $attempt) {
            $name = $attempt->identity?->fullName()
                ?: trim(($attempt->payload['first_name'] ?? '').' '.($attempt->payload['last_name'] ?? ''));

            $referenceExact = strcasecmp((string) $attempt->reference_code, $rawQuery) === 0;
            $referenceMatch = $referenceExact || collect($tokens)->every(fn (string $token): bool => stripos((string) $attempt->reference_code, $token) !== false);
            $matchedFields = $referenceExact ? ['exact_reference_code'] : ($referenceMatch ? ['reference_code'] : []);
            if ($attempt->identity !== null && $this->identityNameMatches($attempt->identity, $tokens)) {
                $matchedFields[] = 'name';
            }

            if ($attempt->status === AdmissionAttempt::STATUS_ACCEPTED) {
                $kind = 'waitlist_applicant';
                $pipelineState = 'waitlist_ready';
                $badgeLabel = 'Waitlist · Ready';
                $badgeTone = 'emerald';
                $readinessBucket = 'ready';
                $destinationUrl = route('exams.sessions.waitlist', [
                    'readiness' => 'ready',
                    'academic_cycle_id' => $cycle->getKey(),
                    'focus_attempt_id' => $attempt->id,
                ]);
            } else {
                $kind = 'pipeline_applicant';
                $pipelineState = 'not_ready';
                $badgeLabel = $attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION
                    ? 'Not Ready · Correction'
                    : 'Not Ready · Registrar';
                $badgeTone = 'amber';
                $readinessBucket = 'not_ready';
                $destinationUrl = route('exams.sessions.waitlist', [
                    'readiness' => 'not_ready',
                    'academic_cycle_id' => $cycle->getKey(),
                    'focus_attempt_id' => $attempt->id,
                ]);
            }

            $items[] = new SessionSearchSuggestion(
                key: 'waitlist:'.$attempt->id,
                kind: $kind,
                assignmentId: null,
                attemptId: (int) $attempt->id,
                referenceCode: (string) $attempt->reference_code,
                name: $name,
                examSessionId: 0,
                sessionCode: '',
                sessionStatus: '',
                roomLabel: '',
                startsAt: null,
                endsAt: null,
                gradingSessionId: null,
                matchedFields: $matchedFields,
                destination: [
                    'url' => $destinationUrl,
                    'focus_kind' => 'attempt',
                    'focus_id' => (int) $attempt->id,
                    'route_name' => 'exams.sessions.waitlist',
                ],
                pipelineState: $pipelineState,
                pipelineBadgeLabel: $badgeLabel,
                pipelineBadgeTone: $badgeTone,
                targetTab: 'waitlist',
                readinessBucket: $readinessBucket,
            );
        }

        return $items;
    }

    /**
     * Searches consultation sessions for Consultation Scheduling.
     *
     * @param  list<string>  $tokens
     * @return list<SessionSearchSuggestion>
     */
    private function consultationSessionItems(AcademicCycle $cycle, array $tokens, array $filters = []): array
    {
        if ($tokens === []) {
            return [];
        }

        $allStatuses = [
            ConsultationSession::STATUS_DRAFT,
            ConsultationSession::STATUS_PUBLISHED,
            ConsultationSession::STATUS_IN_PROGRESS,
            ConsultationSession::STATUS_COMPLETED,
            ConsultationSession::STATUS_CANCELLED,
        ];

        $query = ConsultationSession::query()
            ->where('academic_cycle_id', $cycle->getKey())
            ->with('room');

        $statusFilter = trim((string) ($filters['status'] ?? ''));
        if ($statusFilter === '' || $statusFilter === 'active') {
            $query->where('consultation_sessions.status', ConsultationSession::STATUS_DRAFT);
        } elseif (in_array($statusFilter, $allStatuses, true)) {
            $query->where('consultation_sessions.status', $statusFilter);
        } else {
            $query->whereIn('consultation_sessions.status', $allStatuses);
        }
        foreach ($tokens as $token) {
            $like = '%'.self::likeValue($token).'%';
            $query->where(function (EloquentBuilder $match) use ($like): void {
                $match->whereRaw('LOWER(consultation_sessions.code) LIKE LOWER(?)', [$like])
                    ->orWhereHas('room', function (EloquentBuilder $rq) use ($like): void {
                        $rq->whereRaw('LOWER(rooms.name) LIKE LOWER(?)', [$like])
                            ->orWhereRaw('LOWER(rooms.code) LIKE LOWER(?)', [$like]);
                    });
            });
        }

        $escapedTokens = array_map(self::likeValue(...), $tokens);
        $matchRankBindings = [
            implode(' ', $tokens),
            ...array_map(static fn (string $token): string => '%'.$token.'%', $escapedTokens),
        ];

        $rows = $query->select('consultation_sessions.*')
            ->selectRaw(
                'CASE WHEN lower(consultation_sessions.code) = lower(?) THEN 0 WHEN '.implode(' AND ', array_fill(0, count($escapedTokens), 'LOWER(consultation_sessions.code) LIKE LOWER(?)')).' THEN 1 ELSE 2 END as match_rank',
                $matchRankBindings,
            )
            ->orderBy('match_rank')
            ->orderBy('consultation_sessions.id')
            ->limit(9)
            ->get();
        $items = [];

        foreach ($rows as $session) {
            $roomLabel = (string) ($session->room?->name ?: $session->room?->code ?: '');
            $items[] = new SessionSearchSuggestion(
                key: 'session:'.$session->id,
                kind: 'session',
                assignmentId: null,
                attemptId: null,
                referenceCode: null,
                name: null,
                examSessionId: (int) $session->id,
                sessionCode: (string) $session->code,
                sessionStatus: (string) $session->status,
                roomLabel: $roomLabel,
                startsAt: self::formatDate($session->scheduled_at),
                endsAt: null,
                gradingSessionId: null,
                matchedFields: ['session_code'],
                destination: [
                    'url' => route('consultation.sessions.show', ['session' => $session->id]),
                    'focus_kind' => null,
                    'focus_id' => null,
                    'route_name' => 'consultation.sessions.show',
                ],
            );
        }

        return $items;
    }

    /**
     * Searches examinees, waitlist, and upstream pipeline applicants for Consultation Scheduling across all 3 states.
     *
     * @param  list<string>  $tokens
     * @return list<SessionSearchSuggestion>
     */
    private function consultationApplicantItems(
        AcademicCycle $cycle,
        Institution $institution,
        array $tokens,
        string $rawQuery,
        array $filters = [],
    ): array {
        if ($tokens === []) {
            return [];
        }

        $query = AdmissionAttempt::query()
            ->forWorkspace()
            ->where('institution_id', $institution->getKey())
            ->where('academic_cycle_id', $cycle->getKey())
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->whereDoesntHave('consultationDecisions', fn (EloquentBuilder $query) => $query->whereIn('decision_type', ['approve', 'modify', 'override']));

        TokenizedInnerMatchScope::apply($query, $rawQuery, [
            'admission_attempts.reference_code',
            function (EloquentBuilder $sub, string $pattern): void {
                $sub->orWhereHas('identity', function (EloquentBuilder $iq) use ($pattern): void {
                    $iq->where(function (EloquentBuilder $nameSub) use ($pattern): void {
                        TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.first_name', $pattern);
                        TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.middle_name', $pattern);
                        TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.last_name', $pattern);
                    });
                });
            },
        ]);

        $escapedTokens = array_map(self::likeValue(...), $tokens);
        $matchRankBindings = [
            $rawQuery,
            ...array_map(static fn (string $token): string => '%'.$token.'%', $escapedTokens),
        ];

        $attempts = $query->with([
            'identity',
            'consultationAssignments.consultationSession',
            'examAssignments.examSession.gradingSession',
            'resultDocuments',
        ])->select('admission_attempts.*')
            ->selectRaw(
                'CASE WHEN lower(admission_attempts.reference_code) = lower(?) THEN 0 WHEN '.implode(' AND ', array_fill(0, count($escapedTokens), 'LOWER(admission_attempts.reference_code) LIKE LOWER(?)')).' THEN 1 ELSE 2 END as match_rank',
                $matchRankBindings,
            )
            ->orderBy('match_rank')
            ->orderBy('admission_attempts.id')
            ->limit(9)
            ->get();

        $items = [];

        foreach ($attempts as $attempt) {
            $name = $attempt->identity?->fullName()
                ?: trim(($attempt->payload['first_name'] ?? '').' '.($attempt->payload['last_name'] ?? ''));

            $referenceExact = strcasecmp((string) $attempt->reference_code, $rawQuery) === 0;
            $referenceMatch = $referenceExact || collect($tokens)->every(fn (string $token): bool => stripos((string) $attempt->reference_code, $token) !== false);
            $matchedFields = $referenceExact ? ['exact_reference_code'] : ($referenceMatch ? ['reference_code'] : []);
            if ($attempt->identity !== null && $this->identityNameMatches($attempt->identity, $tokens)) {
                $matchedFields[] = 'name';
            }

            $activeCa = $attempt->consultationAssignments->first(
                fn (ConsultationAssignment $ca): bool => in_array($ca->status, ConsultationAssignment::ACTIVE_STATUSES, true)
            );

            if ($activeCa !== null) {
                $session = $activeCa->consultationSession;
                $statusFilter = trim((string) ($filters['status'] ?? ''));
                $sessionMatchesScope = match (true) {
                    $statusFilter === '' || $statusFilter === 'active' => $session?->status === ConsultationSession::STATUS_DRAFT,
                    $statusFilter === 'all' => true,
                    default => $session?->status === $statusFilter,
                };
                if (! $sessionMatchesScope) {
                    continue;
                }
                $sessionCode = $session?->code ?? 'Session';
                $destUrl = route('consultation.sessions.show', ['session' => $activeCa->consultation_session_id]).'?focus_assignment_id='.$activeCa->id;

                $items[] = new SessionSearchSuggestion(
                    key: 'examinee:'.$activeCa->id,
                    kind: 'examinee',
                    assignmentId: (int) $activeCa->id,
                    attemptId: (int) $attempt->id,
                    referenceCode: (string) $attempt->reference_code,
                    name: $name,
                    examSessionId: (int) $activeCa->consultation_session_id,
                    sessionCode: $sessionCode,
                    sessionStatus: (string) ($session?->status ?? ''),
                    roomLabel: '',
                    startsAt: self::formatDate($session?->scheduled_at),
                    endsAt: null,
                    gradingSessionId: null,
                    matchedFields: $matchedFields,
                    destination: [
                        'url' => $destUrl,
                        'focus_kind' => 'assignment',
                        'focus_id' => (int) $activeCa->id,
                        'route_name' => 'consultation.sessions.show',
                    ],
                    pipelineState: 'scheduled',
                    pipelineBadgeLabel: 'Scheduled · '.$sessionCode,
                    pipelineBadgeTone: 'primary',
                    targetTab: 'sessions',
                    readinessBucket: null,
                );
            } else {
                $hasGradedExam = $attempt->examAssignments->contains(
                    fn (ExamAssignment $ea): bool => $ea->isActive() && $ea->examSession?->gradingSession?->status === GradingSession::STATUS_FINALIZED
                );
                $hasReadyResultDoc = $attempt->resultDocuments->contains(
                    fn (ResultDocument $rd): bool => $rd->status === ResultDocument::STATUS_READY
                );
                $isReady = $hasGradedExam && $hasReadyResultDoc;

                if ($isReady) {
                    $kind = 'waitlist_applicant';
                    $pipelineState = 'waitlist_ready';
                    $badgeLabel = 'Waitlist · Ready';
                    $badgeTone = 'emerald';
                    $readinessBucket = 'ready';
                    $destUrl = route('consultation.sessions.waitlist', [
                        'readiness' => 'ready',
                        'academic_cycle_id' => $cycle->getKey(),
                        'focus_attempt_id' => $attempt->id,
                    ]);
                } else {
                    $kind = 'pipeline_applicant';
                    $pipelineState = 'not_ready';
                    $badgeLabel = 'Not Ready · Upstream';
                    $badgeTone = 'amber';
                    $readinessBucket = 'not_ready';
                    $destUrl = route('consultation.sessions.waitlist', [
                        'readiness' => 'not_ready',
                        'academic_cycle_id' => $cycle->getKey(),
                        'focus_attempt_id' => $attempt->id,
                    ]);
                }

                $items[] = new SessionSearchSuggestion(
                    key: 'waitlist:'.$attempt->id,
                    kind: $kind,
                    assignmentId: null,
                    attemptId: (int) $attempt->id,
                    referenceCode: (string) $attempt->reference_code,
                    name: $name,
                    examSessionId: 0,
                    sessionCode: '',
                    sessionStatus: '',
                    roomLabel: '',
                    startsAt: null,
                    endsAt: null,
                    gradingSessionId: null,
                    matchedFields: $matchedFields,
                    destination: [
                        'url' => $destUrl,
                        'focus_kind' => 'attempt',
                        'focus_id' => (int) $attempt->id,
                        'route_name' => 'consultation.sessions.waitlist',
                    ],
                    pipelineState: $pipelineState,
                    pipelineBadgeLabel: $badgeLabel,
                    pipelineBadgeTone: $badgeTone,
                    targetTab: 'waitlist',
                    readinessBucket: $readinessBucket,
                );
            }
        }

        return $items;
    }

    /** @param list<string> $tokens @return list<SessionSearchSuggestion> */
    private function sessionItems(EloquentBuilder $parents, array $tokens, string $domainContext): array
    {
        $query = DB::table('exam_sessions as es')
            ->leftJoin('rooms as room', 'room.id', '=', 'es.room_id')
            ->whereIn('es.id', (clone $parents)->select('exam_sessions.id'));
        $query->leftJoin('grading_sessions as gs', 'gs.exam_session_id', '=', 'es.id');
        foreach ($tokens as $token) {
            $like = '%'.self::likeValue($token).'%';
            $query->where(function (Builder $match) use ($like): void {
                $match->whereRaw('LOWER(es.code) LIKE LOWER(?)', [$like])
                    ->orWhereRaw('LOWER(room.name) LIKE LOWER(?)', [$like])
                    ->orWhereRaw('LOWER(room.code) LIKE LOWER(?)', [$like]);
            });
        }
        $query->select([
            'es.id as exam_session_id', 'es.code as session_code', 'es.status as session_status',
            'es.starts_at', 'es.effective_end_at as ends_at', 'gs.id as grading_session_id',
            'room.name as room_name', 'room.code as room_code',
            DB::raw("COALESCE(room.name, room.code, '') as room_label"),
        ])->orderBy('es.id')->limit(9);

        $items = [];
        foreach ($query->get() as $row) {
            $session = (int) $row->exam_session_id;
            $gradingSessionId = $row->grading_session_id === null ? null : (int) $row->grading_session_id;
            if ($domainContext === 'scheduling') {
                $url = route('exams.sessions.show', ['session' => $session]);
                $destination = [
                    'url' => $url,
                    'route_name' => 'exams.sessions.show',
                    'focus_kind' => null,
                    'focus_id' => null,
                ];
            } elseif ($domainContext === 'monitoring') {
                $url = route('exams.sessions.monitoring', ['session' => $session]);
                $destination = ['url' => $url];
            } else {
                $url = '/scoring/sessions/'.$session;
                $destination = ['url' => $url];
            }
            $items[] = new SessionSearchSuggestion(
                key: 'session:'.$session,
                kind: 'session',
                assignmentId: null,
                attemptId: null,
                referenceCode: null,
                name: null,
                examSessionId: $session,
                sessionCode: (string) $row->session_code,
                sessionStatus: (string) $row->session_status,
                roomLabel: (string) $row->room_label,
                startsAt: self::formatDate($row->starts_at),
                endsAt: self::formatDate($row->ends_at),
                gradingSessionId: $gradingSessionId,
                matchedFields: $this->matchedSessionFields($row, $tokens),
                destination: $domainContext === 'scheduling' ? $destination : [...$destination, 'focus_kind' => null, 'focus_id' => null],
            );
        }

        return $items;
    }

    /** @param list<string> $tokens @return list<string> */
    private function matchedSessionFields(object $row, array $tokens): array
    {
        $fields = [];
        foreach ([
            'session_code' => (string) $row->session_code,
            'room_name' => (string) $row->room_name,
            'room_code' => (string) $row->room_code,
        ] as $field => $value) {
            foreach ($tokens as $token) {
                if (stripos($value, $token) !== false) {
                    $fields[] = $field;
                    break;
                }
            }
        }

        return $fields;
    }

    private function nameMatches(object $row, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (stripos((string) $row->first_name, $token) === false
                && stripos((string) $row->middle_name, $token) === false
                && stripos((string) $row->last_name, $token) === false) {
                return false;
            }
        }

        return true;
    }

    private function identityNameMatches(ApplicantIdentity $identity, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (stripos((string) $identity->first_name, $token) === false
                && stripos((string) $identity->middle_name, $token) === false
                && stripos((string) $identity->last_name, $token) === false) {
                return false;
            }
        }

        return true;
    }

    private static function rank(SessionSearchSuggestion $item): int
    {
        if ($item->kind === 'session') {
            return 5;
        }
        if (in_array('exact_reference_code', $item->matchedFields, true)) {
            return 0;
        }
        if (in_array('reference_code', $item->matchedFields, true)) {
            return 1;
        }
        if ($item->pipelineState === 'scheduled') {
            return 2;
        }
        if ($item->pipelineState === 'waitlist_ready') {
            return 3;
        }

        return 4;
    }

    private static function likeValue(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function formatDate(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }
}
