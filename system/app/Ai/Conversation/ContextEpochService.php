<?php

namespace App\Ai\Conversation;

use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamTaskGrant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ContextEpochService
{
    /**
     * Open or reuse an open conversation for the actor/surface partition.
     *
     * Route, browser-session, and surface-profile changes advance the server
     * epoch under a row lock and invalidate unconfirmed work.
     */
    public function resolveOpenConversation(
        string $actorType,
        string $actorId,
        ?int $institutionId,
        string $surfaceKey,
        ?string $routeName = null,
        ?string $browserSessionId = null,
        ?string $surfaceProfileId = null,
    ): XiamConversation {
        return DB::transaction(function () use (
            $actorType,
            $actorId,
            $institutionId,
            $surfaceKey,
            $routeName,
            $browserSessionId,
            $surfaceProfileId,
        ): XiamConversation {
            $partition = $this->partitionKey($actorType, $actorId, $surfaceKey);
            $existing = XiamConversation::query()
                ->where('partition_key', $partition)
                ->where('status', 'open')
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($this->contextChanged($existing, $routeName, $browserSessionId, $surfaceProfileId ?? $surfaceKey)) {
                    return $this->advanceLocked(
                        $existing,
                        $routeName,
                        $browserSessionId,
                        $surfaceProfileId ?? $surfaceKey,
                        null,
                    );
                }

                return $existing;
            }

            return XiamConversation::query()->create([
                'id' => (string) Str::uuid(),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'institution_id' => $institutionId,
                'surface_key' => $surfaceKey,
                'partition_key' => $partition,
                'context_epoch' => (string) Str::uuid(),
                'epoch_version' => 1,
                'browser_session_id' => $browserSessionId,
                'route_name' => $routeName,
                'surface_profile_id' => $surfaceProfileId ?? $surfaceKey,
                'status' => 'open',
            ]);
        });
    }

    public function transition(
        XiamConversation $conversation,
        string $routeName,
        string $surfaceProfileId,
        ?string $browserSessionId = null,
        ?string $expectedEpoch = null,
    ): XiamConversation {
        return DB::transaction(function () use (
            $conversation,
            $routeName,
            $surfaceProfileId,
            $browserSessionId,
            $expectedEpoch,
        ): XiamConversation {
            $locked = XiamConversation::query()->lockForUpdate()->findOrFail($conversation->id);

            return $this->advanceLocked(
                $locked,
                $routeName,
                $browserSessionId,
                $surfaceProfileId,
                $expectedEpoch,
            );
        });
    }

    public function segment(XiamConversation $conversation, string $reason = 'segmented'): XiamConversation
    {
        unset($reason);
        $conversation->forceFill(['status' => 'closed'])->save();

        return $this->resolveOpenConversation(
            $conversation->actor_type,
            $conversation->actor_id,
            $conversation->institution_id,
            $conversation->surface_key,
        );
    }

    public function partitionKey(string $actorType, string $actorId, string $surfaceKey): string
    {
        return $actorType.':'.$actorId.':'.$surfaceKey;
    }

    private function contextChanged(
        XiamConversation $conversation,
        ?string $routeName,
        ?string $browserSessionId,
        string $surfaceProfileId,
    ): bool {
        return ($routeName !== null && $conversation->route_name !== $routeName)
            || ($browserSessionId !== null && $conversation->browser_session_id !== $browserSessionId)
            || $conversation->surface_profile_id !== $surfaceProfileId;
    }

    private function advanceLocked(
        XiamConversation $conversation,
        ?string $routeName,
        ?string $browserSessionId,
        string $surfaceProfileId,
        ?string $expectedEpoch,
    ): XiamConversation {
        if ($expectedEpoch !== null && $expectedEpoch !== (string) $conversation->context_epoch) {
            throw new RuntimeException('context_epoch_conflict');
        }

        $previousEpoch = (string) $conversation->context_epoch;
        $nextEpoch = (string) Str::uuid();
        $nextVersion = (int) ($conversation->epoch_version ?? 1) + 1;

        $updated = XiamConversation::query()
            ->whereKey($conversation->id)
            ->where('context_epoch', $previousEpoch)
            ->where('epoch_version', (int) ($conversation->epoch_version ?? 1))
            ->update([
                'context_epoch' => $nextEpoch,
                'epoch_version' => $nextVersion,
                'route_name' => $routeName,
                'browser_session_id' => $browserSessionId,
                'surface_profile_id' => $surfaceProfileId,
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('context_epoch_conflict');
        }

        XiamProposal::query()
            ->where('conversation_id', $conversation->id)
            ->where('status', 'pending')
            ->update(['status' => 'invalidated']);

        XiamTaskGrant::query()
            ->where('actor_id', $conversation->actor_id)
            ->where('context_epoch', $previousEpoch)
            ->where('status', XiamTaskGrant::STATUS_ACTIVE)
            ->update([
                'status' => XiamTaskGrant::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoke_reason' => 'context_epoch_transition',
            ]);

        return $conversation->fresh() ?? $conversation;
    }
}
