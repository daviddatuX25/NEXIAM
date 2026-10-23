<?php

namespace App\Staff\Queries;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Release\ResultDocument;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class CycleRoadmapAggregateQuery
{
    /**
     * @return array{
     *   cycle_label: string,
     *   window_label: ?string,
     *   window_name: ?string,
     *   start_date: string,
     *   end_date: string,
     *   days_total: int,
     *   days_elapsed: int,
     *   days_remaining: int,
     *   progress_percent: float,
     *   velocity: array{
     *     total_applications: int,
     *     avg_per_day: int,
     *     pace_per_active_day: int,
     *     active_days: int,
     *     today_count: int,
     *     last_7_days_count: int,
     *     peak_day_count: int,
     *     daily_trend: array{labels: list<string>, values: list<int>}
     *   },
     *   milestones: list<array{
     *     key: string,
     *     step: int,
     *     label: string,
     *     metric: string,
     *     count: int,
     *     status: 'completed'|'current'|'upcoming'
     *   }>
     * }
     */
    public function for(?AcademicCycle $cycle, ?int $institutionId): array
    {
        $cycleId = $cycle?->id;
        $window = $cycle?->primaryWindow();
        $now = now();

        $start = $cycle?->opens_on ? Carbon::parse($cycle->opens_on)->startOfDay() : ($window?->opens_at ? Carbon::parse($window->opens_at) : $now->copy()->startOfDay());
        $end = $cycle?->closes_on ? Carbon::parse($cycle->closes_on)->endOfDay() : ($window?->closes_at ? Carbon::parse($window->closes_at) : $now->copy()->endOfDay());

        $daysTotal = max(1, (int) round($start->diffInDays($end)));
        $rawElapsed = (int) round($start->diffInDays($now, false));
        $daysElapsed = max(0, min($daysTotal, $rawElapsed));
        $daysRemaining = $now->greaterThan($end) ? 0 : max(0, (int) round($now->diffInDays($end, false)));
        $progressPercent = $daysTotal > 0 ? round(($daysElapsed / $daysTotal) * 100, 1) : 0.0;

        $attemptsBase = AdmissionAttempt::query()->forWorkspace()
            ->when($cycleId !== null, fn ($query) => $query->where('academic_cycle_id', $cycleId))
            ->when($institutionId !== null, fn ($query) => $query->where('institution_id', $institutionId));

        $totalApplications = (clone $attemptsBase)->count();
        $todayCount = (clone $attemptsBase)->where('created_at', '>=', $now->copy()->startOfDay())->count();
        $last7DaysCount = (clone $attemptsBase)->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay())->count();

        $driver = DB::connection()->getDriverName();
        $dayExpr = $driver === 'sqlite' ? "strftime('%Y-%m-%d', created_at)" : "to_char(created_at, 'YYYY-MM-DD')";
        $activeDaysCount = max(1, (int) (clone $attemptsBase)->select(DB::raw("COUNT(DISTINCT {$dayExpr}) as active_days"))->value('active_days'));
        $pacePerActiveDay = $totalApplications > 0 ? max(1, (int) round($totalApplications / $activeDaysCount)) : 0;

        $dailyRows = (clone $attemptsBase)
            ->select(DB::raw("{$dayExpr} as ymd"), DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $now->copy()->subDays(13)->startOfDay())
            ->groupBy(DB::raw($dayExpr))
            ->pluck('total', 'ymd')
            ->all();

        $dailyLabels = [];
        $dailyValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dailyLabels[] = $date->format('M j');
            $dailyValues[] = (int) ($dailyRows[$date->format('Y-m-d')] ?? 0);
        }
        $peakDayCount = $dailyValues === [] ? 0 : max($dailyValues);

        $acceptedCount = (clone $attemptsBase)->where('status', 'accepted')->count();
        $scheduledSessions = ExamSession::query()->forWorkspace()
            ->when($cycleId !== null, fn ($query) => $query->where('academic_cycle_id', $cycleId))
            ->whereHas('academicCycle', fn ($query) => $query->when($institutionId !== null, fn ($cycleQuery) => $cycleQuery->where('institution_id', $institutionId)))
            ->count();
        $assignedExaminees = ExamAssignment::query()
            ->whereHas('examSession', fn ($sessionQuery) => $sessionQuery->forWorkspace()
                ->when($cycleId !== null, fn ($query) => $query->where('academic_cycle_id', $cycleId))
                ->whereHas('academicCycle', fn ($query) => $query->when($institutionId !== null, fn ($cycleQuery) => $cycleQuery->where('institution_id', $institutionId))))
            ->count();
        $testedCount = ExamAttendanceRecord::query()
            ->where('outcome', 'present')
            ->whereHas('examAssignment.examSession', fn ($sessionQuery) => $sessionQuery->forWorkspace()
                ->when($cycleId !== null, fn ($query) => $query->where('academic_cycle_id', $cycleId))
                ->whereHas('academicCycle', fn ($query) => $query->when($institutionId !== null, fn ($cycleQuery) => $cycleQuery->where('institution_id', $institutionId))))
            ->count();
        $finalizedSessions = ExamSession::query()->forWorkspace()
            ->when($cycleId !== null, fn ($query) => $query->where('academic_cycle_id', $cycleId))
            ->whereHas('academicCycle', fn ($query) => $query->when($institutionId !== null, fn ($cycleQuery) => $cycleQuery->where('institution_id', $institutionId)))
            ->whereIn('status', ['completed', 'finalized'])
            ->count();
        $releasedCount = ResultDocument::query()
            ->whereHas('admissionAttempt', fn ($query) => $query->forWorkspace()
                ->when($cycleId !== null, fn ($attemptQuery) => $attemptQuery->where('academic_cycle_id', $cycleId))
                ->when($institutionId !== null, fn ($attemptQuery) => $attemptQuery->where('institution_id', $institutionId)))
            ->count();
        $clearedCount = (clone $attemptsBase)
            ->whereHas('latestClearanceReceipt', fn ($query) => $query->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED))
            ->count();

        $milestones = [
            [
                'key' => 'setup',
                'step' => 1,
                'label' => 'Cycle Setup',
                'metric' => $cycle ? 'Cycle Active' : 'Pending',
                'count' => $cycle ? 1 : 0,
                'status' => $cycle ? 'completed' : 'current',
            ],
            [
                'key' => 'applications',
                'step' => 2,
                'label' => 'Applications',
                'metric' => "{$totalApplications} applied · {$acceptedCount} verified",
                'count' => $totalApplications,
                'status' => $totalApplications > 0 ? ($scheduledSessions > 0 ? 'completed' : 'current') : 'upcoming',
            ],
            [
                'key' => 'scheduling',
                'step' => 3,
                'label' => 'Exam Scheduling',
                'metric' => "{$scheduledSessions} sessions · {$assignedExaminees} assigned",
                'count' => $scheduledSessions,
                'status' => $scheduledSessions > 0 ? ($testedCount > 0 ? 'completed' : 'current') : 'upcoming',
            ],
            [
                'key' => 'examination',
                'step' => 4,
                'label' => 'Examination',
                'metric' => "{$testedCount} tested",
                'count' => $testedCount,
                'status' => $testedCount > 0 ? ($finalizedSessions > 0 ? 'completed' : 'current') : 'upcoming',
            ],
            [
                'key' => 'scoring',
                'step' => 5,
                'label' => 'Scoring & Prep',
                'metric' => "{$finalizedSessions} sessions completed",
                'count' => $finalizedSessions,
                'status' => $finalizedSessions > 0 ? ($releasedCount > 0 ? 'completed' : 'current') : 'upcoming',
            ],
            [
                'key' => 'release',
                'step' => 6,
                'label' => 'Release of Results',
                'metric' => "{$releasedCount} released",
                'count' => $releasedCount,
                'status' => $releasedCount > 0 ? ($clearedCount > 0 ? 'completed' : 'current') : 'upcoming',
            ],
            [
                'key' => 'clearance',
                'step' => 7,
                'label' => 'Enrollment Clearance',
                'metric' => "{$clearedCount} cleared",
                'count' => $clearedCount,
                'status' => $clearedCount > 0 ? 'current' : 'upcoming',
            ],
        ];

        $windowLabel = $cycle?->applicationWindowLabel();
        $cycleRangeLabel = $start->format('M j, Y').' to '.$end->format('M j, Y');
        $cleanWindowLabel = ($windowLabel && $windowLabel !== $cycleRangeLabel && $windowLabel !== 'No window set') ? $windowLabel : null;

        return [
            'cycle_label' => $cycle?->staffLabel() ?? 'No Active Cycle',
            'window_label' => $cleanWindowLabel,
            'window_name' => $window?->name,
            'start_date' => $start->format('M j, Y'),
            'end_date' => $end->format('M j, Y'),
            'days_total' => $daysTotal,
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'progress_percent' => $progressPercent,
            'velocity' => [
                'total_applications' => $totalApplications,
                'avg_per_day' => $pacePerActiveDay,
                'pace_per_active_day' => $pacePerActiveDay,
                'active_days' => $activeDaysCount,
                'today_count' => $todayCount,
                'last_7_days_count' => $last7DaysCount,
                'peak_day_count' => $peakDayCount,
                'daily_trend' => [
                    'labels' => $dailyLabels,
                    'values' => $dailyValues,
                ],
            ],
            'milestones' => $milestones,
        ];
    }
}
