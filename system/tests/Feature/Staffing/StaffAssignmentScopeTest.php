<?php

use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Staffing\Actions\CreateStaffAccount;
use App\Staffing\Actions\UpdateStaffAssignment;
use Illuminate\Support\Str;

function authorizeStaffSession(User $user): User
{
    $user->authenticationState()->updateOrCreate(
        ['user_id' => $user->id],
        [
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => 'SECRET',
            'two_factor_generation' => (string) Str::uuid(),
            'session_version' => 1,
            'must_change_password' => false,
        ]
    );

    return $user;
}

test('central office account creation discards academic assignments', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $program = Program::query()->where('institution_id', $hr->actorScope()->institutionId)
        ->where('is_active', true)
        ->firstOrFail();

    $created = app(CreateStaffAccount::class)->handle(
        $hr,
        'Central Office Staff',
        'central-created@nexiam.test',
        ['institutional_admin'],
        'admin',
        (int) $hr->actorScope()->institutionId,
        [$program->code],
    );

    expect($created->staffProfile?->department_id)->toBeNull()
        ->and($created->staffProfile?->program_codes)->toBe([]);
});

test('central office account update clears department and program assignments', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $target = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('institution_id', $target->staffProfile->institution_id)
        ->where('is_active', true)
        ->firstOrFail();
    $department = Department::query()->where('institution_id', $target->staffProfile->institution_id)
        ->where('is_active', true)
        ->firstOrFail();
    $target->staffProfile->update([
        'department_id' => $department->id,
        'program_codes' => [$program->code],
    ]);

    $updated = app(UpdateStaffAssignment::class)->handle(
        $hr,
        $target,
        $target->name,
        $target->email,
        'admin',
        [$program->code],
    );

    expect($updated->staffProfile?->department_id)->toBeNull()
        ->and($updated->staffProfile?->program_codes)->toBe([]);
});

test('central office actor scope excludes assigned and dean-derived academic jurisdiction', function (): void {
    seedE2Foundation();

    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $program = Program::query()->where('institution_id', $admin->staffProfile->institution_id)
        ->where('is_active', true)
        ->firstOrFail();
    $department = Department::query()->where('institution_id', $admin->staffProfile->institution_id)
        ->where('is_active', true)
        ->firstOrFail();

    DB::table('staff_profiles')->where('user_id', $admin->id)->update([
        'department_id' => $department->id,
        'program_codes' => json_encode([$program->code], JSON_THROW_ON_ERROR),
    ]);
    $department->update(['dean_user_id' => $admin->id]);

    $scope = $admin->fresh()->actorScope();

    expect($scope->programCodes)->toBe([])
        ->and($scope->departmentId)->toBeNull();
});

test('central-office create request discards submitted program codes', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $program = Program::query()->where('institution_id', $hr->actorScope()->institutionId)
        ->where('is_active', true)
        ->firstOrFail();

    $this->actingAs(authorizeStaffSession($hr))
        ->withSession(['auth.surface' => 'staff', 'auth.session_version' => 1])
        ->post(route('staff.personnel.store'), [
            'name' => 'Registrar Staff',
            'email' => 'registrar-created@nexiam.test',
            'roles' => ['registrar_clerk'],
            'office' => 'registrar',
            'program_codes' => [$program->code],
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $created = User::query()->where('email', 'registrar-created@nexiam.test')->firstOrFail();
    expect($created->staffProfile?->program_codes)->toBe([])
        ->and($created->staffProfile?->department_id)->toBeNull();
});

test('support staff update succeeds when roles are omitted', function (): void {
    seedE2Foundation();

    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();

    $this->actingAs(authorizeStaffSession($clerk))
        ->withSession(['auth.surface' => 'staff', 'auth.session_version' => 1])
        ->put(route('staff.personnel.update', $support), [
            'name' => 'Updated Guidance Support',
            'email' => $support->email,
            'password' => '',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    expect($support->fresh()->name)->toBe('Updated Guidance Support')
        ->and($support->fresh()->hasRole('guidance_support_staff'))->toBeTrue();
});

test('central-office update request discards submitted program codes', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $target = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('institution_id', $target->staffProfile->institution_id)
        ->where('is_active', true)
        ->firstOrFail();
    $department = Department::query()->where('institution_id', $target->staffProfile->institution_id)
        ->where('is_active', true)
        ->firstOrFail();
    $target->staffProfile->update([
        'department_id' => $department->id,
        'program_codes' => [$program->code],
    ]);

    $this->actingAs(authorizeStaffSession($hr))
        ->withSession(['auth.surface' => 'staff', 'auth.session_version' => 1])
        ->put(route('staff.personnel.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '',
            'office' => 'admin',
            'program_codes' => [$program->code],
        ])
        ->assertRedirect(route('staff.personnel.index'));

    expect($target->fresh()->staffProfile?->department_id)->toBeNull()
        ->and($target->fresh()->staffProfile?->program_codes)->toBe([]);
});

test('support role follows the selected Registrar or Guidance office on HR edits', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();

    $this->actingAs(authorizeStaffSession($hr))
        ->withSession(['auth.surface' => 'staff', 'auth.session_version' => 1])
        ->put(route('staff.personnel.update', $support), [
            'name' => $support->name,
            'email' => $support->email,
            'password' => '',
            'office' => 'registrar',
        ])
        ->assertRedirect(route('staff.personnel.index'));

    $support->refresh();
    expect($support->staffProfile?->office)->toBe('registrar')
        ->and($support->hasRole('registrar_support_staff'))->toBeTrue()
        ->and($support->hasRole('guidance_support_staff'))->toBeFalse();
});
