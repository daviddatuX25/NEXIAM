<?php

namespace App\Assessment\Support;

use App\Admission\Support\CommandIdempotency;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class TestKitPreparationReceipt
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
     * @return array{test_kit_id:int,preparation_id:int,preparation_revision:int,preparation_attributes:array<string,mixed>,assembled_test_kit_version_id:?int}|null
     */
    public function result(Operation $receipt): ?array
    {
        $payload = $receipt->payload;
        $result = is_array($payload) ? ($payload['test_kit_preparation_result'] ?? null) : null;
        if ($result === null) {
            return null;
        }
        if (! is_array($result)
            || ! is_numeric($result['test_kit_id'] ?? null)
            || ! is_numeric($result['preparation_id'] ?? null)
            || ! is_numeric($result['preparation_revision'] ?? null)
            || ! is_array($result['preparation_attributes'] ?? null)
            || (int) ($result['preparation_attributes']['id'] ?? 0) !== (int) $result['preparation_id']
            || (($result['assembled_test_kit_version_id'] ?? null) !== null
                && ! is_numeric($result['assembled_test_kit_version_id']))) {
            throw new RuntimeException('The Test Kit preparation receipt has an invalid result identity.');
        }

        return [
            'test_kit_id' => (int) $result['test_kit_id'],
            'preparation_id' => (int) $result['preparation_id'],
            'preparation_revision' => (int) $result['preparation_revision'],
            'preparation_attributes' => $result['preparation_attributes'],
            'assembled_test_kit_version_id' => isset($result['assembled_test_kit_version_id'])
                ? (int) $result['assembled_test_kit_version_id']
                : null,
        ];
    }

    /** @param array<string,mixed> $result */
    public function preparationSnapshot(array $result): TestKitPreparation
    {
        $preparation = new TestKitPreparation;
        $preparation->setRawAttributes($result['preparation_attributes'], true);
        $preparation->exists = true;

        return $preparation;
    }

    public function remember(
        Operation $receipt,
        TestKitPreparation $preparation,
        ?TestKitVersion $assembledVersion = null,
    ): void {
        if ($assembledVersion !== null && (int) $assembledVersion->test_kit_id !== (int) $preparation->test_kit_id) {
            throw new RuntimeException('The assembled Test Kit version does not belong to the preparation family.');
        }

        $payload = $receipt->payload;
        if (! is_array($payload) || ! is_string($payload['request_hash'] ?? null)) {
            throw new RuntimeException('The Test Kit preparation receipt has no payload digest.');
        }

        $payload['test_kit_preparation_result'] = [
            'test_kit_id' => (int) $preparation->test_kit_id,
            'preparation_id' => (int) $preparation->getKey(),
            'preparation_revision' => (int) $preparation->revision,
            'preparation_attributes' => array_intersect_key($preparation->getAttributes(), array_flip([
                'id', 'test_kit_id', 'state', 'revision', 'composition_revision',
                'test_instrument_version_id', 'norm_package_version_id', 'answer_sheet_draft_id',
                'template_version_id', 'booklet_version_id', 'booklet_set_id',
                'assembled_test_kit_version_id', 'created_by_user_id', 'updated_by_user_id',
                'created_at', 'updated_at',
            ])),
            'assembled_test_kit_version_id' => $assembledVersion === null
                ? null
                : (int) $assembledVersion->getKey(),
        ];
        $receipt->forceFill(['payload' => $payload])->save();
    }
}
