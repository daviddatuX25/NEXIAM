<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Authorization\CapabilityCatalog;
use App\Communications\PublicPolicySourceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyCommandReceipt;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublicPolicyCommandSupport
{
    public function __construct(private readonly PublicPolicySourceAuthorizer $sources, private readonly RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $payload @param callable(User, ?PublicPolicy, ?int, string): PublicPolicy $mutation */
    public function run(User $actor, string $requestId, string $operation, ?int $policyId, ?int $expectedRevision, string $publisherKey, array $payload, callable $mutation, bool $requiresPublish = false): PublicPolicy
    {
        $requestId = strtolower($requestId);

        return DB::transaction(function () use ($actor, $requestId, $operation, $policyId, $expectedRevision, $publisherKey, $payload, $mutation, $requiresPublish): PublicPolicy {
            $freshActor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $institutionId = (int) $freshActor->actorScope()->institutionId;
            if ($institutionId <= 0) {
                throw ValidationException::withMessages(['policy' => 'The actor institution could not be resolved.']);
            }
            Institution::query()->whereKey($institutionId)->lockForUpdate()->firstOrFail();

            if ($policyId === null) {
                $policy = null;
            } elseif ($operation === 'designate_intake') {
                $lockedPolicies = PublicPolicy::query()
                    ->where('institution_id', $institutionId)
                    ->where(fn ($query) => $query->whereKey($policyId)->orWhere('is_designated_intake', true))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $policy = $lockedPolicies->firstWhere('id', $policyId);
                if ($policy === null) {
                    throw ValidationException::withMessages(['policy' => 'The selected policy is not available in this institution.']);
                }
            } else {
                $policy = PublicPolicy::query()
                    ->where('institution_id', $institutionId)
                    ->whereKey($policyId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sourceKey = $policy?->publisher_key ?? $publisherKey;
            if ($sourceKey === '' || ($policy !== null && $policy->publisher_key !== $publisherKey && $publisherKey !== '')) {
                throw ValidationException::withMessages(['publisher_key' => 'The issuing source cannot be changed.']);
            }
            $targetId = $policy?->program_id === null ? ($payload['program_id'] ?? null) : $policy->program_id;
            $this->sources->assertCanTarget($freshActor, $sourceKey, $targetId === null ? null : (int) $targetId, (bool) ($policy?->is_designated_intake));
            $hash = hash('sha256', json_encode([
                'operation' => $operation, 'target' => $policyId, 'expected_revision' => $expectedRevision,
                'publisher_key' => $sourceKey, 'program_id' => $targetId === null ? null : (int) $targetId,
                'payload' => $this->canonicalize($payload),
            ], JSON_THROW_ON_ERROR));

            $receipt = PublicPolicyCommandReceipt::query()->where('actor_id', $freshActor->getKey())
                ->where('request_id', $requestId)->lockForUpdate()->first();
            $capability = $receipt?->required_capability ?? ($requiresPublish
                ? CapabilityCatalog::COMMUNICATIONS_POLICIES_PUBLISH : CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE);
            if ($policy !== null && $policy->is_designated_intake && in_array($freshActor->actorScope()->office, ['admin', 'registrar'], true)) {
                if ($capability === CapabilityCatalog::COMMUNICATIONS_POLICIES_PUBLISH) {
                    $this->sources->assertCanPublishPolicy($freshActor, $policy);
                } else {
                    $this->sources->assertCanManagePolicy($freshActor, $policy);
                }
            } else {
                if ($capability === CapabilityCatalog::COMMUNICATIONS_POLICIES_PUBLISH) {
                    $this->sources->assertCanPublish($freshActor, $institutionId, $sourceKey);
                } else {
                    $this->sources->assertCanManage($freshActor, $institutionId, $sourceKey);
                }
            }
            if ($receipt !== null) {
                $wrongTarget = ! ($operation === 'save' && $policyId === null)
                    && (int) $receipt->public_policy_id !== (int) ($policyId ?? 0);
                if ($receipt->operation !== $operation || $wrongTarget || ! hash_equals($receipt->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['request_id' => 'This request ID was already used for a different command. Use a new request ID.']);
                }
                $snapshot = $receipt->result_snapshot;

                return (new PublicPolicy)->newFromBuilder($snapshot);
            }
            if ($policy !== null && $expectedRevision !== (int) $policy->revision) {
                throw ValidationException::withMessages(['expected_revision' => 'This policy changed. Reload its current revision before retrying.']);
            }

            $result = $mutation($freshActor, $policy, $targetId === null ? null : (int) $targetId, $sourceKey);
            $snapshot = $result->getAttributes();
            PublicPolicyCommandReceipt::query()->create([
                'actor_id' => $freshActor->getKey(), 'request_id' => $requestId, 'operation' => $operation,
                'public_policy_id' => $result->getKey(), 'required_capability' => $capability,
                'expected_revision' => $expectedRevision, 'payload_hash' => $hash, 'result_snapshot' => $snapshot,
                'created_at' => now(),
            ]);
            $event = match ($operation) {
                'save' => 'communications.public_policy_saved',
                'publish' => 'communications.public_policy_published',
                'withdraw' => 'communications.public_policy_withdrawn',
                'designate_intake' => 'communications.public_policy_intake_designated',
                default => 'communications.public_policy_commanded',
            };
            $auditDetails = [
                'public_policy_id' => $result->getKey(),
                'revision' => $result->revision,
                'publisher_key' => $result->publisher_key,
                'program_id' => $result->program_id,
                'published_revision_id' => $result->published_revision_id,
            ];
            if ($operation === 'designate_intake') {
                $auditDetails['previous_intake_policy_id'] = $payload['expected_intake_policy_id'] ?? null;
                $auditDetails['selected_intake_policy_id'] = (int) $result->getKey();
                $auditDetails['selected_published_revision_id'] = $result->published_revision_id === null ? null : (int) $result->published_revision_id;
            }
            $this->audit->handle($event, $auditDetails, (string) $freshActor->getKey());

            return $result;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }
}
