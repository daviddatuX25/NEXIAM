<?php

namespace App\Staffing\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Department;
use App\Models\User;

final class StaffVoucherTargetAuthority
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly StaffRoleCeiling $ceiling,
        private readonly StaffTargetManagementScope $targetManagementScope,
    ) {}

    public function permits(User $actor, User $target): bool
    {
        $actor->load('staffProfile');
        $target->load('staffProfile');
        $actorProfile = $actor->staffProfile;
        $targetProfile = $target->staffProfile;
        if ($actorProfile === null || $targetProfile === null || ! $actorProfile->isActive() || ! $targetProfile->isActive()
            || $actor->is($target) || $actorProfile->institution_id === null
            || (int) $actorProfile->institution_id !== (int) $targetProfile->institution_id) {
            return false;
        }

        $institutionId = (int) $targetProfile->institution_id;
        if ($this->authorizer->decideFresh($actor, CapabilityCatalog::HR_STAFF_MANAGE, ['institution_id' => $institutionId])->allowed) {
            return true;
        }

        $hasDeanDesignation = $actor->hasRole('dean')
            || Department::query()->where('dean_user_id', $actor->id)->exists();
        if ($hasDeanDesignation) {
            $hasActiveOwnedDepartment = Department::query()
                ->where('institution_id', $institutionId)
                ->where('dean_user_id', $actor->id)
                ->where('is_active', true)
                ->exists();

            return $hasActiveOwnedDepartment && $this->targetManagementScope->allows($actor, $target);
        }

        return $this->authorizer->decideFresh($actor, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE, ['institution_id' => $institutionId])->allowed
            && $actorProfile->office === $targetProfile->office
            && $this->ceiling->rankOf($actor) >= 3
            && $this->ceiling->rankOf($actor) > $this->ceiling->rankOf($target);
    }
}
