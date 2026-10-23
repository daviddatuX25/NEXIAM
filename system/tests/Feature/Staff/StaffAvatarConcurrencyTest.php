<?php

declare(strict_types=1);

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;

beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('public');
});

function createConcurrencyStaff(array $profileOverrides = []): User
{
    $user = User::factory()->create([
        'name' => 'Maria Santos',
        'email' => 'avatar.'.fake()->unique()->numerify('#####').'@nexiam.test',
        'password' => bcrypt('Staff-Secret-123!'),
    ]);

    StaffProfile::query()->create(array_merge([
        'user_id' => $user->id,
        'institution_id' => Institution::query()->firstOrFail()->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
        'avatar_type' => 'initials',
        'avatar_preset' => null,
        'avatar_path' => null,
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

function concurrencyExifOrientation(string $jpegBytes, int $orientation): string
{
    $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1)
        .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation)."\0\0";
    $payload = "Exif\0\0".$tiff;
    $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

    return substr($jpegBytes, 0, 2).$segment.substr($jpegBytes, 2);
}

test('avatar upload rolls back and deletes staged file when audit event fails', function (): void {
    $user = createConcurrencyStaff();
    $filesBefore = Storage::disk('public')->allFiles();

    $this->mock(RecordAuditEvent::class, function (MockInterface $mock): void {
        $mock->shouldReceive('handle')
            ->once()
            ->with('auth.staff_avatar.updated', Mockery::type('array'), Mockery::type('string'))
            ->andThrow(new RuntimeException('Audit write failed.'));
    });

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($user)->post(route('staff.profile.avatar.update'), [
        'type' => 'upload',
        'photo' => UploadedFile::fake()->image('first.png', 320, 240),
    ]))->toThrow(RuntimeException::class, 'Audit write failed.');

    expect($user->staffProfile()->firstOrFail()->avatar_path)->toBeNull()
        ->and(array_diff(Storage::disk('public')->allFiles(), $filesBefore))->toBe([]);
});

test('avatar replacement retains new file and purges old file', function (): void {
    $user = createConcurrencyStaff();

    $this->actingAs($user)->post(route('staff.profile.avatar.update'), [
        'type' => 'upload',
        'photo' => UploadedFile::fake()->image('first.png', 320, 240),
    ])->assertRedirect();
    $firstPath = $user->staffProfile()->firstOrFail()->avatar_path;
    expect($firstPath)->not->toBeNull();
    Storage::disk('public')->assertExists($firstPath);

    $this->actingAs($user)->post(route('staff.profile.avatar.update'), [
        'type' => 'upload',
        'photo' => UploadedFile::fake()->image('second.png', 400, 300),
    ])->assertRedirect();

    $secondPath = $user->staffProfile()->firstOrFail()->avatar_path;
    expect($secondPath)->not->toBeNull()->and($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertExists($secondPath);
    Storage::disk('public')->assertMissing($firstPath);
});

test('avatar upload normalizes EXIF orientation', function (): void {
    $user = createConcurrencyStaff();
    $image = imagecreatetruecolor(40, 20);
    ob_start();
    imagejpeg($image, null, 90);
    $jpegBytes = ob_get_clean();
    imagedestroy($image);
    expect($jpegBytes)->toBeString();

    $orientedJpeg = concurrencyExifOrientation($jpegBytes, 6);
    $this->actingAs($user)->post(route('staff.profile.avatar.update'), [
        'type' => 'upload',
        'photo' => UploadedFile::fake()->createWithContent('oriented.jpg', $orientedJpeg),
    ])->assertRedirect();

    $path = $user->staffProfile()->firstOrFail()->avatar_path;
    expect($path)->not->toBeNull();
    $dimensions = getimagesizefromstring(Storage::disk('public')->get($path));
    expect($dimensions[0])->toBe(256)->and($dimensions[1])->toBe(256);
});
