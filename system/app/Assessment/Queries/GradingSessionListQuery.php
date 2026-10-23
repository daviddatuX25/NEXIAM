<?php

namespace App\Assessment\Queries;

use App\Assessment\Support\AssessmentAdministrationFinalizationPredicate;
use App\Assessment\Support\GradingRoster;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Support\Search\ModularSortScope;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read model for completed exam sittings and their optional grading ledgers.
 */
class GradingSessionListQuery
{
    public const SORT_OPTIONS = [
        ['value' => 'starts_at', 'label' => 'Exam date'],
        ['value' => 'code', 'label' => 'Session Code'],
        ['value' => 'status', 'label' => 'Status'],
    ];

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function sortOptions(): array
    {
        return self::SORT_OPTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function forInertia(
        int $institutionId,
        int $limit = 50,
        string $search = '',
        string $status = 'active',
        ?int $cycleId = null,
        ?int $roomId = null,
        ?string $sort = null,
        ?string $dir = null,
    ): array {
        $search = trim($search);
        $sort = $sort ?? (string) request('sort', 'starts_at');
        $dir = $dir ?? (string) request('dir', 'desc');
        $roomFilter = request('room_id');
        $noRoom = $roomFilter === 'none';
        if ($roomId === null && is_numeric($roomFilter)) {
            $roomId = (int) $roomFilter;
        }

        $areaQuery = AptitudeArea::query()->where('is_active', true);
        $areaCount = $areaQuery->count();
        $activeAreas = $areaQuery->get(['id', 'code', 'name', 'max_items'])
            ->map(fn (AptitudeArea $area): array => [
                'id' => (int) $area->id,
                'code' => strtoupper($area->code),
                'name' => $area->name,
                'max_items' => (int) $area->max_items,
            ])->values()->all();

        $query = ExamSession::query()->forWorkspace()
            ->where('exam_sessions.status', ExamSession::STATUS_COMPLETED)
            ->whereHas('academicCycle', fn (Builder $cycle) => $cycle->where('institution_id', $institutionId))
            ->with(['room', 'academicCycle', 'gradingSession']);

        if ($status === 'repair_required') {
            $query->whereDoesntHave('gradingSession');
        } elseif (in_array($status, ['open', 'review', 'finalized'], true)) {
            $query->whereHas('gradingSession', fn (Builder $grading) => $grading->where('status', $status));
        } elseif ($status === 'active') {
            $query->where(function (Builder $unfinished): void {
                $unfinished->whereDoesntHave('gradingSession')
                    ->orWhereHas('gradingSession', fn (Builder $grading) => $grading->whereIn('status', ['open', 'review']));
            });
        }
        if ($cycleId !== null) {
            $query->where('academic_cycle_id', $cycleId);
        }
        if ($noRoom) {
            $query->whereNull('room_id');
        } elseif ($roomId !== null) {
            $query->where('room_id', $roomId);
        }
        if ($search !== '') {
            $this->applySearch($query, $search, false);
        }

        $totalSessions = (clone $query)->count('exam_sessions.id');
        $totalFinalized = (clone $query)->whereHas('gradingSession', fn (Builder $grading) => $grading->where('status', GradingSession::STATUS_FINALIZED))->count();
        $allowedSorts = [
            'starts_at' => 'exam_sessions.starts_at',
            'code' => 'exam_sessions.code',
            'status' => function (Builder $builder, string $direction): void {
                $builder->orderBy(
                    GradingSession::query()->select('status')->whereColumn('grading_sessions.exam_session_id', 'exam_sessions.id'),
                    $direction,
                );
            },
        ];
        $resolvedSort = ModularSortScope::apply(
            $query,
            $sort,
            $dir,
            $allowedSorts,
            defaultKey: 'starts_at',
            defaultDir: 'desc',
            tieBreakColumn: 'exam_sessions.id',
        );

        $sessions = $query->limit(max(0, $limit))->get();
        $examSessionIds = $sessions->modelKeys();
        $eligibleBySession = GradingRoster::queryForSessionIds($examSessionIds)->get()->groupBy('exam_session_id');
        $gradingSessions = $sessions->pluck('gradingSession')->filter();
        $readiness = app(AssessmentAdministrationFinalizationPredicate::class)->evaluateMany($gradingSessions);

        $rows = $sessions->map(function (ExamSession $examSession) use ($eligibleBySession, $readiness): array {
            $gradingSession = $examSession->gradingSession;
            $eligibleCount = $eligibleBySession->get($examSession->id, collect())->count();
            $scoredCount = 0;
            if ($gradingSession !== null && isset($readiness[$gradingSession->id])) {
                $evaluation = $readiness[$gradingSession->id];
                $scoredCount = (int) ($evaluation['details']['scored_count'] ?? 0);
            }

            return [
                'id' => (int) $examSession->id,
                'exam_session_id' => (int) $examSession->id,
                'exam_code' => (string) $examSession->code,
                'starts_at' => $examSession->starts_at?->toIso8601String(),
                'room_name' => (string) ($examSession->room?->name ?? '—'),
                'grading_status' => $gradingSession?->status,
                'grading_version' => $gradingSession?->version,
                'score_url' => route('scoring.sessions.show', ['examSession' => $examSession->id]),
                'applicants_total' => $eligibleCount,
                'applicants_scored' => $scoredCount,
                'progress_percent' => $eligibleCount > 0 ? (int) round(($scoredCount / $eligibleCount) * 100) : 0,
                'status' => $gradingSession?->status,
                'opened_at' => $gradingSession?->opened_at?->toIso8601String(),
                'finalized_at' => $gradingSession?->finalized_at?->toIso8601String(),
                'readiness_state' => $readiness[$gradingSession?->id]['state'] ?? 'repair_required',
                'repair_required' => $gradingSession === null,
            ];
        })->values()->all();

        $cycles = AcademicCycle::query()->where('institution_id', $institutionId)->orderByDesc('id')
            ->get(['id', 'school_year', 'semester', 'name', 'code'])
            ->map(fn (AcademicCycle $cycle): array => ['id' => (int) $cycle->id, 'label' => $cycle->staffLabel()])->all();
        $rooms = Room::query()->where('institution_id', $institutionId)->orderBy('name')->get(['id', 'name'])
            ->map(fn (Room $room): array => ['id' => (int) $room->id, 'name' => (string) $room->name])->all();

        return [
            'sessions' => $rows,
            'candidate_exam_sessions' => [],
            'aptitude_areas' => $activeAreas,
            'aptitude_areas_count' => $areaCount,
            'cycles' => $cycles,
            'rooms' => $rooms,
            'summary' => [
                'total_grading_sessions' => $totalSessions,
                'total_candidates' => (int) GradingRoster::queryForSessionIds((clone $query)->reorder()->pluck('exam_sessions.id')->all())->count(),
                'total_graded' => (int) $totalFinalized,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'academic_cycle_id' => $cycleId,
                'room_id' => $noRoom ? 'none' : $roomId,
                'sort' => $resolvedSort['sort'],
                'dir' => $resolvedSort['dir'],
            ],
            'sort_options' => self::SORT_OPTIONS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forXiamSafe(int $institutionId, int $sampleLimit = 5): array
    {
        $payload = $this->forInertia($institutionId, $sampleLimit);

        return [
            'summary' => $payload['summary'],
            'sessions' => array_map(static fn (array $row): array => [
                'id' => (int) $row['exam_session_id'],
                'exam_session_code' => (string) $row['exam_code'],
                'status' => (string) ($row['grading_status'] ?? 'repair_required'),
                'progress_percent' => (int) $row['progress_percent'],
            ], $payload['sessions']),
        ];
    }

    private function applySearch(Builder $query, string $search, bool $gradingSessionQuery = true): void
    {
        TokenizedInnerMatchScope::apply($query, $search, [
            function (Builder $sub, string $pattern): void {
                TokenizedInnerMatchScope::orWhereColumnLike($sub, 'exam_sessions.code', $pattern);
                $sub->orWhereHas('room', function (Builder $room) use ($pattern): void {
                    TokenizedInnerMatchScope::whereColumnLike($room, 'rooms.name', $pattern);
                });
            },
            function (Builder $sub, string $pattern): void {
                $sub->orWhereHas('assignments', function (Builder $assignment) use ($pattern): void {
                    $assignment->active()->whereHas('admissionAttempt', function (Builder $attempt) use ($pattern): void {
                        $attempt->where(function (Builder $attemptSub) use ($pattern): void {
                            TokenizedInnerMatchScope::whereColumnLike($attemptSub, 'admission_attempts.reference_code', $pattern);
                            $attemptSub->orWhereHas('identity', function (Builder $identity) use ($pattern): void {
                                $identity->where(function (Builder $nameSub) use ($pattern): void {
                                    TokenizedInnerMatchScope::whereColumnLike($nameSub, 'applicant_identities.first_name', $pattern);
                                    TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.middle_name', $pattern);
                                    TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.last_name', $pattern);
                                });
                            });
                        });
                    });
                });
            },
        ]);
    }
}
