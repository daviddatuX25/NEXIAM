<?php

use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\SaveSeatingArrangement;
use App\Foundation\Actions\SetRoomDefaultSeatingArrangement;
use App\Foundation\Actions\UpdateRoom;
use App\Foundation\SeatingArrangementPreview;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function seatingTestRoom(int $capacity = 30): Room
{
    $institution = Institution::query()->create([
        'code' => 'SYN-INST-001',
        'name' => 'Seating Test Institution',
    ]);

    return Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'R-101',
        'name' => 'Room 101',
        'capacity' => $capacity,
        'is_active' => true,
    ]);
}

it('saves an audited reusable arrangement and derives row-major desks', function (): void {
    $room = seatingTestRoom();
    $actor = User::factory()->create();

    $arrangement = app(SaveSeatingArrangement::class)->handle(null, [
        'name' => 'Main Layout',
        'rows' => 3,
        'columns' => 4,
        'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
        'room_ids' => [],
        'is_active' => true,
    ], $actor);
    $desks = app(SeatingArrangementPreview::class)->forArrangement($arrangement);

    expect($arrangement->normalized_name)->toBe('main layout')
        ->and($arrangement->desk_count)->toBe(12)
        ->and($arrangement->rooms)->toBeEmpty()
        ->and($desks)->toHaveCount(12)
        ->and(array_column($desks, 'label'))->toBe([
            'A1', 'A2', 'A3', 'A4',
            'B1', 'B2', 'B3', 'B4',
            'C1', 'C2', 'C3', 'C4',
        ])
        ->and(array_column(array_slice($desks, 0, 6), 'booklet'))->toBe(['A', 'B', 'A', 'B', 'B', 'A'])
        ->and(AuditEvent::query()->where('event_type', 'setup.seating_arrangement_saved')->exists())->toBeTrue();
});

it('supports every deterministic booklet pattern', function (
    string $pattern,
    array $expected,
): void {
    $arrangement = new SeatingArrangement([
        'rows' => 2,
        'columns' => 4,
        'booklet_pattern' => $pattern,
    ]);
    $preview = app(SeatingArrangementPreview::class);

    expect(array_column($preview->forArrangement($arrangement), 'booklet'))->toBe($expected)
        ->and($preview->forArrangement($arrangement))->toBe($preview->forArrangement($arrangement));
})->with([
    'uniform' => [SeatingArrangement::PATTERN_UNIFORM, ['A', 'A', 'A', 'A', 'A', 'A', 'A', 'A']],
    'checkerboard' => [SeatingArrangement::PATTERN_CHECKERBOARD, ['A', 'B', 'A', 'B', 'B', 'A', 'B', 'A']],
    'latin four' => [SeatingArrangement::PATTERN_LATIN_FOUR, ['A', 'B', 'C', 'D', 'B', 'C', 'D', 'A']],
    'linear alternating' => [SeatingArrangement::PATTERN_LINEAR_ALTERNATING, ['A', 'B', 'A', 'B', 'A', 'B', 'A', 'B']],
]);

it('normalizes arrangement names globally across rooms', function (): void {
    $firstRoom = seatingTestRoom();
    $secondRoom = Room::query()->create([
        'institution_id' => $firstRoom->institution_id,
        'code' => 'R-102',
        'name' => 'Room 102',
        'capacity' => 30,
        'is_active' => true,
    ]);
    $actor = User::factory()->create();
    $action = app(SaveSeatingArrangement::class);
    $attributes = [
        'name' => 'Main   Layout',
        'rows' => 3,
        'columns' => 4,
        'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
        'availability_mode' => SeatingArrangement::AVAILABILITY_SELECTED_ROOMS,
        'room_ids' => [$firstRoom->id],
        'is_active' => true,
    ];

    $first = $action->handle(null, $attributes, $actor);

    expect($first->normalized_name)->toBe('main layout')
        ->and(fn () => $action->handle(null, [...$attributes, 'name' => ' main layout ', 'room_ids' => [$secondRoom->id]], $actor))
        ->toThrow(ValidationException::class)
        ->and(SeatingArrangement::query()->where('normalized_name', 'main layout')->count())->toBe(1);
});

it('applies reusable arrangements to all rooms or selected rooms only', function (): void {
    $room = seatingTestRoom();
    $otherRoom = Room::query()->create([
        'institution_id' => $room->institution_id,
        'code' => 'R-102',
        'name' => 'Room 102',
        'capacity' => 30,
        'is_active' => true,
    ]);
    $actor = User::factory()->create();
    $action = app(SaveSeatingArrangement::class);

    $global = $action->handle(null, [
        'name' => 'Every Room',
        'rows' => 3,
        'columns' => 4,
        'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
        'room_ids' => [$room->id, $otherRoom->id],
        'is_active' => true,
    ], $actor);
    $selected = $action->handle(null, [
        'name' => 'Room One Only',
        'rows' => 3,
        'columns' => 4,
        'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
        'availability_mode' => SeatingArrangement::AVAILABILITY_SELECTED_ROOMS,
        'room_ids' => [$room->id],
        'is_active' => true,
    ], $actor);

    expect($global->fresh('rooms')->rooms)->toBeEmpty()
        ->and($selected->fresh('rooms')->rooms->modelKeys())->toBe([$room->id])
        ->and($room->fresh('seatingArrangements')->seatingArrangements->modelKeys())->toBe([$selected->id])
        ->and($otherRoom->fresh('seatingArrangements')->seatingArrangements)->toBeEmpty();
});

it('enforces active defaults and blocks invalid default changes', function (): void {
    $room = seatingTestRoom();
    $actor = User::factory()->create();
    $save = app(SaveSeatingArrangement::class);
    $setDefault = app(SetRoomDefaultSeatingArrangement::class);
    $attributes = [
        'rows' => 3,
        'columns' => 4,
        'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
        'room_ids' => [],
        'is_active' => true,
    ];
    $first = $save->handle(null, [...$attributes, 'name' => 'First'], $actor);
    $second = $save->handle(null, [...$attributes, 'name' => 'Second'], $actor);

    $configured = $setDefault->handle($room, $first, $actor);
    $replaced = $setDefault->handle($room, $second, $actor);

    expect($configured->defaultSeatingArrangement->is($first))->toBeTrue()
        ->and($replaced->defaultSeatingArrangement->is($second))->toBeTrue()
        ->and(fn () => $save->handle($second, [...$attributes, 'name' => 'Second', 'is_active' => false, 'lock_version' => $second->lock_version], $actor))
        ->toThrow(ValidationException::class);

    $cleared = $setDefault->handle($room, null, $actor);
    $inactive = $save->handle($second, [...$attributes, 'name' => 'Second', 'is_active' => false, 'lock_version' => $second->lock_version], $actor);

    expect($inactive->is_active)->toBeFalse()
        ->and($cleared->default_seating_arrangement_id)->toBeNull()
        ->and(fn () => $setDefault->handle($room, $inactive, $actor))
        ->toThrow(ValidationException::class);
});

it('guards capacity for selected arrangements and room defaults', function (): void {
    $room = seatingTestRoom(12);
    $actor = User::factory()->create();
    $save = app(SaveSeatingArrangement::class);

    expect(fn () => $save->handle(null, [
        'name' => 'Too Large',
        'rows' => 4,
        'columns' => 4,
        'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
        'availability_mode' => SeatingArrangement::AVAILABILITY_SELECTED_ROOMS,
        'room_ids' => [$room->id],
        'is_active' => true,
    ], $actor))->toThrow(ValidationException::class);

    $arrangement = $save->handle(null, [
        'name' => 'Room Capacity Layout',
        'rows' => 3,
        'columns' => 4,
        'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
        'room_ids' => [],
        'is_active' => true,
    ], $actor);
    app(SetRoomDefaultSeatingArrangement::class)->handle($room, $arrangement, $actor);

    expect(fn () => app(UpdateRoom::class)->handle($room, [
        'name' => $room->name,
        'capacity' => 11,
        'is_active' => true,
    ], $actor))->toThrow(ValidationException::class)
        ->and($room->fresh()->capacity)->toBe(12);
});

it('authorizes reusable arrangement and room-default routes', function (): void {
    $this->withoutVite();
    $room = seatingTestRoom();
    $arrangement = SeatingArrangement::factory()->create([
        'name' => 'Route Layout',
        'normalized_name' => 'route layout',
        'rows' => 3,
        'columns' => 4,
        'availability_mode' => SeatingArrangement::AVAILABILITY_SELECTED_ROOMS,
    ]);
    $arrangement->rooms()->attach($room);

    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, 'web');
    Permission::findOrCreate(CapabilityCatalog::SETUP_INSTITUTION_MANAGE, 'web');
    $authorized = User::factory()->create();
    $authorized->givePermissionTo([
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
    ]);

    $this->actingAs($authorized)
        ->get(route('setup.seating-arrangements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/SeatingArrangements/Index')
            ->where('page_state', 'ready')
            ->has('arrangements.data', 1)
            ->where('arrangements.data.0.id', $arrangement->id));

    $this->actingAs($authorized)
        ->get(route('setup.seating-arrangements.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/SeatingArrangements/Create')
            ->where('mode', 'create')
            ->has('patterns', 4)
            ->has('availability_modes', 2)
            ->has('preview'));

    $this->actingAs($authorized)
        ->get(route('setup.seating-arrangements.edit', $arrangement))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/SeatingArrangements/Edit')
            ->where('arrangement.id', $arrangement->id)
            ->where('arrangement.room_ids.0', $room->id)
            ->has('preview'));

    $this->actingAs($authorized)
        ->get(route('setup.rooms.show', $room))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Rooms/Show')
            ->where('room.id', $room->id)
            ->where('applicable_seating_arrangements.0.id', $arrangement->id));

    $this->actingAs($authorized)
        ->post(route('setup.rooms.default-seating-arrangement', $room), [
            'seating_arrangement_id' => $arrangement->id,
        ])
        ->assertRedirect();

    expect($room->fresh()->default_seating_arrangement_id)->toBe($arrangement->id);

    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)
        ->get(route('setup.seating-arrangements.index'))
        ->assertForbidden();

    $this->actingAs($unauthorized)
        ->post(route('setup.rooms.default-seating-arrangement', $room), [
            'seating_arrangement_id' => $arrangement->id,
        ])
        ->assertForbidden();
});
