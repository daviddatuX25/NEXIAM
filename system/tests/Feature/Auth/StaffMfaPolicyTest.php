<?php

declare(strict_types=1);

use App\Auth\Support\StaffMfaPolicy;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['ciam.mfa.enabled' => true]);
});
function userWithRole(string $roleName): User
{
    $user = User::factory()->create();
    $role = Role::query()->firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    $user->assignRole($role);

    return $user;
}

test('central administrative roles require MFA', function (string $roleName): void {
    expect(app(StaffMfaPolicy::class)->isMfaRequired(userWithRole($roleName)))->toBeTrue();
})->with([
    'institutional_admin',
    'hr_administrator',
    'registrar_admin',
    'registrar_clerk',
    'registrar_support_staff',
    'guidance_counselor',
    'guidance_clerk',
    'guidance_support_staff',
]);

test('all staff roles including program head require MFA when exemption is removed', function (): void {
    seedE2Foundation();

    $user = userWithRole('program_head');
    $user->staffProfile()->create([
        'institution_id' => Institution::query()->where('code', 'SYN-INST-001')->value('id'),
        'office' => 'program',
    ]);

    expect(app(StaffMfaPolicy::class)->isMfaRequired($user))->toBeTrue();
});

test('role exemption applies only when explicitly configured', function (): void {
    seedE2Foundation();

    config(['ciam.mfa.exempt_roles' => ['program_head']]);

    $user = userWithRole('program_head');
    $user->staffProfile()->create([
        'institution_id' => Institution::query()->where('code', 'SYN-INST-001')->value('id'),
        'office' => 'program',
    ]);

    expect(app(StaffMfaPolicy::class)->isMfaRequired($user))->toBeFalse();
});
test('explicitly forced program head requires MFA', function (): void {
    seedE2Foundation();

    $user = userWithRole('program_head');
    $user->staffProfile()->create([
        'institution_id' => Institution::query()->where('code', 'SYN-INST-001')->value('id'),
        'office' => 'program',
    ]);

    StaffAuthenticationState::query()->create([
        'user_id' => $user->id,
        'mfa_required' => true,
    ]);

    expect(app(StaffMfaPolicy::class)->isMfaRequired($user))->toBeTrue();
});

test('a program office user with an enforced central role requires MFA', function (): void {
    seedE2Foundation();

    $user = userWithRole('institutional_admin');
    $user->staffProfile()->create([
        'institution_id' => Institution::query()->where('code', 'SYN-INST-001')->value('id'),
        'office' => 'program',
    ]);

    expect(app(StaffMfaPolicy::class)->isMfaRequired($user))->toBeTrue();
});

test('staff without a positively identified academic office fail closed', function (): void {
    expect(app(StaffMfaPolicy::class)->isMfaRequired(userWithRole('program_head')))->toBeTrue()
        ->and(app(StaffMfaPolicy::class)->isMfaRequired(User::factory()->create()))->toBeTrue();
});

test('policy result is consistent across application environments', function (): void {
    $user = userWithRole('institutional_admin');
    $policy = app(StaffMfaPolicy::class);
    $originalEnvironment = app()->environment();

    try {
        app()->detectEnvironment(fn (): string => 'local');
        $localResult = $policy->isMfaRequired($user);

        app()->detectEnvironment(fn (): string => 'testing');
        $testingResult = $policy->isMfaRequired($user);

        app()->detectEnvironment(fn (): string => 'production');
        $productionResult = $policy->isMfaRequired($user);
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnvironment);
    }

    expect($localResult)->toBeTrue()
        ->and($testingResult)->toBeTrue()
        ->and($productionResult)->toBeTrue();
});

test('explicit per-account mfa_required and mfa_exempt flags override global default policy', function (): void {
    seedE2Foundation();
    $policy = app(StaffMfaPolicy::class);
    $user = userWithRole('guidance_counselor');
    $user->staffProfile()->create([
        'institution_id' => Institution::query()->where('code', 'SYN-INST-001')->value('id'),
        'office' => 'guidance',
    ]);
    $state = StaffAuthenticationState::query()->create([
        'user_id' => $user->id,
        'mfa_required' => true,
        'mfa_exempt' => false,
    ]);

    config(['ciam.mfa.enabled' => false]);
    expect($policy->isMfaRequired($user->fresh()))->toBeTrue();

    config(['ciam.mfa.enabled' => true]);
    $state->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();
    expect($policy->isMfaRequired($user->fresh()))->toBeFalse();
});
