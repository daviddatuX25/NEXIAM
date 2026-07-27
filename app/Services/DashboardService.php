<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\ConsultationSummary;
use App\Models\ExamSession;
use App\Models\GradingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    /**
     * All 11 pipeline statuses in order.
     *
     * @var string[]
     */
    public const PIPELINE_STATUSES = [
        'pending', 'accepted', 'draft_scheduled', 'scheduled', 'printed',
        'attended', 'submitted', 'scored', 'graded', 'released', 'dismissed',
    ];

    /**
     * Pipeline status → human label.
     *
     * @var array<string, string>
     */
    public const PIPELINE_LABELS = [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'draft_scheduled' => 'Draft Scheduled',
        'scheduled' => 'Scheduled',
        'printed' => 'Printed',
        'attended' => 'Attended',
        'submitted' => 'Submitted',
        'scored' => 'Scored',
        'graded' => 'Graded',
        'released' => 'Released',
        'dismissed' => 'Dismissed',
    ];

    /**
     * Pipeline-based KPI stats using pipeline_status column.
     *
     * Returns grouped KPI cards:
     * - Total applications
     * - Active pipeline (pending → scored)
     * - Completed (graded + released)
     * - Dismissed
     *
     * @return array<int, array{key: string, label: string, value: int|string, href?: string}>
     */
    public function getApplicationStats(User $user): array
    {
        if (! $user->hasAnyRole(['super_admin', 'registrar_administrator'])) {
            return [];
        }

        $activeAcademicYear = AcademicYear::active();

        $base = Application::query();
        if ($activeAcademicYear !== null) {
            $base->forAcademicYear($activeAcademicYear);
        }

        // Count by pipeline_status (COALESCE null to 'pending')
        $counts = (clone $base)
            ->select(DB::raw("COALESCE(pipeline_status, 'pending') as status"), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw("COALESCE(pipeline_status, 'pending')"))
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($counts);

        $activeStatuses = ['pending', 'accepted', 'draft_scheduled', 'scheduled', 'printed', 'attended', 'submitted', 'scored'];
        $inPipeline = 0;
        foreach ($activeStatuses as $s) {
            $inPipeline += $counts[$s] ?? 0;
        }

        $completed = ($counts['graded'] ?? 0) + ($counts['released'] ?? 0);
        $dismissed = $counts['dismissed'] ?? 0;

        return [
            [
                'key' => 'total_applications',
                'label' => 'Total Applications',
                'value' => $total,
                'href' => '/admin/applications',
            ],
            [
                'key' => 'in_pipeline',
                'label' => 'In Pipeline',
                'value' => $inPipeline,
                'href' => '/admin/applications',
            ],
            [
                'key' => 'completed',
                'label' => 'Completed',
                'value' => $completed,
                'href' => '/admin/applications',
            ],
            [
                'key' => 'dismissed',
                'label' => 'Dismissed',
                'value' => $dismissed,
                'href' => '/admin/applications',
            ],
        ];
    }

    /**
     * Pipeline status distribution for funnel chart (all 11 statuses).
     *
     * @return array<int, array{status: string, label: string, count: int, percentage: float}>
     */
    public function getPipelineDistribution(User $user): array
    {
        if (! $user->hasAnyRole(['super_admin', 'registrar_administrator'])) {
            return [];
        }

        $activeAcademicYear = AcademicYear::active();

        $base = Application::query();
        if ($activeAcademicYear !== null) {
            $base->forAcademicYear($activeAcademicYear);
        }

        $counts = $base
            ->select(DB::raw("COALESCE(pipeline_status, 'pending') as status"), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw("COALESCE(pipeline_status, 'pending')"))
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($counts);

        return array_map(fn (string $status) => [
            'status' => $status,
            'label' => self::PIPELINE_LABELS[$status] ?? ucfirst($status),
            'count' => $counts[$status] ?? 0,
            'percentage' => $total > 0 ? round((($counts[$status] ?? 0) / $total) * 100, 1) : 0,
        ], self::PIPELINE_STATUSES);
    }

    /**
     * Session-level KPI stats (proctor / registrar_administrator / super_admin).
     *
     * @return array<int, array{key: string, label: string, value: int|string, href?: string}>
     */
    public function getSessionStats(User $user): array
    {
        if (! $user->hasAnyRole(['super_admin', 'proctor', 'test_administrator'])) {
            return [];
        }

        if (! Schema::hasTable('exam_sessions')) {
            return [];
        }

        $activeAcademicYear = AcademicYear::active();

        $upcomingQuery = ExamSession::query()
            ->whereIn('status', [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS]);

        if ($activeAcademicYear !== null) {
            $upcomingQuery->where('academic_year_id', $activeAcademicYear->id);
        }

        $upcoming = $upcomingQuery->count();

        $attendanceQuery = DB::table('exam_session_applicant')
            ->join('exam_sessions', 'exam_sessions.id', '=', 'exam_session_applicant.exam_session_id')
            ->whereIn('exam_sessions.status', [ExamSession::STATUS_IN_PROGRESS, ExamSession::STATUS_COMPLETED])
            ->where('exam_session_applicant.attendance_status', 'pending');

        if ($activeAcademicYear !== null) {
            $attendanceQuery->where('exam_sessions.academic_year_id', $activeAcademicYear->id);
        }

        $attendanceDue = $attendanceQuery->count();

        $submissionsQuery = DB::table('exam_session_applicant')
            ->join('exam_sessions', 'exam_sessions.id', '=', 'exam_session_applicant.exam_session_id')
            ->whereIn('exam_sessions.status', [ExamSession::STATUS_IN_PROGRESS, ExamSession::STATUS_COMPLETED])
            ->where('exam_session_applicant.submission_status', 'pending');

        if ($activeAcademicYear !== null) {
            $submissionsQuery->where('exam_sessions.academic_year_id', $activeAcademicYear->id);
        }

        $submissionsDue = $submissionsQuery->count();

        return [
            [
                'key' => 'sessions_upcoming',
                'label' => 'Upcoming Sessions',
                'value' => $upcoming,
                'href' => '/admin/exam-scheduling',
            ],
            [
                'key' => 'attendance_due',
                'label' => 'Attendance Due',
                'value' => $attendanceDue,
                'href' => '/admin/exam-scheduling',
            ],
            [
                'key' => 'submissions_due',
                'label' => 'Submissions Due',
                'value' => $submissionsDue,
                'href' => '/admin/exam-scheduling',
            ],
        ];
    }

    /**
     * Grading + release KPI stats (registrar_administrator / super_admin only).
     *
     * @return array<int, array{key: string, label: string, value: int|string, href?: string}>
     */
    public function getGradingStats(User $user): array
    {
        if (! $user->hasAnyRole(['super_admin', 'test_administrator'])) {
            return [];
        }

        $activeAcademicYear = AcademicYear::active();

        $gradingQuery = GradingSession::query()
            ->whereIn('status', [GradingSession::STATUS_OPEN, GradingSession::STATUS_IN_PROGRESS, GradingSession::STATUS_REVIEW]);

        if ($activeAcademicYear !== null) {
            $gradingQuery->whereHas('examSession', fn ($q) => $q->forAcademicYear($activeAcademicYear));
        }

        $pendingGrading = $gradingQuery->count();

        $releaseQuery = ConsultationSummary::query()
            ->where('status', ConsultationSummary::STATUS_DRAFT);

        if ($activeAcademicYear !== null) {
            $releaseQuery->whereHas('applicant.application', fn ($q) => $q->forAcademicYear($activeAcademicYear));
        }

        $pendingRelease = $releaseQuery->count();

        return [
            [
                'key' => 'grading_pending',
                'label' => 'Pending Grading',
                'value' => $pendingGrading,
                'href' => '/grading',
            ],
            [
                'key' => 'release_pending',
                'label' => 'Pending Release',
                'value' => $pendingRelease,
                'href' => '/release',
            ],
        ];
    }
}
