<?php

declare(strict_types=1);

namespace App\Staffing\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;

final class UsableHrAdministratorPool
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    public function lockInstitution(int $institutionId): void
    {
        Institution::query()->whereKey($institutionId)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  list<int>  $excludedUserIds
     */
    public function hasUsableAdministratorRemaining(int $institutionId, array $excludedUserIds = []): bool
    {
        if ($institutionId < 1) {
            return false;
        }

        $users = User::role('hr_administrator')
            ->whereHas('staffProfile', fn ($query) => $query
                ->where('institution_id', $institutionId)
                ->where('status', StaffProfile::STATUS_ACTIVE))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $userIds = $users->modelKeys();
        if ($userIds === []) {
            return false;
        }

        $profiles = StaffProfile::query()
            ->whereIn('user_id', $userIds)
            ->where('institution_id', $institutionId)
            ->where('status', StaffProfile::STATUS_ACTIVE)
            ->orderBy('user_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('user_id');
        $states = StaffAuthenticationState::query()
            ->whereIn('user_id', $userIds)
            ->orderBy('user_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('user_id');

        foreach ($users as $user) {
            if (in_array((int) $user->id, $excludedUserIds, true)) {
                continue;
            }

            if ($this->isUsableAdministrator(
                $user,
                $profiles->get($user->id),
                $states->get($user->id),
                $institutionId,
            )) {
                return true;
            }
        }

        return false;
    }

    public function isUsableAdministrator(
        User $user,
        ?StaffProfile $profile,
        ?StaffAuthenticationState $state,
        int $institutionId,
    ): bool {
        return $institutionId > 0
            && $user->hasRole('hr_administrator')
            && $profile !== null
            && $profile->isActive()
            && (int) $profile->institution_id === $institutionId
            && is_string($user->password)
            && $user->password !== ''
            && $state !== null
            && $state->activation_completed_at !== null
            && $state->password_set_at !== null
            && ! $state->must_change_password
            && $state->hasConfirmedTwoFactor()
            && is_string($state->two_factor_secret)
            && $state->two_factor_secret !== ''
            && $this->authorizer->decideFresh($user, CapabilityCatalog::HR_STAFF_MANAGE, [
                'institution_id' => $institutionId,
            ])->allowed;
    }
}
