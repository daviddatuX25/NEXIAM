<?php

use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->institution = Institution::current();
    Room::query()->where('institution_id', $this->institution->id)->update([
        'latitude' => null,
        'longitude' => null,
    ]);
});

function createWayfindingVenue(array $attributes = []): Room
{
    return Room::factory()->for(Institution::current())->create([
        'code' => 'WAYFINDING-'.strtoupper(fake()->unique()->bothify('??-###')),
        'name' => 'Wayfinding Venue',
        'space_kind' => 'room',
        'is_active' => true,
        'latitude' => 14.5995123,
        'longitude' => 120.9842211,
        ...$attributes,
    ]);
}

test('public API returns active spatial venues in kind and code order with the expected fields', function (): void {
    Storage::fake('public');
    $absolutePhotoPath = '/images/wayfinding/assembly-door.svg';
    $relativePhotoPath = 'rooms/1/service-point.webp';

    $roomB = createWayfindingVenue(['code' => 'ROOM-B']);
    $roomA = createWayfindingVenue([
        'code' => 'ROOM-A',
        'photo_path' => $absolutePhotoPath,
        'building_name' => 'North Hall',
        'floor_level' => '2nd Floor',
        'directions_hint' => 'Use the north entrance.',
    ]);
    $assembly = createWayfindingVenue([
        'code' => 'ASSEMBLY-A',
        'space_kind' => 'assembly_venue',
        'photo_path' => $relativePhotoPath,
    ]);
    $servicePoint = createWayfindingVenue([
        'code' => 'SERVICE-A',
        'space_kind' => 'service_point',
        'photo_path' => null,
    ]);
    $otherInstitution = Institution::query()->create(['code' => 'WAYFINDING-OTHER', 'name' => 'Other Institution']);
    createWayfindingVenue([
        'institution_id' => $otherInstitution->id,
        'code' => 'OTHER-INSTITUTION',
    ]);

    $this->getJson(route('api.wayfinding.venues'))
        ->assertOk()
        ->assertJsonStructure([
            [
                'id',
                'code',
                'name',
                'space_kind',
                'building_name',
                'floor_level',
                'directions_hint',
                'photo_path',
                'latitude',
                'longitude',
            ],
        ])
        ->assertExactJson([
            [
                'id' => $roomA->id,
                'code' => 'ROOM-A',
                'name' => 'Wayfinding Venue',
                'space_kind' => 'room',
                'building_name' => 'North Hall',
                'floor_level' => '2nd Floor',
                'directions_hint' => 'Use the north entrance.',
                'photo_path' => $absolutePhotoPath,
                'latitude' => 14.5995123,
                'longitude' => 120.9842211,
            ],
            [
                'id' => $roomB->id,
                'code' => 'ROOM-B',
                'name' => 'Wayfinding Venue',
                'space_kind' => 'room',
                'building_name' => null,
                'floor_level' => null,
                'directions_hint' => null,
                'photo_path' => null,
                'latitude' => 14.5995123,
                'longitude' => 120.9842211,
            ],
            [
                'id' => $assembly->id,
                'code' => 'ASSEMBLY-A',
                'name' => 'Wayfinding Venue',
                'space_kind' => 'assembly_venue',
                'building_name' => null,
                'floor_level' => null,
                'directions_hint' => null,
                'photo_path' => Storage::disk('public')->url($relativePhotoPath),
                'latitude' => 14.5995123,
                'longitude' => 120.9842211,
            ],
            [
                'id' => $servicePoint->id,
                'code' => 'SERVICE-A',
                'name' => 'Wayfinding Venue',
                'space_kind' => 'service_point',
                'building_name' => null,
                'floor_level' => null,
                'directions_hint' => null,
                'photo_path' => null,
                'latitude' => 14.5995123,
                'longitude' => 120.9842211,
            ],
        ]);
});

test('public API excludes inactive venues and venues without a complete coordinate pair', function (): void {
    $visible = createWayfindingVenue(['code' => 'VISIBLE']);
    createWayfindingVenue(['code' => 'INACTIVE', 'is_active' => false]);
    createWayfindingVenue(['code' => 'NO-LATITUDE', 'latitude' => null, 'longitude' => 120.0]);
    createWayfindingVenue(['code' => 'NO-LONGITUDE', 'latitude' => 14.0, 'longitude' => null]);

    $this->getJson('/api/wayfinding/venues')
        ->assertOk()
        ->assertExactJson([
            [
                'id' => $visible->id,
                'code' => 'VISIBLE',
                'name' => 'Wayfinding Venue',
                'space_kind' => 'room',
                'building_name' => null,
                'floor_level' => null,
                'directions_hint' => null,
                'photo_path' => null,
                'latitude' => 14.5995123,
                'longitude' => 120.9842211,
            ],
        ]);
});
