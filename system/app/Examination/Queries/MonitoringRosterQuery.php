<?php

namespace App\Examination\Queries;

use App\Examination\Support\ExamSessionPresenter;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Scheduling\AssignmentInvariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Exam Monitoring roster projections for authorized Test Administrator conduct.
 */
class MonitoringRosterQuery
{
    public function __construct(
        private readonly ExamSessionPresenter $presenter,
        private readonly AssignmentInvariant $assignmentInvariant,
    ) {}

    /**
     * Conduct-phase sessions visible to Test Administrators.
     *
     * @param  array{search?: ?string, date?: ?string, room_id?: int|string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function activeSessionsFor(?int $cycleId = null, array $filters = []): Collection
    {
        $query = ExamSession::query()->forWorkspace()
            ->with([
                'room:id,code,name,capacity',
                'academicCycle:id,school_year,semester,name,is_active',
            ])
            ->withCount([
                'assignments as assigned_count' => fn ($q) => $q->active(),
            ])
            ->whereIn('status', [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS]);

        if ($cycleId !== null) {
            $query->where('academic_cycle_id', $cycleId);
        }

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('code', 'ilike', "%{$term}%")
                    ->orWhereHas('room', fn ($r) => $r->where('name', 'ilike', "%{$term}%")->orWhere('code', 'ilike', "%{$term}%"))
                    ->orWhereHas('assignments', function ($assignmentQuery) use ($term): void {
                        $assignmentQuery->active()
                            ->whereHas('admissionAttempt', function ($attemptQuery) use ($term): void {
                                $attemptQuery->where('reference_code', 'ilike', "%{$term}%")
                                    ->orWhereHas('identity', function ($identityQuery) use ($term): void {
                                        $identityQuery->where('first_name', 'ilike', "%{$term}%")
                                            ->orWhere('last_name', 'ilike', "%{$term}%")
                                            ->orWhere('middle_name', 'ilike', "%{$term}%");
                                    });
                            });
                    });
            });
        }

        if (! empty($filters['date'])) {
            $query->whereDate('starts_at', $filters['date']);
        }

        if (! empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        return $query
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END")
            ->oldest('starts_at')
            ->oldest('id')
            ->limit(100)
            ->get()
            ->map(fn (ExamSession $session) => $this->presenter->listPayload($session));
    }

    /**
     * Completed conduct-phase sessions for audit, turnout review, and OMR submission repair.
     * Test Administrators view completed sessions within their existing workspace scope.
     *
     * @param  array{search?: ?string, date?: ?string, room_id?: int|string|null}  $filters
     */
    public function historySessionsFor(
        ?int $cycleId = null,
        array $filters = [],
        int $perPage = 15,
        string $pageName = 'history_page'
    ): LengthAwarePaginator {
        $query = ExamSession::query()->forWorkspace()
            ->with([
                'room:id,code,name,capacity',
                'academicCycle:id,school_year,semester,name,is_active',
            ])
            ->withCount([
                'assignments as assigned_count' => fn ($q) => $q->where(function ($sub): void {
                    $sub->forConductRoster()
                        ->orWhere('status', ExamAssignment::STATUS_CANCELLED);
                }),
                'assignments as present_count' => fn ($q) => $q->forConductRoster()
                    ->whereHas('attendanceRecord', fn ($a) => $a->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT)),
                'assignments as absent_count' => fn ($q) => $q->forConductRoster()
                    ->whereHas('attendanceRecord', fn ($a) => $a->where('outcome', ExamAttendanceRecord::OUTCOME_ABSENT)),
                'assignments as void_count' => fn ($q) => $q->forConductRoster()
                    ->whereHas('attendanceRecord', fn ($a) => $a->where('outcome', ExamAttendanceRecord::OUTCOME_VOID)),
                'assignments as submitted_count' => fn ($q) => $q->forConductRoster()
                    ->whereHas('submissionRecord'),
            ])
            ->whereIn('status', [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_CANCELLED]);

        if ($cycleId !== null) {
            $query->where('academic_cycle_id', $cycleId);
        }

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('code', 'ilike', "%{$term}%")
                    ->orWhereHas('room', fn ($r) => $r->where('name', 'ilike', "%{$term}%")->orWhere('code', 'ilike', "%{$term}%"))
                    ->orWhereHas('assignments', function ($assignmentQuery) use ($term): void {
                        $assignmentQuery->where(function ($sub): void {
                            $sub->forConductRoster()
                                ->orWhere('status', ExamAssignment::STATUS_CANCELLED);
                        })
                            ->whereHas('admissionAttempt', function ($attemptQuery) use ($term): void {
                                $attemptQuery->where('reference_code', 'ilike', "%{$term}%")
                                    ->orWhereHas('identity', function ($identityQuery) use ($term): void {
                                        $identityQuery->where('first_name', 'ilike', "%{$term}%")
                                            ->orWhere('last_name', 'ilike', "%{$term}%")
                                            ->orWhere('middle_name', 'ilike', "%{$term}%");
                                    });
                            });
                    });
            });
        }

        if (! empty($filters['date'])) {
            $query->whereDate('starts_at', $filters['date']);
        }

        if (! empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        return $query
            ->latest('closed_at')
            ->latest('starts_at')
            ->latest('id')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString()
            ->through(function (ExamSession $session) {
                $assigned = (int) ($session->assigned_count ?? 0);
                $present = (int) ($session->present_count ?? 0);
                $absent = (int) ($session->absent_count ?? 0);
                $void = (int) ($session->void_count ?? 0);
                $submitted = (int) ($session->submitted_count ?? 0);
                $turnoutPercent = $assigned > 0 ? (int) round(($present / $assigned) * 100) : 0;
                $isCancelled = $session->status === ExamSession::STATUS_CANCELLED;
                $unrecorded = max(0, $assigned - ($present + $absent + $void));
                $submissionStatus = match (true) {
                    $isCancelled => 'cancelled',
                    $assigned === 0 => 'empty',
                    $unrecorded > 0 => 'unrecorded_attendance',
                    $present > 0 && $submitted < $present => 'missing_records',
                    default => 'complete',
                };

                return [
                    'id' => $session->id,
                    'code' => $session->code,
                    'status' => $session->status,
                    'date' => $session->starts_at?->toDateString(),
                    'start_time' => $session->starts_at?->format('H:i'),
                    'end_time' => $session->effective_end_at?->format('H:i'),
                    'closed_at' => $session->closed_at?->toIso8601String(),
                    'room' => $session->room ? [
                        'id' => $session->room->id,
                        'code' => $session->room->code,
                        'name' => $session->room->name,
                        'capacity' => $session->room->capacity,
                    ] : null,
                    'academic_cycle' => $session->academicCycle ? [
                        'id' => $session->academicCycle->id,
                        'label' => $session->academicCycle->staffLabel(),
                    ] : null,
                    'assigned_count' => $assigned,
                    'present_count' => $present,
                    'absent_count' => $absent,
                    'void_count' => $void,
                    'submitted_count' => $submitted,
                    'turnout_percent' => $turnoutPercent,
                    'submission_status' => $submissionStatus,
                ];
            });
    }

    /**
     * Aggregate real database counts across the current academic cycle for
     * the monitoring index telemetry bar.
     *
     * @return array{active_rooms: int, total_rooms: int, total_scheduled: int, present_count: int, absent_count: int, submitted_count: int, is_live: bool, as_of: string}
     */
    public function telemetryForCycle(?AcademicCycle $activeCycle = null): array
    {
        $activeCycle ??= AcademicCycle::active();

        $sessionsQuery = ExamSession::query()->forWorkspace()
            ->whereIn('status', [
                ExamSession::STATUS_PUBLISHED,
                ExamSession::STATUS_IN_PROGRESS,
                ExamSession::STATUS_COMPLETED,
            ]);

        if ($activeCycle !== null) {
            $sessionsQuery->where('academic_cycle_id', $activeCycle->id);
        }

        $sessionIds = (clone $sessionsQuery)->pluck('id');

        $activeSessionsCount = (clone $sessionsQuery)
            ->where('status', ExamSession::STATUS_IN_PROGRESS)
            ->count();

        $completedSessionsCount = (clone $sessionsQuery)
            ->where('status', ExamSession::STATUS_COMPLETED)
            ->count();

        $publishedSessionsCount = (clone $sessionsQuery)
            ->where('status', ExamSession::STATUS_PUBLISHED)
            ->count();

        $totalSessionsCount = $sessionIds->count();

        $activeRooms = (clone $sessionsQuery)
            ->where('status', ExamSession::STATUS_IN_PROGRESS)
            ->whereNotNull('room_id')
            ->distinct('room_id')
            ->count('room_id');

        $totalRooms = (clone $sessionsQuery)
            ->whereNotNull('room_id')
            ->distinct('room_id')
            ->count('room_id');

        $assignmentIds = ExamAssignment::query()
            ->whereIn('exam_session_id', $sessionIds)
            ->forConductRoster()
            ->pluck('id');

        $totalScheduled = $assignmentIds->count();

        $conductSessionIds = (clone $sessionsQuery)
            ->whereIn('status', [ExamSession::STATUS_IN_PROGRESS, ExamSession::STATUS_COMPLETED])
            ->pluck('id');

        $conductAssignmentIds = ExamAssignment::query()
            ->whereIn('exam_session_id', $conductSessionIds)
            ->forConductRoster()
            ->pluck('id');

        $conductScheduled = $conductAssignmentIds->count();

        $presentCount = ExamAttendanceRecord::query()
            ->whereIn('exam_assignment_id', $conductAssignmentIds)
            ->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT)
            ->count();

        $absentCount = ExamAttendanceRecord::query()
            ->whereIn('exam_assignment_id', $conductAssignmentIds)
            ->where('outcome', ExamAttendanceRecord::OUTCOME_ABSENT)
            ->count();

        $voidCount = ExamAttendanceRecord::query()
            ->whereIn('exam_assignment_id', $conductAssignmentIds)
            ->where('outcome', ExamAttendanceRecord::OUTCOME_VOID)
            ->count();

        $unrecordedCount = max(0, $conductScheduled - ($presentCount + $absentCount + $voidCount));

        $submittedCount = ExamSubmissionRecord::query()
            ->whereIn('exam_assignment_id', $conductAssignmentIds)
            ->count();

        return [
            'is_live' => $activeSessionsCount > 0,
            'active_sessions' => $activeSessionsCount,
            'completed_sessions' => $completedSessionsCount,
            'published_sessions' => $publishedSessionsCount,
            'total_sessions' => $totalSessionsCount,
            'active_rooms' => $activeRooms,
            'total_rooms' => $totalRooms,
            'total_scheduled' => $totalScheduled,
            'conduct_scheduled' => $conductScheduled,
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'void_count' => $voidCount,
            'unrecorded_count' => $unrecordedCount,
            'submitted_count' => $submittedCount,
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{session: array<string, mixed>, roster: Collection<int, array<string, mixed>>, desks: list<array<string, mixed>>}
     */
    public function forSession(ExamSession $session): array
    {
        $isTerminalSession = in_array($session->status, [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_CANCELLED], true);

        $session->load([
            'room:id,institution_id,code,name,capacity',
            'seatingArrangement',
            'currentLogisticsPlan.arrangement',
            'currentMaterialSnapshot',
            'academicCycle:id,institution_id,school_year,semester,name,is_active',
            'assignments' => fn ($q) => ($isTerminalSession
                ? $q->where(fn ($sub) => $sub->forConductRoster()->orWhere('status', ExamAssignment::STATUS_CANCELLED))
                : $q->active()
            )->with([
                'admissionAttempt.identity',
                'attendanceRecord',
                'submissionRecord',
                'activeSeatAllocation.sessionSeat',
                'activeLogisticsSeatAllocation.seat',
            ]),
        ]);

        $logisticsPlan = $session->currentLogisticsPlan;
        $isPhysicalSeating = $logisticsPlan !== null
            ? $logisticsPlan->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
            : $session->currentMaterialSnapshot?->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING;

        $roster = $session->assignments->map(fn (ExamAssignment $assignment) => [
            'id' => $assignment->id,
            'attempt_id' => (int) $assignment->admission_attempt_id,
            'reference_number' => $assignment->admissionAttempt?->reference_code,
            'name' => $this->presenter->attemptName($assignment->admissionAttempt),
            'sex' => $assignment->admissionAttempt?->identity?->sex,
            'source' => $assignment->source,
            'attendance' => $assignment->attendanceRecord?->outcome ?? null,
            'attendance_id' => $assignment->attendanceRecord?->id,
            'attendance_recorded_at' => $assignment->attendanceRecord?->recorded_at?->toIso8601String(),
            'submitted' => $assignment->submissionRecord !== null,
            'submitted_at' => $assignment->submissionRecord?->submitted_at?->toIso8601String(),
            'seat_label' => $isPhysicalSeating
                ? ($logisticsPlan !== null
                    ? $assignment->activeLogisticsSeatAllocation?->seat?->seat_label
                    : $assignment->activeSeatAllocation?->sessionSeat?->seat_label)
                : null,
        ])->values();

        // Spatial layout is an operational projection of the current plan; retained snapshots remain a historical generation.
        $desks = $isPhysicalSeating ? $this->buildSpatialDesks($session) : [];

        $sessionPayload = $this->presenter->detailPayload($session);
        if (! $isPhysicalSeating) {
            $sessionPayload['seating_arrangement_id'] = null;
        }

        return [
            'session' => $sessionPayload,
            'roster' => $roster,
            'desks' => $desks,
        ];

    }

    /**
     * Project eligible inbound candidates (from other sessions / unassigned walk-ins)
     * and eligible outbound target sessions for exam-day accommodation/transfer.
     *
     * @return array{accommodate_candidates: list<array<string, mixed>>, transfer_target_sessions: list<array<string, mixed>>, session_remaining_capacity: int}
     */
    public function accommodationContextFor(ExamSession $session, bool $canManage): array
    {
        $session->loadMissing('academicCycle', 'room', 'currentLogisticsPlan', 'currentMaterialSnapshot');
        $hasPlan = $session->currentLogisticsPlan !== null || $session->currentMaterialSnapshot !== null;
        $sessionRemainingCapacity = $hasPlan ? $this->assignmentInvariant->remainingCapacity($session) : 0;

        if (! $canManage) {
            return [
                'accommodate_candidates' => [],
                'transfer_target_sessions' => [],
                'session_remaining_capacity' => $sessionRemainingCapacity,
            ];
        }

        $sessionInstitutionId = (int) ($session->academicCycle?->institution_id ?? $session->room?->institution_id ?? AcademicCycle::query()->whereKey($session->academic_cycle_id)->value('institution_id'));

        $transferTargets = ExamSession::query()
            ->forWorkspace()
            ->where('academic_cycle_id', $session->academic_cycle_id)
            ->where('id', '!=', $session->id)
            ->whereIn('status', [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS])
            ->whereNull('closed_at')
            ->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $sessionInstitutionId))
            ->with(['room:id,code,name,capacity', 'currentLogisticsPlan', 'currentMaterialSnapshot'])
            ->withCount(['assignments as assigned_count' => fn ($q) => $q->active()])
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END")
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->filter(function (ExamSession $target): bool {
                if ($target->status === ExamSession::STATUS_IN_PROGRESS
                    && $target->effective_end_at !== null
                    && now()->gt($target->effective_end_at)) {
                    return false;
                }
                if ($target->currentLogisticsPlan === null && $target->currentMaterialSnapshot === null) {
                    return false;
                }

                return $this->assignmentInvariant->remainingCapacity($target) > 0;
            })
            ->map(fn (ExamSession $target): array => [
                'id' => (int) $target->id,
                'code' => (string) $target->code,
                'status' => (string) $target->status,
                'date' => $target->starts_at?->toDateString(),
                'start_time' => $target->starts_at?->format('H:i'),
                'end_time' => $target->effective_end_at?->format('H:i'),
                'starts_at' => $target->starts_at?->toIso8601String(),
                'room_name' => $target->room?->name ?? $target->room?->code ?? '—',
                'assigned_count' => (int) $target->assigned_count,
                'capacity_limit' => (int) $this->assignmentInvariant->capacityLimit($target),
                'remaining_capacity' => (int) $this->assignmentInvariant->remainingCapacity($target),
            ])
            ->values()
            ->all();

        $sessionCanReceive = in_array($session->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true)
            && $session->closed_at === null
            && ($session->status !== ExamSession::STATUS_IN_PROGRESS || $session->effective_end_at === null || now()->lte($session->effective_end_at))
            && $hasPlan
            && $sessionRemainingCapacity > 0;

        if (! $sessionCanReceive) {
            return [
                'accommodate_candidates' => [],
                'transfer_target_sessions' => $transferTargets,
                'session_remaining_capacity' => $sessionRemainingCapacity,
            ];
        }

        $usedAttemptIds = collect([
            'assessment_raw_result_revisions',
            'assessment_scoring_runs',
            'assessment_conversion_revisions',
            'assessment_result_sets',
        ])
            ->flatMap(fn (string $table) => DB::table($table)
                ->join('assessment_administrations as aa', 'aa.id', '=', "{$table}.administration_id")
                ->pluck('aa.admission_attempt_id'))
            ->merge(DB::table('score_records')->pluck('admission_attempt_id'))
            ->unique()
            ->values()
            ->all();

        $candidatesQuery = AdmissionAttempt::query()
            ->forWorkspace()
            ->where('institution_id', $sessionInstitutionId)
            ->where('academic_cycle_id', $session->academic_cycle_id)
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->whereDoesntHave('examAssignments', fn ($q) => $q->active()->where('exam_session_id', $session->id))
            ->whereDoesntHave('examAssignments.submissionRecord');
        if ($usedAttemptIds !== []) {
            $candidatesQuery->whereNotIn('id', $usedAttemptIds);
        }

        $candidates = $candidatesQuery
            ->with([
                'identity:id,first_name,middle_name,last_name,suffix,sex',
                'examAssignments' => fn ($q) => $q->with(['examSession.room:id,code,name', 'attendanceRecord'])->orderByDesc('id'),
            ])
            ->orderBy('reference_code')
            ->limit(150)
            ->get()
            ->map(function (AdmissionAttempt $attempt): ?array {
                $active = $attempt->examAssignments->first(fn (ExamAssignment $a) => $a->isActive());
                $priorMissed = $active === null
                    ? $attempt->examAssignments->first(fn (ExamAssignment $a) => in_array($a->attendanceRecord?->outcome, [
                        ExamAttendanceRecord::OUTCOME_ABSENT,
                        ExamAttendanceRecord::OUTCOME_VOID,
                    ], true))
                    : null;
                $contextAssignment = $active ?? $priorMissed;
                $otherSession = $contextAssignment?->examSession;
                $attendance = $contextAssignment?->attendanceRecord?->outcome;

                if ($active !== null && $otherSession !== null) {
                    if ($otherSession->status === ExamSession::STATUS_COMPLETED
                        && $attendance !== ExamAttendanceRecord::OUTCOME_ABSENT) {
                        return null;
                    }
                    if ($otherSession->status === ExamSession::STATUS_IN_PROGRESS
                        && in_array($attendance, [ExamAttendanceRecord::OUTCOME_PRESENT, ExamAttendanceRecord::OUTCOME_VOID], true)) {
                        return null;
                    }
                }

                if ($otherSession === null) {
                    $sourceKind = 'unassigned';
                    $sourceLabel = 'Unscheduled / Walk-in';
                    $defaultReasonCategory = 'travel_distance';
                } elseif ($priorMissed !== null || $otherSession->status === ExamSession::STATUS_COMPLETED || $attendance === ExamAttendanceRecord::OUTCOME_ABSENT) {
                    $sourceKind = 'absent';
                    $sourceLabel = "Missed: {$otherSession->code} (Absent)";
                    $defaultReasonCategory = 'tardiness_consideration';
                } else {
                    $sourceKind = 'scheduled_other';
                    $sourceLabel = "Scheduled: {$otherSession->code}";
                    $defaultReasonCategory = 'travel_distance';
                }

                return [
                    'attempt_id' => (int) $attempt->id,
                    'reference_code' => (string) $attempt->reference_code,
                    'name' => $this->presenter->attemptName($attempt),
                    'sex' => $attempt->identity?->sex,
                    'current_session_id' => $otherSession !== null ? (int) $otherSession->id : null,
                    'current_session_code' => $otherSession?->code,
                    'current_session_status' => $otherSession?->status,
                    'current_session_room' => $otherSession?->room?->name ?? $otherSession?->room?->code,
                    'current_session_starts_at' => $otherSession?->starts_at?->toIso8601String(),
                    'attendance_outcome' => $attendance,
                    'source_kind' => $sourceKind,
                    'source_label' => $sourceLabel,
                    'default_reason_category' => $defaultReasonCategory,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'accommodate_candidates' => $candidates,
            'transfer_target_sessions' => $transferTargets,
            'session_remaining_capacity' => $sessionRemainingCapacity,
        ];
    }

    /**
     * Build spatial seating layout matrix from session seat allocations.
     *
     * @return list<array{desk_id: int, seat_label: string, row: int, column: int, candidate: array<string, mixed>|null, attendance: string|null, submitted: bool}>
     */
    private function buildSpatialDesks(ExamSession $session): array
    {
        $plan = $session->currentLogisticsPlan;
        if ($plan !== null) {
            $seats = $plan->seats()
                ->with([
                    'activeAllocation.assignment' => fn ($q) => $q->active()->with([
                        'admissionAttempt.identity',
                        'attendanceRecord',
                        'submissionRecord',
                    ]),
                ])
                ->orderBy('row_number')->orderBy('column_number')->get();
        } else {
            $snapshot = $session->currentMaterialSnapshot;
            if ($snapshot === null) {
                $session->load('currentMaterialSnapshot');
                $snapshot = $session->currentMaterialSnapshot;
            }
            if ($snapshot === null) {
                return [];
            }
            $seats = $snapshot->seats()
                ->with([
                    'activeAllocation.assignment' => fn ($q) => $q->active()->with([
                        'admissionAttempt.identity',
                        'attendanceRecord',
                        'submissionRecord',
                    ]),
                ])
                ->orderBy('row_number')->orderBy('column_number')->get();
        }

        return $seats->map(function ($seat) {
            $allocation = $seat->activeAllocation;
            $assignment = $allocation?->assignment;
            $attempt = $assignment?->admissionAttempt;

            return [
                'desk_id' => $seat->id,
                'seat_label' => $seat->seat_label,
                'row' => $seat->row_number,
                'column' => $seat->column_number,
                'candidate' => $assignment !== null ? [
                    'assignment_id' => $assignment->id,
                    'reference_number' => $attempt?->reference_code,
                    'name' => $this->presenter->attemptName($attempt),
                    'sex' => $attempt?->identity?->sex,
                ] : null,
                'attendance' => $assignment?->attendanceRecord?->outcome ?? null,
                'submitted' => $assignment?->submissionRecord !== null,
            ];
        })->all();
    }
}
