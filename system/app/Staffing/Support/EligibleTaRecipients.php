<?php

namespace App\Staffing\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * D-016 eligible recipients for Test Administrator capability delegation.
 */
final class EligibleTaRecipients
{
    public const ELIGIBLE_ROLE_HELPERS = [
        'guidance_clerk',
        'guidance_support_staff',
    ];

    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    public function isEligible(User $candidate): bool
    {
        $profile = $candidate->staffProfile;
        if ($profile === null || $profile->status !== StaffProfile::STATUS_ACTIVE) {
            return false;
        }

        if ($profile->office !== 'guidance') {
            return false;
        }

        return $candidate->hasAnyRole(self::ELIGIBLE_ROLE_HELPERS);
    }

    public function canDelegate(User $actor, User $target): bool
    {
        return $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_TA_DELEGATE)
            && $actor->id !== $target->id
            && $this->isEligible($target);
    }

    /**
     * inherent (counselor seed) | delegated | none
     */
    public function taStatus(User $user): string
    {
        if ($user->hasRole('guidance_counselor')) {
            return 'inherent';
        }

        return $this->hasDelegatedTa($user) ? 'delegated' : 'none';
    }

    public function hasDelegatedTa(User $user): bool
    {
        if (! $this->isEligible($user)) {
            return false;
        }

        foreach (CapabilityCatalog::testAdministratorCapabilities() as $capability) {
            if (! $this->authorizer->allows($user, $capability)) {
                return false;
            }
        }

        // Marker: proctor.assign is not on clerk/support role helpers — only via direct grant.
        return $this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN);
    }

    /**
     * @return Collection<int, User>
     */
    private function eligibleUsers(): Collection
    {
        return User::query()
            ->whereHas('staffProfile', function ($q): void {
                $q->where('office', 'guidance')
                    ->where('status', StaffProfile::STATUS_ACTIVE);
            })
            ->role(self::ELIGIBLE_ROLE_HELPERS)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
