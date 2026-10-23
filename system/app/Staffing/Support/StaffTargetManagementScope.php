<?php

declare(strict_types=1);

namespace App\Staffing\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;

final class StaffTargetManagementScope
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /** @return list<int> */
    public function ownedActiveDepartmentIds(User $actor, int $institutionId): array
    {
        if (! $this->actorIsActiveInInstitution($actor, $institutionId)) {
            return [];
        }

        return Department::query()->where('institution_id', $institutionId)->where('dean_user_id', $actor->id)->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function actorIsActiveInInstitution(User $actor, int $institutionId): bool
    {
        $profile = StaffProfile::query()->where('user_id', $actor->id)->first();
        $freshActor = User::query()->whereKey($actor->id)->with('staffProfile')->first();

        return $institutionId > 0 && $freshActor !== null
            && (int) ($freshActor->actorScope()->institutionId ?? 0) === $institutionId
            && $profile?->isActive() === true && (int) $profile->institution_id === $institutionId;
    }

    public function allows(User $actor, User $target): bool
    {
        $institutionId = (int) ($actor->actorScope()->institutionId ?? 0);
        if (! $this->actorIsActiveInInstitution($actor, $institutionId)) {
            return false;
        }
        $freshActor = User::query()->whereKey($actor->id)->with(['roles', 'staffProfile'])->first();
        $freshTarget = User::query()->whereKey($target->id)->with(['roles', 'staffProfile'])->first();
        $actorProfile = $freshActor?->staffProfile;
        $targetProfile = $freshTarget?->staffProfile;
        if (! $freshActor || ! $freshTarget || ! $actorProfile?->isActive() || ! $targetProfile
            || (int) $targetProfile->institution_id !== $institutionId || (int) $freshActor->id === (int) $freshTarget->id) {
            return false;
        }

        if ($this->authorizer->decideFresh($freshActor, CapabilityCatalog::HR_STAFF_MANAGE, ['institution_id' => $institutionId])->allowed) {
            return true;
        }

        $ownedDepartments = Department::query()->where('institution_id', $institutionId)->where('dean_user_id', $freshActor->id)->where('is_active', true)->pluck('id')->all();
        if ($ownedDepartments !== []) {
            if ($targetProfile->office !== 'program') {
                return false;
            }
            $targetInDepartment = in_array((int) $targetProfile->department_id, array_map('intval', $ownedDepartments), true)
                || Program::query()->where('institution_id', $institutionId)->whereIn('department_id', $ownedDepartments)
                    ->where('is_active', true)->whereIn('code', (array) ($targetProfile->program_codes ?? []))->exists();

            return $targetInDepartment && $this->rank($freshActor, $institutionId, $ownedDepartments) > $this->rank($freshTarget, $institutionId);
        }

        $office = (string) $actorProfile->office;

        return $office !== '' && $targetProfile->office === $office
            && $this->authorizer->decideFresh($freshActor, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE, [
                'institution_id' => $institutionId,
                'office' => $office,
            ])->allowed
            && $this->rank($freshActor, $institutionId) > $this->rank($freshTarget, $institutionId);
    }

    /** @param list<int|string> $ownedDepartments */
    private function rank(User $user, int $institutionId, array $ownedDepartments = []): int
    {
        $rank = $user->roles->pluck('name')->map(fn (string $name) => StaffRoleCeiling::ROLE_RANKS[$name] ?? 0)->max() ?? 0;
        $isDean = $ownedDepartments !== [] || Department::query()->where('institution_id', $institutionId)->where('dean_user_id', $user->id)->where('is_active', true)->exists();

        return $isDean ? max(3, $rank) : $rank;
    }
}
