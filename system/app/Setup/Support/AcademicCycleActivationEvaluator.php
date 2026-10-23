<?php

namespace App\Setup\Support;

use App\Examination\Support\PreflightResult;
use App\Models\Foundation\AcademicCycle;

final class AcademicCycleActivationEvaluator
{
    public function evaluate(AcademicCycle $cycle): PreflightResult
    {
        $exists = $cycle->exists && $cycle->newQuery()->whereKey($cycle->getKey())->exists();
        $activeCycle = $exists
            ? AcademicCycle::query()
                ->where('institution_id', $cycle->institution_id)
                ->where('is_active', true)
                ->first()
            : null;
        $alreadyActive = $exists && ($activeCycle?->is($cycle) ?? false);

        $checks = [
            [
                'key' => 'cycle_exists',
                'label' => 'Academic cycle',
                'status' => $exists ? 'pass' : 'fail',
                'detail' => $exists ? 'Academic cycle exists' : 'Academic cycle does not exist',
            ],
            [
                'key' => 'cycle_not_active',
                'label' => 'Activation state',
                'status' => $alreadyActive ? 'fail' : 'pass',
                'detail' => $alreadyActive ? 'Academic cycle is already active' : 'Academic cycle is not active',
            ],
        ];

        $currentCode = $activeCycle?->code ?? 'None';

        return new PreflightResult(
            outcome: $exists && ! $alreadyActive ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'active_cycle',
                'label' => 'Active cycle',
                'before' => $currentCode,
                'after' => $cycle->code,
            ]],
        );
    }
}
