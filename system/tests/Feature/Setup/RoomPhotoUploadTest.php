<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    Storage::fake('public');
});

function roomPhotoTestActor(bool $authorized = true): User
{
    $actor = User::factory()->create();

    if ($authorized) {
        Permission::findOrCreate(CapabilityCatalog::SETUP_ROOMS_MANAGE, 'web');
        $actor->givePermissionTo(CapabilityCatalog::SETUP_ROOMS_MANAGE);
    }

    StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => Institution::query()->where('code', 'SYN-INST-001')->value('id'),
        'office' => 'registrar',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    StaffAuthenticationState::query()->create([
        'user_id' => $actor->id,
        'session_version' => 1,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
    ]);

    return $actor;
}

function roomPhotoTestRoom(array $attributes = []): Room
{
    return Room::query()->create([
        'institution_id' => Institution::query()->where('code', 'SYN-INST-001')->value('id'),
        'code' => 'PHOTO-ROOM-'.uniqid(),
        'name' => 'Photo Test Room',
        'capacity' => 30,
        'is_active' => true,
        ...$attributes,
    ]);
}

test('authorized staff can upload a room entrance photo as WebP', function (): void {
    $actor = roomPhotoTestActor();
    $room = roomPhotoTestRoom();

    $this->actingAs($actor)
        ->withSession(['auth.surface' => 'staff', 'auth.session_version' => 1])
        ->from(route('setup.rooms.edit', $room))
        ->post(route('setup.rooms.photo.upload', $room), [
            'photo' => UploadedFile::fake()->image('entrance.jpg', 1600, 1200),
        ])
        ->assertRedirect(route('setup.rooms.edit', $room))
        ->assertSessionHas('success', 'Room photo updated.');

    $room->refresh();
    expect($room->photo_path)->toMatch('#^rooms/'.$this->institution->id.'/[0-9a-f-]+\.webp$#');
    Storage::disk('public')->assertExists($room->photo_path);
    $dimensions = getimagesize(Storage::disk('public')->path($room->photo_path));
    expect($dimensions['mime'])->toBe('image/webp')
        ->and($dimensions[0])->toBe(1200)
        ->and($dimensions[1])->toBe(900);
});

test('deleting a room photo removes its public file and clears photo path', function (): void {
    $actor = roomPhotoTestActor();
    $path = 'rooms/'.$this->institution->id.'/existing.webp';
    Storage::disk('public')->put($path, 'old-photo');
    $room = roomPhotoTestRoom(['photo_path' => $path]);

    $this->actingAs($actor)
        ->withSession(['auth.surface' => 'staff', 'auth.session_version' => 1])
        ->from(route('setup.rooms.edit', $room))
        ->delete(route('setup.rooms.photo.delete', $room))
        ->assertRedirect(route('setup.rooms.edit', $room))
        ->assertSessionHas('success', 'Room photo removed.');

    expect($room->fresh()->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('non-image room photo upload is rejected', function (): void {
    $room = roomPhotoTestRoom();

    $this->actingAs(roomPhotoTestActor())
        ->withSession(['auth.surface' => 'staff', 'auth.session_version' => 1])
        ->postJson(route('setup.rooms.photo.upload', $room), [
            'photo' => UploadedFile::fake()->create('notes.txt', 12, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo');
});

test('unauthorized user cannot upload a room photo', function (): void {
    $room = roomPhotoTestRoom();

    $this->actingAs(roomPhotoTestActor(authorized: false))
        ->withSession(['auth.surface' => 'staff', 'auth.session_version' => 1])
        ->post(route('setup.rooms.photo.upload', $room), [
            'photo' => UploadedFile::fake()->image('entrance.jpg'),
        ])
        ->assertForbidden();

    expect($room->fresh()->photo_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});
