<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Authorization\CapabilityCatalog;
use App\Communications\AnnouncementSource;
use App\Communications\AnnouncementSourceAuthorizer;
use App\Models\Communications\Announcement;
use App\Models\Communications\AnnouncementCommandReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

final class AnnouncementCommandSupport
{
    public function __construct(private readonly AnnouncementSourceAuthorizer $sources) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(User, AnnouncementSource, ?Announcement): Announcement  $mutation
     *
     * @throws JsonException
     */
    public function run(
        User $actor,
        string $requestId,
        string $operation,
        ?int $announcementId,
        ?int $expectedRevision,
        string $publisherKey,
        array $payload,
        callable $mutation,
        bool $requiresPublish = false,
    ): Announcement {
        $requestId = strtolower($requestId);

        return DB::transaction(function () use ($actor, $requestId, $operation, $announcementId, $expectedRevision, $publisherKey, $payload, $mutation, $requiresPublish): Announcement {
            $freshActor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $locked = $announcementId === null
                ? null
                : Announcement::query()->whereKey($announcementId)->lockForUpdate()->firstOrFail();
            $targetProgramId = $locked?->program_id ?? ($payload['program_id'] ?? null);
            $payloadHash = hash('sha256', json_encode([
                'operation' => $operation,
                'target' => $announcementId,
                'target_program_id' => $targetProgramId === null ? null : (int) $targetProgramId,
                'expected_revision' => $expectedRevision,
                'publisher_key' => $publisherKey,
                'payload' => $this->canonicalize($payload),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            if ($locked !== null && $publisherKey !== '' && $locked->publisher_key !== $publisherKey) {
                abort(404);
            }

            $publisherKey = $locked?->publisher_key ?? $publisherKey;
            $institutionId = $locked?->institution_id ?? $freshActor->actorScope()->institutionId ?? 0;
            $source = $this->sources->assertCanSee($freshActor, (int) $institutionId, $publisherKey);
            $this->sources->assertCanTarget($freshActor, $source, $targetProgramId === null ? null : (int) $targetProgramId);
            $receipt = AnnouncementCommandReceipt::query()
                ->where('actor_id', $freshActor->getKey())
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->first();

            $requiredCapability = $receipt?->required_capability
                ?? ($requiresPublish || $locked?->status === 'published'
                    ? CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH
                    : CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE);
            if ($requiredCapability === CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH) {
                $this->sources->assertCanPublish($freshActor, (int) $institutionId, $publisherKey);
            } else {
                $this->sources->assertCanManage($freshActor, (int) $institutionId, $publisherKey);
            }

            if ($receipt !== null) {
                $receiptTarget = $receipt->result['target_id'] ?? null;
                if ($receipt->operation !== $operation
                    || $receiptTarget !== $announcementId
                    || ! hash_equals($receipt->payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages([
                        'request_id' => 'This request ID was already used for a different command. Use a new request ID.',
                    ]);
                }

                $snapshot = $receipt->result['snapshot'] ?? null;
                if (is_array($snapshot)) {
                    return (new Announcement)->newFromBuilder($snapshot);
                }

                return Announcement::query()->whereKey($receipt->announcement_id)->firstOrFail();
            }

            if ($locked !== null && $expectedRevision !== $locked->revision) {
                throw ValidationException::withMessages([
                    'expected_revision' => 'This announcement changed. Reload the latest version before saving.',
                ]);
            }

            $announcement = $mutation($freshActor, $source, $locked);
            AnnouncementCommandReceipt::query()->create([
                'actor_id' => $freshActor->getKey(),
                'request_id' => $requestId,
                'operation' => $operation,
                'required_capability' => $requiredCapability,
                'announcement_id' => $announcement->getKey(),
                'expected_revision' => $expectedRevision,
                'payload_hash' => $payloadHash,
                'result' => [
                    'id' => $announcement->getKey(),
                    'target_id' => $announcementId,
                    'revision' => $announcement->revision,
                    'status' => $announcement->status,
                    'snapshot' => $announcement->getAttributes(),
                ],
            ]);

            return $announcement;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonicalize($item);
            }
        }

        return $value;
    }
}
