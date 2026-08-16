<?php

namespace App\Admission\Support;

use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Action-execution ledger for admission-support commands (D-090 §4).
 *
 * Namespace = actor + command + aggregate; same key + canonical payload
 * replays the original operation, same key + different payload conflicts
 * with no mutation, audit, or notification.
 */
final class CommandIdempotency
{
    public function __construct() {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function claim(User $actor, string $command, string $aggregate, array $payload, string $idempotencyKey): Operation
    {
        $namespacedKey = sprintf('%s:%s:%s:%s', $actor->id, $command, $aggregate, $idempotencyKey);
        $requestHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($namespacedKey, $command, $requestHash, $payload): Operation {
            /** @var Operation|null $existing */
            $existing = Operation::query()
                ->where('idempotency_key', $namespacedKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                /** @var array{request_hash?: string}|null $priorPayload */
                $priorPayload = $existing->getAttribute('payload');
                $priorHash = is_array($priorPayload) ? ($priorPayload['request_hash'] ?? null) : null;

                if ($priorHash !== $requestHash) {
                    throw new RuntimeException('Idempotency key was already used with a different request payload.');
                }

                return $existing;
            }

            return Operation::query()->create([
                'idempotency_key' => $namespacedKey,
                'type' => $command,
                'status' => Operation::STATUS_SUCCEEDED,
                'attempts' => 0,
                'payload' => ['request_hash' => $requestHash, ...$payload],
                'finished_at' => now(),
            ]);
        });
    }
}
