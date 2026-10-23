<?php

namespace App\Support\Navigation;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Queries\EnrollmentEligibilityQuery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\User;
use App\ProgramHead\Queries\PendingProgramReviewQuery;
use App\ResultSheet\Queries\ReleaseOverviewQuery;
use Illuminate\Database\Eloquent\Builder;

final class OperationalQueueBadgeProvider
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /**
     * @return array{
     *   badges: array{
     *     admissions: array{kind: 'count', count: int, unit: string, variant: 'neutral'|'info'|'warning'|'critical', pulse?: bool, accessible_label: string}|null,
     *     exam_scheduling: array{kind: 'count', count: int, unit: string, variant: 'neutral'|'info'|'warning'|'critical', pulse?: bool, accessible_label: string}|null,
     *     exam_monitoring: array{kind: 'count', count: int, unit: string, variant: 'neutral'|'info'|'warning'|'critical', pulse?: bool, accessible_label: string}|null,
     *     scoring: array{kind: 'count', count: int, unit: string, variant: 'neutral'|'info'|'warning'|'critical', pulse?: bool, accessible_label: string}|null,
     *     result_documents: array{kind: 'count', count: int, unit: string, variant: 'neutral'|'info'|'warning'|'critical', pulse?: bool, accessible_label: string}|null,
     *     consultation_scheduling: array{kind: 'count', count: int, unit: string, variant: 'neutral'|'info'|'warning'|'critical', pulse?: bool, accessible_label: string}|null,
     *     consultation_release: array{kind: 'count', count: int, unit: string, variant: 'neutral'|'info'|'warning'|'critical', pulse?: bool, accessible_label: string}|null,
     *     admission_clearance: array{kind: 'count', count: int, unit: string, variant: 'neutral'|'info'|'warning'|'critical', pulse?: bool, accessible_label: string}|null
     *   },
     *   watermark: array<string, int>
     * }
     */
    public function forUser(User $user): array
    {
        $empty = [
            'admissions' => null,
            'exam_scheduling' => null,
            'exam_monitoring' => null,
            'scoring' => null,
            'result_documents' => null,
            'consultation_scheduling' => null,
            'consultation_release' => null,
            'admission_clearance' => null,
        ];

        if ($user->applicantAccount !== null) {
            return ['badges' => $empty, 'watermark' => []];
        }

        $profile = $user->staffProfile;
        if ($profile === null || ! $profile->isActive() || $profile->deactivated_at !== null) {
            return ['badges' => $empty, 'watermark' => []];
        }

        $institutionId = (int) ($user->actorScope()->institutionId ?? $profile->institution_id ?? 0);
        if ($institutionId < 1) {
            return ['badges' => $empty, 'watermark' => []];
        }

        $activeCycleId = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->value('id');
        $activeCycleId = $activeCycleId !== null ? (int) $activeCycleId : null;

        $badges = $empty;
        $watermark = [];
        $academicReviews = $this->academicReviewBadges($user, $institutionId);
        $badges = array_merge($badges, $academicReviews['badges']);
        $watermark = array_merge($watermark, $academicReviews['watermark']);

        if ($this->authorizer->allows(
            $user,
            CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW,
            ['institution_id' => $institutionId, 'office' => 'registrar'],
        ) || $this->authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)) {
            $pendingApplicationsCount = (int) $this->scopeAttemptCycle(
                AdmissionAttempt::query()->forWorkspace()->where('status', AdmissionAttempt::STATUS_PENDING),
                $institutionId,
                $activeCycleId,
            )->count();

            $watermark['admissions'] = $pendingApplicationsCount;
            if ($pendingApplicationsCount > 0) {
                $badges['admissions'] = [
                    'kind' => 'count',
                    'count' => $pendingApplicationsCount,
                    'unit' => 'pending_applications',
                    'variant' => 'info',
                    'accessible_label' => $pendingApplicationsCount === 1
                        ? '1 pending application'
                        : "{$pendingApplicationsCount} pending applications",
                ];
            }
        }

        $canManageExams = $this->authorizer->allowsAny(
            $user,
            CapabilityCatalog::examSessionManagementCapabilities(),
            ['institution_id' => $institutionId, 'office' => 'guidance'],
        ) || $this->authorizer->allowsAny($user, CapabilityCatalog::examSessionManagementCapabilities());

        if ($canManageExams) {
            $schedulingCount = (int) $this->scopeExamCycle(
                ExamSession::query()->forWorkspace()->where('status', ExamSession::STATUS_DRAFT),
                $institutionId,
                $activeCycleId,
            )->count();

            $watermark['exam_scheduling'] = $schedulingCount;
            if ($schedulingCount > 0) {
                $badges['exam_scheduling'] = [
                    'kind' => 'count',
                    'count' => $schedulingCount,
                    'unit' => 'active_exam_sessions',
                    'variant' => 'neutral',
                    'accessible_label' => $schedulingCount === 1
                        ? '1 draft exam session'
                        : "{$schedulingCount} draft exam sessions",
                ];
            }
        }

        if ($canManageExams) {
            $monitoringQuery = $this->scopeExamCycle(
                ExamSession::query()->forWorkspace()->whereIn('status', [
                    ExamSession::STATUS_PUBLISHED,
                    ExamSession::STATUS_IN_PROGRESS,
                ]),
                $institutionId,
                $activeCycleId,
            );

            $monitoringAggregate = $monitoringQuery
                ->selectRaw("COUNT(*) as total_count, COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) as running_count")
                ->first();
            $monitoringCount = (int) ($monitoringAggregate->total_count ?? 0);
            $runningCount = (int) ($monitoringAggregate->running_count ?? 0);

            $watermark['exam_monitoring'] = $monitoringCount;
            $watermark['exam_monitoring_running'] = $runningCount;
            if ($monitoringCount > 0) {
                $badges['exam_monitoring'] = [
                    'kind' => 'count',
                    'count' => $monitoringCount,
                    'unit' => 'active_monitoring_sessions',
                    'variant' => 'info',
                    'pulse' => $runningCount > 0,
                    'accessible_label' => $monitoringCount === 1
                        ? '1 active monitoring session'
                        : "{$monitoringCount} active monitoring sessions",
                ];
            }
        }

        if ($this->authorizer->allowsAny($user, [
            CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        ])) {
            $scoringCount = (int) $this->scopeExamCycle(
                ExamSession::query()->forWorkspace()
                    ->where('exam_sessions.status', ExamSession::STATUS_COMPLETED)
                    ->where(function (Builder $unfinished): void {
                        $unfinished->whereDoesntHave('gradingSession')
                            ->orWhereHas('gradingSession', fn (Builder $grading) => $grading->whereIn('status', ['open', 'review']));
                    }),
                $institutionId,
                $activeCycleId,
            )->count();
            $watermark['scoring'] = $scoringCount;
            if ($scoringCount > 0) {
                $badges['scoring'] = [
                    'kind' => 'count',
                    'count' => $scoringCount,
                    'unit' => 'pending_scoring_sessions',
                    'variant' => 'info',
                    'accessible_label' => $scoringCount === 1
                        ? '1 session awaiting scoring'
                        : "{$scoringCount} sessions awaiting scoring",
                ];
            }
        }

        if ($this->authorizer->allowsAny($user, [
            CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
            CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
        ])) {
            $unprintedOverview = app(ReleaseOverviewQuery::class)->forInertia(
                $institutionId,
                100,
                array_filter([
                    'status' => 'unprinted',
                    'academic_cycle_id' => $activeCycleId,
                ], static fn (mixed $value): bool => $value !== null),
            );
            $printingCount = count($unprintedOverview['sessions'] ?? []);

            $watermark['result_documents'] = $printingCount;
            if ($printingCount > 0) {
                $badges['result_documents'] = [
                    'kind' => 'count',
                    'count' => $printingCount,
                    'unit' => 'unprinted_result_sessions',
                    'variant' => 'info',
                    'accessible_label' => $printingCount === 1
                        ? '1 session awaiting result printing'
                        : "{$printingCount} sessions awaiting result printing",
                ];
            }
        }

        if ($this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)) {
            $releaseSchedulingCount = (int) $this->scopeConsultationCycle(
                ConsultationSession::query()->where('status', ConsultationSession::STATUS_DRAFT),
                $institutionId,
                $activeCycleId,
            )->count();

            $watermark['consultation_scheduling'] = $releaseSchedulingCount;
            if ($releaseSchedulingCount > 0) {
                $badges['consultation_scheduling'] = [
                    'kind' => 'count',
                    'count' => $releaseSchedulingCount,
                    'unit' => 'active_release_sessions',
                    'variant' => 'neutral',
                    'accessible_label' => $releaseSchedulingCount === 1
                        ? '1 draft Release Day session'
                        : "{$releaseSchedulingCount} draft Release Day sessions",
                ];
            }
        }

        if ($this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)) {
            $releaseDayAggregate = $this->scopeConsultationCycle(
                ConsultationSession::query()->whereIn('status', [
                    ConsultationSession::STATUS_PUBLISHED,
                    ConsultationSession::STATUS_IN_PROGRESS,
                ]),
                $institutionId,
                $activeCycleId,
            )
                ->selectRaw("COUNT(*) as total_count, COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) as running_count")
                ->first();
            $releaseDayCount = (int) ($releaseDayAggregate->total_count ?? 0);
            $releaseDayRunningCount = (int) ($releaseDayAggregate->running_count ?? 0);

            $watermark['consultation_release'] = $releaseDayCount;
            $watermark['consultation_release_running'] = $releaseDayRunningCount;
            if ($releaseDayCount > 0) {
                $badges['consultation_release'] = [
                    'kind' => 'count',
                    'count' => $releaseDayCount,
                    'unit' => 'open_release_sessions',
                    'variant' => 'info',
                    'pulse' => $releaseDayRunningCount > 0,
                    'accessible_label' => $releaseDayCount === 1
                        ? '1 open Release Day session'
                        : "{$releaseDayCount} open Release Day sessions",
                ];
            }
        }
        if ($this->authorizer->allowsAny($user, [
            CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY,
            CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_CONFIRM,
        ], ['institution_id' => $institutionId, 'office' => 'registrar']) || $this->authorizer->allowsAny($user, [
            CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY,
            CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_CONFIRM,
        ])) {
            $candidateQuery = $this->scopeAttemptCycle(
                AdmissionAttempt::query()->forWorkspace()
                    ->where('status', AdmissionAttempt::STATUS_ACCEPTED),
                $institutionId,
                $activeCycleId,
            )->with(['identity', 'clearanceReceipts', 'latestClearanceReceipt']);

            $readyCount = 0;
            foreach ($candidateQuery->get() as $attempt) {
                if (EnrollmentEligibilityQuery::determineClearanceStatus($attempt) === 'ready') {
                    $readyCount++;
                }
            }

            $watermark['admission_clearance'] = $readyCount;
            if ($readyCount > 0) {
                $badges['admission_clearance'] = [
                    'kind' => 'count',
                    'count' => $readyCount,
                    'unit' => 'ready_for_clearance',
                    'variant' => 'info',
                    'accessible_label' => $readyCount === 1
                        ? '1 applicant ready for clearance'
                        : "{$readyCount} applicants ready for clearance",
                ];
            }
        }

        return [
            'badges' => $badges,
            'watermark' => $watermark,
        ];
    }

    /**
     * @return array{badges: array<string, array{kind: 'count', count: int, unit: string, variant: 'warning', accessible_label: string}>, watermark: array<string, int>}
     */
    private function academicReviewBadges(User $user, int $institutionId): array
    {
        $scope = $user->actorScope();
        if ($scope->office !== 'program' || $scope->programCodes === []
            || $scope->institutionId === null || (int) $scope->institutionId !== $institutionId || $scope->activeCycleCode === null) {
            return ['badges' => [], 'watermark' => []];
        }

        $reviewCycleId = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('code', $scope->activeCycleCode)
            ->where('is_active', true)
            ->value('id');
        if ($reviewCycleId === null) {
            return ['badges' => [], 'watermark' => []];
        }

        $ownedDepartmentIds = Department::query()->where('institution_id', $institutionId)
            ->where('dean_user_id', $user->id)->where('is_active', true)->pluck('id')->all();
        $isDean = $ownedDepartmentIds !== [];
        $programs = Program::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->whereIn('code', array_values(array_filter($scope->programCodes, 'is_string')))
            ->when($isDean, fn (Builder $query) => $query->whereIn('department_id', $ownedDepartmentIds))
            ->orderBy('code')
            ->get();

        $badges = [];
        $watermark = [];
        $ownedCount = 0;

        foreach ($programs as $program) {
            $authorizationScope = [
                'institution_id' => $institutionId,
                'program' => $program->code,
                'cycle' => $scope->activeCycleCode,
            ];
            if (! $this->authorizer->decideFresh($user, CapabilityCatalog::PROGRAM_HEAD_VIEW, $authorizationScope)->allowed
                || ! $this->authorizer->decideFresh($user, CapabilityCatalog::PROGRAM_HEAD_FOLLOW_UP_RESOLVE, $authorizationScope)->allowed) {
                continue;
            }

            $count = PendingProgramReviewQuery::countFor($program, (int) $reviewCycleId);
            $ownedCount += $count;
            if (! $isDean) {
                $key = 'my_program_'.strtolower($program->code);
                $watermark[$key] = $count;
                if ($count > 0) {
                    $badges[$key] = [
                        'kind' => 'count',
                        'count' => $count,
                        'unit' => 'pending_program_reviews',
                        'variant' => 'warning',
                        'accessible_label' => $count === 1
                            ? '1 pending academic review'
                            : "{$count} pending academic reviews",
                    ];
                }
            }
        }

        if ($isDean && $programs->isNotEmpty()) {
            $watermark['my_programs'] = $ownedCount;
            if ($ownedCount > 0) {
                $badges['my_programs'] = [
                    'kind' => 'count',
                    'count' => $ownedCount,
                    'unit' => 'pending_program_reviews',
                    'variant' => 'warning',
                    'accessible_label' => $ownedCount === 1
                        ? '1 pending academic review across your programs'
                        : "{$ownedCount} pending academic reviews across your programs",
                ];
            }
        }

        return ['badges' => $badges, 'watermark' => $watermark];
    }

    /**
     * @param  Builder<AdmissionAttempt>  $query
     * @return Builder<AdmissionAttempt>
     */
    private function scopeAttemptCycle(Builder $query, int $institutionId, ?int $activeCycleId): Builder
    {
        $query->where('institution_id', $institutionId);

        if ($activeCycleId !== null) {
            return $query->where('academic_cycle_id', $activeCycleId);
        }

        return $query->whereHas('academicCycle', fn (Builder $cycle) => $cycle->where('institution_id', $institutionId));
    }

    /**
     * @param  Builder<ExamSession>  $query
     * @return Builder<ExamSession>
     */
    private function scopeExamCycle(Builder $query, int $institutionId, ?int $activeCycleId): Builder
    {
        if ($activeCycleId !== null) {
            return $query->where('academic_cycle_id', $activeCycleId);
        }

        return $query->whereHas('academicCycle', fn (Builder $cycle) => $cycle->where('institution_id', $institutionId));
    }

    /**
     * @param  Builder<ConsultationSession>  $query
     * @return Builder<ConsultationSession>
     */
    private function scopeConsultationCycle(Builder $query, int $institutionId, ?int $activeCycleId): Builder
    {
        if ($activeCycleId !== null) {
            return $query->where('academic_cycle_id', $activeCycleId);
        }

        return $query->whereHas('academicCycle', fn (Builder $cycle) => $cycle->where('institution_id', $institutionId));
    }
}
