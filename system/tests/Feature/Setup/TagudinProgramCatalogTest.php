<?php

use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Communications\ProgramPublicProfile;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ProgramPublicProfileSeeder;
use Database\Seeders\StaffIdentitySeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('seeds the verified Tagudin undergraduate catalog without retiring historical rows', function (): void {
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $catalog = app(TagudinUndergraduateCatalog::class);
    $canonicalCodes = array_column($catalog->programs(), 'code');
    $retiredCodes = $catalog->retiredCodes();
    expect($retiredCodes)->toBe(['BSN', 'BSPSY', 'BSCS', 'BSCE', 'BAT', 'BSHM'])
        ->and($catalog->retirementProvenance()['accessed_on'])->toBe('2026-10-05');

    $this->get(route('apply.form'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admission/Apply')
            ->has('programs', 13)
            ->where('programs', fn ($programs): bool => collect($programs)->pluck('code')->intersect($retiredCodes)->isEmpty())
        );

    expect(Program::query()->where('institution_id', $institution->id)->whereIn('code', $canonicalCodes)->count())->toBe(13)
        ->and(Program::query()->where('institution_id', $institution->id)->whereIn('code', $retiredCodes)->exists())->toBeFalse();

    foreach ($catalog->programs() as $program) {
        $stored = Program::query()
            ->where('institution_id', $institution->id)
            ->where('code', $program['code'])
            ->firstOrFail();

        expect($stored->name)->toBe($program['name'])
            ->and($stored->is_admission_offered)->toBeTrue();
    }

    $historical = [];
    foreach ($retiredCodes as $code) {
        $historical[$code] = Program::query()->create([
            'institution_id' => $institution->id,
            'code' => $code,
            'name' => 'Synthetic historical reference '.$code,
            'is_active' => true,
            'is_admission_offered' => true,
        ]);
    }

    (new DepartmentSeeder)->run();

    foreach ($historical as $code => $program) {
        $preserved = Program::query()->findOrFail($program->id);
        expect($preserved->code)->toBe($code)
            ->and($preserved->name)->toBe('Synthetic historical reference '.$code)
            ->and($preserved->is_active)->toBeTrue()
            ->and($preserved->is_admission_offered)->toBeFalse();
    }
});

it('keeps staff passwords and the configured Tagudin assignments on seed reruns', function (): void {
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());

    $head = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $head->forceFill(['password' => Hash::make('existing-password')])->save();

    (new StaffIdentitySeeder)->run();

    expect(Hash::check('existing-password', $head->fresh()->password))->toBeTrue()
        ->and($head->staffProfile()->firstOrFail()->program_codes)->toBe(['BSIT']);
});

it('places BPA under CBME and seeds published public program descriptions with empty photos', function (): void {
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    (new ProgramPublicProfileSeeder)->run();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $bpa = Program::query()->with('department')->where('institution_id', $institution->id)->where('code', 'BPA')->firstOrFail();
    expect($bpa->department?->code)->toBe('CBME');

    $catalog = app(TagudinUndergraduateCatalog::class);
    foreach ($catalog->programs() as $entry) {
        $program = Program::query()->where('institution_id', $institution->id)->where('code', $entry['code'])->firstOrFail();
        $profile = ProgramPublicProfile::query()
            ->with('publishedRevision')
            ->where('program_id', $program->id)
            ->firstOrFail();

        expect($profile->publishedRevision)->not->toBeNull()
            ->and($profile->publishedRevision->short_description)->toBe($entry['summary'])
            ->and($profile->publishedRevision->description)->toBe($entry['description'])
            ->and($profile->publishedRevision->photos)->toBe([]);
    }
});
