<?php

namespace App\Staff\Queries;

use App\Admission\Support\QuotaReservation;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;

final class ProgramQuotaVarianceAggregateQuery
{
    public function for(?int $cycleId, ?int $institutionId): array
    {
        $cycleId ??= AcademicCycle::active()?->id;
        $rows = [];
        $quotas = ProgramQuota::query()->where('academic_cycle_id', $cycleId)->get()->keyBy('program_id');
        foreach (Program::query()->active()->with('department')->where('institution_id', $institutionId)->orderBy('code')->get() as $program) {
            $quota = $quotas->get($program->id);
            $planned = $quota ? (int) $quota->slots : 0;
            $actual = $cycleId ? QuotaReservation::committedForCycle($cycleId, (int) $program->id) : 0;
            $utilization = $planned > 0 ? round($actual / $planned * 100, 1) : 0.0;
            $badge = ! $quota ? 'Unconfigured' : ($actual < $planned ? 'Normal' : ($actual === $planned ? 'At Capacity' : 'Over-Quota'));
            $rows[] = ['program' => $program->name, 'program_code' => $program->code, 'college' => $program->department?->code ?? 'Unassigned', 'department' => $program->department?->name ?? 'Unassigned', 'planned_quota' => $planned, 'actual_committed' => $actual, 'variance' => $actual - $planned, 'utilization_percent' => $utilization, 'badge' => $badge];
        }
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['college'];
            $groups[$key] ??= ['college' => $key, 'department' => $row['department'], 'planned_quota' => 0, 'actual_committed' => 0, 'variance' => 0, 'programs' => []];
            $groups[$key]['planned_quota'] += $row['planned_quota'];
            $groups[$key]['actual_committed'] += $row['actual_committed'];
            $groups[$key]['variance'] += $row['variance'];
            $groups[$key]['programs'][] = $row;
        }

        return ['programs' => $rows, 'departments' => array_values($groups), 'totals' => ['planned_quota' => array_sum(array_column($rows, 'planned_quota')), 'actual_committed' => array_sum(array_column($rows, 'actual_committed')), 'variance' => array_sum(array_column($rows, 'variance'))]];
    }
}
