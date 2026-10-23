<?php

namespace App\Examination\Support;

use App\Application\Actions\ActionExecutionIdentity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ActionExecutionReceiptStore
{
    private const TABLE = 'examination_action_execution_receipts';

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $command
     */
    public function replay(
        ActionExecutionIdentity|\App\Examination\Support\ActionExecutionIdentity $identity,
        User $actor,
        string $actionKey,
        array $target,
        array $command,
    ): ?ActionExecutionReceiptClaim {
        $target = $this->canonicalize($target);
        $requestFingerprint = $this->requestFingerprint($actionKey, $target, $command);

        return $this->findExisting(
            identity: $identity,
            actor: $actor,
            actionKey: $actionKey,
            target: $target,
            requestFingerprint: $requestFingerprint,
            lock: false,
        );
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $command
     */
    public function claim(
        ActionExecutionIdentity|\App\Examination\Support\ActionExecutionIdentity $identity,
        User $actor,
        string $actionKey,
        array $target,
        array $command,
    ): ActionExecutionReceiptClaim {
        if ($identity->capabilityId !== $actionKey) {
            throw new RuntimeException('action_execution_receipt_conflict');
        }

        $target = $this->canonicalize($target);
        $requestFingerprint = $this->requestFingerprint($actionKey, $target, $command);
        $now = now();

        $inserted = DB::table(self::TABLE)->insertOrIgnore([
            'execution_id' => $identity->executionId,
            'action_key' => $actionKey,
            'actor_id' => $actor->getKey(),
            'target_json' => json_encode($target, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'canonical_digest' => $identity->canonicalDigest,
            'request_fingerprint' => $requestFingerprint,
            'safe_result_json' => null,
            'completed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 1) {
            return new ActionExecutionReceiptClaim($identity->executionId, replayed: false);
        }

        return $this->findExisting(
            identity: $identity,
            actor: $actor,
            actionKey: $actionKey,
            target: $target,
            requestFingerprint: $requestFingerprint,
            lock: true,
        ) ?? throw new RuntimeException('action_execution_receipt_conflict');
    }

    /**
     * @param  array<string, mixed>  $safeResult
     */
    public function complete(ActionExecutionReceiptClaim $claim, array $safeResult): void
    {
        if ($claim->replayed) {
            throw new RuntimeException('action_execution_receipt_already_completed');
        }

        $updated = DB::table(self::TABLE)
            ->where('execution_id', $claim->executionId)
            ->whereNull('completed_at')
            ->update([
                'safe_result_json' => json_encode($safeResult, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('action_execution_receipt_completion_conflict');
        }
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function requestFingerprint(string $actionKey, array $target, array $command): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize([
                'action_key' => $actionKey,
                'command' => $command,
                'target' => $target,
            ]),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function findExisting(
        ActionExecutionIdentity|\App\Examination\Support\ActionExecutionIdentity $identity,
        User $actor,
        string $actionKey,
        array $target,
        string $requestFingerprint,
        bool $lock,
    ): ?ActionExecutionReceiptClaim {
        if ($identity->capabilityId !== $actionKey) {
            throw new RuntimeException('action_execution_receipt_conflict');
        }

        $query = DB::table(self::TABLE)->where('execution_id', $identity->executionId);
        if ($lock) {
            $query->lockForUpdate();
        }

        $receipt = $query->first();
        if ($receipt === null) {
            return null;
        }

        $storedTarget = json_decode((string) $receipt->target_json, true, flags: JSON_THROW_ON_ERROR);
        if (
            (int) $receipt->actor_id !== (int) $actor->getKey()
            || (string) $receipt->action_key !== $actionKey
            || $storedTarget !== $target
            || ! hash_equals((string) $receipt->canonical_digest, $identity->canonicalDigest)
            || ! hash_equals((string) $receipt->request_fingerprint, $requestFingerprint)
        ) {
            throw new RuntimeException('action_execution_receipt_conflict');
        }

        if ($receipt->completed_at === null || $receipt->safe_result_json === null) {
            throw new RuntimeException('action_execution_receipt_incomplete');
        }

        $safeResult = is_array($receipt->safe_result_json)
            ? $receipt->safe_result_json
            : json_decode((string) $receipt->safe_result_json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($safeResult)) {
            throw new RuntimeException('action_execution_receipt_invalid');
        }

        return new ActionExecutionReceiptClaim(
            executionId: $identity->executionId,
            replayed: true,
            safeResult: $safeResult,
        );
    }

    /**
     * @return array<mixed>
     */
    private function canonicalize(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => is_array($item) ? $this->canonicalize($item) : $item,
                $value,
            );
        }

        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }
}
