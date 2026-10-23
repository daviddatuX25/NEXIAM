<?php

declare(strict_types=1);

namespace App\Communications\Queries;

use App\Communications\ProgramPublicProfileAuthorizer;
use App\Models\Communications\ProgramPublicProfile;
use App\Models\Communications\ProgramPublicProfilePhoto;
use App\Models\Communications\ProgramPublicProfileRevision;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProgramPublicProfileQuery
{
    public function __construct(private readonly ProgramPublicProfileAuthorizer $authorizer) {}

    /** @return array{short_description: string, description: string, photos: list<array{id: int, src: string, alt_text: string, caption: string}>}|null */
    public function publicProfile(Program $program): ?array
    {
        $currentProgram = $this->currentActiveProgram((int) $program->getKey(), (int) Institution::current()->getKey());
        if ($currentProgram === null) {
            return null;
        }

        $revision = $this->publishedRevision($currentProgram);
        if ($revision === null) {
            return null;
        }

        return [
            'short_description' => (string) $revision->short_description,
            'description' => (string) $revision->description,
            'photos' => $this->photoDtos($currentProgram, $revision, public: true),
        ];
    }

    /** @return array<string, mixed> */
    public function staffEditor(Program $program, User $actor): array
    {
        $freshActor = $this->authorizer->assertCanManage($actor, $program);
        $currentProgram = $this->currentActiveProgram((int) $program->getKey(), (int) $freshActor->actorScope()->institutionId);
        if ($currentProgram === null) {
            throw new NotFoundHttpException;
        }

        $profile = ProgramPublicProfile::query()
            ->where('program_id', $currentProgram->getKey())
            ->where('institution_id', $currentProgram->institution_id)
            ->first();
        $revision = $profile === null || (int) $profile->current_revision === 0
            ? null
            : ProgramPublicProfileRevision::query()
                ->where('profile_id', $profile->getKey())
                ->where('revision', $profile->current_revision)
                ->first();
        $published = $profile?->published_revision_id === null
            ? null
            : ProgramPublicProfileRevision::query()
                ->whereKey($profile->published_revision_id)
                ->where('profile_id', $profile->getKey())
                ->where('is_published', true)
                ->first();
        $snapshot = $revision === null ? null : $this->revisionProfile($currentProgram, $revision, public: false);

        return [
            'program' => $this->programDto($currentProgram),
            'profile' => $snapshot ?? ['short_description' => '', 'description' => '', 'photos' => []],
            'revision' => (int) ($revision?->revision ?? 0),
            'published_revision' => $published === null ? null : (int) $published->revision,
            'canManage' => true,
            'canPublish' => $this->authorizer->canPublish($freshActor, $currentProgram),
            'has_published_profile' => $published !== null,
        ];
    }

    /** @return array<string, mixed> */
    public function staffPreview(Program $program, User $actor): array
    {
        $props = $this->staffEditor($program, $actor);
        $props['preview_kind'] = 'private_saved_draft';

        return $props;
    }

    /** @return array{bytes: string, mime: string} */
    public function staffPhotoBytes(Program $program, ProgramPublicProfilePhoto $photo, User $actor): array
    {
        $freshActor = $this->authorizer->assertCanReadPrivateMedia($actor, $program);
        $currentProgram = $this->currentActiveProgram((int) $program->getKey(), (int) $freshActor->actorScope()->institutionId);
        abort_if($currentProgram === null, 404);

        $stored = ProgramPublicProfilePhoto::query()
            ->whereKey($photo->getKey())
            ->where('program_id', $currentProgram->getKey())
            ->where('institution_id', $currentProgram->institution_id)
            ->where('status', ProgramPublicProfilePhoto::STATUS_READY)
            ->where('storage_disk', 'sensitive')
            ->first();
        abort_if($stored === null, 404);

        return $this->verifiedPhotoBytes($stored);
    }

    /** @return array{bytes: string, mime: string} */
    public function publicPhotoBytes(string $code, int $photoId): array
    {
        $institutionId = (int) Institution::current()->getKey();
        $program = Program::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->where('code', $code)
            ->first();
        abort_if($program === null, 404);

        $revision = $this->publishedRevision($program);
        abort_if($revision === null, 404);
        $referenced = collect($revision->photos ?? [])->contains(
            static fn (mixed $photo): bool => is_array($photo) && (int) ($photo['id'] ?? 0) === $photoId,
        );
        abort_unless($referenced, 404);

        $photo = ProgramPublicProfilePhoto::query()
            ->whereKey($photoId)
            ->where('program_id', $program->getKey())
            ->where('institution_id', $institutionId)
            ->where('status', ProgramPublicProfilePhoto::STATUS_READY)
            ->where('storage_disk', 'sensitive')
            ->first();
        abort_if($photo === null, 404);

        return $this->verifiedPhotoBytes($photo);
    }

    /** @return array{id: int, src: string, alt_text: string, caption: string, available: bool} */
    public function stagedPhotoDto(Program $program, ProgramPublicProfilePhoto $photo, User $actor): array
    {
        $freshActor = $this->authorizer->assertCanManage($actor, $program);
        $currentProgram = $this->currentActiveProgram((int) $program->getKey(), (int) $freshActor->actorScope()->institutionId);
        abort_if($currentProgram === null, 404);
        $stored = ProgramPublicProfilePhoto::query()
            ->whereKey($photo->getKey())
            ->where('program_id', $currentProgram->getKey())
            ->where('institution_id', $currentProgram->institution_id)
            ->where('status', ProgramPublicProfilePhoto::STATUS_READY)
            ->where('storage_disk', 'sensitive')
            ->first();
        if ($stored === null) {
            throw new NotFoundHttpException;
        }

        $available = $this->photoIsReadable($stored);

        return [
            'id' => (int) $stored->getKey(),
            'src' => $this->staffPhotoUrl($currentProgram, $stored),
            'alt_text' => '',
            'caption' => '',
            'available' => $available,
        ];
    }

    /** @return array{short_description: string, description: string, photos: list<array{id: int, src: string, alt_text: string, caption: string, available?: bool}>, revision: int, is_published: bool} */
    public function staffRevisionProfile(Program $program, ProgramPublicProfileRevision $revision, User $actor): array
    {
        $freshActor = $this->authorizer->assertCanManage($actor, $program);
        $currentProgram = $this->currentActiveProgram((int) $program->getKey(), (int) $freshActor->actorScope()->institutionId);
        abort_if($currentProgram === null, 404);
        abort_unless((int) $revision->profile?->program_id === (int) $currentProgram->getKey(), 404);

        return $this->revisionProfile($currentProgram, $revision, public: false);
    }

    /** @return array{short_description: string, description: string, photos: list<array{id: int, src: string, alt_text: string, caption: string, available?: bool}>, revision: int, is_published: bool} */
    public function publishedRevisionProfile(Program $program, ProgramPublicProfileRevision $revision, User $actor): array
    {
        $freshActor = $this->authorizer->assertCanPublish($actor, $program);
        $currentProgram = $this->currentActiveProgram((int) $program->getKey(), (int) $freshActor->actorScope()->institutionId);
        abort_if($currentProgram === null, 404);
        abort_unless(
            (int) $revision->profile?->program_id === (int) $currentProgram->getKey() && $revision->is_published,
            404,
        );

        return $this->revisionProfile($currentProgram, $revision, public: false);
    }

    /** @return array{bytes: string, mime: string} */
    private function verifiedPhotoBytes(ProgramPublicProfilePhoto $photo): array
    {
        abort_unless($this->photoPathMatchesProgram($photo), 404);
        try {
            $disk = Storage::disk('sensitive');
            $bytes = $disk->exists((string) $photo->storage_path) ? $disk->get((string) $photo->storage_path) : null;
        } catch (\Throwable) {
            $bytes = null;
        }

        if (! is_string($bytes)
            || strlen($bytes) !== (int) $photo->byte_size
            || ! hash_equals((string) $photo->sha256, hash('sha256', $bytes))) {
            throw new NotFoundHttpException;
        }

        $imageInfo = @getimagesizefromstring($bytes);
        if (! is_array($imageInfo)
            || ($imageInfo['mime'] ?? null) !== $photo->mime_type
            || (int) ($imageInfo[0] ?? 0) !== (int) $photo->width
            || (int) ($imageInfo[1] ?? 0) !== (int) $photo->height) {
            throw new NotFoundHttpException;
        }

        return ['bytes' => $bytes, 'mime' => (string) $photo->mime_type];
    }

    private function photoIsReadable(ProgramPublicProfilePhoto $photo): bool
    {
        try {
            $this->verifiedPhotoBytes($photo);

            return true;
        } catch (NotFoundHttpException) {
            return false;
        }
    }

    private function photoPathMatchesProgram(ProgramPublicProfilePhoto $photo): bool
    {
        $programId = (int) $photo->program_id;

        return $photo->storage_disk === 'sensitive'
            && preg_match('~\\Aprogram-public-profiles/'.$programId.'/[0-9a-f-]{36}\\.(?:jpg|png|webp)\\z~', (string) $photo->storage_path) === 1;
    }

    private function currentActiveProgram(int $programId, int $institutionId): ?Program
    {
        return Program::query()
            ->whereKey($programId)
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->first();
    }

    private function publishedRevision(Program $program): ?ProgramPublicProfileRevision
    {
        $profile = ProgramPublicProfile::query()
            ->where('program_id', $program->getKey())
            ->where('institution_id', $program->institution_id)
            ->whereNotNull('published_revision_id')
            ->first();
        if ($profile === null) {
            return null;
        }

        return ProgramPublicProfileRevision::query()
            ->whereKey($profile->published_revision_id)
            ->where('profile_id', $profile->getKey())
            ->where('is_published', true)
            ->first();
    }

    /** @return array<string, mixed> */
    private function revisionProfile(Program $program, ProgramPublicProfileRevision $revision, bool $public): array
    {
        return [
            'short_description' => (string) $revision->short_description,
            'description' => (string) $revision->description,
            'photos' => $this->photoDtos($program, $revision, $public),
            'revision' => (int) $revision->revision,
            'is_published' => (bool) $revision->is_published,
        ];
    }

    /** @return list<array{id: int, src: string, alt_text: string, caption: string, available?: bool}> */
    private function photoDtos(Program $program, ProgramPublicProfileRevision $revision, bool $public): array
    {
        $photos = is_array($revision->photos) ? $revision->photos : [];
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): int => is_array($item) ? (int) ($item['id'] ?? 0) : 0,
            $photos,
        ), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $storedPhotos = ProgramPublicProfilePhoto::query()
            ->where('program_id', $program->getKey())
            ->where('institution_id', $program->institution_id)
            ->where('status', ProgramPublicProfilePhoto::STATUS_READY)
            ->where('storage_disk', 'sensitive')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($photos as $snapshotPhoto) {
            if (! is_array($snapshotPhoto)) {
                continue;
            }
            $id = (int) ($snapshotPhoto['id'] ?? 0);
            $stored = $storedPhotos->get($id);
            if (! $stored instanceof ProgramPublicProfilePhoto) {
                continue;
            }
            $available = $this->photoIsReadable($stored);
            if ($public && ! $available) {
                continue;
            }
            $result[] = [
                'id' => $id,
                'src' => $public ? $this->publicPhotoUrl($program, $stored) : $this->staffPhotoUrl($program, $stored),
                'alt_text' => (string) ($snapshotPhoto['alt_text'] ?? ''),
                'caption' => (string) ($snapshotPhoto['caption'] ?? ''),
                ...($public ? [] : ['available' => $available]),
            ];
        }

        return $result;
    }

    /** @return array{id: int, code: string, name: string} */
    private function programDto(Program $program): array
    {
        return [
            'id' => (int) $program->getKey(),
            'code' => (string) $program->code,
            'name' => (string) $program->name,
        ];
    }

    private function publicPhotoUrl(Program $program, ProgramPublicProfilePhoto $photo): string
    {
        return '/programs/'.rawurlencode((string) $program->code).'/photos/'.(int) $photo->getKey();
    }

    private function staffPhotoUrl(Program $program, ProgramPublicProfilePhoto $photo): string
    {
        return '/setup/programs/'.(int) $program->getKey().'/public-details/photos/'.(int) $photo->getKey();
    }
}
