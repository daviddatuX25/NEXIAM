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

test('registrar clerk can view personnel index scoped to own office and manage support', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Personnel/Index')
            ->where('can_full_hr', false)
            ->where('can_manage_personnel', true)
            ->where('can_delegate_ta', false)
            ->where('users.data', fn ($rows) => collect($rows)->every(
                fn ($row) => ($row['office'] ?? '') === 'registrar'
            ))
            ->where('users.data', fn ($rows) => collect($rows)->pluck('id')->doesntContain($guidance->id))
        );
});

test('guidance clerk can view staff page but cannot delegate', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_manage_personnel', true)
            ->where('can_delegate_ta', false)
        );
});

test('counselor can view and manage guidance office with ta delegate', function (): void {
    seedE2Foundation();
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($counselor)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_manage_personnel', true)
            ->where('can_delegate_ta', true)
        );
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

test('hr cannot pick support staff role from permanent checklist', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->post(route('staff.personnel.store'), [
            'name' => 'Bad Support Pick',
            'email' => 'bad.support@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'account_type' => 'permanent',
            'roles' => ['guidance_support_staff'],
            'office' => 'guidance',
        ])
        ->assertSessionHasErrors('roles.0');

    expect(User::query()->where('email', 'bad.support@nexiam.test')->exists())->toBeFalse();
});

test('hr can create support staff via office-derived account type', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->post(route('staff.personnel.store'), [
            'name' => 'Hr Support',
            'email' => 'hr.support@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'account_type' => 'support',
            'office' => 'guidance',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $created = User::query()->where('email', 'hr.support@nexiam.test')->firstOrFail();
    expect($created->hasRole('guidance_support_staff'))->toBeTrue()
        ->and($created->staffProfile?->office)->toBe('guidance');
});

test('registrar admin creates registrar support with office-derived role and coerced office', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('staff.personnel.store'), [
            'name' => 'Reg Support',
            'email' => 'reg.support@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'office' => 'registrar',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $created = User::query()->where('email', 'reg.support@nexiam.test')->firstOrFail();
    expect($created->hasRole('registrar_support_staff'))->toBeTrue()
        ->and($created->staffProfile?->office)->toBe('registrar');

    // Office actors cannot target another office — the office is coerced server-side.
    $this->actingAs($admin)
        ->post(route('staff.personnel.store'), [
            'name' => 'Cross Office',
            'email' => 'cross.office@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'office' => 'guidance',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $coerced = User::query()->where('email', 'cross.office@nexiam.test')->firstOrFail();
    expect($coerced->hasRole('registrar_support_staff'))->toBeTrue()
        ->and($coerced->staffProfile?->office)->toBe('registrar');
});

test('registrar clerk can create registrar support staff', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->get(route('staff.personnel.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_full_hr', false)
            ->where('support_role.name', 'registrar_support_staff')
            ->where('roles', [])
        );

    $this->actingAs($clerk)
        ->post(route('staff.personnel.store'), [
            'name' => 'Clerk Support',
            'email' => 'clerk.support@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $created = User::query()->where('email', 'clerk.support@nexiam.test')->firstOrFail();
    expect($created->hasRole('registrar_support_staff'))->toBeTrue()
        ->and($created->staffProfile?->office)->toBe('registrar');
});

test('guidance clerk can create guidance support staff', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->post(route('staff.personnel.store'), [
            'name' => 'Guide Support',
            'email' => 'guide.support@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $created = User::query()->where('email', 'guide.support@nexiam.test')->firstOrFail();
    expect($created->hasRole('guidance_support_staff'))->toBeTrue()
        ->and($created->staffProfile?->office)->toBe('guidance');
});

test('office actor cannot reassign roles via update', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->put(route('staff.personnel.update', $support), [
            'name' => 'Renamed Support',
            'email' => 'guidance.support@nexiam.test',
            'password' => '',
            'roles' => ['guidance_clerk'],
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $support->refresh();
    expect($support->name)->toBe('Renamed Support')
        ->and($support->hasRole('guidance_support_staff'))->toBeTrue()
        ->and($support->hasRole('guidance_clerk'))->toBeFalse();
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

test('guidance clerk cannot edit or deactivate the guidance counselor (higher rank)', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->get(route('staff.personnel.edit', $counselor))
        ->assertForbidden();

    $this->actingAs($clerk)
        ->post(route('staff.personnel.deactivate', $counselor))
        ->assertSessionHasErrors('deactivate');

    expect($counselor->fresh()->staffProfile?->status)->toBe(StaffProfile::STATUS_ACTIVE);
});

test('guidance clerk cannot manage a peer guidance clerk', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $peer = User::factory()->create(['email' => 'guidance.clerk2@nexiam.test']);
    $peer->syncRoles(['guidance_clerk']);
    StaffProfile::query()->create([
        'user_id' => $peer->id,
        'institution_id' => $clerk->staffProfile->institution_id,
        'office' => 'guidance',
        'program_codes' => ['BSN'],
        'active_cycle_code' => 'SYN-CYCLE-2026',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    $this->actingAs($clerk)
        ->get(route('staff.personnel.edit', $peer))
        ->assertForbidden();

    $this->actingAs($clerk)
        ->post(route('staff.personnel.deactivate', $peer))
        ->assertSessionHasErrors('deactivate');
});

test('guidance clerk can edit and deactivate guidance support staff (lower rank)', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->get(route('staff.personnel.edit', $support))
        ->assertOk();

    $this->actingAs($clerk)
        ->post(route('staff.personnel.deactivate', $support))
        ->assertRedirect(route('staff.personnel.index'));

    expect($support->fresh()->staffProfile?->status)->toBe(StaffProfile::STATUS_INACTIVE);
});

test('guidance clerk cannot reset a support staff password (HR or office lead only)', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->put(route('staff.personnel.update', $support), [
            'name' => 'Renamed Support',
            'email' => 'guidance.support@nexiam.test',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])
        ->assertSessionHasErrors('roles');
});

test('guidance counselor can reset a guidance clerk password (own office, lower rank)', function (): void {
    seedE2Foundation();
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($counselor)
        ->put(route('staff.personnel.update', $clerk), [
            'name' => $clerk->name,
            'email' => 'guidance.clerk@nexiam.test',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $this->post('/logout');

    $this->post('/login', [
        'email' => 'guidance.clerk@nexiam.test',
        'password' => 'NewPass123',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($clerk->fresh());
});

test('registrar clerk cannot edit or deactivate the registrar admin (higher rank)', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $admin = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->get(route('staff.personnel.edit', $admin))
        ->assertForbidden();

    $this->actingAs($clerk)
        ->post(route('staff.personnel.deactivate', $admin))
        ->assertSessionHasErrors('deactivate');
});

test('hr can edit and deactivate an office lead', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($hr)
        ->get(route('staff.personnel.edit', $counselor))
        ->assertOk();

    $this->actingAs($hr)
        ->post(route('staff.personnel.deactivate', $counselor))
        ->assertRedirect(route('staff.personnel.index'));

    expect($counselor->fresh()->staffProfile?->status)->toBe(StaffProfile::STATUS_INACTIVE);
});

test('personnel index marks self row and per-user manageability', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $clerk->id && $row['is_self'] === true && $row['can_manage_user'] === false
            ))
            ->where('users.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $counselor->id && $row['is_self'] === false && $row['can_manage_user'] === false
            ))
            ->where('users.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $support->id && $row['is_self'] === false && $row['can_manage_user'] === true
            ))
        );
});

test('any staff member can open their own profile page', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->get(route('staff.profile'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Profile/Show')
            ->where('user.email', 'guidance.clerk@nexiam.test')
        );
});
