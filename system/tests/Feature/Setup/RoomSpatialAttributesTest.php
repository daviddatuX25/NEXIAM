<?php

use App\Foundation\Actions\CreateRoom;
use App\Foundation\Actions\UpdateRoom;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
});

function spatialTestRoom(array $attributes = []): Room
{
    return Room::query()->create([
        'institution_id' => Institution::query()->where('code', 'SYN-INST-001')->value('id'),
        'code' => 'SPATIAL-101',
        'name' => 'Spatial Test Room',
        'capacity' => 30,
        'is_active' => true,
        ...$attributes,
    ]);
}

it('creates and updates room coordinates and wayfinding details', function (): void {
    $actor = User::factory()->create();
    $room = app(CreateRoom::class)->handle([
        'code' => 'SPATIAL-102',
        'name' => 'Spatial Room',
        'capacity' => 30,
        'latitude' => 14.5995123,
        'longitude' => 120.9842211,
        'building_name' => 'North Hall',
        'floor_level' => '2nd Floor',
        'directions_hint' => 'Enter through the north doorway.',
    ], $actor, $this->institution);

    expect((float) $room->latitude)->toBe(14.5995123)
        ->and((float) $room->longitude)->toBe(120.9842211)
        ->and($room->building_name)->toBe('North Hall')
        ->and($room->floor_level)->toBe('2nd Floor')
        ->and($room->directions_hint)->toBe('Enter through the north doorway.');

    $updated = app(UpdateRoom::class)->handle($room, [
        'name' => 'Spatial Room Updated',
        'capacity' => 35,
        'is_active' => true,
        'latitude' => 0.0,
        'longitude' => 0.0,
        'building_name' => 'South Hall',
        'floor_level' => 'Ground',
        'directions_hint' => 'Use the south entrance beside the library.',
    ], $actor);

    expect((float) $updated->latitude)->toBe(0.0)
        ->and((float) $updated->longitude)->toBe(0.0)
        ->and($updated->building_name)->toBe('South Hall')
        ->and($updated->floor_level)->toBe('Ground')
        ->and($updated->directions_hint)->toBe('Use the south entrance beside the library.');
});

it('rejects latitude without longitude', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('setup.rooms.store'), [
            'code' => 'LAT-ONLY',
            'name' => 'Latitude Only',
            'capacity' => 20,
            'latitude' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('longitude');
});

it('rejects longitude without latitude', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('setup.rooms.store'), [
            'code' => 'LON-ONLY',
            'name' => 'Longitude Only',
            'capacity' => 20,
            'longitude' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('latitude');
});

it('rejects latitude outside WGS84 bounds', function (float $latitude): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('setup.rooms.store'), [
            'code' => 'OUT-OF-RANGE',
            'name' => 'Out of Range',
            'capacity' => 20,
            'latitude' => $latitude,
            'longitude' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('latitude');
})->with([90.0000001, -90.0000001]);

it('accepts zero coordinates as the equator and prime meridian', function (): void {
    $room = app(CreateRoom::class)->handle([
        'code' => 'ZERO-COORD',
        'name' => 'Zero Coordinates',
        'capacity' => 20,
        'latitude' => 0.0,
        'longitude' => 0.0,
    ], User::factory()->create(), $this->institution);

    expect((float) $room->latitude)->toBe(0.0)
        ->and((float) $room->longitude)->toBe(0.0);
});

it('persists a room without coordinates when both are omitted', function (): void {
    $room = spatialTestRoom();

    expect($room->latitude)->toBeNull()
        ->and($room->longitude)->toBeNull();
});
