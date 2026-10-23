<?php

namespace App\Assessment\Support;

use App\Admission\Support\CommandIdempotency;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AssessmentNormCommandReceipt
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
                'request_id' => 'This request ID was already used for different content. Refresh before retrying.',
            ]);
        }
    }

    /**
     * @return array{package_id: int, version_id: int}|null
     */
    public function result(Operation $receipt): ?array
    {
        $payload = $receipt->payload;
        $result = is_array($payload) ? ($payload['norm_package_result'] ?? null) : null;

        if ($result === null) {
            return null;
        }

        if (! is_array($result)
            || ! is_numeric($result['package_id'] ?? null)
            || ! is_numeric($result['version_id'] ?? null)) {
            throw new RuntimeException('The norm package command receipt has an invalid result identity.');
        }

        return [
            'package_id' => (int) $result['package_id'],
            'version_id' => (int) $result['version_id'],
        ];
    }

    public function remember(Operation $receipt, AssessmentNormPackageVersion $version): void
    {
        $payload = $receipt->payload;
        if (! is_array($payload) || ! is_string($payload['request_hash'] ?? null)) {
            throw new RuntimeException('The norm package command receipt has no payload digest.');
        }

        $receipt->payload = [
            ...$payload,
            'norm_package_result' => [
                'package_id' => (int) $version->norm_package_id,
                'version_id' => (int) $version->id,
                'version_number' => (int) $version->version_number,
            ],
        ];
        $receipt->save();
    }
}
