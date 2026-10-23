<?php

namespace App\Staff\Queries;

use App\Admission\Support\QuotaReservation;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Staffing\InstitutionalSetupDelegation;
use App\Models\User;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use App\Testing\PracticeWorkspace;

final class StaffDashboardProjection
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly AdmissionsFunnelAggregateQuery $admissions,
        private readonly AssessmentThroughputAggregateQuery $assessment,
        private readonly ComputeSetupReadinessQuery $setupReadiness,
        private readonly CycleRoadmapAggregateQuery $roadmap,
        private readonly AcademicProgramQuotaScopeQuery $academicScope,
        private readonly CoursePlacementAggregateQuery $coursePlacement,
    ) {}

    /**
     * @return array{
     *   as_of: string,
     *   active_cycle: array{id: int, code: string, name: string}|null,
     *   actor: array{name: string|null, email: string|null, office: string|null, capabilities: list<string>},
     *   registrar: array<string, mixed>|null,
     *   guidance: array<string, mixed>|null,
     *   program_head: array<string, mixed>|null,
     *   course_placement: array{placed: int, quota: int, percent: float}|null,
     *   my_activity: list<array{id: string, event: string, summary: string, created_at: string}>
     * }
     */
    public function for(User $user): array
    {
        $scope = $user->actorScope();
        $institutionId = $scope->institutionId;
        $activeCycle = ($institutionId !== null)
            ? AcademicCycle::query()->where('institution_id', $institutionId)->where('is_active', true)->first()
            : AcademicCycle::active();
        $activeCycle ??= AcademicCycle::active();
        $cycleId = $activeCycle?->id;

        $canViewExecutive = $this->authorizer->allows($user, CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW);
        $canViewAdmissions = $this->authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW);
        $canViewGuidance = $this->authorizer->allowsAny($user, [
            CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
            CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
        ]);
        $canViewGuidanceReports = $this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_REPORTS_VIEW);
        $canAdviseGuidance = $canViewGuidanceReports || $this->authorizer->allowsAny($user, [
            CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
            CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
            CapabilityCatalog::GUIDANCE_SELECTION_MANAGE,
        ]);
        $canViewProgramHead = $this->authorizer->allows($user, CapabilityCatalog::PROGRAM_HEAD_VIEW);
        $canManageSetup = $this->authorizer->allowsAny($user, [
            CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
            CapabilityCatalog::SETUP_CYCLES_MANAGE,
            CapabilityCatalog::SETUP_ROOMS_MANAGE,
            CapabilityCatalog::SETUP_PROGRAMS_MANAGE,
        ]);

        $registrarData = ($canViewAdmissions || $canViewExecutive) ? $this->getRegistrarData($cycleId, $institutionId) : null;
        $guidanceData = ($canViewGuidance || $canViewExecutive) ? $this->getGuidanceData($cycleId, $institutionId) : null;
        $setupReadinessData = ($canViewExecutive || $canManageSetup) ? $this->getSetupReadinessData($user, $institutionId) : null;
        $academicScopeData = ($canViewExecutive || $canViewAdmissions || $canViewProgramHead || $canAdviseGuidance)
            ? $this->academicScope->for($user, $cycleId, $institutionId, $canViewExecutive || $canViewAdmissions || $canAdviseGuidance)
            : null;
        $programHeadData = $canViewProgramHead ? $this->getProgramHeadData($user, $cycleId, $institutionId) : null;
        $coursePlacementData = $this->getCoursePlacementData(
            $academicScopeData,
            $programHeadData,
            $canViewExecutive,
            $canViewAdmissions,
            $canViewProgramHead,
            $canAdviseGuidance,
            $cycleId,
            $institutionId,
        );

        return [
            'as_of' => now()->toIso8601String(),
            'active_cycle' => $activeCycle ? [
                'id' => $activeCycle->id,
                'name' => $activeCycle->staffLabel(),
                'code' => $activeCycle->code,
            ] : null,
            'actor' => [
                'name' => $user->name,
                'email' => $user->email,
                'office' => $scope->office,
                'capabilities' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ],
            'cycle_roadmap' => $this->roadmap->for($activeCycle, $institutionId),
            'setup_readiness' => $setupReadinessData,
            'academic_scope' => $academicScopeData,
            'course_placement' => $coursePlacementData,
            'admissions' => $canViewExecutive ? $registrarData : null,
            'assessment' => $canViewExecutive ? $this->assessment->for($cycleId, $institutionId) : null,
            'facility_health' => $setupReadinessData['facility_health'] ?? null,
            'supervisory_audit' => $setupReadinessData['supervisory_audit'] ?? null,
            'registrar' => $registrarData,
            'guidance' => $guidanceData,
            'program_head' => $programHeadData,
            'my_activity' => $this->getMyActivity($user),
        ];
    }

    /**
     * Safe AI projection conforming to the published workspace.summary@1 contract.
     *
     * @return array{
     *   summary: array{
     *     institution_id: int,
     *     active_cycle_name: string,
     *     pending_reviews_count: int,
     *     today_sessions_count: int,
     *     upcoming_sessions_count: int,
     *     draft_sessions_count: int,
     *     as_of: string
     *   }
     * }
     */
    public function forXiamSafe(int $institutionId, ?int $cycleId = null): array
    {
        $asOf = now();

        if ($institutionId < 1) {
            return [
                'summary' => [
                    'institution_id' => 0,
                    'active_cycle_name' => 'No active cycle',
                    'pending_reviews_count' => 0,
                    'today_sessions_count' => 0,
                    'upcoming_sessions_count' => 0,
                    'draft_sessions_count' => 0,
                    'as_of' => $asOf->toIso8601String(),
                ],
            ];
        }

        $activeCycle = $cycleId !== null
            ? AcademicCycle::query()->where('institution_id', $institutionId)->where('id', $cycleId)->first()
            : AcademicCycle::query()->where('institution_id', $institutionId)->where('is_active', true)->first();

        $institutionCycles = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->select('id');

        $todayStart = $asOf->copy()->startOfDay();
        $todayEnd = $asOf->copy()->endOfDay();
        $sessionQuery = ExamSession::query()->forWorkspace()
            ->whereIn('academic_cycle_id', $institutionCycles);

        $pendingReviewsCount = AdmissionAttempt::query()->forWorkspace()
            ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
            ->whereIn('status', [AdmissionAttempt::STATUS_PENDING, AdmissionAttempt::STATUS_NEEDS_CORRECTION, 'submitted', 'under_review', 'pending_verification'])
            ->count();

        $todaySessionsCount = (clone $sessionQuery)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->where('status', '!=', ExamSession::STATUS_CANCELLED)
            ->count();

        $upcomingSessionsCount = (clone $sessionQuery)
            ->where('starts_at', '>=', $asOf)
            ->whereNotIn('status', [ExamSession::STATUS_CANCELLED, ExamSession::STATUS_COMPLETED])
            ->count();

        $draftSessionsCount = (clone $sessionQuery)
            ->where('status', ExamSession::STATUS_DRAFT)
            ->count();

        return [
            'summary' => [
                'institution_id' => $institutionId,
                'active_cycle_name' => $activeCycle?->name ?? 'No active cycle',
                'pending_reviews_count' => $pendingReviewsCount,
                'today_sessions_count' => $todaySessionsCount,
                'upcoming_sessions_count' => $upcomingSessionsCount,
                'draft_sessions_count' => $draftSessionsCount,
                'as_of' => $asOf->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $academicScopeData
     * @param  array<string, mixed>|null  $programHeadData
     * @return array{placed: int, quota: int, percent: float}|null
     */
    private function getCoursePlacementData(
        ?array $academicScopeData,
        ?array $programHeadData,
        bool $canViewExecutive,
        bool $canViewAdmissions,
        bool $canViewProgramHead,
        bool $canAdviseGuidance,
        ?int $cycleId,
        ?int $institutionId,
    ): ?array {
        if ($cycleId === null || $institutionId === null
            || ! AcademicCycle::query()->whereKey($cycleId)->where('institution_id', $institutionId)->exists()) {
            return null;
        }

        if ($canViewExecutive || $canViewAdmissions || $canAdviseGuidance) {
            $placed = 0;
            $quota = 0;

            foreach ($academicScopeData['programs'] ?? [] as $program) {
                $placed += (int) ($program['course_placed'] ?? 0);
                $quota += (int) ($program['planned_quota'] ?? 0);
            }

            return [
                'placed' => $placed,
                'quota' => $quota,
                'percent' => $quota > 0 ? round(($placed / $quota) * 100, 1) : 0.0,
            ];
        }

        if ($canViewProgramHead) {
            if ($programHeadData === null || (int) ($programHeadData['programs_count'] ?? 0) === 0) {
                return null;
            }

            $placed = (int) ($programHeadData['allocated'] ?? 0);
            $quota = (int) ($programHeadData['quota'] ?? 0);

            return [
                'placed' => $placed,
                'quota' => $quota,
                'percent' => $quota > 0 ? round(($placed / $quota) * 100, 1) : 0.0,
            ];
        }

        return null;
    }

    /**
     * @return array{
     *   kpis: array{
     *     total: int,
     *     in_pipeline: int,
     *     completed: int,
     *     dismissed: int,
     *     admission_clearances_confirmed: int,
     *     legacy_enrolled_records: int,
     *     yoy_delta?: string,
     *     yoy_comparison?: string,
     *     yoy_growth: string,
     *     quota_target: int,
     *     quota_percentage: float
     *   },
     *   growth_trend: array{labels: list<string>, values: list<int>},
     *   pipeline_distribution: list<array{label: string, value: int, color: string}>,
     *   top_courses: list<array{label: string, value: int, color: string}>
     * }
     */
    private function getRegistrarData(?int $cycleId, ?int $institutionId): array
    {
        return $this->admissions->for($cycleId, $institutionId);
    }

    /**
     * @return array{
     *   kpis: array{
     *     scheduled_sessions: int,
     *     candidates_tested: int,
     *     pending_scoring: int,
     *     turnaround_days: float,
     *     attendance_percentage: float
     *   },
     *   session_trends: array{labels: list<string>, scheduled: list<int>, completed: list<int>},
     *   attendance_distribution: list<array{label: string, value: int, color: string}>
     * }
     */
    private function getGuidanceData(?int $cycleId, ?int $institutionId): array
    {
        $data = $this->assessment->for($cycleId, $institutionId);
        $data['kpis']['turnaround_days'] = 0.0;

        $now = now();
        $weeklyScheduled = [];
        $weeklyCompleted = [];
        $weeklyLabels = [];

        for ($w = 5; $w >= 0; $w--) {
            $wStart = $now->copy()->subWeeks($w)->startOfWeek();
            $wEnd = $wStart->copy()->endOfWeek();
            $weeklyLabels[] = 'W'.(6 - $w);
            $weeklyScheduled[] = ExamSession::query()->forWorkspace()
                ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
                ->when($institutionId !== null, fn ($q) => $q->whereHas('academicCycle', fn ($c) => $c->where('institution_id', $institutionId)))
                ->whereBetween('starts_at', [$wStart, $wEnd])
                ->count();
            $weeklyCompleted[] = ExamSession::query()->forWorkspace()
                ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
                ->when($institutionId !== null, fn ($q) => $q->whereHas('academicCycle', fn ($c) => $c->where('institution_id', $institutionId)))
                ->whereIn('status', ['completed', 'finalized'])
                ->whereBetween('starts_at', [$wStart, $wEnd])
                ->count();
        }

        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $todayAppointments = ConsultationAssignment::query()
            ->whereHas('consultationSession', function ($q) use ($cycleId, $institutionId, $todayStart, $todayEnd): void {
                $q->when($cycleId !== null, fn ($sq) => $sq->where('academic_cycle_id', $cycleId))
                    ->when($institutionId !== null, fn ($sq) => $sq->whereHas('academicCycle', fn ($c) => $c->where('institution_id', $institutionId)))
                    ->whereBetween('scheduled_at', [$todayStart, $todayEnd])
                    ->where('status', '!=', ConsultationSession::STATUS_CANCELLED);
            })
            ->count();

        $openReferrals = ProgramFollowUp::query()
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->whereHas('attempt', function ($q) use ($cycleId, $institutionId): void {
                $q->forWorkspace()
                    ->when($cycleId !== null, fn ($sq) => $sq->where('academic_cycle_id', $cycleId))
                    ->when($institutionId !== null, fn ($sq) => $sq->where('institution_id', $institutionId));
            })
            ->count();

        $openInquiries = ApplicantOfficeInquiry::query()
            ->when($institutionId !== null, fn ($q) => $q->where('institution_id', $institutionId))
            ->where('office', 'guidance')
            ->whereIn('status', ['submitted', 'in_progress', 'pending_office'])
            ->count();

        $pendingAdvising = AdmissionAttempt::query()->forWorkspace()
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->when($institutionId !== null, fn ($q) => $q->where('institution_id', $institutionId))
            ->whereHas('examAssignments.attendanceRecord', fn ($q) => $q->where('outcome', 'present'))
            ->whereDoesntHave('consultationDecisions')
            ->count();

        $data['kpis']['today_release_appointments'] = $todayAppointments;
        $data['kpis']['pending_referrals'] = $openReferrals;
        $data['kpis']['open_inquiries'] = $openInquiries;
        $data['kpis']['pending_advising_qualifiers'] = $pendingAdvising;

        $data['session_trends'] = [
            'labels' => $weeklyLabels,
            'scheduled' => $weeklyScheduled,
            'completed' => $weeklyCompleted,
        ];

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function getProgramHeadData(User $user, ?int $cycleId, ?int $institutionId): array
    {
        $scope = $user->actorScope();
        $assignedCodes = $scope->programCodes;
        $departmentId = $scope->departmentId ?? ($user->staffProfile?->office === 'program' ? $user->staffProfile?->department_id : null);
        $isDean = $departmentId !== null;

        $department = $departmentId !== null
            ? Department::query()
                ->where('is_active', true)
                ->when($institutionId !== null, fn ($q) => $q->where('institution_id', $institutionId))
                ->select(['id', 'code', 'name'])
                ->find($departmentId)
            : null;

        if (empty($assignedCodes)) {
            return [
                'is_dean' => $isDean,
                'department_code' => $department?->code,
                'department_name' => $department?->name,
                'program_code' => null,
                'program_name' => null,
                'programs_count' => 0,
                'quota' => 0,
                'allocated' => 0,
                'open_slots' => 0,
                'percentage' => 0.0,
                'potential_candidates' => 0,
                'all_choice_candidates' => 0,
                'advising_pool' => 0,
                'pending_follow_ups' => 0,
                'cleared_candidates' => 0,
            ];
        }

        $programs = Program::query()
            ->active()
            ->with('department:id,code,name')
            ->when($institutionId !== null, fn ($q) => $q->where('institution_id', $institutionId))
            ->whereIn('code', $assignedCodes)
            ->orderBy('code')
            ->get();

        if ($programs->isEmpty()) {
            return [
                'is_dean' => $isDean,
                'department_code' => $department?->code,
                'department_name' => $department?->name,
                'program_code' => $assignedCodes[0] ?? null,
                'program_name' => null,
                'programs_count' => 0,
                'quota' => 0,
                'allocated' => 0,
                'open_slots' => 0,
                'percentage' => 0.0,
                'potential_candidates' => 0,
                'all_choice_candidates' => 0,
                'advising_pool' => 0,
                'pending_follow_ups' => 0,
                'cleared_candidates' => 0,
            ];
        }

        $firstProgram = $programs->first();
        if ($department === null && $firstProgram?->department !== null) {
            $department = $firstProgram->department;
        }
        if (! $isDean && $programs->count() > 1 && $programs->pluck('department_id')->filter()->unique()->count() === 1 && $scope->departmentId !== null) {
            $isDean = true;
        }

        $programCodes = $programs->pluck('code')->map(fn ($code) => (string) $code)->all();
        $programIds = $programs->pluck('id')->map(fn ($id) => (int) $id)->all();

        $attemptsBase = AdmissionAttempt::query()->forWorkspace()
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->when($institutionId !== null, fn ($q) => $q->where('institution_id', $institutionId));

        $totalApplicants = (clone $attemptsBase)
            ->whereIn('preferred_program_code', $programCodes)
            ->count();

        $allChoiceApplicants = (clone $attemptsBase)
            ->where(function ($q) use ($programCodes): void {
                $q->whereIn('preferred_program_code', $programCodes)
                    ->orWhereIn('program_preference_2_code', $programCodes)
                    ->orWhereIn('program_preference_3_code', $programCodes);
            })
            ->count();

        $quotas = $cycleId
            ? ProgramQuota::query()->where('academic_cycle_id', $cycleId)->whereIn('program_id', $programIds)->get()->keyBy('program_id')
            : collect();

        $quota = 0;
        foreach ($programs as $prog) {
            $row = $quotas->get($prog->id);
            $quota += $row ? (int) $row->slots : 90;
        }

        $practiceSetId = app(PracticeWorkspace::class)->currentId();
        $committedMap = $cycleId
            ? QuotaReservation::batchCommittedForCycle($cycleId, $programIds, practiceSetId: $practiceSetId)
            : [];
        $committed = array_sum($committedMap);
        $openSlots = max(0, $quota - $committed);
        $pct = $quota > 0 ? round(($committed / $quota) * 100, 1) : 0.0;

        $advisingPool = (clone $attemptsBase)
            ->whereIn('preferred_program_code', $programCodes)
            ->whereHas('examAssignments.attendanceRecord', fn ($q) => $q->where('outcome', 'present'))
            ->count();

        $clearedCandidates = (clone $attemptsBase)
            ->whereIn('preferred_program_code', $programCodes)
            ->whereHas('latestClearanceReceipt', fn ($q) => $q->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED))
            ->count();

        $pendingFollowUps = ProgramFollowUp::query()
            ->whereIn('program_id', $programIds)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->whereHas('attempt', function ($q) use ($cycleId, $institutionId): void {
                $q->forWorkspace()
                    ->when($cycleId !== null, fn ($sq) => $sq->where('academic_cycle_id', $cycleId))
                    ->when($institutionId !== null, fn ($sq) => $sq->where('institution_id', $institutionId));
            })
            ->count();

        return [
            'is_dean' => $isDean,
            'department_code' => $department?->code,
            'department_name' => $department?->name,
            'program_code' => $programs->count() === 1 ? (string) $firstProgram->code : ($department?->code ?? (string) $firstProgram->code),
            'program_name' => $programs->count() === 1 ? (string) $firstProgram->name : ($department?->name ?? (string) $firstProgram->name),
            'programs_count' => $programs->count(),
            'quota' => $quota,
            'allocated' => $committed,
            'open_slots' => $openSlots,
            'percentage' => $pct,
            'potential_candidates' => $totalApplicants,
            'all_choice_candidates' => $allChoiceApplicants,
            'advising_pool' => $advisingPool,
            'pending_follow_ups' => $pendingFollowUps,
            'cleared_candidates' => $clearedCandidates,
        ];
    }

    /**
     * @return list<array{id: string, event: string, summary: string, created_at: string}>
     */
    private function getMyActivity(User $user): array
    {
        $events = AuditEvent::query()
            ->where(function ($q) use ($user) {
                $q->where('actor', (string) $user->id)
                    ->orWhere('actor', $user->email);
            })
            ->latest('created_at')
            ->limit(6)
            ->get();

        if ($events->isEmpty()) {
            return [];
        }

        return $events->map(fn (AuditEvent $e) => [
            'id' => (string) $e->id,
            'event' => (string) $e->event_type,
            'summary' => (string) ($e->payload['summary'] ?? $e->payload['event'] ?? $e->event_type),
            'created_at' => $e->created_at->toIso8601String(),
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function getSetupReadinessData(User $user, ?int $institutionId): array
    {
        $readiness = $this->setupReadiness->handle($user);
        $tracks = collect($readiness['groups'] ?? [])->map(function (array $group): array {
            $checks = collect($group['categories'] ?? [])->pluck('checks')->flatten(1);
            $total = $checks->count();
            $passed = $checks->where('passed', true)->count();

            return [
                'key' => $group['key'],
                'label' => $group['label'],
                'passed' => $passed,
                'total' => $total,
                'percent' => $total === 0 ? 0 : (int) round(($passed / $total) * 100),
            ];
        })->values()->all();

        $rooms = Room::query()->where('institution_id', $institutionId)->where('is_active', true);
        $delegations = InstitutionalSetupDelegation::query()
            ->active()
            ->where('institution_id', $institutionId)
            ->with(['recipient:id,name,email', 'actor:id,name'])
            ->get();

        $auditEvents = AuditEvent::query()->where(function ($query) use ($institutionId): void {
            $query->whereNull('payload->institution_id')
                ->orWhere('payload->institution_id', $institutionId)
                ->orWhere('payload->institution_id', (string) $institutionId);
        });

        $facilityHealth = [
            'open_application_windows' => ApplicationWindow::query()
                ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
                ->where('status', 'published')
                ->where('opens_at', '<=', now())
                ->where('closes_at', '>=', now())
                ->count(),
            'active_exam_rooms' => (clone $rooms)->count(),
            'seat_capacity' => (int) (clone $rooms)->sum('capacity'),
            'active_setup_delegations' => $delegations->map(fn ($delegation) => [
                'id' => $delegation->id,
                'reason' => $delegation->reason,
                'scopes' => $delegation->scopes ?? [],
                'recipient' => $delegation->recipient?->only(['id', 'name', 'email']),
                'actor' => $delegation->actor?->only(['id', 'name']),
                'granted_at' => $delegation->granted_at?->toIso8601String(),
            ])->all(),
        ];

        $supervisoryAudit = [
            'last_24_hours' => (clone $auditEvents)->where('created_at', '>=', now()->subDay())->count(),
            'last_7_days' => (clone $auditEvents)->where('created_at', '>=', now()->subDays(7))->count(),
            'recent_events' => (clone $auditEvents)->latest('created_at')->limit(8)->get()->map(fn ($event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'actor' => $event->actor,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->all(),
        ];

        return [
            'overall_percent' => (int) ($readiness['overall_percent'] ?? 0),
            'passed' => (int) ($readiness['passed'] ?? 0),
            'total' => (int) ($readiness['total'] ?? 0),
            'tracks' => $tracks,
            'facility_health' => $facilityHealth,
            'supervisory_audit' => $supervisoryAudit,
        ];
    }
}
