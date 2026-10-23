<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Authorization\CapabilityCatalog;
use App\Communications\ProgramPublicProfileAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Communications\ProgramPublicProfile;
use App\Models\Communications\ProgramPublicProfileCommandReceipt;
use App\Models\Communications\ProgramPublicProfilePhoto;
use App\Models\Communications\ProgramPublicProfileRevision;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;

final class ProgramPublicProfileCommandSupport
{
    public function __construct(
        private readonly ProgramPublicProfileAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{request_id: string, expected_revision: int, short_description: string, description: string, photos: list<array{id: int, alt_text: string, caption: string}>}  $payload
     */
    public function save(User $actor, Program $program, array $payload): ProgramPublicProfileRevision
    {
        return $this->run($actor, $program, 'save', $payload['request_id'], $payload['expected_revision'], [
            'short_description' => $payload['short_description'],
            'description' => $payload['description'],
            'photos' => $payload['photos'],
        ]);
    }

    /** @param array{request_id: string, expected_revision: int} $payload */
    public function publish(User $actor, Program $program, array $payload): ProgramPublicProfileRevision
    {
        return $this->run($actor, $program, 'publish', $payload['request_id'], $payload['expected_revision'], []);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function run(
        User $actor,
        Program $program,
        string $operation,
        string $requestId,
        int $expectedRevision,
        array $payload,
    ): ProgramPublicProfileRevision {
        $requestId = strtolower($requestId);

        return DB::transaction(function () use ($actor, $program, $operation, $requestId, $expectedRevision, $payload): ProgramPublicProfileRevision {
            $lockedProgram = Program::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();
            $profile = ProgramPublicProfile::query()
                ->where('program_id', $lockedProgram->getKey())
                ->lockForUpdate()
                ->first();
            $operationCapability = $operation === 'publish'
                ? CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_PUBLISH
                : CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE;
            $freshActor = match ($operationCapability) {
                CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_PUBLISH => $this->authorizer->assertCanPublish($actor, $lockedProgram),
                CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE => $this->authorizer->assertCanManage($actor, $lockedProgram),
                default => throw new AuthorizationException('The profile operation is not authorized.'),
            };
            $receipt = ProgramPublicProfileCommandReceipt::query()
                ->where('actor_id', $freshActor->getKey())
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->first();
            if ($receipt !== null && $receipt->required_capability !== $operationCapability) {
                throw ValidationException::withMessages([
                    'request_id' => 'The recorded profile command authority is unavailable. Start a new command after reloading.',
                ]);
            }
            $requiredCapability = $operationCapability;

            $payloadHash = hash('sha256', json_encode([
                'program_id' => (int) $lockedProgram->getKey(),
                'operation' => $operation,
                'expected_revision' => $expectedRevision,
                'payload' => $this->canonicalize($payload),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            if ($receipt !== null) {
                if ((int) $receipt->program_id !== (int) $lockedProgram->getKey()
                    || $receipt->operation !== $operation
                    || ! hash_equals((string) $receipt->payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages([
                        'request_id' => 'This request ID was already used for a different program or command. Use a new request ID.',
                    ]);
                }

                $revision = ProgramPublicProfileRevision::query()
                    ->whereKey($receipt->revision_id)
                    ->where('profile_id', $receipt->profile_id)
                    ->first();
                if ($revision === null) {
                    throw ValidationException::withMessages([
                        'request_id' => 'The recorded result is unavailable. Reload the latest profile before retrying.',
                    ]);
                }

                return $revision;
            }

            $currentRevision = (int) ($profile?->current_revision ?? 0);
            if ($currentRevision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_revision' => 'This program profile changed. Reload the latest version before saving or publishing.',
                ]);
            }

            if ($operation === 'publish' && ($profile === null || $currentRevision < 1)) {
                throw ValidationException::withMessages([
                    'expected_revision' => 'Save a program profile draft before publishing it.',
                ]);
            }

            if ($profile === null) {
                $profile = ProgramPublicProfile::query()->create([
                    'program_id' => $lockedProgram->getKey(),
                    'institution_id' => $lockedProgram->institution_id,
                    'current_revision' => 0,
                    'published_revision_id' => null,
                ]);
            }

            if ($operation === 'save') {
                [$shortDescription, $description, $photos] = $this->validatedSnapshot($lockedProgram, $payload);
                $isPublished = false;
            } else {
                $source = ProgramPublicProfileRevision::query()
                    ->where('profile_id', $profile->getKey())
                    ->where('revision', $currentRevision)
                    ->firstOrFail();
                $shortDescription = (string) $source->short_description;
                $description = (string) $source->description;
                $photos = $source->photos ?? [];
                $this->assertSnapshotPhotosReady($lockedProgram, $photos);
                $isPublished = true;
            }

            $revisionNumber = $currentRevision + 1;
            $revision = ProgramPublicProfileRevision::query()->create([
                'profile_id' => $profile->getKey(),
                'revision' => $revisionNumber,
                'short_description' => $shortDescription,
                'description' => $description,
                'photos' => $photos,
                'is_published' => $isPublished,
                'operation' => $operation,
                'actor_id' => $freshActor->getKey(),
                'actor_name' => $freshActor->name,
            ]);

            $profile->forceFill([
                'current_revision' => $revisionNumber,
                'published_revision_id' => $isPublished ? $revision->getKey() : $profile->published_revision_id,
            ])->save();

            $snapshot = [
                'profile_id' => (int) $profile->getKey(),
                'program_id' => (int) $lockedProgram->getKey(),
                'revision_id' => (int) $revision->getKey(),
                'revision' => $revisionNumber,
                'published_revision_id' => $profile->published_revision_id === null
                    ? null
                    : (int) $profile->published_revision_id,
                'short_description' => $shortDescription,
                'description' => $description,
                'photos' => $photos,
                'is_published' => $isPublished,
            ];

            ProgramPublicProfileCommandReceipt::query()->create([
                'actor_id' => $freshActor->getKey(),
                'request_id' => $requestId,
                'operation' => $operation,
                'required_capability' => $requiredCapability,
                'program_id' => $lockedProgram->getKey(),
                'profile_id' => $profile->getKey(),
                'expected_revision' => $expectedRevision,
                'revision_id' => $revision->getKey(),
                'payload_hash' => $payloadHash,
                'result' => $snapshot,
            ]);

            $this->audit->handle('communications.program_public_profile_'.$operation, [
                'program_id' => (int) $lockedProgram->getKey(),
                'profile_id' => (int) $profile->getKey(),
                'revision_id' => (int) $revision->getKey(),
                'revision' => $revisionNumber,
                'photo_ids' => array_values(array_map(static fn (array $photo): int => (int) $photo['id'], $photos)),
            ], (string) $freshActor->getKey());

            return $revision;
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string, 2: list<array{id: int, alt_text: string, caption: string}>}
     */
    private function validatedSnapshot(Program $program, array $payload): array
    {
        $photos = $payload['photos'] ?? null;
        if (! is_array($photos) || count($photos) > 6) {
            throw ValidationException::withMessages(['photos' => 'Choose no more than six program photos.']);
        }

        $seen = [];
        $photoIds = [];
        foreach ($photos as $index => $photo) {
            if (! is_array($photo)
                || ! isset($photo['id'], $photo['alt_text'])
                || ! is_numeric($photo['id'])
                || ! is_string($photo['alt_text'])) {
                throw ValidationException::withMessages(["photos.{$index}" => 'Choose a ready photo and provide its alternative text.']);
            }
            $id = (int) $photo['id'];
            if ($id < 1 || isset($seen[$id])) {
                throw ValidationException::withMessages(["photos.{$index}.id" => 'Each program photo may appear only once.']);
            }
            $seen[$id] = true;
            $photoIds[] = $id;
        }

        $staged = ProgramPublicProfilePhoto::query()
            ->where('program_id', $program->getKey())
            ->where('institution_id', $program->institution_id)
            ->where('status', ProgramPublicProfilePhoto::STATUS_READY)
            ->where('storage_disk', 'sensitive')
            ->whereIn('id', $photoIds)
            ->get()
            ->keyBy('id');

        $snapshotPhotos = [];
        foreach (array_values($photos) as $index => $photo) {
            $id = (int) $photo['id'];
            $altText = trim((string) $photo['alt_text']);
            $caption = trim((string) ($photo['caption'] ?? ''));
            if ($altText === '' || mb_strlen($altText) > 160) {
                throw ValidationException::withMessages(["photos.{$index}.alt_text" => 'Add meaningful alternative text of 1 to 160 characters.']);
            }
            if (mb_strlen($caption) > 240) {
                throw ValidationException::withMessages(["photos.{$index}.caption" => 'Keep captions within 240 characters.']);
            }
            $stagedPhoto = $staged->get($id);
            if (! $stagedPhoto instanceof ProgramPublicProfilePhoto) {
                throw ValidationException::withMessages(["photos.{$index}.id" => 'This photo is not ready for this program. Upload it again.']);
            }
            $this->assertPhotoStorageReady($stagedPhoto, "photos.{$index}.id");
            $snapshotPhotos[] = ['id' => $id, 'alt_text' => $altText, 'caption' => $caption];
        }

        $shortDescription = trim((string) ($payload['short_description'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        if (mb_strlen($shortDescription) > 240) {
            throw ValidationException::withMessages(['short_description' => 'Keep the short description within 240 characters.']);
        }
        if ($description === '' || mb_strlen($description) > 10000) {
            throw ValidationException::withMessages(['description' => 'Add a full description of 1 to 10,000 characters.']);
        }

        return [$shortDescription, $description, $snapshotPhotos];
    }

    /** @param list<array{id: int, alt_text: string, caption: string}> $photos */
    private function assertSnapshotPhotosReady(Program $program, array $photos): void
    {
        if (count($photos) > 6) {
            throw ValidationException::withMessages(['photos' => 'The saved profile contains more than six photos.']);
        }

        $ids = array_map(static fn (array $photo): int => (int) ($photo['id'] ?? 0), $photos);
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['photos' => 'The saved profile contains a duplicate photo. Save a corrected draft before publishing.']);
        }

        $staged = ProgramPublicProfilePhoto::query()
            ->where('program_id', $program->getKey())
            ->where('institution_id', $program->institution_id)
            ->where('status', ProgramPublicProfilePhoto::STATUS_READY)
            ->where('storage_disk', 'sensitive')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($photos as $index => $photo) {
            $stored = $staged->get((int) ($photo['id'] ?? 0));
            if (! $stored instanceof ProgramPublicProfilePhoto) {
                throw ValidationException::withMessages(["photos.{$index}.id" => 'A saved photo is no longer ready. Replace or remove it before publishing.']);
            }
            $this->assertPhotoStorageReady($stored, "photos.{$index}.id");
            if (trim((string) ($photo['alt_text'] ?? '')) === ''
                || mb_strlen((string) ($photo['alt_text'] ?? '')) > 160
                || mb_strlen((string) ($photo['caption'] ?? '')) > 240) {
                throw ValidationException::withMessages(["photos.{$index}" => 'The saved photo description is invalid. Save a corrected draft before publishing.']);
            }
        }
    }

    private function assertPhotoStorageReady(ProgramPublicProfilePhoto $photo, string $field): void
    {
        try {
            $disk = Storage::disk('sensitive');
            $bytes = $disk->exists((string) $photo->storage_path) ? $disk->get((string) $photo->storage_path) : null;
        } catch (\Throwable) {
            $bytes = null;
        }

        if (! is_string($bytes)
            || strlen($bytes) !== (int) $photo->byte_size
            || ! hash_equals((string) $photo->sha256, hash('sha256', $bytes))) {
            throw ValidationException::withMessages([$field => 'A private photo is missing or its stored checksum no longer matches. Replace or remove it before saving or publishing.']);
        }
    }

    /** @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item) && ! array_is_list($item)) {
                $value[$key] = $this->canonicalize($item);
            } elseif (is_array($item)) {
                $value[$key] = array_map(fn (mixed $child): mixed => is_array($child) && ! array_is_list($child)
                    ? $this->canonicalize($child)
                    : $child, $item);
            }
        }

        return $value;
    }
}
