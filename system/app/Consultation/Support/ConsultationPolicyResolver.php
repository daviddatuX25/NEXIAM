<?php

declare(strict_types=1);

namespace App\Consultation\Support;

use App\Models\Consultation\ConsultationPolicy;
use App\Models\Foundation\AcademicCycle;

final class ConsultationPolicyResolver
{
    public function isOnlineEnabled(int $institutionId, int $academicCycleId): bool
    {
        $policy = ConsultationPolicy::query()
            ->where('institution_id', $institutionId)
            ->where('academic_cycle_id', $academicCycleId)
            ->first();

        return $policy?->allowsOnlineConsultation() ?? true;
    }

    public function permitsEncounter(int $institutionId, int $academicCycleId, ?int $pinnedVersion): bool
    {
        if ($pinnedVersion === null) {
            return false;
        }

        $policy = ConsultationPolicy::query()
            ->where('institution_id', $institutionId)
            ->where('academic_cycle_id', $academicCycleId)
            ->first();

        return $policy === null
            ? $pinnedVersion === 0
            : $policy->allowsOnlineConsultation();
    }

    public function forCycle(AcademicCycle $cycle): ConsultationPolicy
    {
        return ConsultationPolicy::query()
            ->where('institution_id', $cycle->institution_id)
            ->where('academic_cycle_id', $cycle->id)
            ->first() ?? new ConsultationPolicy([
                'institution_id' => $cycle->institution_id,
                'academic_cycle_id' => $cycle->id,
                'normal_method' => ConsultationPolicy::METHOD_ONLINE,
                'allow_online_exceptions' => false,
                'version' => 0,
            ]);
    }
}
