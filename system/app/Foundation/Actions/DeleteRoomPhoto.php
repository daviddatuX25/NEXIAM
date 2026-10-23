<?php

namespace App\Foundation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Support\Media\MediaStorageLifecycle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class DeleteRoomPhoto
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly MediaStorageLifecycle $lifecycle,
    ) {}

    public function handle(Room $room, User|string $actor): Room
    {
        $oldPath = DB::transaction(function () use ($room, $actor): ?string {
            $lockedRoom = Room::query()->lockForUpdate()->findOrFail($room->id);
            $this->authorize($lockedRoom, $actor);

            $oldPath = $lockedRoom->photo_path;
            $lockedRoom->update(['photo_path' => null]);
            $this->audit->handle('setup.room_photo_deleted', [
                'room_id' => (int) $lockedRoom->id,
                'institution_id' => (int) $lockedRoom->institution_id,
                'photo_path' => $oldPath,
            ], is_string($actor) ? $actor : (string) $actor->id);

            return $oldPath;
        });

        $this->lifecycle->deleteQuietly('public', $oldPath);

        return $room->fresh();
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
