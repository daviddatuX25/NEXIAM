<?php

namespace App\Authorization;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * GF-21 decision authority. Spatie Permission is consulted only as a
 * persistence/helper for capability grant rows — never as route middleware
 * authority and never via Laravel Gate auto-checks.
 */
class CapabilityAuthorizer
{
    /**
     * @param  array<string, mixed>  $scope
     */
    public function decide(?Authenticatable $actor, string $capability, array $scope = []): AuthorizationDecision
    {
        if ($actor === null) {
            return AuthorizationDecision::deny('unauthenticated', $capability);
        }

        if (! $actor instanceof User) {
            return AuthorizationDecision::deny('capability_missing', $capability);
        }

        try {
            $granted = $actor->hasPermissionTo($capability);
        } catch (PermissionDoesNotExist) {
            $granted = false;
        }

        if (! $granted) {
            return AuthorizationDecision::deny('capability_missing', $capability);
        }

        if ($scope !== [] && ! $this->scopeAllows($actor->actorScope(), $scope)) {
            return AuthorizationDecision::deny('scope_mismatch', $capability);
        }

        return AuthorizationDecision::allow($capability);
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function allows(?Authenticatable $actor, string $capability, array $scope = []): bool
    {
        return $this->decide($actor, $capability, $scope)->allowed;
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function scopeAllows(ActorScope $actorScope, array $scope): bool
    {
        if (isset($scope['office']) && $actorScope->office !== $scope['office']) {
            return false;
        }

        if (isset($scope['institution_id']) && $actorScope->institutionId !== (int) $scope['institution_id']) {
            return false;
        }

        if (isset($scope['cycle']) && $actorScope->activeCycleCode !== $scope['cycle']) {
            return false;
        }

        if (isset($scope['program'])) {
            if (! in_array($scope['program'], $actorScope->programCodes, true)) {
                return false;
            }
        }

        if (array_key_exists('allowed', $scope) && $scope['allowed'] === false) {
            return false;
        }

        return true;
    }
}
