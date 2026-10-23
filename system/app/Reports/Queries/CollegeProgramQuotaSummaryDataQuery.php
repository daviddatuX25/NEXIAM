<?php

namespace App\Reports\Queries;

use App\Admission\Support\QuotaReservation;
use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CollegeProgramQuotaSummaryDataQuery implements ContextualReadToolHandler
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $cycleId = isset($filters['academic_cycle_id'])
            ? (int) $filters['academic_cycle_id']
            : AcademicCycle::active()?->id;

        $cycle = $cycleId === null ? null : AcademicCycle::query()->with('institution')->find($cycleId);

        $programCode = isset($filters['program_code']) && trim((string) $filters['program_code']) !== ''
            ? trim((string) $filters['program_code'])
            : null;
        $departmentCode = isset($filters['department_code']) && trim((string) $filters['department_code']) !== ''
            ? trim((string) $filters['department_code'])
            : null;
        $authorizedCodes = isset($filters['authorized_program_codes']) && is_array($filters['authorized_program_codes'])
            ? $filters['authorized_program_codes']
            : null;

        $programs = Program::query()
            ->active()
            ->with('department')
            ->when($programCode !== null, fn ($q) => $q->where('code', $programCode))
            ->when($departmentCode !== null, fn ($q) => $q->whereHas('department', fn ($dq) => $dq->where('code', $departmentCode)))
            ->when($authorizedCodes !== null, fn ($q) => $q->whereIn('code', $authorizedCodes))
            ->orderBy('code')
            ->get();

        $quotas = $cycleId !== null
            ? ProgramQuota::query()->where('academic_cycle_id', $cycleId)->get()->keyBy('program_id')
            : collect();

        $attemptsScope = AdmissionAttempt::query()->forPracticeSet(null)
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId));

        $firstChoices = (clone $attemptsScope)
            ->whereNotNull('preferred_program_code')
            ->select('preferred_program_code', DB::raw('COUNT(*) as total'))
            ->groupBy('preferred_program_code')
            ->pluck('total', 'preferred_program_code')
            ->all();

        $programRows = [];
        $totalQuota = 0;
        $totalPlaced = 0;
        $totalFirstChoice = 0;

        foreach ($programs as $index => $program) {
            $quotaRow = $quotas->get($program->id);
            $planned = $quotaRow ? (int) $quotaRow->slots : 50;
            $first = (int) ($firstChoices[$program->code] ?? 0);
            $placed = $cycleId ? QuotaReservation::committedForCycle($cycleId, (int) $program->id) : 0;
            $openSlots = max(0, $planned - $placed);
            $fillRate = $planned > 0 ? number_format(($placed / $planned) * 100, 1).'%' : '0.0%';

            $totalQuota += $planned;
            $totalPlaced += $placed;
            $totalFirstChoice += $first;

            $programRows[] = [
                'row_no' => $index + 1,
                'college_code' => $this->escapeText($program->department?->code ?? 'General'),
                'program_code' => $this->escapeText($program->code),
                'program_name' => $this->escapeText($program->name),
                'planned_quota' => $planned,
                'first_choice_demand' => $first,
                'placed_count' => $placed,
                'open_slots' => $openSlots,
                'fill_rate' => $fillRate,
            ];
        }

        $overallFill = $totalQuota > 0 ? number_format(($totalPlaced / $totalQuota) * 100, 1).'%' : '0.0%';

        return [
            'institution_name' => $this->escapeText((string) ($cycle?->institution?->name ?? Institution::query()->value('name') ?? 'Institution')),
            'institution_code' => $this->escapeText((string) ($cycle?->institution?->code ?? Institution::query()->value('code') ?? '')),
            'academic_cycle' => $this->escapeText($cycle?->staffLabel() ?? ''),
            'report_date' => now()->toDateString(),
            'total_programs' => count($programRows),
            'total_planned_quota' => $totalQuota,
            'total_placed_candidates' => $totalPlaced,
            'total_first_choice_demand' => $totalFirstChoice,
            'overall_fill_rate' => $overallFill,
            'programs' => $programRows,
            'prepared_by' => 'Dean / Department Chair',
            'verified_by' => 'Academic Affairs Coordinator',
            'approved_by' => 'Vice President for Academic Affairs',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array
    {
        $institutionId = (int) ($context->institutionId ?? $actor->actorScope()->institutionId ?? 0);
        $cycleId = isset($input['academic_cycle_id']) && is_numeric($input['academic_cycle_id'])
            ? (int) $input['academic_cycle_id']
            : null;

        return $this->forXiamSafe($institutionId, $cycleId);
    }

    /**
     * @return array{
     *   institution_id: int,
     *   cycle_id: ?int,
     *   cycle_name: string,
     *   total_quota: int,
     *   total_committed: int,
     *   overall_utilization_percent: float,
     *   programs: list<array{
     *     program_code: string,
     *     program_name: string,
     *     department_code: string,
     *     quota: int,
     *     first_choice_demand: int,
     *     committed: int,
     *     open_slots: int,
     *     utilization_percent: float,
     *     status_label: string
     *   }>,
     *   departments: list<array{
     *     department_code: string,
     *     quota: int,
     *     committed: int,
     *     utilization_percent: float
     *   }>,
     *   as_of: string
     * }
     */
    public function forXiamSafe(int $institutionId, ?int $cycleId = null): array
    {
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->when(
                $cycleId === null,
                fn ($query) => $query->where('is_active', true),
                fn ($query) => $query->whereKey($cycleId),
            )
            ->first();
        $resolvedCycleId = $cycle === null ? null : (int) $cycle->id;

        $programs = Program::query()
            ->active()
            ->where('institution_id', $institutionId)
            ->with('department:id,code')
            ->orderBy('code')
            ->get(['id', 'department_id', 'code', 'name']);
        $programIds = $programs->map(fn (Program $program): int => (int) $program->id)->all();

        $quotas = $resolvedCycleId === null
            ? collect()
            : ProgramQuota::query()
                ->where('academic_cycle_id', $resolvedCycleId)
                ->whereIn('program_id', $programIds)
                ->get(['program_id', 'slots'])
                ->keyBy('program_id');

        $attemptsScope = AdmissionAttempt::query()
            ->forPracticeSet(null)
            ->where('institution_id', $institutionId)
            ->when($resolvedCycleId !== null, fn ($query) => $query->where('academic_cycle_id', $resolvedCycleId));
        $firstChoices = $resolvedCycleId === null
            ? []
            : (clone $attemptsScope)
                ->whereNotNull('preferred_program_code')
                ->select('preferred_program_code', DB::raw('COUNT(*) as total'))
                ->groupBy('preferred_program_code')
                ->pluck('total', 'preferred_program_code')
                ->all();
        $committedByProgram = $resolvedCycleId === null
            ? []
            : QuotaReservation::batchCommittedForCycle($resolvedCycleId, $programIds);

        $programRows = [];
        $departmentTotals = [];
        $totalQuota = 0;
        $totalCommitted = 0;

        foreach ($programs as $program) {
            $quotaRow = $quotas->get($program->id);
            $quota = $quotaRow === null ? 50 : (int) $quotaRow->slots;
            $firstChoiceDemand = (int) ($firstChoices[$program->code] ?? 0);
            $committed = (int) ($committedByProgram[(int) $program->id] ?? 0);
            $departmentCode = (string) ($program->department?->code ?? 'General');
            $utilizationPercent = $quota > 0 ? round(($committed / $quota) * 100, 1) : 0.0;
            $statusLabel = $quotaRow === null
                ? 'Default Quota'
                : ($committed >= $quota
                    ? 'At Capacity'
                    : ($firstChoiceDemand > $quota
                        ? 'High Demand'
                        : ($committed > 0 || $firstChoiceDemand > 0 ? 'Active' : 'Open Slots')));

            $programRows[] = [
                'program_code' => (string) $program->code,
                'program_name' => (string) $program->name,
                'department_code' => $departmentCode,
                'quota' => $quota,
                'first_choice_demand' => $firstChoiceDemand,
                'committed' => $committed,
                'open_slots' => max(0, $quota - $committed),
                'utilization_percent' => $utilizationPercent,
                'status_label' => $statusLabel,
            ];

            $departmentTotals[$departmentCode] ??= ['quota' => 0, 'committed' => 0];
            $departmentTotals[$departmentCode]['quota'] += $quota;
            $departmentTotals[$departmentCode]['committed'] += $committed;
            $totalQuota += $quota;
            $totalCommitted += $committed;
        }

        $departments = [];
        foreach ($departmentTotals as $departmentCode => $totals) {
            $departments[] = [
                'department_code' => $departmentCode,
                'quota' => $totals['quota'],
                'committed' => $totals['committed'],
                'utilization_percent' => $totals['quota'] > 0
                    ? round(($totals['committed'] / $totals['quota']) * 100, 1)
                    : 0.0,
            ];
        }

        return [
            'institution_id' => $institutionId,
            'cycle_id' => $resolvedCycleId,
            'cycle_name' => $cycle?->staffLabel() ?? '',
            'total_quota' => $totalQuota,
            'total_committed' => $totalCommitted,
            'overall_utilization_percent' => $totalQuota > 0
                ? round(($totalCommitted / $totalQuota) * 100, 1)
                : 0.0,
            'programs' => $programRows,
            'departments' => $departments,
            'as_of' => now()->toIso8601String(),
        ];
    }

    private function escapeText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
