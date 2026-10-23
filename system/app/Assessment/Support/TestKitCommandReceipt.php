<?php

namespace App\Assessment\Support;

use App\Admission\Support\CommandIdempotency;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class TestKitCommandReceipt
{
    public function __construct(private readonly CommandIdempotency $idempotency) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function claim(User $actor, string $command, string $aggregate, array $payload, string $idempotencyKey): Operation
    {
        try {
            return $this->idempotency->claim($actor, $command, $aggregate, $payload, $idempotencyKey);
        } catch (RuntimeException $exception) {
            if (! str_contains($exception->getMessage(), 'already used with a different request payload')) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'idempotency_key' => 'This idempotency key was already used for different content.',
            ]);
        }
    }

    /**
     * @return array{test_kit_id:int,test_kit_version_id:int,selected_acceptance_receipt_id:?int}|null
     */
    public function result(Operation $receipt): ?array
    {
        $payload = $receipt->payload;
        $result = is_array($payload) ? ($payload['test_kit_result'] ?? null) : null;
        if ($result === null) {
            return null;
        }

        if (! is_array($result)
            || ! is_numeric($result['test_kit_id'] ?? null)
            || ! is_numeric($result['test_kit_version_id'] ?? null)
            || (($result['selected_acceptance_receipt_id'] ?? null) !== null
                && ! is_numeric($result['selected_acceptance_receipt_id']))) {
            throw new RuntimeException('The Test Kit command receipt has an invalid result identity.');
        }

        return [
            'test_kit_id' => (int) $result['test_kit_id'],
            'test_kit_version_id' => (int) $result['test_kit_version_id'],
            'selected_acceptance_receipt_id' => isset($result['selected_acceptance_receipt_id'])
                ? (int) $result['selected_acceptance_receipt_id']
                : null,
        ];
    }

    public function remember(Operation $receipt, TestKitVersion $version): void
    {
        $payload = $receipt->payload;
        if (! is_array($payload) || ! is_string($payload['request_hash'] ?? null)) {
            throw new RuntimeException('The Test Kit command receipt has no payload digest.');
        }

        $payload['test_kit_result'] = [
            'test_kit_id' => (int) $version->test_kit_id,
            'test_kit_version_id' => (int) $version->getKey(),
            'selected_acceptance_receipt_id' => $version->selected_acceptance_receipt_id === null
                ? null
                : (int) $version->selected_acceptance_receipt_id,
        ];
        $receipt->forceFill(['payload' => $payload])->save();
    }
}
