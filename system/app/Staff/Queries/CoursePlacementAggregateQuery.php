<?php

namespace App\Staff\Queries;

use App\Admission\Support\QuotaReservation;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Testing\PracticeWorkspace;

final class CoursePlacementAggregateQuery
{
    /**
     * @return array{placed: int, quota: int, percent: float}|null
     */
    public function forCycle(?int $cycleId, ?int $institutionId): ?array
    {
        if ($cycleId === null || $institutionId === null
            || ! AcademicCycle::query()->whereKey($cycleId)->where('institution_id', $institutionId)->exists()) {
            return null;
        }

        $programIds = Program::query()
            ->active()
            ->where('institution_id', $institutionId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $quotas = ProgramQuota::query()
            ->where('academic_cycle_id', $cycleId)
            ->whereIn('program_id', $programIds)
            ->get(['program_id', 'slots'])
            ->keyBy('program_id');

        $quota = 0;
        foreach ($programIds as $programId) {
            $quota += (int) ($quotas->get($programId)?->slots ?? 90);
        }

        $commitments = QuotaReservation::batchCommittedForCycle(
            $cycleId,
            $programIds,
            practiceSetId: app(PracticeWorkspace::class)->currentId(),
        );
        $placed = array_sum($commitments);

        return [
            'placed' => $placed,
            'quota' => $quota,
            'percent' => $quota > 0 ? round(($placed / $quota) * 100, 1) : 0.0,
        ];
    }
}
