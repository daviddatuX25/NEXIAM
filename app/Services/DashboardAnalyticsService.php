<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\ExamSession;
use App\Models\GradingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Provides time-series and distribution analytics for the dashboard.
 *
 * All methods return arrays suitable for consumption by AreaChart/PieChart
 * Svelte components. Role-based access is enforced per method.
 */
class DashboardAnalyticsService
{
    /**
     * Check if a table exists to avoid errors on fresh installations.
     */
    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    /**
     * Whether the current connection uses MySQL.
     */
    private function isMySQL(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    // ─── Application Analytics ───────────────────────────────────────────────

    /**
     * Daily application submission counts for the last N days.
     *
     * @return array{labels: string[], values: int[]}
     */
    public function getApplicationTrends(User $user, int $months = 6): array
    {
        if (! $user->hasAnyRole(['super_admin', 'admin', 'registrar_administrator'])) {
            return ['labels' => [], 'values' => []];
        }

        if (! $this->tableExists('applications')) {
            return ['labels' => [], 'values' => []];
        }

        $academicYear = AcademicYear::active();
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? 'strftime("%Y-%m", created_at)'
            : 'DATE_FORMAT(created_at, "%Y-%m")';

        $rows = Application::query()
            ->selectRaw("{$monthExpr} as month, COUNT(*) as count")
            ->where('created_at', '>=', now()->subMonths($months)->startOfMonth())
            ->when($academicYear !== null, fn ($q) => $q->where('academic_year_id', $academicYear->id))
            ->groupByRaw($monthExpr)
            ->orderByRaw($monthExpr)
            ->get();

        $map = $rows->pluck('count', 'month')->map(fn ($v) => (int) $v)->toArray();

        $labels = [];
        $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M Y');
            $values[] = $map[$date->format('Y-m')] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Current application pipeline status distribution (all 11 statuses).
     *
     * @return array<int, array{label: string, value: int, color: string}>
     */
    public function getApplicationStatusDistribution(User $user): array
    {
        if (! $user->hasAnyRole(['super_admin', 'admin', 'registrar_administrator'])) {
            return [];
        }

        if (! $this->tableExists('applications')) {
            return [];
        }

        $academicYear = AcademicYear::active();

        $rows = Application::query()
            ->select(DB::raw("COALESCE(pipeline_status, 'pending') as status"), DB::raw('COUNT(*) as count'))
            ->when($academicYear !== null, fn ($q) => $q->where('academic_year_id', $academicYear->id))
            ->groupBy(DB::raw("COALESCE(pipeline_status, 'pending')"))
            ->get();

        $colorMap = [
            'pending' => '#94a3b8',
            'accepted' => '#3b82f6',
            'draft_scheduled' => '#818cf8',
            'scheduled' => '#6366f1',
            'printed' => '#8b5cf6',
            'attended' => '#a78bfa',
            'submitted' => '#7c3aed',
            'scored' => '#c084fc',
            'graded' => '#a855f7',
            'released' => '#22c55e',
            'dismissed' => '#ef4444',
        ];

        $labelMap = [
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

        return $rows->map(fn ($row) => [
            'label' => $labelMap[$row->status] ?? ucfirst($row->status),
            'value' => (int) $row->count,
            'color' => $colorMap[$row->status] ?? '#94a3b8',
        ])->toArray();
    }

    /**
     * Top course preferences across all three preference slots.
     *
     * @return array<int, array{label: string, value: int, color: string}>
     */
    public function getCoursePreferenceDistribution(User $user): array
    {
        if (! $user->hasAnyRole(['super_admin', 'admin', 'registrar_administrator'])) {
            return [];
        }

        if (! $this->tableExists('applications')) {
            return [];
        }

        $academicYear = AcademicYear::active();

        // Collect course IDs from all three preference slots
        $totals = [];
        $cols = ['course_preference_1', 'course_preference_2', 'course_preference_3'];

        foreach ($cols as $col) {
            $rows = DB::table('applications')
                ->select($col, DB::raw('COUNT(*) as count'))
                ->when($academicYear !== null, fn ($q) => $q->where('academic_year_id', $academicYear->id))
                ->whereNotNull($col)
                ->groupBy($col)
                ->get();

            foreach ($rows as $row) {
                $id = $row->$col;
                $totals[$id] = ($totals[$id] ?? 0) + $row->count;
            }
        }

        arsort($totals);
        $top = array_slice($totals, 0, 5, true);

        // Resolve course names
        $courseIds = array_keys($top);
        $courses = DB::table('courses')
            ->whereIn('id', $courseIds)
            ->pluck('name', 'id')
            ->toArray();

        $palette = ['#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#14b8a6'];

        return collect($top)->map(fn ($count, $id) => [
            'label' => $courses[$id] ?? "Course #{$id}",
            'value' => $count,
            'color' => $palette[array_search($id, $courseIds) % count($palette)],
        ])->values()->toArray();
    }

    // ─── Session Analytics ───────────────────────────────────────────────────

    /**
     * Weekly session counts (scheduled vs completed) for the last N weeks.
     *
     * @return array{labels: string[], scheduled: int[], completed: int[]}
     */
    public function getSessionTrends(User $user, int $weeks = 12): array
    {
        $allowedRoles = ['super_admin', 'proctor', 'test_administrator', 'registrar_administrator'];
        if (! $user->hasAnyRole($allowedRoles)) {
            return ['labels' => [], 'scheduled' => [], 'completed' => []];
        }

        if (! $this->tableExists('exam_sessions')) {
            return ['labels' => [], 'scheduled' => [], 'completed' => []];
        }

        $academicYear = AcademicYear::active();

        // MySQL-specific: YEARWEEK is not available in SQLite
        if (! $this->isMySQL()) {
            return $this->buildEmptyWeeklyTrends($weeks);
        }

        try {
            $rows = ExamSession::query()
                ->selectRaw('YEARWEEK(date, 1) as yrweek, COUNT(*) as total,
                            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed',
                    [ExamSession::STATUS_COMPLETED])
                ->where('date', '>=', now()->subWeeks($weeks))
                ->when($academicYear !== null, fn ($q) => $q->where('academic_year_id', $academicYear->id))
                ->groupByRaw('YEARWEEK(date, 1)')
                ->orderByRaw('YEARWEEK(date, 1)')
                ->get();

            $map = $rows->pluck('total', 'yrweek')->map(fn ($v) => (int) $v)->toArray();
            $completedMap = $rows->pluck('completed', 'yrweek')->map(fn ($v) => (int) $v)->toArray();

            return $this->buildWeeklyTrends($weeks, $map, $completedMap);
        } catch (Throwable) {
            return $this->buildEmptyWeeklyTrends($weeks);
        }
    }

    /**
     * Current exam session status distribution.
     *
     * @return array<int, array{label: string, value: int, color: string}>
     */
    public function getSessionStatusDistribution(User $user): array
    {
        if (! $user->hasAnyRole(['super_admin', 'proctor', 'test_administrator', 'registrar_administrator'])) {
            return [];
        }

        if (! $this->tableExists('exam_sessions')) {
            return [];
        }

        $academicYear = AcademicYear::active();

        $rows = ExamSession::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->when($academicYear !== null, fn ($q) => $q->where('academic_year_id', $academicYear->id))
            ->groupBy('status')
            ->get();

        $colorMap = [
            ExamSession::STATUS_DRAFT => '#94a3b8',
            ExamSession::STATUS_PUBLISHED => '#3b82f6',
            ExamSession::STATUS_IN_PROGRESS => '#f59e0b',
            ExamSession::STATUS_COMPLETED => '#22c55e',
            ExamSession::STATUS_CANCELLED => '#ef4444',
        ];

        $labelMap = [
            ExamSession::STATUS_DRAFT => 'Draft',
            ExamSession::STATUS_PUBLISHED => 'Published',
            ExamSession::STATUS_IN_PROGRESS => 'In Progress',
            ExamSession::STATUS_COMPLETED => 'Completed',
            ExamSession::STATUS_CANCELLED => 'Cancelled',
        ];

        return $rows->map(fn ($row) => [
            'label' => $labelMap[$row->status] ?? ucfirst($row->status),
            'value' => (int) $row->count,
            'color' => $colorMap[$row->status] ?? '#3b82f6',
        ])->toArray();
    }

    /**
     * Weekly attendance rate (% present vs absent) for the last N weeks.
     *
     * @return array{labels: string[], present: int[], absent: int[]}
     */
    public function getAttendanceTrends(User $user, int $weeks = 12): array
    {
        if (! $user->hasAnyRole(['super_admin', 'proctor', 'test_administrator', 'registrar_administrator'])) {
            return ['labels' => [], 'present' => [], 'absent' => []];
        }

        if (! $this->tableExists('exam_session_applicant') || ! $this->tableExists('exam_sessions')) {
            return ['labels' => [], 'present' => [], 'absent' => []];
        }

        if (! $this->isMySQL()) {
            return $this->buildEmptyAttendanceTrends($weeks);
        }

        $academicYear = AcademicYear::active();

        try {
            $rows = DB::table('exam_session_applicant')
                ->join('exam_sessions', 'exam_sessions.id', '=', 'exam_session_applicant.exam_session_id')
                ->selectRaw('YEARWEEK(exam_sessions.date, 1) as yrweek,
                             COUNT(*) as total,
                             SUM(CASE WHEN attendance_status = ? THEN 1 ELSE 0 END) as present,
                             SUM(CASE WHEN attendance_status = ? THEN 1 ELSE 0 END) as absent',
                    ['present', 'absent'])
                ->where('exam_sessions.date', '>=', now()->subWeeks($weeks))
                ->when($academicYear !== null, fn ($q) => $q->where('exam_sessions.academic_year_id', $academicYear->id))
                ->groupByRaw('YEARWEEK(exam_sessions.date, 1)')
                ->orderByRaw('YEARWEEK(exam_sessions.date, 1)')
                ->get();

            $presentMap = $rows->pluck('present', 'yrweek')->map(fn ($v) => (int) $v)->toArray();
            $absentMap = $rows->pluck('absent', 'yrweek')->map(fn ($v) => (int) $v)->toArray();

            return $this->buildAttendanceTrends($weeks, $presentMap, $absentMap);
        } catch (Throwable) {
            return $this->buildEmptyAttendanceTrends($weeks);
        }
    }

    // ─── Grading Analytics ───────────────────────────────────────────────────

    /**
     * Current grading session status distribution.
     *
     * @return array<int, array{label: string, value: int, color: string}>
     */
    public function getGradingStatusDistribution(User $user): array
    {
        if (! $user->hasAnyRole(['super_admin', 'test_administrator', 'registrar_administrator'])) {
            return [];
        }

        if (! $this->tableExists('grading_sessions')) {
            return [];
        }

        $academicYear = AcademicYear::active();

        $rows = GradingSession::query()
            ->select('grading_sessions.status', DB::raw('COUNT(*) as count'))
            ->join('exam_sessions', 'exam_sessions.id', '=', 'grading_sessions.exam_session_id')
            ->when($academicYear !== null, fn ($q) => $q->where('exam_sessions.academic_year_id', $academicYear->id))
            ->groupBy('grading_sessions.status')
            ->get();

        $colorMap = [
            GradingSession::STATUS_OPEN => '#f59e0b',
            GradingSession::STATUS_IN_PROGRESS => '#3b82f6',
            GradingSession::STATUS_REVIEW => '#a855f7',
            GradingSession::STATUS_FINALIZED => '#22c55e',
        ];

        $labelMap = [
            GradingSession::STATUS_OPEN => 'Open',
            GradingSession::STATUS_IN_PROGRESS => 'In Progress',
            GradingSession::STATUS_REVIEW => 'Review',
            GradingSession::STATUS_FINALIZED => 'Finalized',
        ];

        return $rows->map(fn ($row) => [
            'label' => $labelMap[$row->status] ?? ucfirst($row->status),
            'value' => (int) $row->count,
            'color' => $colorMap[$row->status] ?? '#3b82f6',
        ])->toArray();
    }

    /**
     * Average days from session completion to grading finalization per week.
     *
     * @return array{labels: string[], values: float[]}
     */
    public function getGradingTurnaround(User $user, int $weeks = 12): array
    {
        if (! $user->hasAnyRole(['super_admin', 'test_administrator', 'registrar_administrator'])) {
            return ['labels' => [], 'values' => []];
        }

        if (! $this->tableExists('grading_sessions') || ! $this->tableExists('exam_sessions')) {
            return ['labels' => [], 'values' => []];
        }

        if (! $this->isMySQL()) {
            return $this->buildEmptyWeeklyTrends($weeks);
        }

        try {
            $rows = DB::table('grading_sessions')
                ->join('exam_sessions', 'exam_sessions.id', '=', 'grading_sessions.exam_session_id')
                ->selectRaw('YEARWEEK(grading_sessions.finalized_at, 1) as yrweek,
                             AVG(DATEDIFF(grading_sessions.finalized_at, exam_sessions.date)) as avg_days')
                ->whereNotNull('grading_sessions.finalized_at')
                ->where('grading_sessions.finalized_at', '>=', now()->subWeeks($weeks))
                ->groupByRaw('YEARWEEK(grading_sessions.finalized_at, 1)')
                ->orderByRaw('YEARWEEK(grading_sessions.finalized_at, 1)')
                ->get();

            $map = $rows->pluck('avg_days', 'yrweek')->map(fn ($v) => round((float) $v, 1))->toArray();

            return $this->buildWeeklyTrendsValues($weeks, $map);
        } catch (Throwable) {
            return $this->buildEmptyWeeklyTrends($weeks);
        }
    }

    // ─── User Analytics ─────────────────────────────────────────────────────

    /**
     * New user registrations per month for the last N months.
     *
     * @return array{labels: string[], values: int[]}
     */
    public function getUserGrowth(int $months = 6): array
    {
        if (! $this->tableExists('users')) {
            return ['labels' => [], 'values' => []];
        }

        if (! $this->isMySQL()) {
            return $this->buildEmptyMonthlyGrowth($months);
        }

        try {
            $rows = DB::table('users')
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
                ->where('created_at', '>=', now()->subMonths($months))
                ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
                ->orderByRaw("DATE_FORMAT(created_at, '%Y-%m')")
                ->get();

            $map = $rows->pluck('count', 'month')->map(fn ($v) => (int) $v)->toArray();

            $labels = [];
            $values = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $monthKey = $date->format('Y-m');
                $labels[] = $date->format('M Y');
                $values[] = $map[$monthKey] ?? 0;
            }

            return ['labels' => $labels, 'values' => $values];
        } catch (Throwable) {
            return $this->buildEmptyMonthlyGrowth($months);
        }
    }

    /**
     * User count grouped by role.
     *
     * @return array<int, array{label: string, value: int, color: string}>
     */
    public function getUserRoleDistribution(): array
    {
        if (! $this->tableExists('users') || ! $this->tableExists('roles')) {
            return [];
        }

        try {
            $rows = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->select('roles.name', DB::raw('COUNT(*) as count'))
                ->groupBy('roles.name')
                ->get();

            $palette = ['#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#14b8a6', '#ef4444'];

            return $rows->map(fn ($row, $idx) => [
                'label' => ucfirst(str_replace('_', ' ', $row->name)),
                'value' => (int) $row->count,
                'color' => $palette[$idx % count($palette)],
            ])->toArray();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Recent audit log entries for the current user ("My Activity").
     *
     * @return array<int, array{event: string, summary: string, category: string, created_at: string}>
     */
    public function getMyActivity(User $user, int $limit = 10): array
    {
        if (! $this->tableExists('audit_logs')) {
            return [];
        }

        try {
            return AuditLog::query()
                ->where('actor_type', $user->getMorphClass())
                ->where('actor_id', $user->getKey())
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get(['id', 'event', 'summary', 'category', 'created_at'])
                ->toArray();
        } catch (Throwable) {
            return [];
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build weekly trend arrays with zero-filled slots.
     *
     * @param  array<int, int>  $scheduledMap
     * @param  array<int, int>  $completedMap
     * @return array{labels: string[], scheduled: int[], completed: int[]}
     */
    private function buildWeeklyTrends(int $weeks, array $scheduledMap, array $completedMap): array
    {
        $labels = [];
        $scheduled = [];
        $completed = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $date = now()->subWeeks($i);
            $yrweek = (int) $date->format('YW');
            $labels[] = $date->startOfWeek()->format('M d');
            $scheduled[] = $scheduledMap[$yrweek] ?? 0;
            $completed[] = $completedMap[$yrweek] ?? 0;
        }

        return ['labels' => $labels, 'scheduled' => $scheduled, 'completed' => $completed];
    }

    /**
     * Build weekly value arrays with zero-filled slots.
     *
     * @param  array<int, float|int>  $valueMap
     * @return array{labels: string[], values: float[]}
     */
    private function buildWeeklyTrendsValues(int $weeks, array $valueMap): array
    {
        $labels = [];
        $values = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $date = now()->subWeeks($i);
            $yrweek = (int) $date->format('YW');
            $labels[] = $date->startOfWeek()->format('M d');
            $values[] = $valueMap[$yrweek] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Build empty weekly trend arrays (zero-filled).
     *
     * @return array{labels: string[], scheduled: int[], completed: int[]}
     */
    private function buildEmptyWeeklyTrends(int $weeks): array
    {
        $labels = [];
        $scheduled = [];
        $completed = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $date = now()->subWeeks($i);
            $labels[] = $date->startOfWeek()->format('M d');
            $scheduled[] = 0;
            $completed[] = 0;
        }

        return ['labels' => $labels, 'scheduled' => $scheduled, 'completed' => $completed];
    }

    /**
     * Build attendance trend arrays with zero-filled slots.
     *
     * @param  array<int, int>  $presentMap
     * @param  array<int, int>  $absentMap
     * @return array{labels: string[], present: int[], absent: int[]}
     */
    private function buildAttendanceTrends(int $weeks, array $presentMap, array $absentMap): array
    {
        $labels = [];
        $present = [];
        $absent = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $date = now()->subWeeks($i);
            $yrweek = (int) $date->format('YW');
            $labels[] = $date->startOfWeek()->format('M d');
            $present[] = $presentMap[$yrweek] ?? 0;
            $absent[] = $absentMap[$yrweek] ?? 0;
        }

        return ['labels' => $labels, 'present' => $present, 'absent' => $absent];
    }

    /**
     * Build empty attendance trend arrays (zero-filled).
     *
     * @return array{labels: string[], present: int[], absent: int[]}
     */
    private function buildEmptyAttendanceTrends(int $weeks): array
    {
        $labels = [];
        $present = [];
        $absent = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $date = now()->subWeeks($i);
            $labels[] = $date->startOfWeek()->format('M d');
            $present[] = 0;
            $absent[] = 0;
        }

        return ['labels' => $labels, 'present' => $present, 'absent' => $absent];
    }

    /**
     * Build empty monthly growth arrays (zero-filled).
     *
     * @return array{labels: string[], values: int[]}
     */
    private function buildEmptyMonthlyGrowth(int $months): array
    {
        $labels = [];
        $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M Y');
            $values[] = 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
