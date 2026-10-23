<?php

namespace App\Admission\Queries;

use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;

class CurrentIntakeQuery
{
    /**
     * @return array{cycle: AcademicCycle, window: ApplicationWindow, policy: PublicPolicy}|null
     */
    public function forInstitution(Institution $institution): ?array
    {
        $now = now();
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->where('opens_on', '<=', $now->toDateString())
            ->where('closes_on', '>=', $now->toDateString())
            ->orderBy('id')
            ->first();

        if ($cycle === null) {
            return null;
        }

        $window = ApplicationWindow::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('status', 'published')
            ->where('opens_at', '<=', $now)
            ->where('closes_at', '>=', $now)
            ->orderBy('opens_at')
            ->orderBy('id')
            ->first();

        $policy = PublicPolicy::designatedIntake((int) $institution->getKey());

        if ($window === null || $policy === null) {
            return null;
        }

        return compact('cycle', 'window', 'policy');
    }
}
