<?php

namespace App\Consultation\Queries;

use App\Consultation\Support\ConsultationSessionPresenter;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Support\Search\ModularSortScope;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Canonical ConsultationSession read query with authoritative SQL aggregates,
 * mandatory multi-tenant relational fencing, and dual Inertia / Xiam-safe projections.
 */
class ConsultationSessionListQuery
{
    public function __construct(private readonly ConsultationSessionPresenter $presenter) {}

    /**
     * Authoritative typed read query with mandatory tenant scope.
     *
     * @param  array<string, mixed>  $input
     */
    public function query(array $input, int $institutionId, int $limit = 100, bool $schedulingOnly = false): ConsultationSessionQueryResult
    {
        $rawSearch = isset($input['search']) && is_string($input['search']) ? trim($input['search']) : null;
        $rawStatus = isset($input['status']) && is_string($input['status']) ? trim($input['status']) : null;
        $rawCycleId = isset($input['academic_cycle_id']) && is_numeric($input['academic_cycle_id'])
            ? (int) $input['academic_cycle_id']
            : null;
        $rawSort = isset($input['sort']) && is_string($input['sort']) ? trim($input['sort']) : null;
        $rawDir = isset($input['dir']) && is_string($input['dir']) ? trim($input['dir']) : null;

        $activeStatuses = $schedulingOnly
            ? [ConsultationSession::STATUS_DRAFT]
            : [
                ConsultationSession::STATUS_DRAFT,
                ConsultationSession::STATUS_PUBLISHED,
                ConsultationSession::STATUS_IN_PROGRESS,
            ];
        $filters = [
            'search' => $rawSearch !== '' ? $rawSearch : null,
            'status' => $rawStatus !== '' ? $rawStatus : null,
            'academic_cycle_id' => $rawCycleId,
            'sort' => $rawSort !== '' ? $rawSort : null,
            'dir' => $rawDir !== '' ? $rawDir : null,
        ];

        // Fail-closed if a foreign academic cycle is requested under the institution context
        if ($filters['academic_cycle_id'] !== null) {
            $validCycle = AcademicCycle::query()
                ->where('id', $filters['academic_cycle_id'])
                ->where('institution_id', $institutionId)
                ->exists();

            if (! $validCycle) {
                return new ConsultationSessionQueryResult(
                    summary: [
                        'total_count' => 0,
                        'assigned_count' => 0,
                        'capacity_count' => 0,
                        'by_status' => [],
                    ],
                    sessions: collect(),
                    filters: array_merge($filters, [
                        'sort' => $rawSort ?? 'scheduled_at',
                        'dir' => $rawDir ?? 'desc',
                    ]),
                    presenterRows: [],
                );
            }
        }

        // Clean filter query without relations or withCount (used for clean SQL aggregates)
        $filterQuery = ConsultationSession::query()
            ->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId));

        if ($filters['search']) {
            TokenizedInnerMatchScope::apply(
                $filterQuery,
                $filters['search'],
                [
                    'consultation_sessions.code',
                    function (EloquentBuilder $sub, string $pattern): void {
                        $sub->orWhereHas('room', function (EloquentBuilder $rq) use ($pattern): void {
                            $rq->where(function (EloquentBuilder $inner) use ($pattern): void {
                                TokenizedInnerMatchScope::orWhereColumnLike($inner, 'rooms.name', $pattern);
                                TokenizedInnerMatchScope::orWhereColumnLike($inner, 'rooms.code', $pattern);
                            });
                        });
                    },
                    function (EloquentBuilder $sub, string $pattern): void {
                        $sub->orWhereHas('assignments', function (EloquentBuilder $aq) use ($pattern): void {
                            $aq->active()->whereHas('admissionAttempt', function (EloquentBuilder $tq) use ($pattern): void {
                                $tq->where(function (EloquentBuilder $inner) use ($pattern): void {
                                    TokenizedInnerMatchScope::orWhereColumnLike($inner, 'admission_attempts.reference_code', $pattern);
                                    $inner->orWhereHas('identity', function (EloquentBuilder $iq) use ($pattern): void {
                                        $iq->where(function (EloquentBuilder $nameSub) use ($pattern): void {
                                            TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.first_name', $pattern);
                                            TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.last_name', $pattern);
                                            TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.middle_name', $pattern);
                                        });
                                    });
                                });
                            });
                        });
                    },
                ]
            );
        }

        if ($filters['status'] !== null && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $filterQuery->whereIn('consultation_sessions.status', $activeStatuses);
            } else {
                $filterQuery->where('consultation_sessions.status', $filters['status']);
            }
        }

        if ($filters['academic_cycle_id']) {
            $filterQuery->where('consultation_sessions.academic_cycle_id', $filters['academic_cycle_id']);
        } else {
            $activeCycle = AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->where('is_active', true)
                ->first();

            if ($activeCycle !== null) {
                $filterQuery->where('consultation_sessions.academic_cycle_id', $activeCycle->id);
                $filters['academic_cycle_id'] = $activeCycle->id;
            }
        }

        // Authoritative SQL aggregates computed across matching set BEFORE sample limit
        $totalCount = (int) (clone $filterQuery)->count();

        $sessionIdsQuery = (clone $filterQuery)->select('consultation_sessions.id');
        $assignedCount = (int) ConsultationAssignment::query()
            ->whereHas('admissionAttempt', fn ($query) => $query->forWorkspace())
            ->active()
            ->whereIn('consultation_session_id', $sessionIdsQuery)
            ->count();

        // Join rooms to sum capacity once per matching session (not once per room)
        $capacityCount = (int) (clone $filterQuery)
            ->join('rooms', 'rooms.id', '=', 'consultation_sessions.room_id')
            ->sum('rooms.capacity');

        // Clean grouped query without non-grouped column conflicts
        $byStatus = (array) (clone $filterQuery)
            ->selectRaw('consultation_sessions.status, count(*) as count')
            ->groupBy('consultation_sessions.status')
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
            ])
            ->withCount([
                'assignments as assigned_count' => fn ($q) => $q->active()->whereHas('admissionAttempt', fn ($query) => $query->forWorkspace()),
            ]);
        $allowedSorts = [
            'scheduled_at' => 'consultation_sessions.scheduled_at',
            'code' => 'consultation_sessions.code',
            'status' => 'consultation_sessions.status',
            'created_at' => 'consultation_sessions.created_at',
        ];

        $resolvedSort = ModularSortScope::apply(
            $query,
            $filters['sort'] ?? null,
            $filters['dir'] ?? null,
            $allowedSorts,
            'scheduled_at',
            'desc',
            'consultation_sessions.id'
        );

        $filters['sort'] = $resolvedSort['sort'];
        $filters['dir'] = $resolvedSort['dir'];

        $sessions = $query->limit($limit)->get();
        $presenterRows = $sessions->map(
            fn (ConsultationSession $session) => $this->presenter->listPayload($session)
        )->all();

        return new ConsultationSessionQueryResult(
            summary: $summary,
            sessions: $sessions,
            filters: $filters,
            presenterRows: $presenterRows,
        );
    }

    /**
     * Backward-compatible web controller ingress.
     *
     * @return array{sessions: Collection<int, array<string, mixed>>, filters: array<string, mixed>, summary: array<string, mixed>}
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
        ];
    }
}
