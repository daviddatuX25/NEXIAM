<?php

namespace App\Examination\Queries;

use App\Examination\Support\ExamSessionPresenter;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Support\Search\ModularSortScope;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Canonical ExamSession read query with authoritative SQL aggregates,
 * mandatory multi-tenant relational fencing, and dual Inertia / Xiam-safe projections.
 */
class ExamSessionListQuery
{
    public function __construct(private readonly ExamSessionPresenter $presenter) {}

    /**
     * Authoritative typed read query with mandatory tenant scope.
     *
     * @param  array<string, mixed>  $input
     */
    public function query(array $input, int $institutionId, int $limit = 100, bool $schedulingOnly = false): ExamSessionQueryResult
    {
        $rawSearch = isset($input['search']) && is_string($input['search']) ? trim($input['search']) : null;
        $rawStatus = isset($input['status']) && is_string($input['status']) ? trim($input['status']) : null;
        $rawCycleId = isset($input['academic_cycle_id']) && is_numeric($input['academic_cycle_id'])
            ? (int) $input['academic_cycle_id']
            : null;
        $rawSort = isset($input['sort']) && is_string($input['sort']) ? trim($input['sort']) : null;
        $rawDir = isset($input['dir']) && is_string($input['dir']) ? trim($input['dir']) : null;
        $activeStatuses = $schedulingOnly
            ? [ExamSession::STATUS_DRAFT]
            : [ExamSession::STATUS_DRAFT, ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS];

        $allowedSorts = [
            'starts_at' => 'starts_at',
            'code' => 'code',
            'status' => 'status',
            'created_at' => 'created_at',
        ];

        $resolvedSort = ModularSortScope::resolve($rawSort, $rawDir, $allowedSorts, 'starts_at', 'desc');

        $filters = [
            'search' => $rawSearch !== '' ? $rawSearch : null,
            'status' => $rawStatus !== '' ? $rawStatus : null,
            'academic_cycle_id' => $rawCycleId,
            'sort' => $resolvedSort['sort'],
            'dir' => $resolvedSort['dir'],
        ];
        // Fail-closed if a foreign academic cycle is requested under the institution context
        if ($filters['academic_cycle_id'] !== null) {
            $validCycle = AcademicCycle::query()
                ->where('id', $filters['academic_cycle_id'])
                ->where('institution_id', $institutionId)
                ->exists();

            if (! $validCycle) {
                return new ExamSessionQueryResult(
                    summary: [
                        'total_count' => 0,
                        'assigned_count' => 0,
                        'capacity_count' => 0,
                        'by_status' => [],
                    ],
                    sessions: collect(),
                    filters: $filters,
                    presenterRows: [],
                );
            }
        }

        // Clean filter query without relations or withCount (used for clean SQL aggregates)
        $filterQuery = ExamSession::query()->forWorkspace()
            ->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId));

        if ($filters['search']) {
            TokenizedInnerMatchScope::apply($filterQuery, $filters['search'], [
                fn ($q, $p) => $q->orWhere('exam_sessions.code', 'like', $p),
                fn ($q, $p) => $q->orWhereHas('room', fn ($rq) => $rq->where('name', 'like', $p)->orWhere('code', 'like', $p)),
                fn ($q, $p) => $q->orWhereHas('assignments', fn ($aq) => $aq->active()->whereHas('admissionAttempt', fn ($tq) => $tq->where('reference_code', 'like', $p)->orWhereHas('identity', fn ($iq) => $iq->where('first_name', 'like', $p)->orWhere('last_name', 'like', $p)))),
            ]);
        }

        if ($filters['status'] !== null && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $filterQuery->whereIn('exam_sessions.status', $activeStatuses);
            } else {
                $filterQuery->where('exam_sessions.status', $filters['status']);
            }
        }

        if ($filters['academic_cycle_id']) {
            $filterQuery->where('exam_sessions.academic_cycle_id', $filters['academic_cycle_id']);
        } else {
            $activeCycle = AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->where('is_active', true)
                ->first();

            if ($activeCycle !== null) {
                $filterQuery->where('exam_sessions.academic_cycle_id', $activeCycle->id);
                $filters['academic_cycle_id'] = $activeCycle->id;
            }
        }

        // Authoritative SQL aggregates computed across matching set BEFORE sample limit
        $totalCount = (int) (clone $filterQuery)->count();

        $sessionIdsQuery = (clone $filterQuery)->select('exam_sessions.id');
        $assignedCount = (int) ExamAssignment::query()
            ->active()
            ->whereIn('exam_session_id', $sessionIdsQuery)
            ->count();

        // Join rooms to sum capacity once per matching session (not once per room)
        $capacityCount = (int) (clone $filterQuery)
            ->join('rooms', 'rooms.id', '=', 'exam_sessions.room_id')
            ->sum('rooms.capacity');

        // Clean grouped query without non-grouped column conflicts
        $byStatus = (array) (clone $filterQuery)
            ->selectRaw('exam_sessions.status, count(*) as count')
            ->groupBy('exam_sessions.status')
            ->pluck('count', 'status')
            ->all();

        $summary = [
            'total_count' => $totalCount,
            'assigned_count' => $assignedCount,
            'capacity_count' => $capacityCount,
            'by_status' => $byStatus,
        ];

        // Attach eager relations and withCount ONLY for the presentation rows
        $query = (clone $filterQuery)
            ->with([
                'room:id,code,name,capacity',
                'academicCycle:id,school_year,semester,name,is_active,institution_id',
                'bookletVersion.booklet:id,name',
                'bookletVersion.templateVersion.template:id,name',
                'seatingArrangement',
                'currentLogisticsPlan.arrangement',
                'currentMaterialSnapshot',
            ])
            ->withCount([
                'assignments as assigned_count' => fn ($q) => $q->active(),
            ])
            ->withExists('logisticsPlans as has_logistics_generation');

        $resolvedSort = ModularSortScope::apply(
            $query,
            $filters['sort'] ?? null,
            $filters['dir'] ?? null,
            $allowedSorts,
            'starts_at',
            'desc'
        );
        $filters['sort'] = $resolvedSort['sort'];
        $filters['dir'] = $resolvedSort['dir'];
        $sessions = $query->limit($limit)->get();
        $presenterRows = $sessions->map(
            fn (ExamSession $session) => $this->presenter->listPayload($session)
        )->all();

        $examineeMatches = [];
        if ($filters['search']) {
            $search = $filters['search'];
            $examineeMatches = ExamAssignment::query()
                ->active()
                ->whereHas('examSession', function ($sessionQuery) use ($institutionId, $filters, $activeStatuses): void {
                    $sessionQuery->forWorkspace()
                        ->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId));

                    if ($filters['academic_cycle_id']) {
                        $sessionQuery->where('exam_sessions.academic_cycle_id', $filters['academic_cycle_id']);
                    }

                    if ($filters['status'] !== null && $filters['status'] !== 'all') {
                        if ($filters['status'] === 'active') {
                            $sessionQuery->whereIn('exam_sessions.status', $activeStatuses);
                        } else {
                            $sessionQuery->where('exam_sessions.status', $filters['status']);
                        }
                    }
                })
                ->whereHas('admissionAttempt', function ($attemptQuery) use ($search): void {
                    $attemptQuery->where('reference_code', 'like', "%{$search}%")
                        ->orWhereHas('identity', function ($identityQuery) use ($search): void {
                            $identityQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%");
                        });
                })
                ->with([
                    'admissionAttempt.identity',
                    'examSession.room',
                ])
                ->limit(10)
                ->get()
                ->map(function (ExamAssignment $assignment): array {
                    $attempt = $assignment->admissionAttempt;
                    $identity = $attempt?->identity;
                    $session = $assignment->examSession;
                    $room = $session?->room;

                    $roomLabel = $room ? ($room->name ?: $room->code) : 'Unassigned';

                    return [
                        'assignment_id' => $assignment->id,
                        'attempt_id' => (int) ($attempt?->id ?? $assignment->admission_attempt_id),
                        'reference_code' => (string) ($attempt?->reference_code ?? ''),
                        'name' => $identity ? $identity->fullName() : '',
                        'exam_session_id' => (int) ($session?->id ?? $assignment->exam_session_id),
                        'session_code' => (string) ($session?->code ?? ''),
                        'room_label' => (string) $roomLabel,
                    ];
                })
                ->all();
        }

        return new ExamSessionQueryResult(
            summary: $summary,
            sessions: $sessions,
            filters: $filters,
            presenterRows: $presenterRows,
            examineeMatches: $examineeMatches,
        );
    }

    /**
     * Backward-compatible web controller ingress.
     *
     * @return array{sessions: Collection<int, array<string, mixed>>, filters: array<string, mixed>, summary: array<string, mixed>, examinee_matches: list<array<string, mixed>>}
     */
    public function forScheduling(Request $request): array
    {
        $user = $request->user();
        $institutionId = (int) ($user?->actorScope()?->institutionId ?? 0);
        if ($institutionId < 1) {
            $institutionId = (int) (Institution::query()->value('id') ?? 1);
        }

        $input = $request->all();
        if (! is_string($input['status'] ?? null) || trim($input['status']) === '') {
            $input['status'] = 'active';
        }

        $result = $this->query($input, $institutionId, schedulingOnly: true);

        return [
            'sessions' => collect($result->presenterRows),
            'filters' => $result->filters,
            'summary' => $result->summary,
            'examinee_matches' => $result->examineeMatches,
        ];
    }
}
