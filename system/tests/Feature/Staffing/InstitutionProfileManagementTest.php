<?php

declare(strict_types=1);

use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('public');
});

test('institutional admin can access institution profile page', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $this->actingAs($admin)
        ->get('/setup/institution')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Institution/Index')
            ->has('institution')
            ->has('env_defaults')
            ->where('can_manage', true)
        );
});

test('operational staff and clerks cannot access or mutate institution profile', function (): void {
    seedE2Foundation();
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    foreach ([$counselor, $clerk] as $actor) {
        $this->actingAs($actor)->get('/setup/institution')->assertForbidden();

        $this->actingAs($actor)->put('/setup/institution', [
            'name' => 'Unauthorized University',
        ])->assertForbidden();

        $this->actingAs($actor)->post('/setup/institution/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
        ])->assertForbidden();

        $this->actingAs($actor)->delete('/setup/institution/logo')->assertForbidden();

        $this->actingAs($actor)->post('/setup/institution/reset')->assertForbidden();
    }
});

test('unauthenticated guests are redirected to login', function (): void {
    $this->get('/setup/institution')->assertRedirect('/login');
    $this->put('/setup/institution', ['name' => 'Test'])->assertRedirect('/login');
});

test('institutional admin can update institution profile details', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $payload = [
        'name' => 'Northern Polytechnic State University',
        'short_name' => 'NPSU',
        'campus_name' => 'Main Heritage Campus',
        'address' => 'National Highway, Heritage Town, Province 1234',
        'location' => 'Heritage Town, Province',
        'contact_number' => '+63 917 123 4567',
        'email' => 'info@npsu.edu.ph',
        'website' => 'https://npsu.edu.ph',
    ];

    $this->actingAs($admin)
        ->put('/setup/institution', $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    $institution = Institution::current()->fresh();
    expect($institution->name)->toBe('Northern Polytechnic State University')
        ->and($institution->short_name)->toBe('NPSU')
        ->and($institution->campus_name)->toBe('Main Heritage Campus')
        ->and($institution->address)->toBe('National Highway, Heritage Town, Province 1234')
        ->and($institution->location)->toBe('Heritage Town, Province')
        ->and($institution->contact_number)->toBe('+63 917 123 4567')
        ->and($institution->email)->toBe('info@npsu.edu.ph')
        ->and($institution->website)->toBe('https://npsu.edu.ph');

    $audit = DB::table('audit_events')
        ->where('event_type', 'setup.institution_profile_updated')
        ->latest('id')
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->actor)->toBe((string) $admin->id);
});

test('validation rejects invalid email and website url', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $this->actingAs($admin)
        ->from('/setup/institution')
        ->put('/setup/institution', [
            'name' => 'Valid Name',
            'email' => 'not-an-email',
            'website' => 'not-a-url',
        ])
        ->assertRedirect('/setup/institution')
        ->assertSessionHasErrors(['email', 'website']);
});

test('institutional admin can upload, replace, and delete institutional logo', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    // 1. Upload initial logo
    $initialLogo = UploadedFile::fake()->image('crest.png', 600, 600);
    $this->actingAs($admin)
        ->post('/setup/institution/logo', ['logo' => $initialLogo])
        ->assertRedirect()
        ->assertSessionHas('success');

    $institution = Institution::current()->fresh();
    expect($institution->logo_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists($institution->logo_path))->toBeTrue()
        ->and($institution->logo_mime)->toBe('image/webp')
        ->and($institution->logo_sha256)->not->toBeNull()
        ->and($institution->logo_byte_size)->toBeGreaterThan(0);

    $oldPath = $institution->logo_path;

    // 2. Replace with a new logo
    $replacementLogo = UploadedFile::fake()->image('crest_v2.png', 512, 512);
    $this->actingAs($admin)
        ->post('/setup/institution/logo', ['logo' => $replacementLogo])
        ->assertRedirect()
        ->assertSessionHas('success');

    $institution = $institution->fresh();
    expect($institution->logo_path)->not->toBe($oldPath)
        ->and(Storage::disk('public')->exists($institution->logo_path))->toBeTrue()
        ->and(Storage::disk('public')->exists($oldPath))->toBeFalse(); // Old path purged

    // 3. Delete logo
    $this->actingAs($admin)
        ->delete('/setup/institution/logo')
        ->assertRedirect()
        ->assertSessionHas('success');

    $institution = $institution->fresh();
    expect($institution->logo_path)->toBeNull()
        ->and($institution->logo_sha256)->toBeNull()
        ->and($institution->fallbackMonogram())->toBe('I');
});

test('logo upload rejects non-image and oversized files', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $fakePdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
    $this->actingAs($admin)
        ->from('/setup/institution')
        ->post('/setup/institution/logo', ['logo' => $fakePdf])
        ->assertRedirect('/setup/institution')
        ->assertSessionHasErrors(['logo']);

    $oversized = UploadedFile::fake()->image('huge.png', 4000, 4000)->size(3000); // 3 MB > 2048 KB
    $this->actingAs($admin)
        ->from('/setup/institution')
        ->post('/setup/institution/logo', ['logo' => $oversized])
        ->assertRedirect('/setup/institution')
        ->assertSessionHasErrors(['logo']);
});

test('institutional admin can reset profile to environment defaults', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    // Mutate to custom
    Institution::current()->update([
        'name' => 'Custom Modified Name',
        'short_name' => 'CMN',
    ]);

    $this->actingAs($admin)
        ->post('/setup/institution/reset')
        ->assertRedirect()
        ->assertSessionHas('success');

    $institution = Institution::current()->fresh();
    $config = config('public-site.institution');
    expect($institution->name)->toBe($config['name'])
        ->and($institution->short_name)->toBe($config['short_name']);

    $audit = DB::table('audit_events')
        ->where('event_type', 'setup.institution_profile_reset')
        ->latest('id')
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->actor)->toBe((string) $admin->id);
});

test('public logo streaming route serves binary with etag and handles 304', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    // When no logo, 404
    Institution::current()->update(['logo_path' => null]);
    $this->get('/institution/logo')->assertNotFound();

    // Upload logo
    $this->actingAs($admin)->post('/setup/institution/logo', [
        'logo' => UploadedFile::fake()->image('crest.png', 300, 300),
    ])->assertRedirect();

    $institution = Institution::current()->fresh();
    $etag = '"'.$institution->logo_sha256.'"';

    // Fetch logo publicly
    $response = $this->get('/institution/logo');
    $response->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('ETag', $etag);

    // Conditional GET with If-None-Match returns 304
    $this->withHeader('If-None-Match', $etag)
        ->get('/institution/logo')
        ->assertStatus(304);
});
