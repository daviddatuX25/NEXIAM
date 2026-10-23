<?php

namespace App\Foundation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveSeatingArrangement
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  array{name: mixed, rows: mixed, columns: mixed, booklet_pattern: mixed, availability_mode: mixed, room_ids: mixed, is_active: mixed, lock_version?: mixed}  $attributes
     */
    public function handle(?SeatingArrangement $arrangement, array $attributes, User $actor): SeatingArrangement
    {
        $validated = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:190'],
            'rows' => ['required', 'integer', 'min:1', 'max:500'],
            'columns' => ['required', 'integer', 'min:1', 'max:500'],
            'booklet_pattern' => ['required', Rule::in(SeatingArrangement::PATTERNS)],
            'availability_mode' => ['required', Rule::in(SeatingArrangement::AVAILABILITY_MODES)],
            'room_ids' => ['array'],
            'room_ids.*' => ['integer', 'distinct', 'exists:rooms,id'],
            'is_active' => ['required', 'boolean'],
            'lock_version' => [$arrangement === null ? 'nullable' : 'required', 'integer', 'min:1'],
        ])->validate();

        return DB::transaction(function () use ($arrangement, $validated, $actor): SeatingArrangement {
            if ($actor->staffProfile()->exists()) {
                $institution = Institution::current();
                if (! $institution->usesSeatingArrangements() || ! app(CapabilityAuthorizer::class)->decideFresh(
                    $actor,
                    CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
                    ['institution_id' => (int) $institution->id],
                )->allowed) {
                    throw new AuthorizationException;
                }
            }
            $locked = $arrangement === null
                ? null
                : SeatingArrangement::query()->lockForUpdate()->findOrFail($arrangement->id);

            if ($locked !== null && (int) $locked->lock_version !== (int) $validated['lock_version']) {
                throw ValidationException::withMessages([
                    'lock_version' => 'This seating arrangement changed after you opened it. Review the current version and try again.',
                ]);
            }

            $normalizedName = preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $validated['name']))) ?? '';
            $duplicate = SeatingArrangement::query()
                ->where('normalized_name', $normalizedName)
                ->when($locked !== null, fn ($query) => $query->whereKeyNot($locked->id))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => 'A seating arrangement with this name already exists.',
                ]);
            }

            $roomIds = collect($validated['room_ids'] ?? [])->map(fn (mixed $id): int => (int) $id)->unique()->values();
            if ($validated['availability_mode'] === SeatingArrangement::AVAILABILITY_SELECTED_ROOMS && $roomIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'room_ids' => 'Select at least one room or make the arrangement available to all rooms.',
                ]);
            }

            $rooms = Room::query()->whereKey($roomIds)->orderBy('id')->lockForUpdate()->get();
            if ($rooms->count() !== $roomIds->count()) {
                throw ValidationException::withMessages(['room_ids' => 'One or more selected rooms no longer exist.']);
            }
            $nonExamRoom = $rooms->first(fn (Room $room): bool => ! $room->allowsSeatingArrangement());
            if ($nonExamRoom !== null) {
                throw ValidationException::withMessages([
                    'room_ids' => "{$nonExamRoom->name} cannot be assigned a seating arrangement because it is an assembly venue or service point.",
                ]);
            }

            $deskCount = (int) $validated['rows'] * (int) $validated['columns'];
            $incompatibleRoom = $rooms->first(fn (Room $room): bool => $deskCount > (int) $room->capacity);
            if ($incompatibleRoom !== null) {
                throw ValidationException::withMessages([
                    'room_ids' => "This arrangement needs {$deskCount} desks, but {$incompatibleRoom->name} has capacity {$incompatibleRoom->capacity}.",
                ]);
            }

            $isActive = (bool) $validated['is_active'];
            if ($locked !== null) {
                $invalidDefault = $locked->defaultForRooms()
                    ->lockForUpdate()
                    ->get()
                    ->first(function (Room $room) use ($validated, $roomIds, $deskCount, $isActive): bool {
                        return ! $isActive
                            || $deskCount > (int) $room->capacity
                            || ($validated['availability_mode'] === SeatingArrangement::AVAILABILITY_SELECTED_ROOMS
                                && ! $roomIds->contains((int) $room->id));
                    });
                if ($invalidDefault !== null) {
                    throw ValidationException::withMessages([
                        'is_active' => "Update {$invalidDefault->name}'s default before changing this arrangement.",
                    ]);
                }
            }

            $locked ??= new SeatingArrangement;
            $locked->fill([
                'name' => trim((string) $validated['name']),
                'normalized_name' => $normalizedName,
                'rows' => (int) $validated['rows'],
                'columns' => (int) $validated['columns'],
                'booklet_pattern' => (string) $validated['booklet_pattern'],
                'availability_mode' => (string) $validated['availability_mode'],
                'is_active' => $isActive,
                'lock_version' => $locked->exists ? ((int) $locked->lock_version + 1) : 1,
            ])->save();

            $locked->rooms()->sync(
                $validated['availability_mode'] === SeatingArrangement::AVAILABILITY_SELECTED_ROOMS
                    ? $roomIds->all()
                    : [],
            );

            $this->audit->handle('setup.seating_arrangement_saved', [
                'seating_arrangement_id' => $locked->id,
                'desk_count' => $deskCount,
                'booklet_pattern' => $locked->booklet_pattern,
                'availability_mode' => $locked->availability_mode,
                'room_ids' => $roomIds->all(),
                'is_active' => $locked->is_active,
                'lock_version' => $locked->lock_version,
            ], (string) $actor->id);

            return $locked->fresh(['rooms']);
        });
    }
}
