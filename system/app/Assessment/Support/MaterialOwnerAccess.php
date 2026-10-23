<?php

namespace App\Assessment\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class MaterialOwnerAccess
{
    /**
     * @var list<string>
     */
    private const OWNER_CAPABILITIES = [
        CapabilityCatalog::GUIDANCE_SCORING_OMR,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
    ];

    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    public function institutionId(User $actor, string $requiredCapability): int
    {
        if (! in_array($requiredCapability, self::OWNER_CAPABILITIES, true)) {
            throw new AuthorizationException('Material ownership access is unavailable.');
        }

        $principal = User::query()->find($actor->getKey());

        if ($principal === null) {
            throw new AuthorizationException('Material ownership access is unavailable.');
        }

        $activeProfile = $principal->staffProfile()
            ->where('status', StaffProfile::STATUS_ACTIVE)
            ->first();
        $scope = $principal->actorScope();
        $institutionId = (int) ($scope->institutionId ?? 0);

        if ($activeProfile === null
            || $scope->office !== 'guidance'
            || $institutionId < 1
            || (int) $activeProfile->institution_id !== $institutionId
            || ! Institution::query()->whereKey($institutionId)->exists()
            || ! $this->authorizer->decideFresh(
                $principal,
                $requiredCapability,
                ['office' => 'guidance', 'institution_id' => $institutionId],
            )->allowed) {
            throw new AuthorizationException('Material ownership access is unavailable.');
        }

        return $institutionId;
    }
}
