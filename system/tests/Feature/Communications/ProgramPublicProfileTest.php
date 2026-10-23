<?php

declare(strict_types=1);

use App\Authorization\CapabilityCatalog;
use App\Communications\Actions\PublishProgramPublicProfile;
use App\Communications\Actions\SaveProgramPublicProfile;
use App\Communications\Actions\StageProgramPhoto;
use App\Communications\Queries\ProgramPublicProfileQuery;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Communications\ProgramPublicProfile;
use App\Models\Communications\ProgramPublicProfileCommandReceipt;
use App\Models\Communications\ProgramPublicProfilePhoto;
use App\Models\Communications\ProgramPublicProfilePhotoStageReceipt;
use App\Models\Communications\ProgramPublicProfileRevision;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\ProgramPublicProfileDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedE2Foundation();
    $this->profileDiskName = 'program-profile-test-'.Str::uuid();
    $this->profileDisk = Storage::fake($this->profileDiskName);
    Storage::set('sensitive', $this->profileDisk);
    $this->head = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $this->program = Program::query()->where('code', 'BSIT')->firstOrFail();
});

afterEach(function (): void {
    if (isset($this->profileDisk, $this->profileDiskName)) {
        File::deleteDirectory($this->profileDisk->path(''));
    }
});

function programProfileUpload(
    string $name = 'synthetic-program-photo.png',
    int $width = 120,
    int $height = 80,
    int $variant = 0,
    string $suffix = '',
    string $format = 'png',
): UploadedFile {
    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, 24 + ($variant % 160), 82, 54);
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $background);
    ob_start();
    $encoded = match ($format) {
        'jpeg' => imagejpeg($image, null, 88),
        'webp' => imagewebp($image, null, 86),
        default => imagepng($image, null, 6),
    };
    $bytes = ob_get_clean();
    imagedestroy($image);

    if ($encoded !== true || ! is_string($bytes) || $bytes === '') {
        throw new RuntimeException('Could not generate the synthetic test raster.');
    }

    return UploadedFile::fake()->createWithContent($name, $bytes.$suffix);
}
function programUndecodablePngUpload(): UploadedFile
{
    $pngChunk = static function (string $type, string $data): string {
        return pack('N', strlen($data)).$type.$data.hash('crc32b', $type.$data, true);
    };
    $header = pack('N', 120).pack('N', 80).chr(8).chr(6).chr(0).chr(0).chr(0);
    $bytes = "\x89PNG\r\n\x1A\n"
        .$pngChunk('IHDR', $header)
        .$pngChunk('IDAT', 'not-zlib-compressed-image-data')
        .$pngChunk('IEND', '');

    return UploadedFile::fake()->createWithContent('undecodable.png', $bytes);
}

function profileSavePayload(int $expectedRevision, string $description, ?string $requestId = null, array $photos = []): array
{
    return [
        'request_id' => $requestId ?? (string) Str::uuid(),
        'expected_revision' => $expectedRevision,
        'short_description' => 'Synthetic BSN profile summary.',
        'description' => $description,
        'photos' => $photos,
    ];
}

test('own-program descriptions remain private until explicit publication', function (): void {
    $url = "/setup/programs/{$this->program->id}/public-details";
    $this->actingAs($this->head)->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->component('Setup/ProgramPublicDetails')
        ->where('program.code', 'BSIT')
        ->where('revision', 0)
        ->where('profile.description', ''));
    expect(ProgramPublicProfile::query()->where('program_id', $this->program->getKey())->exists())->toBeFalse();

    $this->putJson($url, profileSavePayload(
        0,
        'DEMO CONTENT — a private program description draft.',
    ))->assertOk()->assertJsonPath('profile.revision', 1);
    $this->app['auth']->guard()->logout();

    expect(app(ProgramPublicProfileQuery::class)->publicProfile($this->program))->toBeNull();
    $this->get('/programs/BSN')->assertInertia(fn ($page) => $page->where('publicProfile', null));
    $this->actingAs($this->head)->get($url.'/preview')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Setup/ProgramPublicDetailsPreview')
        ->where('preview_kind', 'private_saved_draft')
        ->where('profile.description', 'DEMO CONTENT — a private program description draft.'));

    $this->postJson($url.'/publish', [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => 1,
    ])->assertOk()->assertJsonPath('profile.revision', 2);
    $this->app['auth']->guard()->logout();
    $this->get('/programs/BSIT')->assertInertia(fn ($page) => $page
        ->where('publicProfile.description', 'DEMO CONTENT — a private program description draft.'));
});

test('save and publish replay one immutable result and drafts do not replace the live snapshot', function (): void {
    $save = app(SaveProgramPublicProfile::class);
    $publish = app(PublishProgramPublicProfile::class);
    $firstPayload = profileSavePayload(
        0,
        'Original public snapshot.',
        '1e1d0001-0000-4000-8000-000000000001',
    );
    $first = $save->handle($this->head, $this->program, $firstPayload);
    $firstReplay = $save->handle($this->head, $this->program, $firstPayload);
    expect((int) $firstReplay->getKey())->toBe((int) $first->getKey())
        ->and((int) $firstReplay->revision)->toBe(1)
        ->and(ProgramPublicProfileRevision::query()->count())->toBe(1)
        ->and(ProgramPublicProfileCommandReceipt::query()->count())->toBe(1);

    $publishPayload = [
        'request_id' => '1e1d0001-0000-4000-8000-000000000002',
        'expected_revision' => 1,
    ];
    $released = $publish->handle($this->head, $this->program, $publishPayload);
    $publishReplay = $publish->handle($this->head, $this->program, $publishPayload);
    $publishedPointer = (int) $released->getKey();
    expect((int) $publishReplay->getKey())->toBe($publishedPointer)
        ->and(ProgramPublicProfileRevision::query()->count())->toBe(2)
        ->and(ProgramPublicProfile::query()->where('program_id', $this->program->id)->value('published_revision_id'))->toBe($publishedPointer);

    $correctionPayload = profileSavePayload(
        2,
        'Corrected draft remains private until a new explicit publication.',
        '1e1d0001-0000-4000-8000-000000000003',
    );
    $correction = $save->handle($this->head, $this->program, $correctionPayload);
    $correctionReplay = $save->handle($this->head, $this->program, $correctionPayload);
    expect((int) $correctionReplay->getKey())->toBe((int) $correction->getKey())
        ->and((int) $correction->revision)->toBe(3)
        ->and((int) ProgramPublicProfile::query()->where('program_id', $this->program->id)->value('published_revision_id'))->toBe($publishedPointer)
        ->and(app(ProgramPublicProfileQuery::class)->publicProfile($this->program)['description'])->toBe('Original public snapshot.')
        ->and(ProgramPublicProfileRevision::query()->count())->toBe(3);

    expect(fn () => $save->handle($this->head, $this->program, [
        ...$correctionPayload,
        'description' => 'Altered payload under the same request ID.',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $save->handle($this->head, $this->program, profileSavePayload(2, 'Stale draft.')))
        ->toThrow(ValidationException::class);
    expect(ProgramPublicProfileRevision::query()->count())->toBe(3);

    $successor = $publish->handle($this->head, $this->program, [
        'request_id' => '1e1d0001-0000-4000-8000-000000000004',
        'expected_revision' => 3,
    ]);
    expect((int) $successor->revision)->toBe(4)
        ->and($successor->description)->toBe('Corrected draft remains private until a new explicit publication.')
        ->and((int) ProgramPublicProfile::query()->where('program_id', $this->program->id)->value('published_revision_id'))->toBe((int) $successor->getKey())
        ->and(ProgramPublicProfileRevision::query()->whereKey($publishedPointer)->value('description'))->toBe('Original public snapshot.');
});

test('staged photos stay private until referenced by a published snapshot and are retained after removal', function (): void {
    $url = "/setup/programs/{$this->program->id}/public-details";
    $upload = programProfileUpload();
    $uploadResponse = $this->actingAs($this->head)->post($url.'/photos', [
        'request_id' => (string) Str::uuid(),
        'photo' => $upload,
    ], ['Accept' => 'application/json'])->assertOk();
    $photoId = (int) $uploadResponse->json('photo.id');
    $privateUrl = (string) $uploadResponse->json('photo.src');
    $photo = ProgramPublicProfilePhoto::query()->findOrFail($photoId);
    $this->app['auth']->guard()->logout();

    $this->getJson($privateUrl)->assertUnauthorized();
    $privateResponse = $this->actingAs($this->head)->get($privateUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    expect($privateResponse->headers->hasCacheControlDirective('no-store'))->toBeTrue();
    $this->app['auth']->guard()->logout();
    $this->get("/programs/BSN/photos/{$photoId}")->assertNotFound();

    $foreignProgram = Program::query()
        ->where('institution_id', $this->program->institution_id)
        ->where('is_active', true)
        ->whereNotIn('code', $this->head->actorScope()->programCodes)
        ->firstOrFail();
    $this->actingAs($this->head)->get("/setup/programs/{$foreignProgram->id}/public-details/photos/{$photoId}")
        ->assertForbidden();

    $this->putJson($url, profileSavePayload(0, 'Synthetic profile with a sanitized photo.', null, [[
        'id' => $photoId,
        'alt_text' => 'Generated synthetic green campus-style illustration.',
        'caption' => 'Synthetic demo art; not a real campus photograph.',
    ]]))->assertOk()->assertJsonPath('profile.revision', 1);
    $this->app['auth']->guard()->logout();
    $this->get("/programs/BSN/photos/{$photoId}")->assertNotFound();
    $this->actingAs($this->head)->postJson($url.'/publish', [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => 1,
    ])->assertOk();
    $this->app['auth']->guard()->logout();
    $publicResponse = $this->get("/programs/BSIT/photos/{$photoId}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    expect($publicResponse->headers->hasCacheControlDirective('no-store'))->toBeTrue();

    $publicProfile = app(ProgramPublicProfileQuery::class)->publicProfile($this->program);
    expect($publicProfile['photos'][0]['id'])->toBe($photoId)
        ->and($publicProfile['photos'][0]['src'])->toBe("/programs/BSIT/photos/{$photoId}");

    $this->actingAs($this->head)->putJson($url, profileSavePayload(2, 'Draft removes its gallery photo.', null, []))
        ->assertOk()->assertJsonPath('profile.revision', 3);
    $this->app['auth']->guard()->logout();
    $this->get("/programs/BSIT/photos/{$photoId}")->assertOk();
    $this->actingAs($this->head)->postJson($url.'/publish', [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => 3,
    ])->assertOk();
    $this->app['auth']->guard()->logout();

    $this->get("/programs/BSIT/photos/{$photoId}")->assertNotFound();
    expect($this->profileDisk->exists($photo->storage_path))->toBeTrue()
        ->and(ProgramPublicProfilePhoto::query()->whereKey($photoId)->exists())->toBeTrue();

    $this->program->update(['is_active' => false]);
    $this->get("/programs/BSIT/photos/{$photoId}")->assertNotFound();
    expect(app(ProgramPublicProfileQuery::class)->publicProfile($this->program))->toBeNull();
});

test('photo staging binds retries to the source and reuses the same ready photo', function (): void {
    $requestId = '1e1d0001-0000-4000-8000-000000000015';
    $stage = app(StageProgramPhoto::class);
    $first = $stage->handle($this->head, $this->program, $requestId, programProfileUpload());
    $replay = $stage->handle($this->head, $this->program, $requestId, programProfileUpload());

    expect((int) $replay->getKey())->toBe((int) $first->getKey())
        ->and(ProgramPublicProfilePhoto::query()->count())->toBe(1)
        ->and(ProgramPublicProfilePhotoStageReceipt::query()->count())->toBe(1)
        ->and(AuditEvent::query()->where('event_type', 'communications.program_public_profile_photo_staged')->count())->toBe(1);

    expect(fn () => $stage->handle(
        $this->head,
        $this->program,
        $requestId,
        programProfileUpload(variant: 1),
    ))->toThrow(ValidationException::class);
    expect(ProgramPublicProfilePhoto::query()->count())->toBe(1);
});

test('invalid raster properties and failed private writes never create ready photo rows', function (): void {
    $url = "/setup/programs/{$this->program->id}/public-details/photos";
    $editUrl = "/setup/programs/{$this->program->id}/public-details";
    $this->actingAs($this->head)->putJson($editUrl, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => 0,
        'short_description' => '',
        'description' => 'A valid description with an invalid photo label.',
        'photos' => [['id' => 1, 'alt_text' => ' ', 'caption' => '']],
    ])->assertUnprocessable()->assertJsonValidationErrors('photos.0.alt_text');
    $this->actingAs($this->head)->putJson($editUrl, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => 0,
        'short_description' => str_repeat('s', 241),
        'description' => str_repeat('d', 10001),
        'photos' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['short_description', 'description']);
    expect(fn () => app(StageProgramPhoto::class)->handle(
        $this->head,
        $this->program,
        (string) Str::uuid(),
        programUndecodablePngUpload(),
    ))->toThrow(ValidationException::class);
    foreach ([
        ['photo' => programProfileUpload('wrong-extension.jpg')],
        ['photo' => UploadedFile::fake()->createWithContent('broken.png', 'not a PNG raster')],
        ['photo' => UploadedFile::fake()->createWithContent('oversize.png', str_repeat('x', (5 * 1024 * 1024) + 1))],
        ['photo' => programProfileUpload('too-wide.png', 4097, 4)],
    ] as $invalid) {
        $this->actingAs($this->head)->post($url, [
            'request_id' => (string) Str::uuid(),
            ...$invalid,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    $valid = programProfileUpload();
    $trailingBytes = file_get_contents($valid->getRealPath()).'EXECUTABLE TRAILER';
    expect(fn () => app(StageProgramPhoto::class)->handle(
        $this->head,
        $this->program,
        (string) Str::uuid(),
        UploadedFile::fake()->createWithContent('polyglot.png', $trailingBytes),
    ))->toThrow(ValidationException::class);
    expect(ProgramPublicProfilePhoto::query()->count())->toBe(0);

    $failedDisk = Mockery::mock(Filesystem::class);
    $failedDisk->shouldReceive('put')->once()->andReturn(false);
    $failedDisk->shouldReceive('delete')->once()->andReturn(true);
    Storage::set('sensitive', $failedDisk);
    expect(fn () => app(StageProgramPhoto::class)->handle(
        $this->head,
        $this->program,
        (string) Str::uuid(),
        programProfileUpload(),
    ))->toThrow(ValidationException::class);
    expect(ProgramPublicProfilePhoto::query()->count())->toBe(0)
        ->and(ProgramPublicProfilePhotoStageReceipt::query()->count())->toBe(0);
});

test('revoked loaded actors and incidental non-program office assignments cannot save or publish', function (): void {
    $save = app(SaveProgramPublicProfile::class);
    $saved = $save->handle($this->head, $this->program, profileSavePayload(0, 'A saved draft before revocation.'));
    $loadedActor = User::query()->with('staffProfile')->findOrFail($this->head->getKey());
    StaffProfile::query()->where('user_id', $this->head->getKey())->update(['program_codes' => []]);

    expect(fn () => $save->handle($loadedActor, $this->program, profileSavePayload(1, 'Revoked edit.')))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(PublishProgramPublicProfile::class)->handle($loadedActor, $this->program, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => 1,
    ]))->toThrow(AuthorizationException::class);

    $publisher = User::factory()->create([
        'email' => 'revoked.profile.publisher@nexiam.test',
        'name' => 'Synthetic Revoked Profile Publisher',
    ]);
    $publisher->givePermissionTo(CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_PUBLISH);
    StaffProfile::query()->create([
        'user_id' => $publisher->getKey(),
        'institution_id' => $this->program->institution_id,
        'office' => 'program',
        'program_codes' => [$this->program->code],
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $loadedPublisher = User::query()->with(['staffProfile', 'permissions'])->findOrFail($publisher->getKey());
    $loadedPublisher->revokePermissionTo(CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_PUBLISH);
    expect(fn () => app(PublishProgramPublicProfile::class)->handle($loadedPublisher, $this->program, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => 1,
    ]))->toThrow(AuthorizationException::class);

    expect((int) $saved->revision)->toBe(1)
        ->and(ProgramPublicProfileRevision::query()->count())->toBe(1)
        ->and(ProgramPublicProfile::query()->where('program_id', $this->program->id)->value('published_revision_id'))->toBeNull()
        ->and(ProgramPublicProfileCommandReceipt::query()->count())->toBe(1);

    $incidental = User::factory()->create();
    $incidental->givePermissionTo(
        CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE,
        CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_PUBLISH,
    );
    StaffProfile::query()->create([
        'user_id' => $incidental->getKey(),
        'institution_id' => $this->program->institution_id,
        'office' => 'guidance',
        'program_codes' => [$this->program->code],
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    expect(fn () => $save->handle($incidental, $this->program, profileSavePayload(1, 'Incidental office assignment.')))
        ->toThrow(AuthorizationException::class);
});

test('an active department dean can manage the department-owned program profile', function (): void {
    $department = $this->program->department;
    if ($department === null) {
        $department = Department::query()->create([
            'institution_id' => $this->program->institution_id,
            'code' => 'PROFILE-DEAN-TEST',
            'name' => 'Synthetic profile-dean test department',
            'is_active' => true,
        ]);
        $this->program->update(['department_id' => $department->getKey()]);
    }

    $dean = User::factory()->create([
        'email' => 'profile.dean.test@nexiam.test',
        'name' => 'Synthetic Profile Dean',
    ]);
    $dean->givePermissionTo(
        CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE,
        CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_PUBLISH,
    );
    StaffProfile::query()->create([
        'user_id' => $dean->getKey(),
        'institution_id' => $this->program->institution_id,
        'department_id' => $department->getKey(),
        'office' => 'program',
        'program_codes' => [],
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $department->update(['is_active' => true, 'dean_user_id' => $dean->getKey()]);

    $revision = app(SaveProgramPublicProfile::class)->handle(
        $dean,
        $this->program,
        profileSavePayload(0, 'Synthetic dean-owned public profile draft.'),
    );

    expect((int) $revision->revision)->toBe(1)
        ->and(ProgramPublicProfile::query()->where('program_id', $this->program->id)->exists())->toBeTrue();
});
test('publishing fails closed when a saved private media checksum is missing or changed', function (): void {
    $photo = app(StageProgramPhoto::class)->handle(
        $this->head,
        $this->program,
        (string) Str::uuid(),
        programProfileUpload(),
    );
    app(SaveProgramPublicProfile::class)->handle($this->head, $this->program, profileSavePayload(
        0,
        'A saved profile awaiting verified private media.',
        null,
        [[
            'id' => (int) $photo->getKey(),
            'alt_text' => 'Synthetic green program illustration.',
            'caption' => 'Synthetic test media.',
        ]],
    ));
    $this->profileDisk->put($photo->storage_path, 'tampered bytes');

    expect(fn () => app(PublishProgramPublicProfile::class)->handle($this->head, $this->program, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => 1,
    ]))->toThrow(ValidationException::class);

    $aggregate = ProgramPublicProfile::query()->where('program_id', $this->program->getKey())->firstOrFail();
    expect((int) $aggregate->current_revision)->toBe(1)
        ->and($aggregate->published_revision_id)->toBeNull()
        ->and(ProgramPublicProfileRevision::query()->count())->toBe(1)
        ->and(ProgramPublicProfileCommandReceipt::query()->count())->toBe(1);
});
test('the synthetic profile seeder creates one published raster fixture and is idempotent', function (): void {
    $passwordHash = $this->head->getAuthPassword();
    (new ProgramPublicProfileDemoSeeder)->run();

    $profile = ProgramPublicProfile::query()->where('program_id', $this->program->getKey())->firstOrFail();
    $firstRevisionCount = ProgramPublicProfileRevision::query()->where('profile_id', $profile->getKey())->count();
    $firstPhotoCount = ProgramPublicProfilePhoto::query()->where('program_id', $this->program->getKey())->count();
    expect((int) $profile->current_revision)->toBe(2)
        ->and($profile->published_revision_id)->not->toBeNull()
        ->and($firstRevisionCount)->toBe(2)
        ->and($firstPhotoCount)->toBe(1)
        ->and(app(ProgramPublicProfileQuery::class)->publicProfile($this->program)['description'])->toContain('DEMO CONTENT');

    (new ProgramPublicProfileDemoSeeder)->run();
    $profile->refresh();
    expect((int) $profile->current_revision)->toBe(2)
        ->and(ProgramPublicProfileRevision::query()->where('profile_id', $profile->getKey())->count())->toBe($firstRevisionCount)
        ->and(ProgramPublicProfilePhoto::query()->where('program_id', $this->program->getKey())->count())->toBe($firstPhotoCount)
        ->and(User::query()->whereKey($this->head->getKey())->value('password'))->toBe($passwordHash);
});

test('the synthetic profile seeder never replaces an existing draft', function (): void {
    $passwordHash = $this->head->getAuthPassword();
    $draft = app(SaveProgramPublicProfile::class)->handle(
        $this->head,
        $this->program,
        profileSavePayload(0, 'An existing real draft must not be overwritten.'),
    );

    (new ProgramPublicProfileDemoSeeder)->run();

    $profile = ProgramPublicProfile::query()->where('program_id', $this->program->getKey())->firstOrFail();
    expect((int) $profile->current_revision)->toBe((int) $draft->revision)
        ->and($profile->published_revision_id)->toBeNull()
        ->and(ProgramPublicProfileRevision::query()->where('profile_id', $profile->getKey())->count())->toBe(1)
        ->and(ProgramPublicProfilePhoto::query()->where('program_id', $this->program->getKey())->count())->toBe(0)
        ->and(User::query()->whereKey($this->head->getKey())->value('password'))->toBe($passwordHash);
});
test('publish-only scoped authority can publish an existing draft without gaining editor access', function (): void {
    $url = "/setup/programs/{$this->program->id}/public-details";
    app(SaveProgramPublicProfile::class)->handle(
        $this->head,
        $this->program,
        profileSavePayload(0, 'A saved draft for the independent publisher grant.'),
    );

    $publisher = User::factory()->create([
        'email' => 'profile.publisher.test@nexiam.test',
        'name' => 'Synthetic Profile Publisher',
    ]);
    $publisher->givePermissionTo(CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_PUBLISH);
    StaffProfile::query()->create([
        'user_id' => $publisher->getKey(),
        'institution_id' => $this->program->institution_id,
        'office' => 'program',
        'program_codes' => [$this->program->code],
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $publisher->getKey(),
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'session_version' => 1,
        'two_factor_generation' => (string) Str::uuid(),
    ]);

    $this->actingAs($publisher)->get($url)->assertForbidden();
    $this->actingAs($publisher)->postJson($url.'/publish', [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => 1,
    ])->assertOk()->assertJsonPath('profile.revision', 2);
    expect(ProgramPublicProfile::query()->where('program_id', $this->program->getKey())->value('published_revision_id'))
        ->toBeGreaterThan(0);
});
test('JPEG, PNG, and WebP uploads are decoded and re-encoded without cropping', function (): void {
    foreach ([
        ['format' => 'jpeg', 'extension' => 'jpg', 'mime' => 'image/jpeg'],
        ['format' => 'png', 'extension' => 'png', 'mime' => 'image/png'],
        ['format' => 'webp', 'extension' => 'webp', 'mime' => 'image/webp'],
    ] as $format) {
        $photo = app(StageProgramPhoto::class)->handle(
            $this->head,
            $this->program,
            (string) Str::uuid(),
            programProfileUpload(
                'synthetic-profile.'.$format['extension'],
                width: 120,
                height: 80,
                format: $format['format'],
            ),
        );
        $stored = $this->profileDisk->get($photo->storage_path);
        $imageInfo = getimagesizefromstring($stored);

        expect($photo->mime_type)->toBe($format['mime'])
            ->and((int) $photo->width)->toBe(120)
            ->and((int) $photo->height)->toBe(80)
            ->and($imageInfo['mime'])->toBe($format['mime'])
            ->and((int) $photo->byte_size)->toBe(strlen($stored));
    }

    expect(ProgramPublicProfilePhoto::query()->count())->toBe(3);
});

test('raster staging rejects trailing payloads even when another terminator is appended', function (): void {
    foreach ([
        ['png', 'png', "\x00\x00\x00\x00IEND\xAE\x42\x60\x82"],
        ['jpeg', 'jpg', "\xFF\xD9"],
    ] as [$format, $extension, $terminator]) {
        $upload = programProfileUpload('source.'.$extension, format: $format);
        $bytes = file_get_contents($upload->getRealPath()).'EXECUTABLE TRAILER'.$terminator;
        $this->actingAs($this->head)->post("/setup/programs/{$this->program->id}/public-details/photos", [
            'request_id' => (string) Str::uuid(),
            'photo' => UploadedFile::fake()->createWithContent('trailer.'.$extension, $bytes),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photo');
    }
    expect(ProgramPublicProfilePhoto::query()->count())->toBe(0);
});

test('JPEG staging normalizes reflected EXIF orientations before stripping metadata', function (int $orientation, array $expectedCorners): void {
    $image = imagecreatetruecolor(40, 24);
    foreach ([[0, 0, 255, 0, 0], [20, 0, 0, 255, 0], [0, 12, 0, 0, 255], [20, 12, 255, 255, 0]] as [$x, $y, $red, $green, $blue]) {
        imagefilledrectangle($image, $x, $y, $x + 19, $y + 11, imagecolorallocate($image, $red, $green, $blue));
    }
    ob_start();
    imagejpeg($image, null, 100);
    $jpeg = ob_get_clean();
    imagedestroy($image);
    $exif = "Exif\0\0II\x2A\x00".pack('V', 8).pack('v', 1)
        .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation)."\0\0".pack('V', 0);
    $bytes = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    $photo = app(StageProgramPhoto::class)->handle($this->head, $this->program, (string) Str::uuid(),
        UploadedFile::fake()->createWithContent('oriented.jpg', $bytes));
    $stored = imagecreatefromstring(Storage::disk('sensitive')->get($photo->storage_path));
    $width = imagesx($stored);
    $height = imagesy($stored);
    expect([$width, $height])->toBe($orientation === 4 ? [40, 24] : [24, 40]);
    $actualCorners = [];
    foreach ([[3, 3], [$width - 4, 3], [3, $height - 4], [$width - 4, $height - 4]] as [$x, $y]) {
        $color = imagecolorsforindex($stored, imagecolorat($stored, $x, $y));
        $actualCorners[] = [$color['red'] > 180, $color['green'] > 180, $color['blue'] > 180];
    }
    imagedestroy($stored);
    expect($actualCorners)->toBe($expectedCorners);
})->with([
    'vertical reflection' => [4, [[false, false, true], [true, true, false], [true, false, false], [false, true, false]]],
    'transpose' => [5, [[true, false, false], [false, false, true], [false, true, false], [true, true, false]]],
    'anti-transpose' => [7, [[true, true, false], [false, true, false], [false, false, true], [true, false, false]]],
]);

test('a shared profile request id cannot be reused for another owned program', function (): void {
    $other = Program::query()->where('institution_id', $this->program->institution_id)
        ->where('is_active', true)->where('id', '!=', $this->program->id)->firstOrFail();
    $this->head->staffProfile()->update(['program_codes' => [$this->program->code, $other->code]]);
    $payload = profileSavePayload(0, 'Private draft for the first owned program.');
    app(SaveProgramPublicProfile::class)->handle($this->head, $this->program, $payload);
    expect(fn () => app(SaveProgramPublicProfile::class)->handle($this->head, $other, $payload))
        ->toThrow(ValidationException::class);
    $this->actingAs($this->head)->get("/setup/programs/{$other->id}/public-details")
        ->assertOk()->assertInertia(fn ($page) => $page->where('revision', 0));
});
