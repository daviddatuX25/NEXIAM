<?php

use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Actions\GrantTestAdministratorCapabilities;
use App\Staffing\Actions\RevokeTestAdministratorCapabilities;
use Spatie\Permission\Models\Permission;

test('actor scope separates explicit assignments from current dean ownership and bounds operation grants', function (): void {
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $otherInstitution = Institution::query()->create([
        'code' => 'SYN-INST-002',
        'name' => 'Other Institution',
    ]);
    $department = Department::query()->create([
        'institution_id' => $institution->id,
        'code' => 'GOV-DEPT',
        'name' => 'Governance Department',
        'is_active' => true,
    ]);
    $program = Program::query()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'code' => 'GOV-ACTIVE',
        'name' => 'Governance Active',
        'is_active' => true,
    ]);
    $inactiveProgram = Program::query()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'code' => 'GOV-INACTIVE',
        'name' => 'Governance Inactive',
        'is_active' => false,
    ]);
    Program::query()->create([
        'institution_id' => $otherInstitution->id,
        'department_id' => null,
        'code' => 'GOV-FOREIGN',
        'name' => 'Other Institution Program',
        'is_active' => true,
    ]);

    $member = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $member->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'department_id' => $department->id,
        'program_codes' => [],
        'active_cycle_code' => 'SYN-CYCLE-2026',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $member->givePermissionTo([
        CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW,
        CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT,
        CapabilityCatalog::ADMISSION_BENCHMARK_PREVIEW,
        CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH,
        CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE,
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
        CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH,
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'GOV-SCOPE-CYCLE',
        'name' => 'Governance Scope Cycle',
        'school_year' => '2026-2027',
        'semester' => 'First',
        'opens_on' => now()->subMonth()->toDateString(),
        'closes_on' => now()->addMonths(6)->toDateString(),
        'is_active' => true,
    ]);
    $authorizer = app(GovernanceAuthorizer::class);

    expect($member->actorScope()->programCodes)->toBe([])
        ->and($authorizer->allows($member, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $cycle))->toBeFalse();

    $department->update(['dean_user_id' => $member->id]);
    expect($member->actorScope()->programCodes)->toBe([$program->code])
        ->and($authorizer->allows($member, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $cycle))->toBeTrue();
    expect($authorizer->allows($member, CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH, $program, $cycle, write: true))
        ->toBeFalse()
        ->and($authorizer->allows($member, CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE, $program, $cycle, write: true))->toBeTrue()
        ->and($authorizer->allows($member, CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH, $program, $cycle, write: true))->toBeTrue()
        ->and($authorizer->allows($member, CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH, $inactiveProgram, $cycle, write: true))->toBeFalse();

    $closedActiveCycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'GOV-SCOPE-CLOSED-ACTIVE',
        'name' => 'Active configuration with a passed close date',
        'school_year' => '2026-2027',
        'semester' => 'Second',
        'opens_on' => now()->subYear()->toDateString(),
        'closes_on' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);
    expect($authorizer->allows($member, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $program, $closedActiveCycle, write: true))->toBeTrue();

    $replacement = User::factory()->create();
    $replacement->givePermissionTo(CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW);
    StaffProfile::query()->create([
        'user_id' => $replacement->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'department_id' => $department->id,
        'program_codes' => [],
        'active_cycle_code' => 'SYN-CYCLE-2026',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $department->update(['dean_user_id' => $replacement->id]);

    expect($member->actorScope()->programCodes)->toBe([])
        ->and($authorizer->allows($member, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $cycle))->toBeFalse()
        ->and($replacement->actorScope()->programCodes)->toBe([$program->code])
        ->and($authorizer->allows($replacement, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $cycle))->toBeTrue();

    $department->update(['is_active' => false]);
    expect($replacement->actorScope()->programCodes)->toBe([])
        ->and($authorizer->allows($replacement, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $cycle))->toBeFalse();

    $assigned = User::factory()->create();
    $assignedProfile = StaffProfile::query()->create([
        'user_id' => $assigned->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'department_id' => $department->id,
        'program_codes' => [$program->code, $inactiveProgram->code, 'GOV-FOREIGN', 'NOT-FOUND'],
        'active_cycle_code' => 'SYN-CYCLE-2026',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $assigned->load('staffProfile');

    expect($assigned->actorScope()->programCodes)->toBe([$program->code]);

    $assignedProfile->update(['status' => StaffProfile::STATUS_INACTIVE]);
    expect($assigned->actorScope()->institutionId)->toBeNull()
        ->and($assigned->actorScope()->programCodes)->toBe([]);
});

test('governance authorization re-resolves preloaded actor grants and explicit scope', function (): void {
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $program = Program::query()->create([
        'institution_id' => $institution->id,
        'department_id' => null,
        'code' => 'GOV-REVOKE',
        'name' => 'Governance Revocation',
        'is_active' => true,
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'GOV-CYCLE',
        'name' => 'Governance Cycle',
        'school_year' => '2026-2027',
        'semester' => 'First',
        'opens_on' => now()->addMonth()->toDateString(),
        'closes_on' => now()->addMonths(6)->toDateString(),
        'is_active' => false,
    ]);
    $actor = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'program_codes' => [$program->code],
        'active_cycle_code' => null,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    $capability = CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT;
    $actor->givePermissionTo($capability);
    $actor->load('permissions');
    $authorizer = app(GovernanceAuthorizer::class);

    expect($authorizer->allows($actor, $capability, $program, $cycle, write: true))->toBeTrue();

    $inactiveProgram = Program::query()->create([
        'institution_id' => $institution->id,
        'department_id' => null,
        'code' => 'GOV-REVOKE-INACTIVE',
        'name' => 'Inactive Governance Program',
        'is_active' => false,
    ]);
    expect($authorizer->allows($actor, $capability, $inactiveProgram, $cycle, write: true))->toBeFalse();
    $foreignInstitution = Institution::query()->create([
        'code' => 'GOV-FOREIGN-INST',
        'name' => 'Governance Foreign Institution',
    ]);
    $foreignProgram = Program::query()->create([
        'institution_id' => $foreignInstitution->id,
        'department_id' => null,
        'code' => 'GOV-REVOKE-FOREIGN',
        'name' => 'Foreign Governance Program',
        'is_active' => true,
    ]);
    expect($authorizer->allows($actor, $capability, $foreignProgram, $cycle, write: true))->toBeFalse();

    $actor->givePermissionTo(CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW);
    $historyCycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'GOV-HISTORY-CYCLE',
        'name' => 'Governance History',
        'school_year' => '2024-2025',
        'semester' => 'First',
        'opens_on' => now()->subYears(2)->toDateString(),
        'closes_on' => now()->subDay()->toDateString(),
        'is_active' => false,
    ]);
    expect($authorizer->allows($actor, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $historyCycle))->toBeTrue()
        ->and($authorizer->allows($actor, $capability, $program, $historyCycle, write: true))->toBeFalse();

    $permission = Permission::query()->where('name', $capability)->firstOrFail();
    $actor->permissions()->detach($permission->id);

    expect($authorizer->allows($actor, $capability, $program, $cycle, write: true))->toBeFalse();
});

test('governance grants stay bounded to guidance ownership and explicit cycle scope', function (): void {
    $programHead = CapabilityCatalog::roleHelperGrants()['program_head'];
    $guidance = CapabilityCatalog::roleHelperGrants()['guidance_counselor'];
    $registrarRoles = [
        CapabilityCatalog::roleHelperGrants()['registrar_admin'],
        CapabilityCatalog::roleHelperGrants()['registrar_clerk'],
        CapabilityCatalog::roleHelperGrants()['registrar_support_staff'],
    ];

    expect($programHead)
        ->toContain(CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW)
        ->toContain(CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT)
        ->toContain(CapabilityCatalog::ADMISSION_BENCHMARK_PREVIEW)
        ->toContain(CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW)
        ->toContain(CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT)
        ->not->toContain(CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH)
        ->toContain(CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE)
        ->toContain(CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH)
        ->toContain(CapabilityCatalog::PROGRAM_QUOTA_READ)
        ->not->toContain(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)

        ->and($guidance)
        ->toContain(CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH)
        ->toContain(CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE)
        ->toContain(CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH)
        ->toContain(CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ);

    foreach ($registrarRoles as $grants) {
        expect($grants)
            ->not->toContain(CapabilityCatalog::PROGRAM_QUOTA_READ)
            ->not->toContain(CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ)
            ->not->toContain(CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW);
    }
});
test('TA designation grants and revokes the full bounded governance capability set', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    $target = User::factory()->create();
    $target->assignRole(['guidance_clerk', 'program_head']);
    StaffProfile::query()->create([
        'user_id' => $target->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    $allCapabilities = CapabilityCatalog::delegatedTestAdministratorCapabilities();
    $granted = app(GrantTestAdministratorCapabilities::class)->handle($actor, $target);
    expect($granted->fresh()->hasDirectPermission(CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH))->toBeTrue()
        ->and($granted->fresh()->hasDirectPermission(CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE))->toBeTrue()
        ->and($granted->fresh()->hasDirectPermission(CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH))->toBeTrue()
        ->and(app(GovernanceAuthorizer::class)->allows($granted, CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH, $program, $cycle, write: true))->toBeTrue();

    $revoked = app(RevokeTestAdministratorCapabilities::class)->handle($actor, $target);
    foreach ($allCapabilities as $capability) {
        expect($revoked->fresh()->hasDirectPermission($capability))->toBeFalse();
    }
    expect(app(GovernanceAuthorizer::class)->allows($granted, CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH, $program, $cycle, write: true))->toBeFalse();
});
test('forward migration removes only explicit academic benchmark publication grants', function (): void {
    seedE2Foundation();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $academic = User::factory()->create();
    $academic->assignRole('program_head');
    StaffProfile::query()->create([
        'user_id' => $academic->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $academic->givePermissionTo([
        CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH,
        CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE,
        CapabilityCatalog::PROGRAM_QUOTA_MANAGE,
    ]);

    $guidance = User::factory()->create();
    $guidance->assignRole('guidance_counselor');
    StaffProfile::query()->create([
        'user_id' => $guidance->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $guidance->givePermissionTo(CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH);

    (require database_path('migrations/2026_10_06_120000_remove_academic_benchmark_publication_grants.php'))->up();

    expect($academic->fresh()->hasDirectPermission(CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH))->toBeFalse()
        ->and($academic->fresh()->hasDirectPermission(CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE))->toBeTrue()
        ->and($academic->fresh()->hasDirectPermission(CapabilityCatalog::PROGRAM_QUOTA_MANAGE))->toBeTrue()
        ->and($guidance->fresh()->hasDirectPermission(CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH))->toBeTrue();
});
