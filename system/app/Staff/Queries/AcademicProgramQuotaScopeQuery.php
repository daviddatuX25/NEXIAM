<?php

namespace App\Staff\Queries;

use App\Admission\Support\QuotaReservation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AcademicProgramQuotaScopeQuery
{
    /**
     * @return array{
     *   scope_mode: 'institution'|'department'|'program',
     *   can_filter_department: bool,
     *   can_filter_program: bool,
     *   departments: list<array<string, mixed>>,
     *   programs: list<array<string, mixed>>
     * }
     */
    public function for(User $user, ?int $cycleId, ?int $institutionId, bool $isGlobalScope): array
    {
        $assignedCodes = $user->actorScope()->programCodes;
        $programs = Program::query()
            ->active()
            ->with('department:id,code,name')
            ->when($institutionId !== null, fn ($query) => $query->where('institution_id', $institutionId))
            ->when(! $isGlobalScope, fn ($query) => $query->whereIn('code', $assignedCodes ?: ['__none__']))
            ->orderBy('code')
            ->get();

        $quotas = $cycleId !== null
            ? ProgramQuota::query()->where('academic_cycle_id', $cycleId)->get()->keyBy('program_id')
            : collect();

        $attemptsScope = AdmissionAttempt::query()
            ->forWorkspace()
            ->when($cycleId !== null, fn ($query) => $query->where('academic_cycle_id', $cycleId))
            ->when($institutionId !== null, fn ($query) => $query->where('institution_id', $institutionId));

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

        $clearedByProgram = (clone $attemptsScope)
            ->whereNotNull('preferred_program_code')
            ->whereHas('latestClearanceReceipt', fn ($query) => $query->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED))
            ->select('preferred_program_code', DB::raw('COUNT(*) as total'))
            ->groupBy('preferred_program_code')
            ->pluck('total', 'preferred_program_code')
            ->all();

        $programIds = $programs->pluck('id')->map(fn ($id) => (int) $id)->all();
        $committedMap = $cycleId ? QuotaReservation::batchCommittedForCycle($cycleId, $programIds) : [];

        $palette = ['#10b981', '#3b82f6', '#6366f1', '#f59e0b', '#ec4899', '#14b8a6', '#8b5cf6', '#0ea5e9'];
        $programRows = [];
        $deptGroups = [];

        foreach ($programs as $idx => $program) {
            $quotaRow = $quotas->get($program->id);
            $planned = $quotaRow ? (int) $quotaRow->slots : 90;
            $firstChoice = (int) ($firstChoiceByProgram[$program->code] ?? 0);
            $secondChoice = (int) ($secondChoiceByProgram[$program->code] ?? 0);
            $thirdChoice = (int) ($thirdChoiceByProgram[$program->code] ?? 0);
            $totalChoices = $firstChoice + $secondChoice + $thirdChoice;
            $committed = $committedMap[(int) $program->id] ?? 0;
            $cleared = (int) ($clearedByProgram[$program->code] ?? 0);
            $netShift = $committed - $firstChoice;
            $openSlots = max(0, $planned - $committed);

            $fillPct = $planned > 0 ? round(($committed / $planned) * 100, 1) : 0.0;
            $demandPct = $planned > 0 ? round(($firstChoice / $planned) * 100, 1) : 0.0;
            $totalChoicePct = $planned > 0 ? round(($totalChoices / $planned) * 100, 1) : 0.0;
            $deptCode = $program->department?->code ?? 'General';
            $deptName = $program->department?->name ?? 'General Academic Programs';
            $color = $palette[$idx % count($palette)];

            $statusLabel = ! $quotaRow
                ? 'Default Quota'
                : ($committed >= $planned ? 'At Capacity' : ($firstChoice > $planned ? 'High Demand' : ($committed > 0 || $firstChoice > 0 ? 'Active' : 'Open Slots')));

            $row = [
                'code' => (string) $program->code,
                'name' => (string) $program->name,
                'department_code' => (string) $deptCode,
                'department_name' => (string) $deptName,
                'planned_quota' => $planned,
                'open_slots' => $openSlots,
                'first_choice_demand' => $firstChoice,
                'second_choice_demand' => $secondChoice,
                'third_choice_demand' => $thirdChoice,
                'total_choice_demand' => $totalChoices,
                'actual_committed' => $committed,
                'course_placed' => $committed,
                'cleared_count' => $cleared,
                'net_shift' => $netShift,
                'utilization_percent' => $fillPct,
                'demand_percent' => $demandPct,
                'total_choice_percent' => $totalChoicePct,
                'status_label' => $statusLabel,
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
                    'actual_committed' => 0,
                    'course_placed' => 0,
                    'cleared_count' => 0,
                    'program_count' => 0,
                    'color' => $palette[count($deptGroups) % count($palette)],
                ];
            }
            $deptGroups[$deptCode]['planned_quota'] += $planned;
            $deptGroups[$deptCode]['first_choice_demand'] += $firstChoice;
            $deptGroups[$deptCode]['total_choice_demand'] += $totalChoices;
            $deptGroups[$deptCode]['actual_committed'] += $committed;
            $deptGroups[$deptCode]['course_placed'] += $committed;
            $deptGroups[$deptCode]['cleared_count'] += $cleared;
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

        $departments = array_values(array_map(function (array $department): array {
            $department['utilization_percent'] = $department['planned_quota'] > 0
                ? round(($department['actual_committed'] / $department['planned_quota']) * 100, 1)
                : 0.0;
            $department['demand_percent'] = $department['planned_quota'] > 0
                ? round(($department['first_choice_demand'] / $department['planned_quota']) * 100, 1)
                : 0.0;
            $department['net_shift'] = $department['course_placed'] - $department['first_choice_demand'];

            return $department;
        }, $deptGroups));

        return [
            'scope_mode' => $isGlobalScope ? 'institution' : (count($programRows) > 1 ? 'department' : 'program'),
            'can_filter_department' => count($departments) > 1,
            'can_filter_program' => count($programRows) > 1,
            'departments' => $departments,
            'programs' => $programRows,
        ];
    }
}
