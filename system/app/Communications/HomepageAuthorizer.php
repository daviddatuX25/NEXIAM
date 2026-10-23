<?php

declare(strict_types=1);

namespace App\Communications;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class HomepageAuthorizer
{
    public function __construct(private readonly CapabilityAuthorizer $capabilities) {}

    public function assertCanManage(User $actor): int
    {
        $freshActor = User::query()->find($actor->getKey());
        $profile = $freshActor === null ? null : StaffProfile::query()->where('user_id', $freshActor->getKey())->first();
        $institutionId = (int) ($profile?->institution_id ?? 0);
        $scope = $freshActor?->actorScope();

        if ($freshActor === null || $profile === null || ! $profile->isActive()
            || $institutionId < 1 || $scope?->institutionId !== $institutionId
            || ! $this->capabilities->decideFresh($freshActor, CapabilityCatalog::COMMUNICATIONS_HOMEPAGE_MANAGE, [
                'institution_id' => $institutionId,
            ])->allowed) {
            throw new AuthorizationException('You are not authorized to manage the public homepage.');
        }

        return $institutionId;
    }
}
