<?php

declare(strict_types=1);

use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\Department;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\StaffIdentitySeeder;
use Database\Seeders\StaffPlatformOverhaulDemoSeeder;

it('seeds central staff without academic assignment while retaining academic assignments', function (): void {
    seedE2Foundation();
    (new DepartmentSeeder)->run();
    (new StaffIdentitySeeder)->run();
    (new StaffPlatformOverhaulDemoSeeder)->run();

    $centralEmails = [
        'admin.lead@nexiam.test',
        'registrar.clerk@nexiam.test',
        'registrar.admin@nexiam.test',
        'registrar.support@nexiam.test',
        'guidance.clerk@nexiam.test',
        'guidance.support@nexiam.test',
        'guidance.counselor@nexiam.test',
        'staff.hr@nexiam.test',
        'staff.guidance@nexiam.test',
        'staff.clerk@nexiam.test',
        'staff.regclerk@nexiam.test',
    ];

    foreach ($centralEmails as $email) {
        $profile = User::query()->where('email', $email)->firstOrFail()->staffProfile;
        expect($profile)->toBeInstanceOf(StaffProfile::class)
            ->and(in_array($profile->office, ['admin', 'registrar', 'guidance'], true))->toBeTrue()
            ->and($profile->department_id)->toBeNull()
            ->and($profile->program_codes)->toBe([]);
    }

    $academic = User::query()->where('email', 'staff.proghead@nexiam.test')->firstOrFail()->staffProfile;
    $dean = User::query()->where('email', 'staff.dean@nexiam.test')->firstOrFail()->staffProfile;
    expect($academic->office)->toBe('program')
        ->and($academic->program_codes)->toBe(['BSIT'])
        ->and($dean->office)->toBe('program')
        ->and($dean->department_id)->toBe(Department::query()->where('code', 'CAS')->value('id'))
        ->and($dean->program_codes)->toContain('BSIT', 'BA-EL', 'BA-Psych');

    $enrolled = StaffAuthenticationState::query()->where('user_id', User::query()->where('email', 'staff.hr@nexiam.test')->value('id'))->firstOrFail();
    expect($enrolled->hasConfirmedTwoFactor())->toBeTrue();
});

it('preserves an existing confirmed factor when demo identities are reseeded', function (): void {
    seedE2Foundation();
    (new DepartmentSeeder)->run();
    (new StaffPlatformOverhaulDemoSeeder)->run();

    $user = User::query()->where('email', 'staff.hr@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail();
    $originalSecret = $state->two_factor_secret;
    $originalConfirmation = $state->two_factor_confirmed_at;

    (new StaffPlatformOverhaulDemoSeeder)->run();
    $state->refresh();

    expect($state->two_factor_secret)->toBe($originalSecret)
        ->and($state->two_factor_confirmed_at->equalTo($originalConfirmation))->toBeTrue();
});
