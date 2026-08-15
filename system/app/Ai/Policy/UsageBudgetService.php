<?php

namespace App\Ai\Policy;

use App\Models\Ai\NexiUsageReservation;
use Illuminate\Support\Str;

final class UsageBudgetService
{
    public function usedAsks(string $actorType, string $actorId): int
    {
        return (int) NexiUsageReservation::query()
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->whereIn('state', [NexiUsageReservation::STATE_RESERVED, NexiUsageReservation::STATE_SETTLED])
            ->sum('amount');
    }

    public function limit(): int
    {
        return max(0, (int) config('nexi.usage.limit', 50));
    }

    public function remaining(string $actorType, string $actorId): int
    {
        return max(0, $this->limit() - $this->usedAsks($actorType, $actorId));
    }

    public function canReserve(string $actorType, string $actorId, int $amount = 1): bool
    {
        return $this->remaining($actorType, $actorId) >= $amount;
    }

    public function reserve(
        string $actorType,
        string $actorId,
        ?int $institutionId,
        string $runId,
        int $amount = 1,
    ): ?NexiUsageReservation {
        if (! $this->canReserve($actorType, $actorId, $amount)) {
            return null;
        }

        return NexiUsageReservation::query()->create([
            'id' => (string) Str::uuid(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'institution_id' => $institutionId,
            'run_id' => $runId,
            'unit' => (string) config('nexi.usage.unit', 'asks'),
            'amount' => $amount,
            'state' => NexiUsageReservation::STATE_RESERVED,
        ]);
    }

    public function settle(string $runId): void
    {
        NexiUsageReservation::query()
            ->where('run_id', $runId)
            ->where('state', NexiUsageReservation::STATE_RESERVED)
            ->update(['state' => NexiUsageReservation::STATE_SETTLED]);
    }

    public function release(string $runId): void
    {
        NexiUsageReservation::query()
            ->where('run_id', $runId)
            ->where('state', NexiUsageReservation::STATE_RESERVED)
            ->update(['state' => NexiUsageReservation::STATE_RELEASED]);
    }

    public function markUnknownStaleReservations(): int
    {
        return NexiUsageReservation::query()
            ->where('state', NexiUsageReservation::STATE_RESERVED)
            ->where('created_at', '<', now()->subHours(2))
            ->update(['state' => NexiUsageReservation::STATE_UNKNOWN]);
    }
}
