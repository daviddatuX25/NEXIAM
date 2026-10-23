<?php

declare(strict_types=1);

use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('public');
});
function createErgonomicsStaff(array $profileOverrides = []): User
{
    $user = User::factory()->create([
        'name' => 'Maria Santos',
        'email' => 'maria.santos.'.fake()->unique()->numerify('#####').'@gmail.com',
        'password' => bcrypt('Staff-Secret-123!'),
    ]);

    $institution = Institution::query()->firstOrFail();

    StaffProfile::query()->create(array_merge([
        'user_id' => $user->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
        'avatar_type' => 'initials',
        'avatar_preset' => null,
        'avatar_path' => null,
        'phone_extension' => null,
        'room_number' => null,
        'consultation_hours' => null,
    ], $profileOverrides));
    StaffAuthenticationState::query()->create([
        'user_id' => $user->id,
        'session_version' => 1,
        'password_set_at' => now(),
        'activation_completed_at' => now(),
        'mfa_required' => false,
        'two_factor_generation' => (string) Str::uuid(),
    ]);

    $user->assignRole('guidance_counselor');

    return $user;
}

test('authenticated staff can view profile with avatar and directory props', function (): void {
    $user = createErgonomicsStaff([
        'phone_extension' => 'Ext. 4022',
        'room_number' => 'Admin Hall Rm 204',
        'consultation_hours' => 'Mon-Thu 8am-5pm',
    ]);

    $this->actingAs($user)
        ->get(route('staff.profile'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Staff/Profile/Show')
            ->has('directory', fn (Assert $dir) => $dir
                ->where('phone_extension', 'Ext. 4022')
                ->where('room_number', 'Admin Hall Rm 204')
                ->where('consultation_hours', 'Mon-Thu 8am-5pm')
            )
            ->has('user.avatar', fn (Assert $av) => $av
                ->where('type', 'initials')
                ->where('preset', null)
                ->where('url', null)
            )
            ->where('auth.user.office', 'guidance')
        );
});

test('authenticated staff can select a valid office-worker SVG preset', function (): void {
    $user = createErgonomicsStaff();

    $this->actingAs($user)
        ->post(route('staff.profile.avatar.update'), [
            'type' => 'preset',
            'preset' => 'counselor',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Avatar updated successfully.');

    $profile = $user->staffProfile()->firstOrFail();
    expect($profile->avatar_type)->toBe('preset')
        ->and($profile->avatar_preset)->toBe('counselor')
        ->and($profile->avatar_path)->toBeNull();
});

test('selecting an invalid SVG preset is rejected by validation', function (): void {
    $user = createErgonomicsStaff();

    $this->actingAs($user)
        ->post(route('staff.profile.avatar.update'), [
            'type' => 'preset',
            'preset' => 'invalid_astronaut_preset',
        ])
        ->assertSessionHasErrors(['preset']);

    $profile = $user->staffProfile()->firstOrFail();
    expect($profile->avatar_type)->toBe('initials');
});

test('authenticated staff can upload a custom photo and previous files are purged', function (): void {
    $user = createErgonomicsStaff();

    $file = UploadedFile::fake()->image('staff_photo.png', 400, 200)->size(500);

    $this->actingAs($user)
        ->post(route('staff.profile.avatar.update'), [
            'type' => 'upload',
            'photo' => $file,
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Avatar updated successfully.');

    $profile = $user->staffProfile()->firstOrFail();
    expect($profile->avatar_type)->toBe('upload')
        ->and($profile->avatar_path)->not->toBeNull()
        ->and($profile->avatar_preset)->toBeNull();

    $storedPath = $profile->avatar_path;
    expect($storedPath)->toEndWith('.png');
    Storage::disk('public')->assertExists($storedPath);
    $normalizedImage = getimagesizefromstring(Storage::disk('public')->get($storedPath));
    expect($normalizedImage[0])->toBe(256)
        ->and($normalizedImage[1])->toBe(256);
    // Switching to preset deletes the old uploaded file
    $this->actingAs($user)
        ->post(route('staff.profile.avatar.update'), [
            'type' => 'preset',
            'preset' => 'records',
        ])
        ->assertRedirect();

    Storage::disk('public')->assertMissing($storedPath);
    $profile->refresh();
    expect($profile->avatar_type)->toBe('preset')
        ->and($profile->avatar_preset)->toBe('records')
        ->and($profile->avatar_path)->toBeNull();
});

test('uploading a non-image file or oversized photo is rejected', function (): void {
    $user = createErgonomicsStaff();

    $pdfFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs($user)
        ->post(route('staff.profile.avatar.update'), [
            'type' => 'upload',
            'photo' => $pdfFile,
        ])
        ->assertSessionHasErrors(['photo']);

    $oversizedFile = UploadedFile::fake()->image('huge.jpg', 2000, 2000)->size(3000); // 3MB > 2MB

    $this->actingAs($user)
        ->post(route('staff.profile.avatar.update'), [
            'type' => 'upload',
            'photo' => $oversizedFile,
        ])
        ->assertSessionHasErrors(['photo']);

    $oversizedDimensions = UploadedFile::fake()->image('wide.png', 4097, 1);

    $this->actingAs($user)
        ->post(route('staff.profile.avatar.update'), [
            'type' => 'upload',
            'photo' => $oversizedDimensions,
        ])
        ->assertSessionHasErrors(['photo']);
});

test('authenticated staff can reset avatar to monogram initials', function (): void {
    $user = createErgonomicsStaff([
        'avatar_type' => 'preset',
        'avatar_preset' => 'counselor',
    ]);

    $this->actingAs($user)
        ->post(route('staff.profile.avatar.update'), [
            'type' => 'initials',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Avatar updated successfully.');

    $profile = $user->staffProfile()->firstOrFail();
    expect($profile->avatar_type)->toBe('initials')
        ->and($profile->avatar_preset)->toBeNull()
        ->and($profile->avatar_path)->toBeNull();
});

test('authenticated staff can update campus directory and desk location information', function (): void {
    $user = createErgonomicsStaff();

    $this->actingAs($user)
        ->post(route('staff.profile.directory.update'), [
            'phone_extension' => 'Ext. 8831',
            'room_number' => 'Guidance Center, Room 102',
            'consultation_hours' => 'Mon-Fri 9:00 AM - 4:00 PM',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Directory information updated successfully.');

    $profile = $user->staffProfile()->firstOrFail();
    expect($profile->phone_extension)->toBe('Ext. 8831')
        ->and($profile->room_number)->toBe('Guidance Center, Room 102')
        ->and($profile->consultation_hours)->toBe('Mon-Fri 9:00 AM - 4:00 PM');
});

test('staff office contact fields are isolated to the owning profile', function (): void {
    $owner = createErgonomicsStaff([
        'phone_extension' => 'Ext. 7711',
        'room_number' => 'Registrar Annex 201',
        'consultation_hours' => 'Weekdays 8am-4pm',
    ]);
    $anotherStaffMember = createErgonomicsStaff();

    $this->actingAs($anotherStaffMember)
        ->get(route('staff.profile'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('directory.phone_extension', null)
            ->where('directory.room_number', null)
            ->where('directory.consultation_hours', null)
            ->missing('auth.user.phone_extension')
            ->missing('auth.user.room_number')
            ->missing('auth.user.consultation_hours')
        );

    expect($owner->staffProfile()->firstOrFail()->phone_extension)->toBe('Ext. 7711');
});

test('HandleInertiaRequests projects avatar, office, and roles on auth.user', function (): void {
    $user = createErgonomicsStaff([
        'avatar_type' => 'preset',
        'avatar_preset' => 'guidance_clerk',
    ]);

    $this->actingAs($user)
        ->get(route('staff.profile'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.user', fn (Assert $authUser) => $authUser
                ->where('name', 'Maria Santos')
                ->where('office', 'guidance')
                ->has('roles')
                ->missing('phone_extension')
                ->missing('room_number')
                ->missing('consultation_hours')
                ->has('avatar', fn (Assert $av) => $av
                    ->where('type', 'preset')
                    ->where('preset', 'guidance_clerk')
                    ->where('url', null)
                )
                ->etc()
            )
        );
});
