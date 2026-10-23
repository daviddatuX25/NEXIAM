<?php

namespace App\Foundation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class CreateRoom
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{code: string, name: string, capacity: int, space_kind?: string|SpaceKind, is_exam_room?: bool, is_active?: bool, latitude?: numeric-string|int|float|null, longitude?: numeric-string|int|float|null, building_name?: string|null, floor_level?: string|null, directions_hint?: string|null}  $attributes
     */
    public function handle(array $attributes, User|string $actor, ?Institution $institution = null): Room
    {
        $targetInstitution = $institution ?? Institution::current();

        return DB::transaction(function () use ($attributes, $actor, $targetInstitution): Room {
            if ($actor instanceof User && $actor->staffProfile()->exists() && ! (
                app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::SETUP_ROOMS_MANAGE, [
                    'institution_id' => (int) $targetInstitution->id,
                ])->allowed || app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                    'institution_id' => (int) $targetInstitution->id,
                ])->allowed
            )) {
                throw new AuthorizationException;
            }
            $kind = $attributes['space_kind'] ?? null;
            if ($kind === null && array_key_exists('is_exam_room', $attributes)) {
                $kind = $attributes['is_exam_room'] ? SpaceKind::Room : SpaceKind::AssemblyVenue;
            }

            $room = Room::query()->create([
                'institution_id' => $targetInstitution->id,
                'code' => strtoupper(trim((string) $attributes['code'])),
                'name' => $attributes['name'],
                'space_kind' => $kind ?? SpaceKind::Room,
                'capacity' => $attributes['capacity'],
                'latitude' => $attributes['latitude'] ?? null,
                'longitude' => $attributes['longitude'] ?? null,
                'building_name' => $attributes['building_name'] ?? null,
                'floor_level' => $attributes['floor_level'] ?? null,
                'directions_hint' => $attributes['directions_hint'] ?? null,
                'is_active' => (bool) ($attributes['is_active'] ?? true),
            ]);
            $actorId = is_string($actor) ? $actor : (string) $actor->id;
            $this->audit->handle('setup.room_created', [
                'room_id' => $room->id,
                'code' => $room->code,
                'capacity' => $room->capacity,
                'is_active' => $room->is_active,
            ], $actorId);

            return $room;
        });
    }
}
