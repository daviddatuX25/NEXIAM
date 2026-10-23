<?php

namespace App\Foundation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateRoom
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{name: string, capacity: int, is_active: bool, space_kind?: string|SpaceKind, latitude?: numeric-string|int|float|null, longitude?: numeric-string|int|float|null, building_name?: string|null, floor_level?: string|null, directions_hint?: string|null}  $attributes
     */
    public function handle(Room $room, array $attributes, User|string $actor): Room
    {
        return DB::transaction(function () use ($room, $attributes, $actor): Room {
            $lockedRoom = Room::query()->lockForUpdate()->findOrFail($room->id);
            if ($actor instanceof User && $actor->staffProfile()->exists() && ! (
                app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::SETUP_ROOMS_MANAGE, [
                    'institution_id' => (int) $lockedRoom->institution_id,
                ])->allowed || app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                    'institution_id' => (int) $lockedRoom->institution_id,
                ])->allowed
            )) {
                throw new AuthorizationException;
            }

            $defaultArrangement = $lockedRoom->defaultSeatingArrangement()
                ->lockForUpdate()
                ->first();
            $targetKind = isset($attributes['space_kind'])
                ? ($attributes['space_kind'] instanceof SpaceKind
                    ? $attributes['space_kind']
                    : SpaceKind::tryFrom((string) $attributes['space_kind']))
                : $lockedRoom->space_kind;

            $isRoomTarget = $targetKind === null || $targetKind->allowsSeatingArrangement();
            if ($isRoomTarget && $defaultArrangement !== null && $defaultArrangement->desk_count > $attributes['capacity']) {
                throw ValidationException::withMessages([
                    'capacity' => "Capacity must be at least {$defaultArrangement->desk_count} for default seating arrangement {$defaultArrangement->name}.",
                ]);
            }

            $targetActive = isset($attributes['is_active']) ? (bool) $attributes['is_active'] : (bool) $lockedRoom->is_active;
            $willDeactivate = ! $targetActive;
            $willChangeToNonRoom = ! $isRoomTarget && ($lockedRoom->space_kind === null || $lockedRoom->space_kind->allowsSeatingArrangement());

            if ($willDeactivate || $willChangeToNonRoom) {
                $hasActiveFutureExam = ExamSession::query()
                    ->where('room_id', $lockedRoom->id)
                    ->where('status', '!=', ExamSession::STATUS_CANCELLED)
                    ->where('effective_end_at', '>', now())
                    ->exists();

                $hasActiveFutureConsultation = ConsultationSession::query()
                    ->where('room_id', $lockedRoom->id)
                    ->where('status', '!=', ConsultationSession::STATUS_CANCELLED)
                    ->where('scheduled_at', '>', now())
                    ->exists();

                if ($hasActiveFutureExam || $hasActiveFutureConsultation) {
                    if ($willDeactivate) {
                        throw ValidationException::withMessages([
                            'is_active' => 'This room has active future sessions and cannot be deactivated.',
                        ]);
                    }

                    if ($willChangeToNonRoom) {
                        throw ValidationException::withMessages([
                            'space_kind' => 'This room has active future sessions and cannot be changed to a non-room space kind.',
                        ]);
                    }
                }
            }
            if (isset($attributes['space_kind'])) {
                $kind = $attributes['space_kind'] instanceof SpaceKind
                    ? $attributes['space_kind']
                    : SpaceKind::tryFrom((string) $attributes['space_kind']);
                if ($kind !== null && ! $kind->allowsSeatingArrangement()) {
                    $attributes['default_seating_arrangement_id'] = null;
                    $lockedRoom->seatingArrangements()->detach();
                }
            }

            $lockedRoom->update($attributes);
            $actorId = is_string($actor) ? $actor : (string) $actor->id;
            $this->audit->handle('setup.room_updated', [
                'room_id' => $lockedRoom->id,
                'capacity' => $lockedRoom->capacity,
                'is_active' => $lockedRoom->is_active,
            ], $actorId);

            return $lockedRoom->fresh();
        });
    }
}
