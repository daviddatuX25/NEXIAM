<?php

namespace App\Reports\Queries;

use App\Admission\Support\QuotaReservation;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Release\ResultDocument;
use App\Models\Reports\ReportTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ReportProjection
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer
    ) {}

    /**
     * @return array{
     *   as_of: string,
     *   cycles: list<array{id: int, name: string, code: string, is_active: bool}>,
     *   selected_cycle_id: int|null,
     *   can_registrar: bool,
     *   can_guidance: bool,
     *   pipeline: list<array{label: string, value: int, color: string}>,
     *   demographics: array{
     *     sex: list<array{label: string, value: int, color: string}>,
     *     age: list<array{label: string, value: int, color: string}>
     *   },
     *   registrar_reports: list<array{type: string, title: string, description: string, domain: string}>,
     *   guidance_reports: list<array{type: string, title: string, description: string, domain: string}>
     * }
     */
    public function for(User $user, ?int $cycleId = null): array
    {
        $canRegistrar = $this->authorizer->allows($user, CapabilityCatalog::REGISTRAR_REPORTS_VIEW);
        $canGuidance = $this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_REPORTS_VIEW);
        $canExecutive = $this->authorizer->allows($user, CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW);
        $canProgramHead = $this->authorizer->allows($user, CapabilityCatalog::PROGRAM_HEAD_VIEW);
        $cycles = AcademicCycle::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        $selectedCycle = $cycleId !== null
            ? $cycles->firstWhere('id', $cycleId)
            : ($cycles->firstWhere('is_active', true) ?? $cycles->first());

        $effectiveCycleId = $selectedCycle?->id;
        $isGlobalScope = $canRegistrar || $canGuidance || $canExecutive;
        $scopedProgramCodes = $isGlobalScope ? null : ($user->actorScope()->programCodes ?: ['__none__']);

        return [
            'as_of' => now()->toIso8601String(),
            'cycles' => $cycles->map(fn (AcademicCycle $c) => [
                'id' => $c->id,
                'name' => $c->staffLabel(),
                'code' => $c->code,
                'is_active' => (bool) $c->is_active,
            ])->all(),
            'selected_cycle_id' => $effectiveCycleId,
            'can_registrar' => $canRegistrar || $canExecutive,
            'can_guidance' => $canGuidance || $canExecutive,
            'can_admin' => $canExecutive || $this->authorizer->allows($user, CapabilityCatalog::SETUP_INSTITUTION_MANAGE),
            'can_program_head' => $canProgramHead || $canExecutive,
            'pipeline' => $this->getPipelineDistribution($effectiveCycleId, $scopedProgramCodes),
            'demographics' => [
                'sex' => $this->getSexDistribution($effectiveCycleId, $scopedProgramCodes),
                'age' => $this->getAgeDistribution($effectiveCycleId, $scopedProgramCodes),
                'strands' => $this->getStrandDistribution($effectiveCycleId, $scopedProgramCodes),
            ],
            'analytics' => $this->getInteractiveAnalytics($user, $effectiveCycleId, $isGlobalScope),
            'registrar_reports' => [
                [
                    'type' => 'course-quota-qualifiers',
                    'title' => 'Course Quota Qualifiers',
                    'description' => 'Qualified applicants ranked against each program quota with reservation fill rates.',
                    'domain' => 'registrar',
                    'surface_key' => 'course_quota_qualifiers',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'course_quota_qualifiers'),
                    'active_template' => $this->activeTemplatePayload('course_quota_qualifiers'),
                ],
                [
                    'type' => 'applications-census',
                    'title' => 'Applications Summary Census',
                    'description' => 'Complete applicant register with personal biographical data, senior high strand, GWA, and final status.',
                    'domain' => 'registrar',
                    'surface_key' => 'applications_census',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'applications_census'),
                    'active_template' => $this->activeTemplatePayload('applications_census'),
                ],
                [
                    'type' => 'feeder-school-origin',
                    'title' => 'Feeder High School & Geographic Origin Census',
                    'description' => 'Feeder high school distribution, municipality census, and regional geographic concentration.',
                    'domain' => 'registrar',
                    'surface_key' => 'feeder_high_school_origin',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'feeder_high_school_origin'),
                    'active_template' => $this->activeTemplatePayload('feeder_high_school_origin'),
                ],
                [
                    'type' => 'daily-admissions-logbook',
                    'title' => 'Daily Admissions Counter Logbook',
                    'description' => 'Daily F2F intake and receiving logbook of applicants reporting with physical document requirements.',
                    'domain' => 'registrar',
                    'surface_key' => 'daily_admissions_logbook',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'daily_admissions_logbook'),
                    'active_template' => $this->activeTemplatePayload('daily_admissions_logbook'),
                ],
            ],
            'guidance_reports' => [
                [
                    'type' => 'passing-report',
                    'title' => 'Passing Candidates Census',
                    'description' => 'Official institutional roster of examinees meeting composite qualification cut-offs with summary pass-rate and stanine bands.',
                    'domain' => 'guidance',
                    'surface_key' => 'passing_report',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'passing_report'),
                    'active_template' => $this->activeTemplatePayload('passing_report'),
                ],
                [
                    'type' => 'exam-attendance',
                    'title' => 'Exam Attendance & Absenteeism',
                    'description' => 'Session-by-session breakdown of registered, present, and no-show candidates with venue details.',
                    'domain' => 'guidance',
                    'surface_key' => 'exam_attendance',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'exam_attendance'),
                    'active_template' => $this->activeTemplatePayload('exam_attendance'),
                ],
                [
                    'type' => 'room-utilization',
                    'title' => 'Room Capacity & Venue Utilization',
                    'description' => 'Seating capacity fill rate, room occupancy, and schedule efficiency per campus room.',
                    'domain' => 'guidance',
                    'surface_key' => 'room_utilization',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'room_utilization'),
                    'active_template' => $this->activeTemplatePayload('room_utilization'),
                ],
                [
                    'type' => 'score-distribution',
                    'title' => 'Score & Stanine Distribution',
                    'description' => 'Mean, median, and percentile bands across all tested aptitude subtests.',
                    'domain' => 'guidance',
                    'surface_key' => 'score_distribution',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'score_distribution'),
                    'active_template' => $this->activeTemplatePayload('score_distribution'),
                ],
                [
                    'type' => 'session-attendance-sheet',
                    'title' => 'Exam Session Attendance Logbook',
                    'description' => 'Official examinee seating and attendance verification sheet for Test Administrators per exam batch.',
                    'domain' => 'guidance',
                    'surface_key' => 'session_attendance_sheet',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'session_attendance_sheet'),
                    'active_template' => $this->activeTemplatePayload('session_attendance_sheet'),
                ],
            ],
            'admin_reports' => [
                [
                    'type' => 'institutional-executive-summary',
                    'title' => 'Institutional Executive & Quota Summary',
                    'description' => 'Official institutional capacity fill rate, university-wide quota utilization, and candidate qualification totals.',
                    'domain' => 'admin',
                    'surface_key' => 'institutional_executive_summary',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'institutional_executive_summary'),
                    'active_template' => $this->activeTemplatePayload('institutional_executive_summary'),
                ],
                [
                    'type' => 'staff-accomplishment-report',
                    'title' => 'Universal Staff Accomplishment & Activity Audit',
                    'description' => 'Operational milestone audit tracking applications verified, exam sessions conducted, answer sheets scored, and clearances confirmed.',
                    'domain' => 'admin',
                    'surface_key' => 'staff_accomplishment_report',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'staff_accomplishment_report'),
                    'active_template' => $this->activeTemplatePayload('staff_accomplishment_report'),
                ],
                [
                    'type' => 'campus-venue-capacity-audit',
                    'title' => 'Campus Venue Capacity & Utilization Audit',
                    'description' => 'Institution-wide examination venue efficiency, room slot utilization, and seat occupancy audit.',
                    'domain' => 'admin',
                    'surface_key' => 'room_utilization',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'room_utilization'),
                    'active_template' => $this->activeTemplatePayload('room_utilization'),
                ],
            ],
            'program_reports' => [
                [
                    'type' => 'college-program-quota-summary',
                    'title' => 'College Program Quota & Enrollment Summary',
                    'description' => 'College and department level breakdown of approved program quotas, 1st-choice demand, placed candidates, and open slots.',
                    'domain' => 'program',
                    'surface_key' => 'college_program_quota_summary',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'college_program_quota_summary'),
                    'active_template' => $this->activeTemplatePayload('college_program_quota_summary'),
                ],
                [
                    'type' => 'course-quota-qualifiers',
                    'title' => 'Own-Program Quota Qualifiers',
                    'description' => 'Pre-enrollment qualified applicants ranked against program quotas with reservation fill rates.',
                    'domain' => 'program',
                    'surface_key' => 'course_quota_qualifiers',
                    'can_configure_template' => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, 'course_quota_qualifiers'),
                    'active_template' => $this->activeTemplatePayload('course_quota_qualifiers'),
                ],
            ],
        ];
    }

    /**
     * @param  list<string>|null  $scopedProgramCodes
     * @return list<array{label: string, value: int, color: string}>
     */
    private function getPipelineDistribution(?int $cycleId, ?array $scopedProgramCodes = null): array
    {
        $counts = AdmissionAttempt::query()->forPracticeSet(null)
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->when($scopedProgramCodes !== null, fn ($q) => $q->whereIn('preferred_program_code', $scopedProgramCodes))
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $colors = [
            'pending' => '#94a3b8',
            'needs_correction' => '#f59e0b',
            'accepted' => '#3b82f6',
            'dismissed' => '#ef4444',
            'archived' => '#64748b',
        ];

        $out = [];
        foreach ($counts as $st => $cnt) {
            $out[] = [
                'label' => ucfirst(str_replace('_', ' ', (string) $st)),
                'value' => (int) $cnt,
                'color' => $colors[$st] ?? '#94a3b8',
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>|null  $scopedProgramCodes
     * @return list<array{label: string, value: int, color: string}>
     */
    private function getSexDistribution(?int $cycleId, ?array $scopedProgramCodes = null): array
    {
        $counts = AdmissionAttempt::query()->forPracticeSet(null)
            ->when($cycleId !== null, fn ($q) => $q->where('admission_attempts.academic_cycle_id', $cycleId))
            ->when($scopedProgramCodes !== null, fn ($q) => $q->whereIn('admission_attempts.preferred_program_code', $scopedProgramCodes))
            ->join('applicant_identities', 'admission_attempts.applicant_identity_id', '=', 'applicant_identities.id')
            ->select('applicant_identities.sex', DB::raw('COUNT(*) as total'))
            ->groupBy('applicant_identities.sex')
            ->pluck('total', 'applicant_identities.sex')
            ->all();

        return [
            ['label' => 'Female', 'value' => (int) ($counts['female'] ?? 0), 'color' => '#ec4899'],
            ['label' => 'Male', 'value' => (int) ($counts['male'] ?? 0), 'color' => '#38bdf8'],
            ['label' => 'Unspecified', 'value' => (int) ($counts['unspecified'] ?? 0), 'color' => '#94a3b8'],
        ];
    }

    /**
     * @param  list<string>|null  $scopedProgramCodes
     * @return list<array{label: string, value: int, color: string}>
     */
    private function getAgeDistribution(?int $cycleId, ?array $scopedProgramCodes = null): array
    {
        $birthdates = AdmissionAttempt::query()->forPracticeSet(null)
            ->when($cycleId !== null, fn ($q) => $q->where('admission_attempts.academic_cycle_id', $cycleId))
            ->when($scopedProgramCodes !== null, fn ($q) => $q->whereIn('admission_attempts.preferred_program_code', $scopedProgramCodes))
            ->join('applicant_identities', 'admission_attempts.applicant_identity_id', '=', 'applicant_identities.id')
            ->whereNotNull('applicant_identities.birthdate')
            ->pluck('applicant_identities.birthdate');
        $under18 = 0;
        $age18to20 = 0;
        $age21to23 = 0;
        $age24plus = 0;

        foreach ($birthdates as $bdate) {
            $age = Carbon::parse($bdate)->age;
            if ($age < 18) {
                $under18++;
            } elseif ($age <= 20) {
                $age18to20++;
            } elseif ($age <= 23) {
                $age21to23++;
            } else {
                $age24plus++;
            }
        }

        return [
            ['label' => '< 18', 'value' => $under18, 'color' => '#6366f1'],
            ['label' => '18–20', 'value' => $age18to20, 'color' => '#8b5cf6'],
            ['label' => '21–23', 'value' => $age21to23, 'color' => '#a78bfa'],
            ['label' => '24+', 'value' => $age24plus, 'color' => '#c4b5fd'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeTemplatePayload(string $surfaceKey): ?array
    {
        $template = ReportTemplate::query()
            ->where('surface_key', $surfaceKey)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return null;
        }

        return [
            'id' => $template->id,
            'name' => $template->name,
            'surface_key' => $template->surface_key ?? $surfaceKey,
            'grain' => $template->grain ?? 'cycle_program',
            'pack_title' => $template->name,
            'surface_title' => $template->name,
            'mode' => $template->mode,
            'paper_size' => $template->paper_size,
            'orientation' => $template->orientation,
            'builder_json' => $template->builder_json,
            'updated_at' => $template->updated_at,
            'thumbnail_url' => route('setup.report-templates.thumbnail', [
                'surface' => $surfaceKey,
                'reportTemplate' => $template->id,
            ]).'?v='.app(DocumentThumbnailService::class)->fingerprint($template->sourceChecksum()),
        ];
    }

    /**
     * @param  list<string>|null  $scopedProgramCodes
     * @return list<array{label: string, value: int, color: string}>
     */
    private function getStrandDistribution(?int $cycleId, ?array $scopedProgramCodes = null): array
    {
        $counts = AdmissionAttempt::query()->forPracticeSet(null)
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->when($scopedProgramCodes !== null, fn ($q) => $q->whereIn('preferred_program_code', $scopedProgramCodes))
            ->whereNotNull('strand')
            ->where('strand', '!=', '')
            ->select('strand', DB::raw('COUNT(*) as total'))
            ->groupBy('strand')
            ->orderByDesc('total')
            ->pluck('total', 'strand')
            ->all();

        $palette = ['#10b981', '#0ea5e9', '#6366f1', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6', '#f43f5e'];
        $out = [];
        $idx = 0;
        foreach ($counts as $strand => $total) {
            $out[] = [
                'label' => strtoupper(trim((string) $strand)),
                'value' => (int) $total,
                'color' => $palette[$idx % count($palette)],
            ];
            $idx++;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function getInteractiveAnalytics(User $user, ?int $cycleId, bool $isGlobalScope): array
    {
        $scope = $user->actorScope();
        $institutionId = $scope->institutionId;
        $assignedCodes = $scope->programCodes;

        $programs = Program::query()
            ->active()
            ->with('department:id,code,name')
            ->when($institutionId !== null, fn ($q) => $q->where('institution_id', $institutionId))
            ->when(! $isGlobalScope, fn ($q) => $q->whereIn('code', $assignedCodes ?: ['__none__']))
            ->orderBy('code')
            ->get();

        $quotas = $cycleId !== null
            ? ProgramQuota::query()->where('academic_cycle_id', $cycleId)->get()->keyBy('program_id')
            : collect();

        $attemptsScope = AdmissionAttempt::query()->forPracticeSet(null)
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->when($institutionId !== null, fn ($q) => $q->where('institution_id', $institutionId));

        $firstChoiceByProgram = (clone $attemptsScope)
            ->whereNotNull('preferred_program_code')
            ->select('preferred_program_code', DB::raw('COUNT(*) as total'))
            ->groupBy('preferred_program_code')
            ->pluck('total', 'preferred_program_code')
            ->all();

        $secondChoiceByProgram = (clone $attemptsScope)
            ->whereNotNull('program_preference_2_code')
            ->select('program_preference_2_code', DB::raw('COUNT(*) as total'))
            ->groupBy('program_preference_2_code')
            ->pluck('total', 'program_preference_2_code')
            ->all();

        $thirdChoiceByProgram = (clone $attemptsScope)
            ->whereNotNull('program_preference_3_code')
            ->select('program_preference_3_code', DB::raw('COUNT(*) as total'))
            ->groupBy('program_preference_3_code')
            ->pluck('total', 'program_preference_3_code')
            ->all();

        $palette = ['#10b981', '#0ea5e9', '#6366f1', '#f59e0b', '#ec4899', '#14b8a6', '#8b5cf6', '#3b82f6'];
        $programRows = [];
        $deptGroups = [];

        foreach ($programs as $idx => $program) {
            $quotaRow = $quotas->get($program->id);
            $planned = $quotaRow ? (int) $quotaRow->slots : 90;
            $first = (int) ($firstChoiceByProgram[$program->code] ?? 0);
            $second = (int) ($secondChoiceByProgram[$program->code] ?? 0);
            $third = (int) ($thirdChoiceByProgram[$program->code] ?? 0);
            $totalChoices = $first + $second + $third;
            $placed = $cycleId ? QuotaReservation::committedForCycle($cycleId, (int) $program->id) : 0;
            $netShift = $placed - $first;
            $openSlots = max(0, $planned - $placed);
            $deptCode = $program->department?->code ?? 'General';
            $deptName = $program->department?->name ?? 'General Academic Programs';
            $color = $palette[$idx % count($palette)];

            $row = [
                'code' => (string) $program->code,
                'name' => (string) $program->name,
                'department_code' => (string) $deptCode,
                'department_name' => (string) $deptName,
                'planned_quota' => $planned,
                'open_slots' => $openSlots,
                'first_choice_demand' => $first,
                'second_choice_demand' => $second,
                'third_choice_demand' => $third,
                'total_choice_demand' => $totalChoices,
                'course_placed' => $placed,
                'net_shift' => $netShift,
                'utilization_percent' => $planned > 0 ? round(($placed / $planned) * 100, 1) : 0.0,
                'demand_percent' => $planned > 0 ? round(($first / $planned) * 100, 1) : 0.0,
                'total_choice_percent' => $planned > 0 ? round(($totalChoices / $planned) * 100, 1) : 0.0,
                'color' => $color,
            ];
            $programRows[] = $row;

            if (! isset($deptGroups[$deptCode])) {
                $deptGroups[$deptCode] = [
                    'code' => (string) $deptCode,
                    'name' => (string) $deptName,
                    'planned_quota' => 0,
                    'first_choice_demand' => 0,
                    'total_choice_demand' => 0,
                    'course_placed' => 0,
                    'program_count' => 0,
                    'color' => $palette[count($deptGroups) % count($palette)],
                ];
            }
            $deptGroups[$deptCode]['planned_quota'] += $planned;
            $deptGroups[$deptCode]['first_choice_demand'] += $first;
            $deptGroups[$deptCode]['total_choice_demand'] += $totalChoices;
            $deptGroups[$deptCode]['course_placed'] += $placed;
            $deptGroups[$deptCode]['program_count']++;
        }

        usort($programRows, function (array $a, array $b): int {
            $scoreA = ($a['course_placed'] * 100) + ($a['first_choice_demand'] * 10) + $a['total_choice_demand'];
            $scoreB = ($b['course_placed'] * 100) + ($b['first_choice_demand'] * 10) + $b['total_choice_demand'];
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            return strcmp($a['code'], $b['code']);
        });

        $departments = array_values(array_map(function (array $d): array {
            $d['utilization_percent'] = $d['planned_quota'] > 0
                ? round(($d['course_placed'] / $d['planned_quota']) * 100, 1)
                : 0.0;
            $d['demand_percent'] = $d['planned_quota'] > 0
                ? round(($d['first_choice_demand'] / $d['planned_quota']) * 100, 1)
                : 0.0;
            $d['net_shift'] = $d['course_placed'] - $d['first_choice_demand'];

            return $d;
        }, $deptGroups));

        $scopedCodes = $assignedCodes ?: ['__none__'];
        $funnelAttemptsScope = (clone $attemptsScope)
            ->when(! $isGlobalScope, fn ($q) => $q->whereIn('preferred_program_code', $scopedCodes));

        // 7-Stage Conversion Funnel
        $totalApps = (clone $funnelAttemptsScope)->count();
        $verifiedApps = (clone $funnelAttemptsScope)->where('status', 'accepted')->count();
        $scheduledCount = ExamAssignment::query()
            ->when($cycleId !== null, fn ($q) => $q->whereHas('examSession', fn ($sq) => $sq->where('academic_cycle_id', $cycleId)))
            ->when(! $isGlobalScope, fn ($q) => $q->whereHas('admissionAttempt', fn ($aq) => $aq->whereIn('preferred_program_code', $scopedCodes)))
            ->count();
        $testedCount = ExamAttendanceRecord::query()
            ->where('outcome', 'present')
            ->when($cycleId !== null, fn ($q) => $q->whereHas('examAssignment.examSession', fn ($sq) => $sq->where('academic_cycle_id', $cycleId)))
            ->when(! $isGlobalScope, fn ($q) => $q->whereHas('examAssignment.admissionAttempt', fn ($aq) => $aq->whereIn('preferred_program_code', $scopedCodes)))
            ->count();
        $scoredSessions = $isGlobalScope
            ? ExamSession::query()
                ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
                ->whereIn('status', ['completed', 'finalized'])
                ->count()
            : ExamAssignment::query()
                ->whereHas('examSession', fn ($sq) => $sq
                    ->when($cycleId !== null, fn ($cq) => $cq->where('academic_cycle_id', $cycleId))
                    ->whereIn('status', ['completed', 'finalized']))
                ->whereHas('admissionAttempt', fn ($aq) => $aq->whereIn('preferred_program_code', $scopedCodes))
                ->count();
        $releasedCount = ResultDocument::query()
            ->whereHas('admissionAttempt', fn ($aq) => $aq
                ->when($cycleId !== null, fn ($cq) => $cq->where('academic_cycle_id', $cycleId))
                ->when(! $isGlobalScope, fn ($pq) => $pq->whereIn('preferred_program_code', $scopedCodes)))
            ->count();
        $clearedCount = (clone $funnelAttemptsScope)
            ->whereHas('latestClearanceReceipt', fn ($q) => $q->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED))
            ->count();

        $baseDenom = max(1, $totalApps);
        $stageFunnel = [
            ['step' => 1, 'label' => $isGlobalScope ? '1. Cycle Setup' : '1. Active Programs', 'count' => $programs->count(), 'unit' => 'programs active', 'percent' => $programs->count() > 0 ? 100 : 0, 'color' => '#10b981'],
            ['step' => 2, 'label' => '2. Applications', 'count' => $totalApps, 'unit' => "{$verifiedApps} verified", 'percent' => $totalApps > 0 ? 100 : 0, 'color' => '#0ea5e9'],
            ['step' => 3, 'label' => '3. Exam Scheduling', 'count' => $scheduledCount, 'unit' => 'examinees assigned', 'percent' => min(100, (int) round(($scheduledCount / $baseDenom) * 100)), 'color' => '#3b82f6'],
            ['step' => 4, 'label' => '4. Examination', 'count' => $testedCount, 'unit' => 'candidates tested', 'percent' => min(100, (int) round(($testedCount / $baseDenom) * 100)), 'color' => '#6366f1'],
            ['step' => 5, 'label' => '5. Scoring & Prep', 'count' => $scoredSessions, 'unit' => $isGlobalScope ? 'sessions scored' : 'examinees scored', 'percent' => $scoredSessions > 0 ? min(100, (int) round(($testedCount / $baseDenom) * 100)) : 0, 'color' => '#8b5cf6'],
            ['step' => 6, 'label' => '6. Release of Results', 'count' => $releasedCount, 'unit' => 'results released', 'percent' => min(100, (int) round(($releasedCount / $baseDenom) * 100)), 'color' => '#a855f7'],
            ['step' => 7, 'label' => '7. Enrollment Clearance', 'count' => $clearedCount, 'unit' => 'clearances confirmed', 'percent' => min(100, (int) round(($clearedCount / $baseDenom) * 100)), 'color' => '#14b8a6'],
        ];

        // Time-Series Granularities (Daily 14d, Weekly 8w, Monthly 6m)
        $now = now();
        $driver = DB::connection()->getDriverName();
        $dayExpr = $driver === 'sqlite' ? "strftime('%Y-%m-%d', created_at)" : "to_char(created_at, 'YYYY-MM-DD')";
        $monthExpr = $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "to_char(created_at, 'YYYY-MM')";

        $dailyRows = (clone $funnelAttemptsScope)
            ->select(DB::raw("{$dayExpr} as ymd"), DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $now->copy()->subDays(13)->startOfDay())
            ->groupBy(DB::raw($dayExpr))
            ->pluck('total', 'ymd')
            ->all();
        $dailyLabels = [];
        $dailyValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = $now->copy()->subDays($i);
            $dailyLabels[] = $d->format('M j');
            $dailyValues[] = (int) ($dailyRows[$d->format('Y-m-d')] ?? 0);
        }

        $weeklyLabels = [];
        $weeklyValues = [];
        for ($w = 7; $w >= 0; $w--) {
            $wStart = $now->copy()->subWeeks($w)->startOfWeek();
            $wEnd = $wStart->copy()->endOfWeek();
            $weeklyLabels[] = $wStart->format('M j');
            $weeklyValues[] = (int) (clone $funnelAttemptsScope)->whereBetween('created_at', [$wStart, $wEnd])->count();
        }

        $monthlyRows = (clone $funnelAttemptsScope)
            ->select(DB::raw("{$monthExpr} as ym"), DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->groupBy(DB::raw($monthExpr))
            ->pluck('total', 'ym')
            ->all();
        $monthlyLabels = [];
        $monthlyValues = [];
        for ($m = 5; $m >= 0; $m--) {
            $dt = $now->copy()->subMonths($m);
            $monthlyLabels[] = $dt->format('M Y');
            $monthlyValues[] = (int) ($monthlyRows[$dt->format('Y-m')] ?? 0);
        }

        $isDean = $scope->departmentId !== null
            || ($user->staffProfile?->office === 'program' && $user->staffProfile?->department_id !== null);
        $scopeMode = $isGlobalScope ? 'institution' : ($isDean || count($programRows) > 1 ? 'department' : 'program');
        $defaultDeptCode = count($departments) === 1 ? (string) $departments[0]['code'] : 'ALL';
        $defaultProgramCode = ($scopeMode === 'program' && count($programRows) === 1) ? (string) $programRows[0]['code'] : 'ALL';

        return [
            'academic_scope' => [
                'scope_mode' => $scopeMode,
                'default_department_code' => $defaultDeptCode,
                'default_program_code' => $defaultProgramCode,
                'can_filter_department' => count($departments) > 1,
                'can_filter_program' => count($programRows) > 1,
                'departments' => $departments,
                'programs' => $programRows,
            ],
            'stage_funnel' => $stageFunnel,
            'time_series' => [
                'daily' => ['labels' => $dailyLabels, 'values' => $dailyValues],
                'weekly' => ['labels' => $weeklyLabels, 'values' => $weeklyValues],
                'monthly' => ['labels' => $monthlyLabels, 'values' => $monthlyValues],
            ],
        ];
    }
}
