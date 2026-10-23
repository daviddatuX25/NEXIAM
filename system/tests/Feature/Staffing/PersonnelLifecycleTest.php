<?php

use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Actions\CreateStaffAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

test('seed creates configurable default hr account', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    expect($hr->hasRole('hr_administrator'))->toBeTrue()
        ->and($hr->staffProfile?->isActive())->toBeTrue()
        ->and($hr->name)->toBe(config('seed.hr.name'));
});

test('hr sees canonical office options and avatar and office label projections', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $clerk->authenticationState()->update([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
        'mfa_required' => false,
    ]);
    $this->actingAs($hr)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Personnel/Index')
            ->where('offices', [
                ['value' => 'admin', 'label' => 'Admin Office'],
                ['value' => 'registrar', 'label' => 'Registrar Office'],
                ['value' => 'guidance', 'label' => 'Guidance Office'],
                ['value' => 'program', 'label' => 'Academic Programs'],
            ])
            ->where('users.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $clerk->id
                    && $row['office_label'] === 'Guidance Office'
                    && $row['can_manage_user'] === true
                    && $row['can_issue_voucher'] === true
                    && $row['mfa_status'] === (config('ciam.mfa.enabled') ? 'pending' : 'exempt')
                    && $row['can_require_mfa'] === true
                    && $row['can_reset_mfa'] === false
                    && $row['avatar'] === ['type' => 'initials', 'preset' => null, 'url' => null]
                    && ! array_key_exists('avatar_path', $row)
            ))
        );
});

test('personnel index hides academic assignments for central offices and ignores their academic filters', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $program = Program::query()
        ->where('institution_id', $hr->actorScope()->institutionId)
        ->where('code', 'BSIT')
        ->where('is_active', true)
        ->with('department')
        ->firstOrFail();

    $this->actingAs($hr)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($rows) => collect($rows)
                ->filter(fn ($row) => in_array($row['office'], ['admin', 'registrar', 'guidance'], true))
                ->isNotEmpty()
                && collect($rows)
                    ->filter(fn ($row) => in_array($row['office'], ['admin', 'registrar', 'guidance'], true))
                    ->every(fn ($row) => $row['department_code'] === null && $row['program_codes'] === []))
        );

    $this->actingAs($hr)
        ->get(route('staff.personnel.index', [
            'office' => 'registrar',
            'department' => $program->department?->code,
            'program' => $program->code,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.department', null)
            ->where('filters.program', null)
            ->where('users.data', fn ($rows) => collect($rows)->isNotEmpty()
                && collect($rows)->every(fn ($row) => $row['office'] === 'registrar'
                    && $row['department_code'] === null
                    && $row['program_codes'] === []))
        );
});

test('ordinary personnel actor sees only own office option and valid office filters narrow results', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->get(route('staff.personnel.index', ['office' => 'guidance']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('offices', [['value' => 'registrar', 'label' => 'Registrar Office']])
            ->where('filters.office', 'guidance')
            ->where('users.data', [])
        );

    $this->actingAs($clerk)
        ->get(route('staff.personnel.index', ['office' => 'registrar']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.office', 'registrar')
            ->where('users.data', fn ($rows) => collect($rows)->isNotEmpty()
                && collect($rows)->every(fn ($row) => $row['office'] === 'registrar'))
        );

    $this->actingAs($clerk)
        ->get(route('staff.personnel.index', ['office' => ['guidance']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.office', null)
            ->where('users.data', fn ($rows) => collect($rows)->every(
                fn ($row) => $row['office'] === 'registrar'
            ))
        );

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $this->actingAs($hr)
        ->get(route('staff.personnel.index', ['office' => 'guidance']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.office', 'guidance')
            ->where('users.data', fn ($rows) => collect($rows)->isNotEmpty()
                && collect($rows)->every(fn ($row) => $row['office'] === 'guidance'))
        );
});

test('personnel index sorts office codes in SQL in both directions', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    foreach (['asc', 'desc'] as $direction) {
        $this->actingAs($hr)
            ->get(route('staff.personnel.index', ['sort' => 'office', 'dir' => $direction]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.sort', 'office')
                ->where('filters.dir', $direction)
                ->where('users.data', function ($rows) use ($direction): bool {
                    $offices = collect($rows)->pluck('office')->filter(fn ($office) => $office !== null)->values()->all();
                    $expected = $offices;
                    sort($expected);
                    if ($direction === 'desc') {
                        $expected = array_reverse($expected);
                    }

                    return $offices === $expected;
                })
            );
    }
});

test('personnel index filters by department and program and sorts by program', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $program = Program::query()
        ->where('institution_id', $hr->actorScope()->institutionId)
        ->where('code', 'BSIT')
        ->where('is_active', true)
        ->with('department')
        ->firstOrFail();
    $departmentCode = $program->department?->code;
    $departmentName = $program->department?->name;

    $this->actingAs($hr)
        ->get(route('staff.personnel.index', ['program' => $program->code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('departments', fn ($options) => collect($options)->contains([
                'value' => $departmentCode,
                'label' => "{$departmentCode} — {$departmentName}",
            ]))
            ->where('programs', fn ($options) => collect($options)->contains([
                'value' => $program->code,
                'label' => "{$program->code} — {$program->name}",
                'department_code' => $departmentCode,
            ]))
            ->where('filters.program', $program->code)
            ->where('filters.department', null)
            ->where('users.data', fn ($rows) => collect($rows)->isNotEmpty()
                && collect($rows)->every(fn ($row) => in_array($program->code, $row['program_codes'], true)
                    && $row['department_code'] === $departmentCode))
        );

    $this->actingAs($hr)
        ->get(route('staff.personnel.index', ['department' => $departmentCode]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.department', $departmentCode)
            ->where('filters.program', null)
            ->where('users.data', fn ($rows) => collect($rows)->isNotEmpty()
                && collect($rows)->every(fn ($row) => $row['department_code'] === $departmentCode))
        );

    $this->actingAs($hr)
        ->get(route('staff.personnel.index', ['sort' => 'program']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'program')
            ->where('users.data', function ($rows): bool {
                $programCodes = collect($rows)
                    ->map(fn ($row) => json_encode($row['program_codes'], JSON_THROW_ON_ERROR))
                    ->all();
                $expected = $programCodes;
                sort($expected);

                return $programCodes === $expected;
            })
        );
    $this->actingAs($hr)
        ->get(route('staff.personnel.index', ['department' => 'UNKNOWN', 'program' => 'UNKNOWN']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.department', null)
            ->where('filters.program', null)
        );

});

test('personnel index projects saved preset and uploaded avatars without exposing media paths', function (): void {
    seedE2Foundation();
    Storage::fake('public');
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();
    Storage::disk('public')->put('staff-avatars/personnel-test.png', 'avatar-bytes');
    StaffProfile::query()->where('user_id', $clerk->id)->update([
        'avatar_type' => 'preset',
        'avatar_preset' => 'guidance_clerk',
    ]);
    StaffProfile::query()->where('user_id', $support->id)->update([
        'avatar_type' => 'upload',
        'avatar_preset' => null,
        'avatar_path' => 'staff-avatars/personnel-test.png',
    ]);

    $this->actingAs($hr)
        ->get(route('staff.personnel.index', ['office' => 'guidance']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $clerk->id
                    && $row['avatar'] === ['type' => 'preset', 'preset' => 'guidance_clerk', 'url' => null]
                    && ! array_key_exists('avatar_path', $row)
            ))
            ->where('users.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $support->id
                    && $row['avatar'] === [
                        'type' => 'upload',
                        'preset' => null,
                        'url' => Storage::disk('public')->url('staff-avatars/personnel-test.png'),
                    ]
                    && ! array_key_exists('avatar_path', $row)
            ))
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
            'roles' => ['registrar_clerk'],
            'office' => 'registrar',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $created = User::query()->where('email', 'new.clerk@nexiam.test')->firstOrFail();
    expect($created->hasRole('registrar_clerk'))->toBeTrue()
        ->and($created->staffProfile?->status)->toBe(StaffProfile::STATUS_ACTIVE)
        ->and($created->authenticationState->activation_completed_at)->toBeNull()
        ->and(Hash::check('Password1', $created->password))->toBeFalse();

    expect(AuditEvent::query()->where('event_type', 'hr.staff.created')->exists())->toBeTrue();
});

test('hr cannot assign forbidden role helpers via api', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->post(route('staff.personnel.store'), [
            'name' => 'Bad Role',
            'email' => 'bad.role@nexiam.test',
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
            'account_type' => 'support',
            'office' => 'guidance',
        ])
        ->assertRedirect(route('staff.personnel.index'));
    $created = User::query()->where('email', 'hr.support@nexiam.test')->firstOrFail();

    expect($created->hasRole('guidance_support_staff'))->toBeTrue()
        ->and($created->staffProfile?->office)->toBe('guidance')
        ->and($created->authenticationState->activation_completed_at)->toBeNull()
        ->and(Hash::check('Password1', $created->password))->toBeFalse();
});

test('registrar admin creates registrar support with office-derived role and coerced office', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('staff.personnel.store'), [
            'name' => 'Reg Support',
            'email' => 'reg.support@nexiam.test',
            'office' => 'registrar',
        ])
        ->assertRedirect(route('staff.personnel.index'));
    $created = User::query()->where('email', 'reg.support@nexiam.test')->firstOrFail();

    expect($created->hasRole('registrar_support_staff'))->toBeTrue()
        ->and($created->staffProfile?->office)->toBe('registrar')
        ->and($created->authenticationState->activation_completed_at)->toBeNull()
        ->and(Hash::check('Password1', $created->password))->toBeFalse();

    // Office actors cannot target another office — the office is coerced server-side.
    $this->actingAs($admin)
        ->post(route('staff.personnel.store'), [
            'name' => 'Cross Office',
            'email' => 'cross.office@nexiam.test',
            'office' => 'guidance',
        ])
        ->assertRedirect(route('staff.personnel.index'));
    $coerced = User::query()->where('email', 'cross.office@nexiam.test')->firstOrFail();

    expect($coerced->hasRole('registrar_support_staff'))->toBeTrue()
        ->and($coerced->staffProfile?->office)->toBe('registrar')
        ->and($coerced->authenticationState->activation_completed_at)->toBeNull()
        ->and(Hash::check('Password1', $coerced->password))->toBeFalse();
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
        ])
        ->assertRedirect(route('staff.personnel.index'));
    $created = User::query()->where('email', 'clerk.support@nexiam.test')->firstOrFail();

    expect($created->hasRole('registrar_support_staff'))->toBeTrue()
        ->and($created->staffProfile?->office)->toBe('registrar')
        ->and($created->authenticationState->activation_completed_at)->toBeNull()
        ->and(Hash::check('Password1', $created->password))->toBeFalse();
});

test('guidance clerk can create guidance support staff', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->post(route('staff.personnel.store'), [
            'name' => 'Guide Support',
            'email' => 'guide.support@nexiam.test',
        ])
        ->assertRedirect(route('staff.personnel.index'));
    $created = User::query()->where('email', 'guide.support@nexiam.test')->firstOrFail();

    expect($created->hasRole('guidance_support_staff'))->toBeTrue()
        ->and($created->staffProfile?->office)->toBe('guidance')
        ->and($created->authenticationState->activation_completed_at)->toBeNull()
        ->and(Hash::check('Password1', $created->password))->toBeFalse();
});

test('office actor cannot reassign roles via update', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)
        ->put(route('staff.personnel.update', $support), [
            'name' => 'Renamed Support',
            'email' => 'guidance.support@nexiam.test',
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
    $clerk->authenticationState->forceFill([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
    ])->save();

    $this->actingAs($hr)
        ->post(route('staff.personnel.deactivate', $clerk))
        ->assertRedirect(route('staff.personnel.index'));

    expect($clerk->fresh()->staffProfile?->status)->toBe(StaffProfile::STATUS_INACTIVE);

    Auth::guard('web')->logout();
    $this->flushSession();

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
    Auth::guard('web')->logout();
    $this->flushSession();

    $loginRes = $this->post('/login', [
        'email' => 'registrar.clerk@nexiam.test',
        'password' => 'password',
    ]);
    if (config('ciam.mfa.enabled')) {
        $loginRes->assertRedirect('/two-factor-challenge');
        $secret = $clerk->fresh()->authenticationState?->two_factor_secret;
        $otp = (new Google2FA)->getCurrentOtp((string) $secret);
        $this->post('/two-factor-challenge', ['code' => $otp])->assertRedirect('/staff');
    } else {
        $loginRes->assertRedirect('/staff');
    }
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
        ['hr_administrator'],
        'admin',
        (int) $admin->actorScope()->institutionId,
    ))->toThrow(RuntimeException::class);
});

test('staff creation prohibits a caller-supplied password', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->post(route('staff.personnel.store'), [
            'name' => 'Password Injection Attempt',
            'email' => 'noconfirm@nexiam.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'roles' => ['registrar_clerk'],
            'office' => 'registrar',
        ])
        ->assertSessionHasErrors('password');

    expect(User::query()->where('email', 'noconfirm@nexiam.test')->exists())->toBeFalse();
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

test('HR support edit derives the office role when the roles field is omitted', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();

    $this->actingAs($hr)
        ->put(route('staff.personnel.update', $support), [
            'name' => $support->name,
            'email' => $support->email,
            'office' => 'registrar',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    expect($support->fresh()->staffProfile?->office)->toBe('registrar')
        ->and($support->fresh()->getRoleNames()->all())->toBe(['registrar_support_staff']);
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

test('directory updates prohibit password injection even for authorized managers', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $support = $clerk;
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $password = $clerk->password;

    $this->actingAs($counselor)
        ->put(route('staff.personnel.update', $support), [
            'name' => 'Renamed Support',
            'email' => 'guidance.support@nexiam.test',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])
        ->assertSessionHasErrors('password');

    expect($clerk->fresh()->password)->toBe($password);
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
