<?php

namespace App\Ai\Policy;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\Ai\XiamModelTariff;
use App\Models\Ai\XiamProviderConfiguration;
use App\Models\Ai\XiamUsageReservation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UsageBudgetService
{
    private const EXTENSION_UNIT = 'asks_extension';

    public function __construct(private CapabilityAuthorizer $authorizer) {}

    public function usedAsks(string $actorType, string $actorId): int
    {
        return $this->netDebit($actorType, $actorId);
    }

    /**
     * @return array{
     *     state: 'available'|'near_limit'|'exhausted',
     *     used: int,
     *     limit: int,
     *     remaining: int|null,
     *     credit_balance: int,
     *     unit: string,
     *     period: string,
     *     resets_at: string|null
     * }
     */
    public function view(string $actorType, string $actorId): array
    {
        $netDebit = $this->netDebit($actorType, $actorId);
        $limit = $this->limit($actorType, $actorId);
        $remaining = $limit < 0 ? null : max(0, $limit - $netDebit);
        $used = max(0, $netDebit);
        $period = (string) config('xiam.usage.period', 'today');

        return [
            'state' => $remaining === 0
                ? 'exhausted'
                : ($limit > 0 && $used >= (int) floor($limit * 0.8) ? 'near_limit' : 'available'),
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'credit_balance' => max(0, -$netDebit),
            'unit' => (string) config('xiam.usage.unit', 'asks'),
            'period' => $period,
            'resets_at' => $period === 'today' ? today()->addDay()->startOfDay()->toIso8601String() : null,
        ];
    }

    private function netDebit(string $actorType, string $actorId): int
    {
        return (int) XiamUsageReservation::query()
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->when(config('xiam.usage.period', 'today') === 'today', fn ($query) => $query->whereDate('created_at', today()))
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('unit', self::EXTENSION_UNIT)
                        ->where('state', XiamUsageReservation::STATE_SETTLED);
                })->orWhere(function ($query): void {
                    $query->where('unit', '!=', self::EXTENSION_UNIT)
                        ->whereIn('state', [
                            XiamUsageReservation::STATE_RESERVED,
                            XiamUsageReservation::STATE_SETTLED,
                            XiamUsageReservation::STATE_UNKNOWN,
                        ]);
                });
            })
            ->selectRaw('coalesce(sum(case when unit = ? then -amount else amount end), 0) as net_debit', [self::EXTENSION_UNIT])
            ->first()
            ->net_debit;
    }

    public function limit(string $actorType = 'staff', ?string $actorId = null): int
    {
        $globalLimit = config('xiam.usage.limit');
        if ($globalLimit !== null) {
            return (int) $globalLimit;
        }

        if ($actorType === 'staff' && $actorId !== null && $this->isAdministrator($actorId)) {
            return (int) config('xiam.usage.admin_limit', -1);
        }

        return match ($actorType) {
            'applicant' => max(0, (int) config('xiam.usage.applicant_limit', 15)),
            default => max(0, (int) config('xiam.usage.staff_limit', 200)),
        };
    }

    public function remaining(string $actorType, string $actorId): int
    {
        $limit = $this->limit($actorType, $actorId);

        if ($limit < 0) {
            return PHP_INT_MAX;
        }

        return max(0, $limit - $this->usedAsks($actorType, $actorId));
    }

    public function canReserve(string $actorType, string $actorId, int $amount = 1): bool
    {
        return $this->remaining($actorType, $actorId) >= $amount;
    }

    public function grantExtension(
        User $granter,
        string $targetActorId,
        int $amount,
        string $reason,
    ): XiamUsageReservation {
        if (! $this->authorizer->allowsAny($granter, [
            CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE,
            CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE,
        ])) {
            throw new AuthorizationException('You are not authorized to grant Xiam usage extensions.');
        }

        if ($amount < 1 || trim($reason) === '') {
            throw new InvalidArgumentException('An extension amount and reason are required.');
        }

        $target = User::query()->find($targetActorId);
        if ($target === null || ! $target->staffProfile()->exists()) {
            throw new InvalidArgumentException('The extension target must be an existing staff actor.');
        }

        $granterScope = $granter->actorScope();
        $targetScope = $target->actorScope();
        if ($granterScope->institutionId !== $targetScope->institutionId
            || (! $this->authorizer->allows($granter, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE)
                && $granterScope->office !== $targetScope->office)) {
            throw new AuthorizationException('Usage extensions are limited to the same institution and office.');
        }

        return DB::transaction(function () use ($target, $amount, $reason, $granter, $targetScope): XiamUsageReservation {
            $actorType = 'staff';
            $actorId = (string) $target->getAuthIdentifier();
            $this->lockQuota($actorType, $actorId);

            $reservation = XiamUsageReservation::query()->create([
                'id' => (string) Str::uuid(),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'institution_id' => $granter->actorScope()->institutionId,
                'office' => $targetScope->office,
                'granted_by_user_id' => $granter->id,
                'extension_reason' => $reason,
                'run_id' => null,
                'unit' => self::EXTENSION_UNIT,
                'amount' => $amount,
                'state' => XiamUsageReservation::STATE_SETTLED,
            ]);
            app(RecordAuditEvent::class)->handle('ai.usage.extension_granted', [
                'institution_id' => $granter->actorScope()->institutionId,
                'office' => $targetScope->office,
                'target_actor_id' => $actorId,
                'amount' => $amount,
                'reason' => $reason,
            ], (string) $granter->id);

            return $reservation;
        });
    }

    public function reserve(
        string $actorType,
        string $actorId,
        ?int $institutionId,
        string $runId,
        int $amount = 1,
    ): ?XiamUsageReservation {
        if ($amount < 1) {
            return null;
        }

        return DB::transaction(function () use ($actorType, $actorId, $institutionId, $runId, $amount): ?XiamUsageReservation {
            $existing = XiamUsageReservation::query()->where('run_id', $runId)->first();
            if ($existing !== null) {
                return $existing;
            }

            $user = User::query()->find($actorId);
            $office = $actorType === 'applicant' ? 'applicant' : ($user?->actorScope()->office ?? 'staff');
            $this->lockQuota($actorType, $actorId);
            $used = $this->usedAsks($actorType, $actorId);
            $limit = $this->limit($actorType, $actorId);
            if ($limit >= 0 && $used + $amount > $limit) {
                return null;
            }

            $estimatedCost = $this->estimateReservationCost($institutionId) * $amount;
            $policy = $institutionId === null ? null : XiamInstitutionPolicy::query()
                ->where('institution_id', $institutionId)
                ->first();
            if ($policy !== null) {
                $poolCeiling = $actorType === 'applicant'
                    ? $policy->daily_applicant_pool_asks
                    : ($policy->office_ask_allocations[$office] ?? null);
                if ($poolCeiling !== null && $this->usedPoolAsks($institutionId, $office) + $amount > (int) $poolCeiling) {
                    return null;
                }

                if (($policy->daily_institution_ask_ceiling ?? 0) > 0
                    && $this->usedInstitutionAsks($institutionId) + $amount > (int) $policy->daily_institution_ask_ceiling) {
                    return null;
                }

                $officeBudget = (int) ($policy->office_budget_allocations_micro[$office] ?? 0);
                $institutionBudget = (int) ($policy->daily_institution_budget_micro ?? 0);
                if (($officeBudget > 0 && $this->usedCost($institutionId, $office) + $estimatedCost > $officeBudget)
                    || ($institutionBudget > 0 && $this->usedCost($institutionId) + $estimatedCost > $institutionBudget)) {
                    return null;
                }
            }

            return XiamUsageReservation::query()->create([
                'id' => (string) Str::uuid(),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'institution_id' => $institutionId,
                'office' => $office,
                'run_id' => $runId,
                'unit' => (string) config('xiam.usage.unit', 'asks'),
                'amount' => $amount,
                'reserved_cost_micro' => $estimatedCost,
                'state' => XiamUsageReservation::STATE_RESERVED,
            ]);
        });
    }

    private function estimateReservationCost(?int $institutionId): int
    {
        $configuration = $institutionId === null ? null : XiamProviderConfiguration::query()
            ->where('institution_id', $institutionId)
            ->first();
        $driver = (string) ($configuration?->driver ?? config('ai.providers.'.config('xiam.ai.provider', 'openai').'.driver', config('xiam.ai.provider', 'openai')));
        $model = (string) ($configuration?->active_model_id ?? config('xiam.ai.model', ''));
        if ($model === '') {
            return 0;
        }

        $tariff = XiamModelTariff::query()->where('model_id', $model)->where('provider_driver', $driver)->where('is_active', true)->first();
        if ($tariff === null) {
            return 0;
        }

        $maxTokens = max(0, (int) ($configuration?->max_tokens ?? config('xiam.ai.max_tokens', 4096)));
        $completionRate = max(
            (float) $tariff->completion_micro_per_token,
            (float) $tariff->reasoning_micro_per_token,
        );

        return (int) ceil($maxTokens * $completionRate);
    }

    public function settleWithUsage(string $runId, array $usage, int $costMicro): void
    {
        $tokens = (int) ($usage['total_tokens'] ?? ((int) ($usage['input_tokens'] ?? 0) + (int) ($usage['output_tokens'] ?? 0)));
        XiamUsageReservation::query()
            ->where('run_id', $runId)
            ->whereIn('state', [XiamUsageReservation::STATE_RESERVED, XiamUsageReservation::STATE_SETTLED])
            ->update([
                'state' => XiamUsageReservation::STATE_SETTLED,
                'reserved_tokens' => max(0, $tokens),
                'reserved_cost_micro' => max(0, $costMicro),
            ]);
    }

    private function usedPoolAsks(int $institutionId, string $office): int
    {
        return (int) XiamUsageReservation::query()->where('institution_id', $institutionId)->where('office', $office)
            ->where('unit', '!=', self::EXTENSION_UNIT)->whereDate('created_at', today())
            ->whereIn('state', [XiamUsageReservation::STATE_RESERVED, XiamUsageReservation::STATE_SETTLED, XiamUsageReservation::STATE_UNKNOWN])
            ->sum('amount');
    }

    private function usedInstitutionAsks(int $institutionId): int
    {
        return (int) XiamUsageReservation::query()->where('institution_id', $institutionId)
            ->where('unit', '!=', self::EXTENSION_UNIT)->whereDate('created_at', today())
            ->whereIn('state', [XiamUsageReservation::STATE_RESERVED, XiamUsageReservation::STATE_SETTLED, XiamUsageReservation::STATE_UNKNOWN])
            ->sum('amount');
    }

    private function usedCost(int $institutionId, ?string $office = null): int
    {
        return (int) XiamUsageReservation::query()->where('institution_id', $institutionId)
            ->when($office !== null, fn ($query) => $query->where('office', $office))
            ->whereDate('created_at', today())
            ->whereIn('state', [XiamUsageReservation::STATE_RESERVED, XiamUsageReservation::STATE_SETTLED, XiamUsageReservation::STATE_UNKNOWN])
            ->sum('reserved_cost_micro');
    }

    public function settle(string $runId): void
    {
        XiamUsageReservation::query()
            ->where('run_id', $runId)
            ->where('state', XiamUsageReservation::STATE_RESERVED)
            ->update(['state' => XiamUsageReservation::STATE_SETTLED]);
    }

    public function release(string $runId): void
    {
        XiamUsageReservation::query()
            ->where('run_id', $runId)
            ->where('state', XiamUsageReservation::STATE_RESERVED)
            ->update(['state' => XiamUsageReservation::STATE_RELEASED]);
    }

    public function markUnknown(string $runId, ?string $providerRequestId = null): void
    {
        XiamUsageReservation::query()
            ->where('run_id', $runId)
            ->where('state', XiamUsageReservation::STATE_RESERVED)
            ->update([
                'state' => XiamUsageReservation::STATE_UNKNOWN,
                'provider_request_id' => $providerRequestId,
            ]);
    }

    public function reconcileUnknown(
        string $runId,
        bool $charged,
        ?string $providerRequestId = null,
        ?int $reservedTokens = null,
        ?int $reservedCostMicro = null,
    ): void {
        $reservation = XiamUsageReservation::query()
            ->where('run_id', $runId)
            ->where('state', XiamUsageReservation::STATE_UNKNOWN)
            ->first();
        if ($reservation === null) {
            return;
        }

        $reservation->forceFill([
            'state' => $charged ? XiamUsageReservation::STATE_SETTLED : XiamUsageReservation::STATE_RELEASED,
            'provider_request_id' => $providerRequestId ?? $reservation->provider_request_id,
            'reserved_tokens' => $reservedTokens ?? $reservation->reserved_tokens,
            'reserved_cost_micro' => $reservedCostMicro ?? $reservation->reserved_cost_micro,
        ])->save();
    }

    public function markUnknownStaleReservations(): int
    {
        return XiamUsageReservation::query()
            ->where('state', XiamUsageReservation::STATE_RESERVED)
            ->where('created_at', '<', now()->subHours(2))
            ->update(['state' => XiamUsageReservation::STATE_UNKNOWN]);
    }

    private function isAdministrator(?string $actorId): bool
    {
        if ($actorId === null) {
            return false;
        }

        $actor = User::query()->find($actorId);

        return $actor !== null && $this->authorizer->allows($actor, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE);
    }

    private function lockQuota(string $actorType, string $actorId): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::selectOne('select pg_advisory_xact_lock(?)', [crc32($actorType.':'.$actorId)]);
    }
}
