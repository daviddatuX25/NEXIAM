<?php

namespace App\Ai\Conversation;

use App\Models\Ai\NexiConversation;
use Illuminate\Support\Str;

final class ContextEpochService
{
    /**
     * Open or reuse an open conversation for the actor/surface partition.
     * Pack changes do not segment the epoch (AD-3 lens); surface/actor/partition changes do.
     */
    public function resolveOpenConversation(
        string $actorType,
        string $actorId,
        ?int $institutionId,
        string $surfaceKey,
    ): NexiConversation {
        $partition = $this->partitionKey($actorType, $actorId, $surfaceKey);

        $existing = NexiConversation::query()
            ->where('partition_key', $partition)
            ->where('status', 'open')
            ->latest('created_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return NexiConversation::query()->create([
            'id' => (string) Str::uuid(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'institution_id' => $institutionId,
            'surface_key' => $surfaceKey,
            'partition_key' => $partition,
            'context_epoch' => (string) Str::uuid(),
            'status' => 'open',
        ]);
    }

    public function segment(NexiConversation $conversation, string $reason = 'segmented'): NexiConversation
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
}
