<?php

use App\Models\Foundation\AuditEvent;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Actions\CreateStaffAccount;
use App\Staffing\Actions\DeactivateStaffAccount;

test('seed creates configurable default hr account', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    expect($hr->hasRole('hr_administrator'))->toBeTrue()
        ->and($hr->staffProfile?->isActive())->toBeTrue()
        ->and($hr->name)->toBe(config('seed.hr.name'));
});

test('hr can open personnel index', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Personnel/Index')
            ->has('users.data')
            ->where('can_full_hr', true)
        );
});

test('registrar clerk is denied personnel access', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->get(route('staff.personnel.index'))
        ->assertForbidden();
});

test('hr can create staff within ceiling', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->post(route('staff.personnel.store'), [
            'name' => 'New Clerk',
            'email' => 'new.clerk@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'roles' => ['registrar_clerk'],
            'office' => 'registrar',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $created = User::query()->where('email', 'new.clerk@nexiam.test')->firstOrFail();
    expect($created->hasRole('registrar_clerk'))->toBeTrue()
        ->and($created->staffProfile?->status)->toBe(StaffProfile::STATUS_ACTIVE);

    expect(AuditEvent::query()->where('event_type', 'hr.staff.created')->exists())->toBeTrue();
});

test('hr cannot assign forbidden role helpers via api', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->post(route('staff.personnel.store'), [
            'name' => 'Bad Role',
            'email' => 'bad.role@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'roles' => ['proctor'],
            'office' => 'guidance',
        ])
        ->assertSessionHasErrors('roles.0');

    expect(User::query()->where('email', 'bad.role@nexiam.test')->exists())->toBeFalse();
});

test('registrar admin can create registrar support only', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('staff.personnel.store'), [
            'name' => 'Reg Support',
            'email' => 'reg.support@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'roles' => ['registrar_support_staff'],
            'office' => 'registrar',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $this->actingAs($admin)
        ->post(route('staff.personnel.store'), [
            'name' => 'Cross Office',
            'email' => 'cross.office@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'roles' => ['guidance_counselor'],
            'office' => 'guidance',
        ])
        ->assertSessionHasErrors('roles.0');
});

test('deactivate blocks staff login and activate restores', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($hr)
        ->post(route('staff.personnel.deactivate', $clerk))
        ->assertRedirect(route('staff.personnel.index'));

    expect($clerk->fresh()->staffProfile?->status)->toBe(StaffProfile::STATUS_INACTIVE);

    $this->post('/logout');

    $this->post('/login', [
        'email' => 'registrar.clerk@nexiam.test',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->actingAs($hr)
        ->post(route('staff.personnel.activate', $clerk))
        ->assertRedirect(route('staff.personnel.index'));

    $this->post('/logout');
    $this->assertGuest();

    $this->post('/login', [
        'email' => 'registrar.clerk@nexiam.test',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($clerk->fresh());
});

test('cannot deactivate own account', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->post(route('staff.personnel.deactivate', $hr))
        ->assertSessionHasErrors('deactivate');
});

test('create staff account command enforces ceiling', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    expect(fn () => app(CreateStaffAccount::class)->handle(
        $admin,
        'Nope',
        'nope@nexiam.test',
        'Password1',
        ['hr_administrator'],
        'hr',
        (int) $admin->actorScope()->institutionId,
    ))->toThrow(RuntimeException::class);
});

test('password confirmation required on create', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->post(route('staff.personnel.store'), [
            'name' => 'No Confirm',
            'email' => 'noconfirm@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Different1',
            'roles' => ['registrar_clerk'],
            'office' => 'registrar',
        ])
        ->assertSessionHasErrors('password');
});
