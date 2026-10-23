<?php

declare(strict_types=1);

use App\Authorization\CapabilityCatalog;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Actions\CreateStaffAccount;
use App\Staffing\Actions\DeactivateStaffAccount;
use App\Staffing\Actions\DisableStaffMfa;
use App\Staffing\Actions\RevokeStaffRole;
use App\Staffing\Actions\SyncStaffRoles;
use App\Staffing\Actions\TransitionStaffCycleDisposition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

function createUsableHrForCycleDispositionTest(User $actor, string $email): User
{
    $institutionId = (int) $actor->staffProfile->institution_id;
    $target = app(CreateStaffAccount::class)->handle(
        $actor,
        'Second Usable HR Administrator',
        $email,
        ['hr_administrator'],
        'admin',
        $institutionId,
    );
    $target->forceFill(['password' => Hash::make('UsableTargetPass!42')])->save();

    StaffAuthenticationState::query()->where('user_id', $target->id)->firstOrFail()->forceFill([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
    ])->save();

    return $target->fresh(['staffProfile', 'authenticationState', 'roles']);
}
function makeOnlyUsableHrForCycleDispositionTest(User $target): void
{
    $otherHrIds = User::role('hr_administrator')->where('id', '!=', $target->id)->pluck('id');

    StaffAuthenticationState::query()->whereIn('user_id', $otherHrIds)->update([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
}

test('HR can retain, rotate, and archive staff across an academic cycle rollover', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $head = User::query()->where('email', 'bsit.head@nexiam.test')->firstOrFail();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();

    $institutionId = (int) $hr->staffProfile->institution_id;
    $targetCycle = AcademicCycle::query()->updateOrCreate(
        ['code' => 'TARGET-CYCLE-2027', 'institution_id' => $institutionId],
        [
            'name' => '2027-2028 1st Semester',
            'school_year' => '2027-2028',
            'semester' => '1',
            'opens_on' => '2027-08-01',
            'closes_on' => '2027-12-31',
            'is_active' => false,
        ]
    );

    $action = app(TransitionStaffCycleDisposition::class);
    $result = $action->handle($hr, $targetCycle->code, [
        ['user_id' => $clerk->id, 'disposition' => 'retain'],
        ['user_id' => $head->id, 'disposition' => 'rotate', 'program_codes' => ['BSIT', 'BSMath']],
        ['user_id' => $support->id, 'disposition' => 'archive'],
    ]);

    expect($result['retained'])->toBe(1)
        ->and($result['rotated'])->toBe(1)
        ->and($result['archived'])->toBe(1);

    $clerk->refresh();
    expect($clerk->staffProfile->active_cycle_code)->toBe('TARGET-CYCLE-2027')
        ->and($clerk->staffProfile->status)->toBe(StaffProfile::STATUS_ACTIVE);

    $head->refresh();
    expect($head->staffProfile->active_cycle_code)->toBe('TARGET-CYCLE-2027')
        ->and($head->staffProfile->program_codes)->toContain('BSMath');

    $support->refresh();
    expect($support->staffProfile->status)->toBe(StaffProfile::STATUS_INACTIVE)
        ->and($support->staffProfile->deactivated_at)->not->toBeNull();

    expect(AuditEvent::query()->where('event_type', 'staff.cycle_disposition.retained')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('event_type', 'staff.cycle_disposition.rotated')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('event_type', 'staff.cycle_disposition.archived')->exists())->toBeTrue();
});

test('cycle rollover rejects self-archive with a forbidden action error', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $institutionId = (int) $hr->staffProfile->institution_id;
    $targetCycle = AcademicCycle::query()->where('institution_id', $institutionId)->firstOrFail();

    expect(fn () => app(TransitionStaffCycleDisposition::class)->handle($hr, $targetCycle->code, [
        ['user_id' => $hr->id, 'disposition' => 'archive'],
    ]))->toThrow(AuthorizationException::class, 'Cannot archive your own account during cycle rollover.');
});

test('cycle rollover checks current GF-21 authority instead of a stale loaded actor grant', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staleActor = $hr->fresh(['roles', 'permissions', 'staffProfile']);
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();

    $hr->removeRole('hr_administrator');
    $hr->revokePermissionTo(CapabilityCatalog::HR_STAFF_MANAGE);
    $hr->revokePermissionTo(CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE);
    expect($staleActor->hasRole('hr_administrator'))->toBeTrue();

    expect(fn () => app(TransitionStaffCycleDisposition::class)->handle($staleActor, $cycle->code, [
        ['user_id' => $clerk->id, 'disposition' => 'retain'],
    ]))->toThrow(AuthorizationException::class, 'Actor personnel authority has been revoked.');
    expect(fn () => app(DeactivateStaffAccount::class)->handle($staleActor, $clerk))
        ->toThrow(RuntimeException::class, 'Actor personnel authority has been revoked.');
});

test('cycle rollover rejects an archived actor despite a stale active profile relation', function () {
    seedE2Foundation();

    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staleActor = $actor->fresh(['staffProfile']);
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $actor->staffProfile->institution_id)->firstOrFail();
    StaffProfile::query()->where('user_id', $actor->id)->update([
        'status' => StaffProfile::STATUS_INACTIVE,
        'deactivated_at' => now(),
    ]);

    expect($staleActor->staffProfile->isActive())->toBeTrue();
    expect(fn () => app(TransitionStaffCycleDisposition::class)->handle($staleActor, $cycle->code, [
        ['user_id' => $clerk->id, 'disposition' => 'retain'],
    ]))->toThrow(AuthorizationException::class, 'Actor is no longer active in this institution.');
});
test('HR may retain their own active account without archiving access', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $targetCycle = AcademicCycle::query()
        ->where('institution_id', $hr->staffProfile->institution_id)
        ->firstOrFail();

    $result = app(TransitionStaffCycleDisposition::class)->handle($hr, $targetCycle->code, [
        ['user_id' => $hr->id, 'disposition' => 'retain'],
    ]);

    expect($result['retained'])->toBe(1)
        ->and($hr->fresh()->staffProfile?->isActive())->toBeTrue()
        ->and($hr->fresh()->staffProfile?->active_cycle_code)->toBe($targetCycle->code);
});

test('cycle rollover protects the last usable HR account independently of self-archive', function () {
    seedE2Foundation();

    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $target = createUsableHrForCycleDispositionTest($actor, 'cycle-last-usable-hr@nexiam.test');
    makeOnlyUsableHrForCycleDispositionTest($target);
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $oldClerkCycle = $clerk->staffProfile->active_cycle_code;
    $cycle = AcademicCycle::query()->where('institution_id', $actor->staffProfile->institution_id)->firstOrFail();

    expect(fn () => app(TransitionStaffCycleDisposition::class)->handle($actor, $cycle->code, [
        ['user_id' => $clerk->id, 'disposition' => 'retain'],
        ['user_id' => $target->id, 'disposition' => 'archive'],
    ]))->toThrow(ConflictHttpException::class, 'at least one active HR administrator');

    expect($target->fresh()->staffProfile?->status)->toBe(StaffProfile::STATUS_ACTIVE)
        ->and($clerk->fresh()->staffProfile?->active_cycle_code)->toBe($oldClerkCycle);
});

test('deactivation shares the institution HR safety invariant', function () {
    seedE2Foundation();

    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $target = createUsableHrForCycleDispositionTest($actor, 'deactivate-last-usable-hr@nexiam.test');
    makeOnlyUsableHrForCycleDispositionTest($target);

    expect(fn () => app(DeactivateStaffAccount::class)->handle($actor, $target))
        ->toThrow(RuntimeException::class, 'at least one active HR administrator with confirmed multi-factor authentication');

    expect($target->fresh()->staffProfile?->status)->toBe(StaffProfile::STATUS_ACTIVE);
});

test('revoking an HR role shares the institution usable-HR invariant', function () {
    seedE2Foundation();

    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $target = createUsableHrForCycleDispositionTest($actor, 'revoke-last-usable-hr@nexiam.test');
    makeOnlyUsableHrForCycleDispositionTest($target);

    expect(fn () => app(RevokeStaffRole::class)->handle($actor, $target, 'hr_administrator'))
        ->toThrow(RuntimeException::class, 'last usable HR administrator');

    expect($target->fresh(['roles'])->hasRole('hr_administrator'))->toBeTrue();
});

test('syncing roles cannot remove the last usable HR administrator', function () {
    seedE2Foundation();

    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $target = createUsableHrForCycleDispositionTest($actor, 'sync-last-usable-hr@nexiam.test');
    makeOnlyUsableHrForCycleDispositionTest($target);

    expect(fn () => app(SyncStaffRoles::class)->handle($actor, $target, ['registrar_clerk']))
        ->toThrow(RuntimeException::class, 'last usable HR administrator');

    expect($target->fresh(['roles'])->hasRole('hr_administrator'))->toBeTrue();
});

test('disabling MFA cannot remove the last usable HR route', function () {
    seedE2Foundation();

    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $target = createUsableHrForCycleDispositionTest($actor, 'disable-last-usable-hr@nexiam.test');
    makeOnlyUsableHrForCycleDispositionTest($target);

    expect(fn () => app(DisableStaffMfa::class)->handle($actor, $target))
        ->toThrow(ConflictHttpException::class, 'at least one active HR administrator with confirmed multi-factor authentication');

    expect($target->fresh('authenticationState')->authenticationState?->hasConfirmedTwoFactor())->toBeTrue();
});

test('disabling MFA is permitted while another usable HR route remains', function () {
    seedE2Foundation();

    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $target = createUsableHrForCycleDispositionTest($actor, 'disable-covered-hr@nexiam.test');
    $survivor = createUsableHrForCycleDispositionTest($actor, 'disable-surviving-hr@nexiam.test');
    $otherHrIds = User::role('hr_administrator')
        ->whereNotIn('id', [$target->id, $survivor->id])
        ->pluck('id');
    StaffAuthenticationState::query()->whereIn('user_id', $otherHrIds)->update([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $disabled = app(DisableStaffMfa::class)->handle($actor, $target);

    expect($disabled->hasConfirmedTwoFactor())->toBeFalse()
        ->and($survivor->fresh('authenticationState')->authenticationState?->hasConfirmedTwoFactor())->toBeTrue();
});
test('academic leaders cannot rotate assignments outside their owned department catalog', function () {

    seedE2Foundation();

    $dean = User::query()->where('email', 'cas.dean@nexiam.test')->firstOrFail();
    $head = User::query()->where('email', 'bsit.head@nexiam.test')->firstOrFail();
    $foreignProgram = Program::query()
        ->where('institution_id', $dean->staffProfile->institution_id)
        ->where('code', 'BSEd')
        ->where('is_active', true)
        ->firstOrFail();
    $head->staffProfile()->update(['department_id' => null]);
    $cycle = AcademicCycle::query()->where('institution_id', $dean->staffProfile->institution_id)->firstOrFail();
    $originalProgramCodes = $head->staffProfile->program_codes;

    expect(fn () => app(TransitionStaffCycleDisposition::class)->handle($dean, $cycle->code, [
        ['user_id' => $head->id, 'disposition' => 'rotate', 'program_codes' => [$foreignProgram->code]],
    ]))->toThrow(AuthorizationException::class, 'department scope');

    expect(fn () => app(TransitionStaffCycleDisposition::class)->handle($dean, $cycle->code, [
        [
            'user_id' => $head->id,
            'disposition' => 'rotate',
            'program_codes' => [$foreignProgram->code],
            'department_id' => $foreignProgram->department_id,
        ],
    ]))->toThrow(AuthorizationException::class, 'department scope');

    expect($head->fresh()->staffProfile?->program_codes)->toBe($originalProgramCodes);
});

test('rotations reject inactive or foreign program catalog entries', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $head = User::query()->where('email', 'bsit.head@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();
    Program::query()
        ->where('institution_id', $hr->staffProfile->institution_id)
        ->where('code', 'BSIT')
        ->update(['is_active' => false]);

    expect(fn () => app(TransitionStaffCycleDisposition::class)->handle($hr, $cycle->code, [
        ['user_id' => $head->id, 'disposition' => 'rotate', 'program_codes' => ['BSIT']],
    ]))->toThrow(ValidationException::class);
});
test('rotations reject programs assigned to inactive departments', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $head = User::query()->where('email', 'bsit.head@nexiam.test')->firstOrFail();
    $program = Program::query()
        ->where('institution_id', $hr->staffProfile->institution_id)
        ->where('code', 'BSEd')
        ->where('is_active', true)
        ->firstOrFail();
    Department::query()->whereKey($program->department_id)->update(['is_active' => false]);
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();

    expect(fn () => app(TransitionStaffCycleDisposition::class)->handle($hr, $cycle->code, [
        ['user_id' => $head->id, 'disposition' => 'rotate', 'program_codes' => [$program->code]],
    ]))->toThrow(ValidationException::class);
});

test('Dean designation rotations fail rather than changing only the profile department', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $dean = User::query()->where('email', 'cas.dean@nexiam.test')->firstOrFail();
    $institutionId = (int) $hr->staffProfile->institution_id;
    $sourceDepartment = Department::query()
        ->where('institution_id', $institutionId)
        ->where('dean_user_id', $dean->id)
        ->firstOrFail();
    $destinationDepartment = Department::query()
        ->where('institution_id', $institutionId)
        ->where('is_active', true)
        ->where('id', '!=', $sourceDepartment->id)
        ->firstOrFail();
    $destinationProgram = Program::query()
        ->where('institution_id', $institutionId)
        ->where('department_id', $destinationDepartment->id)
        ->where('is_active', true)
        ->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $institutionId)->firstOrFail();
    $originalDestinationDeanId = $destinationDepartment->dean_user_id;
    $originalDepartmentId = $dean->staffProfile->department_id;

    expect(fn () => app(TransitionStaffCycleDisposition::class)->handle($hr, $cycle->code, [
        [
            'user_id' => $dean->id,
            'disposition' => 'rotate',
            'program_codes' => [$destinationProgram->code],
            'department_id' => $destinationDepartment->id,
        ],
    ]))->toThrow(ValidationException::class);

    expect($dean->fresh()->staffProfile?->department_id)->toBe($originalDepartmentId)
        ->and($sourceDepartment->fresh()->dean_user_id)->toBe($dean->id)
        ->and($destinationDepartment->fresh()->dean_user_id)->toBe($originalDestinationDeanId);
});

test('authorized HR can rotate a non-Dean to an active department with matching programs', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $head = User::query()->where('email', 'bsit.head@nexiam.test')->firstOrFail();
    $institutionId = (int) $hr->staffProfile->institution_id;
    $destinationProgram = Program::query()
        ->where('institution_id', $institutionId)
        ->where('code', 'BSEd')
        ->where('is_active', true)
        ->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $institutionId)->firstOrFail();

    app(TransitionStaffCycleDisposition::class)->handle($hr, $cycle->code, [[
        'user_id' => $head->id,
        'disposition' => 'rotate',
        'program_codes' => [$destinationProgram->code],
        'department_id' => $destinationProgram->department_id,
    ]]);

    expect($head->fresh()->staffProfile?->department_id)->toBe($destinationProgram->department_id)
        ->and($head->fresh()->staffProfile?->program_codes)->toBe([$destinationProgram->code]);
});

test('rollover refuses to present inactive personnel as retained or rotated', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $profile = $clerk->staffProfile;
    $oldCycle = $profile->active_cycle_code;
    $profile->update(['status' => StaffProfile::STATUS_INACTIVE, 'deactivated_at' => now()]);
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();
    $action = app(TransitionStaffCycleDisposition::class);

    foreach (['retain', 'rotate'] as $disposition) {
        expect(fn () => $action->handle($hr, $cycle->code, [[
            'user_id' => $clerk->id,
            'disposition' => $disposition,
        ]]))->toThrow(ConflictHttpException::class, 'inactive staff');
    }

    expect($clerk->fresh()->staffProfile?->active_cycle_code)->toBe($oldCycle)
        ->and($clerk->fresh()->staffProfile?->status)->toBe(StaffProfile::STATUS_INACTIVE);
});

test('HTTP endpoint transitions cycle dispositions and enforces authorization', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();

    $response = $this->actingAs($hr)->postJson(route('staff.personnel.cycle-dispositions'), [
        'destination_cycle_code' => $cycle->code,
        'dispositions' => [
            ['user_id' => $clerk->id, 'disposition' => 'retain'],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('retained', 1);
});
test('HTTP cycle disposition returns actionable 4xx errors for invalid input and authorization', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();
    $url = route('staff.personnel.cycle-dispositions');

    $this->actingAs($hr)->postJson($url, [
        'destination_cycle_code' => 'UNKNOWN-CYCLE',
        'dispositions' => [['user_id' => $clerk->id, 'disposition' => 'retain']],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('destination_cycle_code')
        ->assertJsonPath('message', 'Destination academic cycle [UNKNOWN-CYCLE] is not found in this institution.');

    $this->actingAs($hr)->postJson($url, [
        'destination_cycle_code' => $cycle->code,
        'dispositions' => [['user_id' => $hr->id, 'disposition' => 'archive']],
    ])->assertForbidden()->assertJsonPath(
        'message',
        'Cannot archive your own account during cycle rollover.',
    );
});

test('HTTP cycle disposition reports last usable HR protection as a conflict', function () {
    seedE2Foundation();

    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $target = createUsableHrForCycleDispositionTest($actor, 'cycle-http-last-usable@nexiam.test');
    makeOnlyUsableHrForCycleDispositionTest($target);
    $cycle = AcademicCycle::query()->where('institution_id', $actor->staffProfile->institution_id)->firstOrFail();

    $this->actingAs($actor)->postJson(route('staff.personnel.cycle-dispositions'), [
        'destination_cycle_code' => $cycle->code,
        'dispositions' => [['user_id' => $target->id, 'disposition' => 'archive']],
    ])->assertStatus(409)->assertJsonPath(
        'message',
        'Cannot archive: at least one active HR administrator with confirmed multi-factor authentication must remain in the institution.',
    );
});
