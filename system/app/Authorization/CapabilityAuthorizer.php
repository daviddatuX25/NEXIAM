<?php

namespace App\Authorization;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
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
     * Resolve a persisted principal again before checking permissions and scope.
     *
     * Direct Actions and replay paths use this boundary so loaded role/permission
     * relations on a caller's User instance cannot preserve revoked grants.
     *
     * @param  array<string, mixed>  $scope
     */
    public function decideFresh(?Authenticatable $actor, string $capability, array $scope = []): AuthorizationDecision
    {
        if (! $actor instanceof User) {
            return $this->decide($actor, $capability, $scope);
        }

        $principal = User::query()->find($actor->getKey());

        if ($principal === null) {
            return AuthorizationDecision::deny('capability_missing', $capability);
        }

        return $this->decide($principal, $capability, $scope);
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function allows(?Authenticatable $actor, string $capability, array $scope = []): bool
    {
        return $this->decide($actor, $capability, $scope)->allowed;
    }

    /**
     * True if any listed capability is allowed (page/Kernel shared lists).
     *
     * @param  list<string>  $capabilities
     * @param  array<string, mixed>  $scope
     */
    public function allowsAny(?Authenticatable $actor, array $capabilities, array $scope = []): bool
    {
        foreach ($capabilities as $capability) {
            if ($this->allows($actor, $capability, $scope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Authorize user has capability or throw AuthorizationException.
     *
     * @param  array<string, mixed>  $scope
     *
     * @throws AuthorizationException
     */
    public function authorize(?Authenticatable $actor, string $capability, array $scope = []): void
    {
        $decision = $this->decide($actor, $capability, $scope);

        if (! $decision->allowed) {
            throw new AuthorizationException("User does not have required capability: {$capability}");
        }
    }

    /**
     * Authorize user has capability or throw AuthorizationException.
     *
     * @param  array<string, mixed>  $scope
     *
     * @throws AuthorizationException
     */
    public static function authorizeUser(User $actor, string $capability, array $scope = []): void
    {
        $authorizer = app(self::class);
        $decision = $authorizer->decide($actor, $capability, $scope);

        if (! $decision->allowed) {
            throw new AuthorizationException("User does not have required capability: {$capability}");
        }
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
