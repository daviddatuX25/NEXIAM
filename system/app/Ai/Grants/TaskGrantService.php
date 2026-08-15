<?php

namespace App\Ai\Grants;

use App\Models\Ai\NexiTaskGrant;
use Illuminate\Support\Str;

final class TaskGrantService
{
    /**
     * @param  list<string>  $allowedCapabilityIds
     * @param  array<string, mixed>|null  $scopeBounds
     */
    public function issue(
        string $actorType,
        string $actorId,
        ?string $sessionId,
        ?int $institutionId,
        string $surfaceKey,
        string $skillKey,
        int $skillVersion,
        array $allowedCapabilityIds,
        string $contextEpoch,
        ?string $originPackKey = null,
        ?int $originPackVersion = null,
        ?array $scopeBounds = null,
        ?\DateTimeInterface $expiresAt = null,
    ): NexiTaskGrant {
        return NexiTaskGrant::query()->create([
            'id' => (string) Str::uuid(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'session_id' => $sessionId,
            'institution_id' => $institutionId,
            'surface_key' => $surfaceKey,
            'skill_key' => $skillKey,
            'skill_version' => $skillVersion,
            'allowed_capability_ids' => $allowedCapabilityIds,
            'scope_bounds' => $scopeBounds,
            'origin_pack_key' => $originPackKey,
            'origin_pack_version' => $originPackVersion,
            'context_epoch' => $contextEpoch,
            'status' => NexiTaskGrant::STATUS_ACTIVE,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findActive(string $grantId): ?NexiTaskGrant
    {
        $grant = NexiTaskGrant::query()->find($grantId);
        if ($grant === null) {
            return null;
        }

        if ($grant->status === NexiTaskGrant::STATUS_ACTIVE && $grant->expires_at !== null && $grant->expires_at->isPast()) {
            $grant->forceFill([
                'status' => NexiTaskGrant::STATUS_EXPIRED,
                'revoked_at' => now(),
                'revoke_reason' => 'expired',
            ])->save();

            return null;
        }

        return $grant->isUsable() ? $grant : null;
    }

    /**
     * Navigation may preserve a grant only within the same surface (and optional scope bounds).
     */
    public function usableAfterNavigation(NexiTaskGrant $grant, string $surfaceKey): bool
    {
        if (! $grant->isUsable()) {
            return false;
        }

        return $grant->surface_key === $surfaceKey;
    }

    public function revoke(NexiTaskGrant $grant, string $reason): void
    {
        if ($grant->status !== NexiTaskGrant::STATUS_ACTIVE) {
            return;
        }

        $grant->forceFill([
            'status' => NexiTaskGrant::STATUS_REVOKED,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ])->save();
    }

    public function revokeAllForActor(string $actorType, string $actorId, string $reason): int
    {
        $count = 0;
        NexiTaskGrant::query()
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->where('status', NexiTaskGrant::STATUS_ACTIVE)
            ->each(function (NexiTaskGrant $grant) use ($reason, &$count): void {
                $this->revoke($grant, $reason);
                $count++;
            });

        return $count;
    }

    public function revokeAllActive(string $reason): int
    {
        $count = 0;
        NexiTaskGrant::query()
            ->where('status', NexiTaskGrant::STATUS_ACTIVE)
            ->each(function (NexiTaskGrant $grant) use ($reason, &$count): void {
                $this->revoke($grant, $reason);
                $count++;
            });

        return $count;
    }
}
