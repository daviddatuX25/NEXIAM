<?php

declare(strict_types=1);

namespace App\Staffing\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Staffing\InstitutionalSetupDelegation;
use App\Models\StaffProfile;
use App\Models\User;

final class EligibleSetupRecipients
{
    public const ELIGIBLE_ROLE_HELPERS = ['registrar_admin', 'registrar_clerk', 'guidance_counselor', 'guidance_clerk'];

    public const SCOPE_CAPABILITIES = [
        'cycles' => CapabilityCatalog::SETUP_CYCLES_MANAGE,
        'rooms' => CapabilityCatalog::SETUP_ROOMS_MANAGE,
        'programs' => CapabilityCatalog::SETUP_PROGRAMS_MANAGE,
    ];

    public const DELEGATED_CAPABILITIES = [
        CapabilityCatalog::SETUP_CYCLES_MANAGE,
        CapabilityCatalog::SETUP_ROOMS_MANAGE,
        CapabilityCatalog::SETUP_PROGRAMS_MANAGE,
        CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
    ];

    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /**
     * @return array<string, string>
     */
    public function scopeOptions(): array
    {
        return [
            'cycles' => 'Academic Calendar & Application Windows',
            'rooms' => 'Campus Venues & Exam Rooms',
            'programs' => 'Colleges, Departments & Degree Offerings',
        ];
    }

    /**
     * @param  array<int, mixed>  $scopes
     * @return list<string>
     */
    public function normalizeScopes(array $scopes): array
    {
        $selected = [];
        foreach ($scopes as $scope) {
            if (! is_string($scope) || ! array_key_exists($scope, self::SCOPE_CAPABILITIES)) {
                throw new \RuntimeException('setup_delegate_scopes_invalid');
            }

            $selected[$scope] = true;
        }

        $normalized = array_values(array_filter(
            array_keys(self::SCOPE_CAPABILITIES),
            static fn (string $scope): bool => isset($selected[$scope]),
        ));

        if ($normalized === []) {
            throw new \RuntimeException('setup_delegate_scopes_required');
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $scopes
     * @return list<string>
     */
    public function capabilitiesForScopes(array $scopes): array
    {
        return array_map(
            static fn (string $scope): string => self::SCOPE_CAPABILITIES[$scope],
            $this->normalizeScopes($scopes),
        );
    }

    /**
     * @return list<string>
     */
    public function delegatedScopes(User $user): array
    {
        if ($user->relationLoaded('activeSetupDelegation')) {
            $delegation = $user->getRelation('activeSetupDelegation');
        } else {
            $delegation = InstitutionalSetupDelegation::query()
                ->active()
                ->where('recipient_id', $user->id)
                ->first();
        }

        if (! $delegation instanceof InstitutionalSetupDelegation || ! is_array($delegation->scopes)) {
            return [];
        }

        try {
            return $this->normalizeScopes($delegation->scopes);
        } catch (\RuntimeException) {
            return [];
        }
    }

    public function isEligible(User $candidate, ?int $institutionId = null): bool
    {
        $profile = $candidate->staffProfile;
        if ($profile === null || $profile->status !== StaffProfile::STATUS_ACTIVE || $profile->deactivated_at !== null) {
            return false;
        }

        if ($institutionId !== null && (int) $profile->institution_id !== $institutionId) {
            return false;
        }

        if (! in_array($profile->office, ['registrar', 'guidance'], true)) {
            return false;
        }

        if ($candidate->hasAnyRole(['institutional_admin', 'hr_administrator'])) {
            return false;
        }

        return $candidate->hasAnyRole(self::ELIGIBLE_ROLE_HELPERS);
    }

    public function canDelegate(User $actor, User $target): bool
    {
        $actorScope = $actor->actorScope();

        return $this->authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_DELEGATE, ['institution_id' => $actorScope->institutionId])
            && $actor->id !== $target->id
            && $this->isEligible($target, $actorScope->institutionId);
    }

    public function hasDelegatedSetup(User $user): bool
    {
        return $this->delegatedScopes($user) !== [];
    }

    public function setupStatus(User $user): string
    {
        if ($user->hasRole('institutional_admin')) {
            return 'inherent';
        }

        if ($this->hasDelegatedSetup($user)) {
            return 'delegated';
        }

        return 'none';
    }
}
