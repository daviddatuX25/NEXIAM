<?php

namespace App\Staff\Queries;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ProgramQuota;
use Illuminate\Support\Facades\DB;

final class AdmissionsFunnelAggregateQuery
{
    public function for(?int $cycleId, ?int $institutionId): array
    {
        $base = AdmissionAttempt::query()->forWorkspace()
            ->when($cycleId !== null, fn ($query) => $query->where('academic_cycle_id', $cycleId))
            ->when($institutionId !== null, fn ($query) => $query->where('institution_id', $institutionId));
        $counts = (clone $base)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status')->all();
        $total = array_sum($counts);
        $previous = AcademicCycle::query()->where('is_active', false)->latest('id')->first();
        $previousTotal = $previous ? AdmissionAttempt::query()->forWorkspace()->where('academic_cycle_id', $previous->id)
            ->when($institutionId !== null, fn ($query) => $query->where('institution_id', $institutionId))->count() : 0;
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "to_char(created_at, 'YYYY-MM')";
        $trendRows = (clone $base)->select(DB::raw("{$monthExpr} as ym"), DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())->groupBy(DB::raw($monthExpr))->pluck('total', 'ym')->all();
        $labels = $values = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M Y');
            $values[] = (int) ($trendRows[$date->format('Y-m')] ?? 0);
        }
        $colors = ['pending' => '#94a3b8', 'needs_correction' => '#f59e0b', 'accepted' => '#3b82f6', 'dismissed' => '#ef4444', 'archived' => '#64748b'];
        $pipeline = [];
        foreach ($counts as $status => $count) {
            $pipeline[] = ['label' => ucfirst(str_replace('_', ' ', (string) $status)), 'value' => (int) $count, 'color' => $colors[$status] ?? '#94a3b8'];
        }
        $top = (clone $base)->whereNotNull('preferred_program_code')->select('preferred_program_code', DB::raw('COUNT(*) as total'))
            ->groupBy('preferred_program_code')->orderByDesc('total')->limit(5)->get();
        $courseColors = ['#10b981', '#38bdf8', '#6366f1', '#a855f7', '#f59e0b'];
        $clearances = (clone $base)->whereHas('latestClearanceReceipt', fn ($query) => $query->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED))->count();
        $enrolled = EnrollmentStamp::query()->whereHas('attempt', fn ($query) => $query->forWorkspace()
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->when($institutionId !== null, fn ($q) => $q->where('institution_id', $institutionId)))
            ->where('stamp_type', EnrollmentStamp::STAMP_ENROLLED)->distinct('admission_attempt_id')->count('admission_attempt_id');
        $delta = $total - $previousTotal;
        $target = (int) ProgramQuota::query()
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->when($institutionId !== null, fn ($q) => $q->whereHas('program', fn ($pq) => $pq->where('institution_id', $institutionId)))
            ->sum('slots');
        $quotaPercentage = $target > 0 ? round($total / $target * 100, 1) : 0.0;

        return [
            'kpis' => ['total' => $total, 'in_pipeline' => ($counts['pending'] ?? 0) + ($counts['needs_correction'] ?? 0) + ($counts['accepted'] ?? 0), 'completed' => $clearances, 'dismissed' => (int) ($counts['dismissed'] ?? 0), 'admission_clearances_confirmed' => $clearances, 'legacy_enrolled_records' => $enrolled, 'yoy_delta' => ($delta >= 0 ? "+{$delta}" : (string) $delta).' YoY', 'yoy_comparison' => 'vs '.($previous?->code ?? 'AY 2025–26'), 'yoy_growth' => ($delta >= 0 ? "+{$delta}" : (string) $delta).' YoY (vs '.($previous?->code ?? 'AY 2025–26').')', 'quota_target' => $target, 'quota_percentage' => $quotaPercentage],
            'growth_trend' => ['labels' => $labels, 'values' => $values],
            'pipeline_distribution' => $pipeline,
            'top_courses' => $top->values()->map(fn ($row, $index) => ['label' => (string) $row->preferred_program_code, 'value' => (int) $row->total, 'color' => $courseColors[$index % count($courseColors)]])->all(),
        ];
    }
}
