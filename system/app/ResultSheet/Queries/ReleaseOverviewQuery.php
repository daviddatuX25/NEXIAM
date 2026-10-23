<?php

namespace App\ResultSheet\Queries;

use App\Assessment\Support\GradingRoster;
use App\Assessment\Support\IndividualScoringReadiness;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Support\Search\ModularSortScope;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;

/**
 * Canonical tenant-fenced printing overview with bounded, set-oriented
 * scoring and prepared-document evidence loading.
 */
class ReleaseOverviewQuery
{
    public const SORT_OPTIONS = [
        ['value' => 'exam_date', 'label' => 'Exam Date'],
        ['value' => 'session_code', 'label' => 'Session Code'],
        ['value' => 'unprinted_count', 'label' => 'Prepared not printed'],
        ['value' => 'prepared_count', 'label' => 'Prepared Count'],
        ['value' => 'print_backlog_count', 'label' => 'Printable Backlog'],
    ];

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function sortOptions(): array
    {
        return self::SORT_OPTIONS;
    }

    /**
     * Inertia presentation projection matching Release/Index.svelte prop contract.
     *
     * @param  array{search?: string, status?: string, sort?: string, dir?: string}|string|null  $searchOrFilters
     * @return array<string|int, mixed>
     */
    public function forInertia(
        int $institutionId,
        int $limit = 50,
        array|string|null $searchOrFilters = null,
        string $status = 'all',
        ?string $sort = null,
        ?string $dir = null,
        ?IndividualScoringReadiness $scoringReadiness = null,
    ): array {
        $cycleId = null;
        if (is_array($searchOrFilters)) {
            $search = trim((string) ($searchOrFilters['search'] ?? ''));
            $status = (string) ($searchOrFilters['status'] ?? $status);
            $sort = (string) ($searchOrFilters['sort'] ?? $sort ?? 'exam_date');
            $dir = (string) ($searchOrFilters['dir'] ?? $dir ?? 'desc');
            if (isset($searchOrFilters['academic_cycle_id']) && is_numeric($searchOrFilters['academic_cycle_id'])) {
                $cycleId = (int) $searchOrFilters['academic_cycle_id'];
            }
        } else {
            $search = trim((string) ($searchOrFilters ?? ''));
            $sort = $sort ?? 'exam_date';
            $dir = $dir ?? 'desc';
        }

        $query = GradingSession::query()
            ->whereIn('grading_sessions.status', [
                GradingSession::STATUS_OPEN,
                GradingSession::STATUS_REVIEW,
                GradingSession::STATUS_FINALIZED,
            ])
            ->whereHas('examSession', function ($query) use ($cycleId): void {
                $query->forWorkspace();
                if ($cycleId !== null && $cycleId > 0) {
                    $query->where('exam_sessions.academic_cycle_id', $cycleId);
                }
            })
            ->with([
                'examSession.room',
                'scoreRecords:id,grading_session_id,admission_attempt_id',
                'assessmentResultSets.administration:id,admission_attempt_id',
            ])
            ->whereHas('examSession.academicCycle', fn ($q) => $q->where('institution_id', $institutionId));
        if ($search !== '') {
            TokenizedInnerMatchScope::apply($query, $search, [
                function (EloquentBuilder $sub, string $pattern): void {
                    $sub->orWhereHas('examSession', function (EloquentBuilder $session) use ($pattern): void {
                        $session->where(function (EloquentBuilder $fields) use ($pattern): void {
                            TokenizedInnerMatchScope::orWhereColumnLike($fields, 'exam_sessions.code', $pattern);
                            $fields->orWhereHas('room', function (EloquentBuilder $room) use ($pattern): void {
                                $room->where(fn (EloquentBuilder $name) => TokenizedInnerMatchScope::orWhereColumnLike($name, 'rooms.name', $pattern));
                            });
                        });
                    });
                },
                function (EloquentBuilder $sub, string $pattern): void {
                    foreach ([
                        'examSession.assignments.admissionAttempt',
                        'scoreRecords.admissionAttempt',
                        'assessmentResultSets.administration.admissionAttempt',
                    ] as $relation) {
                        $sub->orWhereHas($relation, fn (EloquentBuilder $attempt) => $this->matchApplicantSearch($attempt, $pattern));
                    }
                },
            ]);
        }

        $allowedSorts = [
            'exam_date' => function (EloquentBuilder $q, string $direction): void {
                $q->orderBy(
                    ExamSession::query()
                        ->select('starts_at')
                        ->whereColumn('exam_sessions.id', 'grading_sessions.exam_session_id'),
                    $direction
                );
            },
            'session_code' => function (EloquentBuilder $q, string $direction): void {
                $q->orderBy(
                    ExamSession::query()
                        ->select('code')
                        ->whereColumn('exam_sessions.id', 'grading_sessions.exam_session_id'),
                    $direction
                );
            },
            'unprinted_count' => function (EloquentBuilder $q, string $direction): void {
                $q->orderBy('grading_sessions.id', $direction);
            },
            'print_backlog_count' => function (EloquentBuilder $q, string $direction): void {
                $q->orderBy('grading_sessions.id', $direction);
            },
            'prepared_count' => function (EloquentBuilder $q, string $direction): void {
                $q->orderBy('grading_sessions.id', $direction);
            },
        ];

        $resolvedSort = ModularSortScope::apply(
            $query,
            $sort,
            $dir,
            $allowedSorts,
            defaultKey: 'exam_date',
            defaultDir: 'desc',
            tieBreakColumn: 'grading_sessions.id',
        );

        $scoringReadiness ??= app(IndividualScoringReadiness::class);
        $mapped = collect();
        if ($limit !== 0) {
            $query->chunk(25, function (Collection $sessions) use (&$mapped, $scoringReadiness, $status, $resolvedSort, $limit): bool {
                $rows = $mapped->concat($this->projectSessions($sessions, $scoringReadiness))->keyBy('id')->values();
                $mapped = $this->filterAndSortRows($rows, $status, $resolvedSort, $limit);

                return $resolvedSort['sort'] !== 'exam_date' || $limit < 0 || $mapped->count() < $limit;
            });
        }
        $sessionRows = $mapped->values()->all();
        $result = $sessionRows;
        $result['sessions'] = $sessionRows;
        $result['sort_options'] = self::SORT_OPTIONS;
        $result['filters'] = [
            'search' => $search,
            'status' => $status,
            'sort' => $resolvedSort['sort'],
            'dir' => $resolvedSort['dir'],
        ];

        return $result;
    }

    /**
     * Start relationship callbacks with an AND group so LIKE alternatives
     * cannot turn the foreign-key predicate into an alternative match.
     */
    private function matchApplicantSearch(EloquentBuilder $attempt, string $pattern): void
    {
        $attempt->where(function (EloquentBuilder $fields) use ($pattern): void {
            TokenizedInnerMatchScope::orWhereColumnLike($fields, 'admission_attempts.reference_code', $pattern);
            $fields->orWhereHas('identity', function (EloquentBuilder $identity) use ($pattern): void {
                $identity->where(function (EloquentBuilder $names) use ($pattern): void {
                    foreach (['first_name', 'middle_name', 'last_name'] as $column) {
                        TokenizedInnerMatchScope::orWhereColumnLike($names, 'applicant_identities.'.$column, $pattern);
                    }
                });
            });
        });
    }

    /**
     * @param  Collection<int, GradingSession>  $sessions
     * @return Collection<int, array<string, mixed>>
     */
    private function projectSessions(Collection $sessions, IndividualScoringReadiness $scoringReadiness): Collection
    {

        $examSessionIds = $sessions->pluck('exam_session_id')->filter()->unique()->values()->all();
        $rosterAttemptIdsByExamSession = $examSessionIds === []
            ? collect()
            : GradingRoster::queryForSessionIds($examSessionIds)
                ->get(['exam_session_id', 'admission_attempt_id'])
                ->groupBy('exam_session_id')
                ->map(fn ($rows) => $rows->pluck('admission_attempt_id')->all());

        $sessionAttemptIdsByGrading = $sessions->mapWithKeys(function (GradingSession $gs) use ($rosterAttemptIdsByExamSession): array {
            $resultSetAttemptIds = $gs->assessmentResultSets
                ->map(fn ($rs): ?int => $rs->administration?->admission_attempt_id)
                ->filter()
                ->all();
            $rosterIds = $rosterAttemptIdsByExamSession->get($gs->exam_session_id, []);
            $legacyIds = $gs->scoreRecords->pluck('admission_attempt_id')->all();

            return [
                $gs->id => array_values(array_unique(array_map('intval', array_filter([
                    ...$resultSetAttemptIds,
                    ...$rosterIds,
                    ...$legacyIds,
                ])))),
            ];
        });

        $allAttemptIds = $sessionAttemptIdsByGrading->flatten()->unique()->filter()->values()->all();
        $attempts = $allAttemptIds === []
            ? collect()
            : AdmissionAttempt::query()->forWorkspace()->whereIn('id', $allAttemptIds)->get();
        $readinessByAttempt = $scoringReadiness->forAttempts($attempts);
        $documentPointers = $attempts->pluck('current_result_document_id', 'id');

        $readyDocumentsByAttempt = ResultDocument::query()
            ->whereIn('admission_attempt_id', $allAttemptIds)
            ->whereIn('id', $documentPointers->filter()->all())
            ->where('status', ResultDocument::STATUS_READY)
            ->get(['id', 'admission_attempt_id', 'snapshot_payload_json->_assessment_result_set_id as snapshot_result_set_id'])
            ->groupBy('admission_attempt_id');
        $currentDocumentsByAttempt = $readyDocumentsByAttempt->map(
            fn ($documents, $attemptId) => $documents->firstWhere('id', $documentPointers->get($attemptId))
        )->filter();
        $currentDocumentIds = $currentDocumentsByAttempt->pluck('id')->all();
        $printedAttemptMap = ResultDocumentPrintReceipt::query()
            ->whereIn('result_document_id', $currentDocumentIds)
            ->where('operation', 'confirmed')
            ->where('is_current', true)
            ->pluck('admission_attempt_id')
            ->unique()
            ->flip()
            ->all();
        $deliveredAttemptMap = ResultRelease::query()
            ->whereIn('admission_attempt_id', $allAttemptIds)
            ->where('is_current', true)
            ->whereNull('unreleased_at')
            ->pluck('admission_attempt_id')
            ->unique()
            ->flip()
            ->all();

        $mapped = $sessions->map(function (GradingSession $gs) use ($sessionAttemptIdsByGrading, $currentDocumentsByAttempt, $printedAttemptMap, $deliveredAttemptMap, $readinessByAttempt): array {
            $examSession = $gs->examSession;
            $sessionAttemptIds = $sessionAttemptIdsByGrading->get($gs->id, []);
            $total = count($sessionAttemptIds);
            $scored = 0;
            $eligibleUnprepared = 0;
            $prepared = 0;
            $printed = 0;
            $delivered = 0;

            foreach ($sessionAttemptIds as $attemptId) {
                $readiness = $readinessByAttempt[$attemptId] ?? [];
                $isScored = ($readiness['ready'] ?? false)
                    && (int) ($readiness['exam_session_id'] ?? 0) === (int) $examSession->id
                    && (int) ($readiness['grading_session_id'] ?? 0) === (int) $gs->id;
                $document = $currentDocumentsByAttempt->get($attemptId);
                $snapshotResultSetId = (int) ($document?->getAttribute('snapshot_result_set_id') ?? 0);
                $resultSetId = (int) ($readiness['result_set_id'] ?? 0);
                $isPrepared = $document !== null && $isScored && (
                    $resultSetId > 0
                        ? $snapshotResultSetId === $resultSetId
                        : (($readiness['administration_id'] ?? null) === null
                            && $snapshotResultSetId === 0
                            && $gs->status === GradingSession::STATUS_FINALIZED)
                );
                $isPrinted = $isPrepared && isset($printedAttemptMap[$attemptId]);
                $scored += $isScored ? 1 : 0;
                $eligibleUnprepared += $isScored && ! $isPrepared ? 1 : 0;
                $prepared += $isPrepared ? 1 : 0;
                $printed += $isPrinted ? 1 : 0;
                $delivered += isset($deliveredAttemptMap[$attemptId]) ? 1 : 0;
            }

            $unprinted = max(0, $prepared - $printed);

            return [
                'id' => (int) $gs->id,
                'grading_session_id' => (int) $gs->id,
                'exam_session_id' => (int) $examSession->id,
                'print_url' => route('release.print.sessions.index', ['examSession' => $examSession->id]),
                'exam_code' => (string) ($examSession->code ?? "Session #{$examSession->id}"),
                'room_name' => (string) ($examSession->room?->name ?? '—'),
                'starts_at' => $examSession->starts_at?->format('M d, Y h:i A') ?? '—',
                'starts_at_raw' => $examSession->starts_at?->toIso8601String(),
                'status' => (string) $gs->status,
                'examinees_count' => $total,
                'scored_count' => $scored,
                'eligible_unprepared_count' => $eligibleUnprepared,
                'prepared_count' => $prepared,
                'printed_count' => $printed,
                'unprinted_count' => $unprinted,
                'print_backlog_count' => $eligibleUnprepared + $unprinted,
                'delivered_count' => $delivered,
                'released_count' => $delivered,
            ];
        })->filter(fn (array $row): bool => $row['status'] === GradingSession::STATUS_FINALIZED
            || $row['scored_count'] > 0
            || $row['prepared_count'] > 0
            || $row['printed_count'] > 0);

        return $mapped;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $mapped
     * @param  array{sort: string, dir: string}  $resolvedSort
     * @return Collection<int, array<string, mixed>>
     */
    private function filterAndSortRows(Collection $mapped, string $status, array $resolvedSort, int $limit): Collection
    {

        if ($status === 'unprinted') {
            $mapped = $mapped->filter(fn (array $r) => $r['print_backlog_count'] > 0);
        } elseif ($status === 'printed') {
            $mapped = $mapped->filter(fn (array $r) => $r['scored_count'] > 0 && $r['eligible_unprepared_count'] === 0 && $r['unprinted_count'] === 0);
        }

        if ($resolvedSort['sort'] === 'unprinted_count') {
            $mapped = $mapped->sortBy(fn (array $r) => $r['unprinted_count'], descending: $resolvedSort['dir'] === 'desc');
        } elseif ($resolvedSort['sort'] === 'print_backlog_count') {
            $mapped = $mapped->sortBy(fn (array $r) => $r['print_backlog_count'], descending: $resolvedSort['dir'] === 'desc');
        } elseif ($resolvedSort['sort'] === 'prepared_count') {
            $mapped = $mapped->sortBy(fn (array $r) => $r['prepared_count'], descending: $resolvedSort['dir'] === 'desc');
        } elseif ($resolvedSort['sort'] === 'session_code') {
            $mapped = $mapped->sortBy(fn (array $r) => strtolower($r['exam_code']), descending: $resolvedSort['dir'] === 'desc');
        } elseif ($resolvedSort['sort'] === 'exam_date') {
            $mapped = $mapped->sortBy(fn (array $r) => $r['starts_at_raw'] ?? $r['starts_at'], descending: $resolvedSort['dir'] === 'desc');
        }
        $mapped = $mapped->take($limit);

        return $mapped;
    }

    /**
     * Minimized, privacy-scrubbed DTO for LLM / Xiam tool results.
     *
     * @return list<array{
     *     id: int,
     *     exam_code: string,
     *     room_name: string,
     *     starts_at: string,
     *     status: string,
     *     examinees_count: int,
     *     prepared_count: int,
     *     printed_count: int,
     *     delivered_count: int,
     *     released_count: int,
     * }>
     */
    public function forXiamSafe(int $institutionId, int $sampleLimit = 5): array
    {
        $sampleRows = $this->forInertia($institutionId, $sampleLimit);
        $rows = $sampleRows['sessions'] ?? $sampleRows;

        $safeSample = array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? $row['exam_session_id'] ?? 0),
            'exam_code' => (string) $row['exam_code'],
            'room_name' => (string) $row['room_name'],
            'starts_at' => (string) $row['starts_at'],
            'status' => (string) $row['status'],
            'examinees_count' => (int) $row['examinees_count'],
            'prepared_count' => (int) $row['prepared_count'],
            'printed_count' => (int) $row['printed_count'],
            'delivered_count' => (int) $row['delivered_count'],
            'released_count' => (int) $row['released_count'],
        ], $rows);

        return array_values($safeSample);
    }
}
