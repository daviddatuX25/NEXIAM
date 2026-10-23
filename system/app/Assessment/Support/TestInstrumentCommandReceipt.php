<?php

namespace App\Assessment\Support;

use App\Admission\Support\CommandIdempotency;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class TestInstrumentCommandReceipt
{
    public function __construct(private readonly CommandIdempotency $idempotency) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function claim(User $actor, string $command, string $aggregate, array $payload, string $requestId): Operation
    {
        try {
            return $this->idempotency->claim($actor, $command, $aggregate, $payload, $requestId);
        } catch (RuntimeException $exception) {
            if (! str_contains($exception->getMessage(), 'already used with a different request payload')) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'request_id' => 'This request ID was already used for different content. Refresh the profile before retrying.',
            ]);
        }
    }

    /**
     * @return array{instrument_id: int, version_id: int}|null
     */
    public function result(Operation $receipt): ?array
    {
        $payload = $receipt->payload;
        $result = is_array($payload) ? ($payload['test_instrument_result'] ?? null) : null;

        if ($result === null) {
            return null;
        }

        if (! is_array($result)
            || ! is_numeric($result['instrument_id'] ?? null)
            || ! is_numeric($result['version_id'] ?? null)) {
            throw new RuntimeException('The test instrument command receipt has an invalid result identity.');
        }

        return [
            'instrument_id' => (int) $result['instrument_id'],
            'version_id' => (int) $result['version_id'],
        ];
    }

    public function remember(Operation $receipt, TestInstrumentVersion $version): void
    {
        $payload = $receipt->payload;
        if (! is_array($payload) || ! is_string($payload['request_hash'] ?? null)) {
            throw new RuntimeException('The test instrument command receipt has no payload digest.');
        }

        $payload['test_instrument_result'] = [
            'instrument_id' => (int) $version->test_instrument_id,
            'version_id' => (int) $version->getKey(),
        ];

        $receipt->forceFill(['payload' => $payload])->save();
    }
}
