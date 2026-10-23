<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Authorization\CapabilityCatalog;
use App\Communications\ProgramPublicProfileAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Communications\ProgramPublicProfilePhoto;
use App\Models\Communications\ProgramPublicProfilePhotoStageReceipt;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Support\Media\ImagePolicy;
use App\Support\Media\ImageProcessor;
use App\Support\Media\MediaStorageLifecycle;
use App\Support\Media\RasterInput;
use App\Support\Media\StagedMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StageProgramPhoto
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_DIMENSION = 4096;

    private const MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(
        private readonly ProgramPublicProfileAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
        private readonly ImageProcessor $imageProcessor,
        private readonly MediaStorageLifecycle $lifecycle,
    ) {}

    public function handle(User $actor, Program $program, string $requestId, UploadedFile $photo): ProgramPublicProfilePhoto
    {
        $validated = Validator::make(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
        $requestId = strtolower((string) $validated['request_id']);
        $this->authorizer->assertCanManage($actor, $program);
        $source = $this->readAndValidateSource($photo);
        $sourceChecksum = hash('sha256', $source['bytes']);
        $payloadHash = hash('sha256', json_encode([
            'operation' => 'stage_photo',
            'program_id' => (int) $program->getKey(),
            'source_sha256' => $sourceChecksum,
            'mime_type' => $source['mime'],
            'extension' => $source['extension'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $replayed = $this->findReceipt($actor, $program, $requestId, $sourceChecksum, $payloadHash);
        if ($replayed !== null) {
            return $replayed;
        }

        $sanitized = $this->imageProcessor->process(
            RasterInput::fromUploadedFile($photo),
            ImagePolicy::programPhoto(),
            'photo'
        );
        $path = 'program-public-profiles/'.$program->getKey().'/'.Str::uuid().'.'.$sanitized->extension;
        try {
            $staged = $this->lifecycle->stage('sensitive', $path, $sanitized->bytes, $sanitized->mime);
        } catch (Throwable) {
            throw ValidationException::withMessages(['photo' => 'The sanitized photo could not be durably stored. Please choose it again.']);
        }
        $result = $this->lifecycle->commit($staged, function (StagedMedia $staged) use (
            $actor,
            $program,
            $requestId,
            $sourceChecksum,
            $payloadHash,
            $sanitized,
        ): array {
            $lockedProgram = Program::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();
            $freshActor = $this->authorizer->assertCanManage($actor, $lockedProgram);

            $prior = ProgramPublicProfilePhotoStageReceipt::query()
                ->where('actor_id', $freshActor->getKey())
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->first();
            if ($prior !== null) {
                return [
                    'photo' => $this->photoFromReceipt($prior, $lockedProgram, $sourceChecksum, $payloadHash),
                    'displaced_paths' => [$staged->path],
                ];
            }

            $storedPhoto = ProgramPublicProfilePhoto::query()->create([
                'program_id' => $lockedProgram->getKey(),
                'institution_id' => $lockedProgram->institution_id,
                'storage_disk' => $staged->disk,
                'storage_path' => $staged->path,
                'sha256' => $staged->sha256,
                'mime_type' => $staged->mime,
                'byte_size' => $staged->byteSize,
                'width' => $sanitized->width,
                'height' => $sanitized->height,
                'status' => ProgramPublicProfilePhoto::STATUS_READY,
                'created_by' => $freshActor->getKey(),
            ]);

            ProgramPublicProfilePhotoStageReceipt::query()->create([
                'actor_id' => $freshActor->getKey(),
                'program_id' => $lockedProgram->getKey(),
                'photo_id' => $storedPhoto->getKey(),
                'request_id' => $requestId,
                'required_capability' => CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE,
                'source_sha256' => $sourceChecksum,
                'payload_hash' => $payloadHash,
                'result' => [
                    'photo_id' => (int) $storedPhoto->getKey(),
                    'program_id' => (int) $lockedProgram->getKey(),
                    'source_sha256' => $sourceChecksum,
                    'sha256' => $staged->sha256,
                ],
            ]);

            $this->audit->handle('communications.program_public_profile_photo_staged', [
                'program_id' => (int) $lockedProgram->getKey(),
                'photo_id' => (int) $storedPhoto->getKey(),
                'mime_type' => (string) $storedPhoto->mime_type,
                'byte_size' => (int) $storedPhoto->byte_size,
            ], (string) $freshActor->getKey());

            return ['photo' => $storedPhoto];
        });

        return $result['photo'];
    }

    /** @return array{bytes: string, mime: string, extension: string, width: int, height: int} */
    private function readAndValidateSource(UploadedFile $photo): array
    {
        if (! $photo->isValid() || (int) $photo->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['photo' => 'Choose a valid JPEG, PNG, or WebP image no larger than 5 MiB.']);
        }

        $extension = strtolower($photo->getClientOriginalExtension());
        $declaredMime = self::MIME_BY_EXTENSION[$extension] ?? null;
        $temporaryPath = $photo->getRealPath();
        $bytes = is_string($temporaryPath) ? @file_get_contents($temporaryPath) : false;
        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo could not be read or exceeds 5 MiB.']);
        }

        $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $imageInfo = @getimagesizefromstring($bytes);
        if (! is_array($imageInfo)
            || ! isset($imageInfo['mime'])
            || $declaredMime === null
            || $actualMime !== $declaredMime
            || $imageInfo['mime'] !== $declaredMime
            || ! $this->hasStrictContainerEnd($bytes, $declaredMime)) {
            throw ValidationException::withMessages(['photo' => 'The photo extension and image content must match a complete JPEG, PNG, or WebP file.']);
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw ValidationException::withMessages(['photo' => 'Choose an image no larger than 4096 by 4096 pixels.']);
        }

        return [
            'bytes' => $bytes,
            'mime' => $declaredMime,
            'extension' => $extension,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function findReceipt(
        User $actor,
        Program $program,
        string $requestId,
        string $sourceChecksum,
        string $payloadHash,
    ): ?ProgramPublicProfilePhoto {
        return DB::transaction(function () use ($actor, $program, $requestId, $sourceChecksum, $payloadHash): ?ProgramPublicProfilePhoto {
            $lockedProgram = Program::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();
            $freshActor = $this->authorizer->assertCanManage($actor, $lockedProgram);
            $receipt = ProgramPublicProfilePhotoStageReceipt::query()
                ->where('actor_id', $freshActor->getKey())
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->first();

            return $receipt === null ? null : $this->photoFromReceipt($receipt, $lockedProgram, $sourceChecksum, $payloadHash);
        }, attempts: 3);
    }

    private function photoFromReceipt(
        ProgramPublicProfilePhotoStageReceipt $receipt,
        Program $program,
        string $sourceChecksum,
        string $payloadHash,
    ): ProgramPublicProfilePhoto {
        if ((int) $receipt->program_id !== (int) $program->getKey()
            || ! hash_equals((string) $receipt->source_sha256, $sourceChecksum)
            || ! hash_equals((string) $receipt->payload_hash, $payloadHash)
            || $receipt->required_capability !== CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE) {
            throw ValidationException::withMessages([
                'request_id' => 'This upload request ID was already used for a different program or file. Choose the file again to start a new upload.',
            ]);
        }

        $photo = ProgramPublicProfilePhoto::query()
            ->whereKey($receipt->photo_id)
            ->where('program_id', $program->getKey())
            ->where('institution_id', $program->institution_id)
            ->where('storage_disk', 'sensitive')
            ->where('status', ProgramPublicProfilePhoto::STATUS_READY)
            ->first();
        if ($photo === null) {
            throw ValidationException::withMessages(['photo' => 'The original staged photo is no longer ready. Choose it again to start a new upload.']);
        }

        $this->assertDurablePhoto($photo);

        return $photo;
    }

    private function assertDurablePhoto(ProgramPublicProfilePhoto $photo): void
    {
        try {
            $disk = Storage::disk('sensitive');
            $bytes = $disk->exists((string) $photo->storage_path) ? $disk->get((string) $photo->storage_path) : null;
        } catch (Throwable) {
            $bytes = null;
        }

        if (! is_string($bytes)
            || strlen($bytes) !== (int) $photo->byte_size
            || ! hash_equals((string) $photo->sha256, hash('sha256', $bytes))) {
            throw ValidationException::withMessages(['photo' => 'The staged photo is missing or its checksum no longer matches. Choose it again to replace it.']);
        }
    }

    private function hasStrictContainerEnd(string $bytes, string $mime): bool
    {
        return match ($mime) {
            'image/jpeg' => $this->hasCompleteJpeg($bytes),
            'image/png' => $this->hasCompletePng($bytes),
            'image/webp' => $this->hasCompleteWebp($bytes),
            default => false,
        };
    }

    private function hasCompletePng(string $bytes): bool
    {
        if (! str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return false;
        }

        $size = strlen($bytes);
        $offset = 8;
        while ($offset + 12 <= $size) {
            $length = unpack('N', substr($bytes, $offset, 4))[1];
            $type = substr($bytes, $offset + 4, 4);
            if ($length > $size - $offset - 12) {
                return false;
            }
            $offset += $length + 12;
            if ($type === 'IEND') {
                return $length === 0 && $offset === $size;
            }
        }

        return false;
    }

    private function hasCompleteWebp(string $bytes): bool
    {
        $size = strlen($bytes);
        if ($size < 12 || substr($bytes, 0, 4) !== 'RIFF'
            || substr($bytes, 8, 4) !== 'WEBP'
            || unpack('V', substr($bytes, 4, 4))[1] !== $size - 8) {
            return false;
        }

        $offset = 12;
        while ($offset + 8 <= $size) {
            $length = unpack('V', substr($bytes, $offset + 4, 4))[1];
            $paddedLength = $length + ($length % 2);
            if ($paddedLength > $size - $offset - 8) {
                return false;
            }
            $offset += $paddedLength + 8;
        }

        return $offset === $size;
    }

    private function hasCompleteJpeg(string $bytes): bool
    {
        if (! str_starts_with($bytes, "\xFF\xD8")) {
            return false;
        }

        $size = strlen($bytes);
        $offset = 2;
        $inScan = false;
        while ($offset < $size) {
            if ($inScan) {
                $nextMarker = strpos($bytes, "\xFF", $offset);
                if ($nextMarker === false) {
                    return false;
                }
                $offset = $nextMarker;
            } elseif (ord($bytes[$offset]) !== 0xFF) {
                return false;
            }
            while ($offset < $size && ord($bytes[$offset]) === 0xFF) {
                $offset++;
            }
            if ($offset >= $size) {
                return false;
            }
            $marker = ord($bytes[$offset++]);
            if ($inScan && ($marker === 0 || ($marker >= 0xD0 && $marker <= 0xD7))) {
                continue;
            }
            if ($marker === 0xD9) {
                return $offset === $size;
            }
            if ($marker === 0x01) {
                continue;
            }
            if ($marker < 0xC0 || $marker === 0xD8 || ($marker >= 0xD0 && $marker <= 0xD7)
                || $offset + 2 > $size) {
                return false;
            }
            $length = unpack('n', substr($bytes, $offset, 2))[1];
            if ($length < 2 || $length > $size - $offset) {
                return false;
            }
            $offset += $length;
            $inScan = $marker === 0xDA;
        }

        return false;
    }
}
