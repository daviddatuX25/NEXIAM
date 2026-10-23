<?php

namespace App\Ai\Grants;

use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamTaskGrant;
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
    ): XiamTaskGrant {
        return XiamTaskGrant::query()->create([
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
            'status' => XiamTaskGrant::STATUS_ACTIVE,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findActive(string $grantId): ?XiamTaskGrant
    {
        $grant = XiamTaskGrant::query()->find($grantId);
        if ($grant === null) {
            return null;
        }

        if ($grant->status === XiamTaskGrant::STATUS_ACTIVE && $grant->expires_at !== null && $grant->expires_at->isPast()) {
            $grant->forceFill([
                'status' => XiamTaskGrant::STATUS_EXPIRED,
                'revoked_at' => now(),
                'revoke_reason' => 'expired',
            ])->save();

            return null;
        }

        return $grant->isUsable() ? $grant : null;
    }

    public function findActiveForActor(
        string $actorType,
        string $actorId,
        string $surfaceKey,
        ?string $contextEpoch = null,
    ): ?XiamTaskGrant {
        $grants = XiamTaskGrant::query()
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->where('surface_key', $surfaceKey)
            ->where('status', XiamTaskGrant::STATUS_ACTIVE)
            ->latest('created_at')
            ->get();

        foreach ($grants as $grant) {
            $active = $this->findActive($grant->id);
            if ($active === null) {
                continue;
            }

            if ($contextEpoch !== null && (string) $active->context_epoch !== (string) $contextEpoch) {
                $this->revoke($active, 'stale_context_epoch');

                continue;
            }

            return $active;
        }

        return null;
    }

    /**
     * Navigation may preserve a grant only within the same surface (and optional scope bounds).
     */
    public function usableAfterNavigation(XiamTaskGrant $grant, string $surfaceKey): bool
    {
        if (! $grant->isUsable()) {
            return false;
        }

        return $grant->surface_key === $surfaceKey;
    }

    public function revoke(XiamTaskGrant $grant, string $reason): void
    {
        if ($grant->status !== XiamTaskGrant::STATUS_ACTIVE) {
            return;
        }

        $grant->forceFill([
            'status' => XiamTaskGrant::STATUS_REVOKED,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ])->save();
    }

    public function revokeAllForActor(string $actorType, string $actorId, string $reason): int
    {
        $count = 0;
        XiamTaskGrant::query()
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->where('status', XiamTaskGrant::STATUS_ACTIVE)
            ->each(function (XiamTaskGrant $grant) use ($reason, &$count): void {
                $this->revoke($grant, $reason);
                $count++;
            });

        $conversationIds = XiamConversation::query()
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->pluck('id');

        if ($conversationIds->isNotEmpty()) {
            XiamProposal::query()
                ->whereIn('conversation_id', $conversationIds)
                ->where('status', 'pending')
                ->update([
                    'status' => 'invalidated',
                    'failure_reason' => $reason,
                ]);
        }

        return $count;
    }

    public function revokeAllActive(string $reason, ?int $institutionId = null): int
    {
        $count = 0;
        $grantsQuery = XiamTaskGrant::query()->where('status', XiamTaskGrant::STATUS_ACTIVE);
        if ($institutionId !== null && $institutionId > 0) {
            $grantsQuery->where('institution_id', $institutionId);
        }

        $grantsQuery->each(function (XiamTaskGrant $grant) use ($reason, &$count): void {
            $this->revoke($grant, $reason);
            $count++;
        });

        $proposalsQuery = XiamProposal::query()->where('status', 'pending');
        if ($institutionId !== null && $institutionId > 0) {
            $proposalsQuery->whereHas('conversation', fn ($q) => $q->where('institution_id', $institutionId));
        }

        $proposalsQuery->update([
            'status' => 'invalidated',
            'failure_reason' => $reason,
        ]);

        return $count;
    }
}
