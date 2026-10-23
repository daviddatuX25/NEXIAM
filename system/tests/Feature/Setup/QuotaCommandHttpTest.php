<?php

use App\Admission\Actions\ManageRegistrarQuotaGovernanceGrant;
use App\Authorization\CapabilityCatalog;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\ProgramQuotaChange;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Actions\GrantTestAdministratorCapabilities;
use App\Staffing\Actions\RevokeTestAdministratorCapabilities;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->withoutVite();
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);
});

function httpQuotaActor(string $email): User
{
    return User::query()->where('email', $email)->firstOrFail();
}

function httpQuotaProgram(string $code): Program
{
    return Program::query()->where('code', $code)->firstOrFail();
}

function httpQuotaCycle(string $code = 'SYN-CYCLE-2026'): AcademicCycle
{
    return AcademicCycle::query()->where('code', $code)->firstOrFail();
}

function httpPostQuota(User $actor, array $payload)
{
    return test()->actingAs($actor)->postJson(route('setup.quotas.store'), $payload);
}

function httpPostBulkQuotas(User $actor, array $payload)
{
    return test()->actingAs($actor)->postJson(route('setup.quotas.bulk-store'), $payload);
}

test('program head can save quota for assigned program over HTTP with atomic receipt and audit', function (): void {
    $ph = httpQuotaActor('program.head.bsit@nexiam.test');
    $ph->givePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
    $program = httpQuotaProgram('BSIT');
    $cycle = httpQuotaCycle();
    $idempotencyKey = (string) Str::uuid();

    $response = httpPostQuota($ph, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 45,
        'reason' => 'Curriculum allocation update',
        'expected_version' => 0,
        'idempotency_key' => $idempotencyKey,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.slots', 45)
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('receipt.to_slots', 45)
        ->assertJsonPath('receipt.reason', 'Curriculum allocation update')
        ->assertJsonPath('receipt.actor_user_id', $ph->id);

    $quota = ProgramQuota::query()
        ->where('program_id', $program->id)
        ->where('academic_cycle_id', $cycle->id)
        ->firstOrFail();

    expect($quota->slots)->toBe(45)
        ->and(ProgramQuotaChange::query()->where('program_quota_id', $quota->id)->count())->toBe(1);
});

test('program head is denied saving quota for a foreign unassigned program', function (): void {
    $ph = httpQuotaActor('program.head.bsit@nexiam.test');
    $ph->givePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
    $foreign = httpQuotaProgram('BSPSY');
    $cycle = httpQuotaCycle();

    httpPostQuota($ph, [
        'program_id' => $foreign->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 30,
        'reason' => 'Attempt foreign program allocation',
        'expected_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();

    expect(ProgramQuota::query()
        ->where('program_id', $foreign->id)
        ->where('academic_cycle_id', $cycle->id)
        ->exists())->toBeFalse();
});

test('dean ownership replacement allows the active dean but revokes access for the replaced dean', function (): void {
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $department = Department::query()->create([
        'institution_id' => $institution->id,
        'code' => 'TEST_DEPT_DEAN',
        'name' => 'College of Computing Test',
        'is_active' => true,
    ]);
    $program = httpQuotaProgram('BSIT');
    $program->update(['department_id' => $department->id]);
    $cycle = httpQuotaCycle();

    $formerDean = User::factory()->create(['email' => 'former.dean@nexiam.test']);
    $formerDean->assignRole('program_head');
    $formerDean->givePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
    StaffProfile::query()->create([
        'user_id' => $formerDean->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'department_id' => $department->id,
        'program_codes' => [],
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $formerDean->id,
        'session_version' => 1,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
    ]);
    $department->update(['dean_user_id' => $formerDean->id]);

    httpPostQuota($formerDean, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 50,
        'reason' => 'Former dean initial allocation',
        'expected_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    $newDean = User::factory()->create(['email' => 'new.dean@nexiam.test']);
    $newDean->assignRole('program_head');
    $newDean->givePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
    StaffProfile::query()->create([
        'user_id' => $newDean->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'department_id' => $department->id,
        'program_codes' => [],
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    StaffAuthenticationState::query()->create([
        'user_id' => $newDean->id,
        'session_version' => 1,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
    ]);
    // Replace dean assignment immediately
    $department->update(['dean_user_id' => $newDean->id]);

    // Former dean is immediately denied
    httpPostQuota($formerDean, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 55,
        'reason' => 'Former dean stale authority',
        'expected_version' => 1,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();

    // New dean successfully manages quota
    httpPostQuota($newDean, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 60,
        'reason' => 'New dean reviewed capacity',
        'expected_version' => 1,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()
        ->assertJsonPath('data.slots', 60)
        ->assertJsonPath('data.version', 2);
});

test('guidance counselor and current delegated TA can mutate quotas but revoked TA is denied', function (): void {
    $counselor = httpQuotaActor('guidance.counselor@nexiam.test');
    $clerk = httpQuotaActor('guidance.clerk@nexiam.test');
    $program = httpQuotaProgram('BSPSY');
    $cycle = httpQuotaCycle();

    // Guidance Counselor inherent authority
    httpPostQuota($counselor, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 32,
        'reason' => 'Guidance baseline capacity',
        'expected_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()
        ->assertJsonPath('data.slots', 32)
        ->assertJsonPath('data.version', 1);

    // Clerk before delegation is denied
    httpPostQuota($clerk, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 35,
        'reason' => 'Clerk before delegation',
        'expected_version' => 1,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();

    // Grant TA delegation
    app(GrantTestAdministratorCapabilities::class)->handle($counselor, $clerk);

    httpPostQuota($clerk->fresh(), [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 36,
        'reason' => 'Delegated TA adjustment',
        'expected_version' => 1,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()
        ->assertJsonPath('data.slots', 36)
        ->assertJsonPath('data.version', 2);

    // Revoke TA delegation
    app(RevokeTestAdministratorCapabilities::class)->handle($counselor, $clerk->fresh());

    httpPostQuota($clerk->fresh(), [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 40,
        'reason' => 'Revoked TA adjustment',
        'expected_version' => 2,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();
});

test('ordinary staff and registrar quota discussion-only grant are denied write access over HTTP', function (): void {
    $counselor = httpQuotaActor('guidance.counselor@nexiam.test');
    $registrar = httpQuotaActor('registrar.clerk@nexiam.test');
    $program = httpQuotaProgram('BSN');
    $cycle = httpQuotaCycle();

    httpPostQuota($registrar, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 20,
        'reason' => 'Registrar ungranted write attempt',
        'expected_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();

    // Issue explicit narrow Registrar quota read/discussion grant
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $issuer = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $issuer->id,
        'institution_id' => $institution->id,
        'office' => 'registrar',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $issuer->givePermissionTo([CapabilityCatalog::SETUP_INSTITUTION_MANAGE, CapabilityCatalog::SETUP_PROGRAMS_MANAGE]);

    app(ManageRegistrarQuotaGovernanceGrant::class)->issue(
        $issuer,
        $registrar,
        $program,
        $cycle,
        0,
        'Read and discuss coordination required',
        (string) Str::uuid(),
    );

    // Explicit registrar quota grant does not grant quota mutation
    httpPostQuota($registrar->fresh(), [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 25,
        'reason' => 'Registrar grant write attempt',
        'expected_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();
});

test('institution and cycle forgeries or closed cycles are rejected over HTTP', function (): void {
    $counselor = httpQuotaActor('guidance.counselor@nexiam.test');
    $program = httpQuotaProgram('BSIT');
    $cycle = httpQuotaCycle();

    $otherInstitution = Institution::query()->create([
        'code' => 'OTHER-INST-HTTP',
        'name' => 'Other Institution',
    ]);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $otherInstitution->id,
        'code' => 'FOREIGN-CYCLE-HTTP',
        'name' => 'Foreign Cycle',
        'school_year' => '2026-2027',
        'semester' => 'First',
        'opens_on' => now()->subMonth()->toDateString(),
        'closes_on' => now()->addMonths(6)->toDateString(),
        'is_active' => true,
    ]);

    // Foreign institution academic cycle
    httpPostQuota($counselor, [
        'program_id' => $program->id,
        'academic_cycle_id' => $foreignCycle->id,
        'slots' => 40,
        'reason' => 'Mismatched institution cycle',
        'expected_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();

    // Closed academic cycle denies edits
    $cycle->forceFill([
        'is_active' => false,
        'closes_on' => now()->subDay()->toDateString(),
    ])->save();

    httpPostQuota($counselor, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 42,
        'reason' => 'Closed cycle allocation',
        'expected_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();
});

test('bulk quota request executes all-or-nothing and rolls back all members on single failure', function (): void {
    $counselor = httpQuotaActor('guidance.counselor@nexiam.test');
    $cycle = httpQuotaCycle();
    $bsit = httpQuotaProgram('BSIT');
    $bsn = httpQuotaProgram('BSN');

    $foreignInst = Institution::query()->create(['code' => 'FOREIGN-PROG-INST', 'name' => 'Foreign']);
    $foreignProgram = Program::query()->create([
        'institution_id' => $foreignInst->id,
        'code' => 'FOR-001',
        'name' => 'Foreign Program',
        'is_active' => true,
    ]);

    // Mixed request: BSIT is valid, foreignProgram is out of institution governance scope
    httpPostBulkQuotas($counselor, [
        'academic_cycle_id' => $cycle->id,
        'idempotency_key' => (string) Str::uuid(),
        'quotas' => [
            [
                'program_id' => $bsit->id,
                'slots' => 80,
                'reason' => 'Bulk valid member',
                'expected_version' => 0,
            ],
            [
                'program_id' => $foreignProgram->id,
                'slots' => 50,
                'reason' => 'Bulk foreign member',
                'expected_version' => 0,
            ],
        ],
    ])->assertForbidden();

    // Neither program was saved
    expect(ProgramQuota::query()->where('program_id', $bsit->id)->where('academic_cycle_id', $cycle->id)->exists())->toBeFalse()
        ->and(ProgramQuota::query()->where('program_id', $foreignProgram->id)->where('academic_cycle_id', $cycle->id)->exists())->toBeFalse();
});

test('stale expected version returns 409 conflict with current facts and does not mutate quota', function (): void {
    $counselor = httpQuotaActor('guidance.counselor@nexiam.test');
    $program = httpQuotaProgram('BSN');
    $cycle = httpQuotaCycle();

    httpPostQuota($counselor, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 30,
        'reason' => 'Initial allocation',
        'expected_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    // Stale edit with expected_version = 0 when current is 1
    $response = httpPostQuota($counselor, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 35,
        'reason' => 'Stale update',
        'expected_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('code', 'quota_version_conflict')
        ->assertJsonPath('current.program_id', $program->id)
        ->assertJsonPath('current.slots', 30)
        ->assertJsonPath('current.version', 1);

    expect(ProgramQuota::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->value('slots'))->toBe(30)
        ->and(ProgramQuotaChange::query()->where('program_quota_id', ProgramQuota::query()->where('program_id', $program->id)->value('id'))->count())->toBe(1);
});

test('same key with changed payload conflicts while replay returns original receipt after subsequent edits', function (): void {
    $counselor = httpQuotaActor('guidance.counselor@nexiam.test');
    $program = httpQuotaProgram('BSIT');
    $cycle = httpQuotaCycle();
    $firstKey = (string) Str::uuid();

    $firstResponse = httpPostQuota($counselor, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 40,
        'reason' => 'First key allocation',
        'expected_version' => 0,
        'idempotency_key' => $firstKey,
    ]);
    $firstResponse->assertOk();
    $firstChangeId = $firstResponse->json('receipt.change_id');

    // Same key with changed payload returns 409 conflict
    httpPostQuota($counselor, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 45,
        'reason' => 'Different slots using first key',
        'expected_version' => 0,
        'idempotency_key' => $firstKey,
    ])->assertStatus(409);

    // Later edit increases version
    httpPostQuota($counselor, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 50,
        'reason' => 'Second revision',
        'expected_version' => 1,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    // Replay of original request with firstKey returns the original receipt even after subsequent edits
    $replayResponse = httpPostQuota($counselor, [
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 40,
        'reason' => 'First key allocation',
        'expected_version' => 0,
        'idempotency_key' => $firstKey,
    ]);

    $replayResponse->assertOk()
        ->assertJsonPath('receipt.change_id', $firstChangeId)
        ->assertJsonPath('receipt.to_slots', 40)
        ->assertJsonPath('receipt.version', 1);

    // Current quota remains at latest revision (50)
    expect(ProgramQuota::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->value('slots'))->toBe(50);
});
