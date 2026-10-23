<?php

namespace App\Ai\Policy;

use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\Foundation\Institution;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class EmergencyStop
{
    /**
     * Check if emergency stop is active globally or for a specific institution.
     */
    public function active(?int $institutionId = null): bool
    {
        // 1. Deployment-level override (global kill switch)
        if ((bool) config('xiam.emergency_stop', false)) {
            return true;
        }

        try {
            if (! Schema::hasTable('xiam_institution_policies')) {
                return false;
            }

            $instId = $institutionId ?? (int) (Institution::query()->value('id') ?? 1);

            return $instId > 0
                ? XiamInstitutionPolicy::forInstitution($instId)->isEmergencyStopActive()
                : false;
        } catch (Throwable) {
            return true;
        }
    }
}
