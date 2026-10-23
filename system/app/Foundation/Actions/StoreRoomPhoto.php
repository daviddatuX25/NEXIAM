<?php

namespace App\Foundation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Support\Media\ImagePolicy;
use App\Support\Media\ImageProcessor;
use App\Support\Media\MediaStorageLifecycle;
use App\Support\Media\RasterInput;
use App\Support\Media\StagedMedia;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class StoreRoomPhoto
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ImageProcessor $imageProcessor,
        private readonly MediaStorageLifecycle $lifecycle,
    ) {}

    public function handle(Room $room, UploadedFile $photo, User|string $actor): Room
    {
        Validator::validate(['photo' => $photo], [
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $sanitized = $this->imageProcessor->process(
            RasterInput::fromUploadedFile($photo),
            ImagePolicy::roomPhoto(),
            'photo'
        );
        $targetPath = 'rooms/'.$room->institution_id.'/'.Str::uuid().'.'.$sanitized->extension;
        $staged = $this->lifecycle->stage('public', $targetPath, $sanitized->bytes, $sanitized->mime);

        $result = $this->lifecycle->commit($staged, function (StagedMedia $staged) use ($room, $actor): array {
            $lockedRoom = Room::query()->lockForUpdate()->findOrFail($room->id);
            $this->authorize($lockedRoom, $actor);
            $oldPath = $lockedRoom->photo_path;
            $lockedRoom->update(['photo_path' => $staged->path]);
            $this->audit->handle('setup.room_photo_updated', [
                'room_id' => (int) $lockedRoom->id,
                'institution_id' => (int) $lockedRoom->institution_id,
                'photo_path' => $staged->path,
            ], is_string($actor) ? $actor : (string) $actor->id);

            return ['old_path' => $oldPath, 'room' => $lockedRoom->fresh()];
        });

        return $result['room'];
    }

    private function authorize(Room $room, User|string $actor): void
    {
        if (! $actor instanceof User) {
            return;
        }

        if (! (
            $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_ROOMS_MANAGE, [
                'institution_id' => (int) $room->institution_id,
            ])->allowed || $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                'institution_id' => (int) $room->institution_id,
            ])->allowed
        )) {
            throw new AuthorizationException;
        }
    }
}
