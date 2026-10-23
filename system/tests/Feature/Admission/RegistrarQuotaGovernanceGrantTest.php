<?php

use App\Admission\Actions\ManageRegistrarQuotaGovernanceGrant;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Models\Admission\RegistrarQuotaGovernanceGrant;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

function registrarGrantStaff(User $user, Institution $institution, string $office): void
{
    StaffProfile::query()->create([
        'user_id' => $user->id,
        'institution_id' => $institution->id,
        'office' => $office,
        'program_codes' => [],
        'active_cycle_code' => null,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
}

function registrarGrantCycle(Institution $institution, string $code, int $offset): AcademicCycle
{
    $schoolYearStart = 2026 + intdiv($offset, 2);
    $semester = $offset % 2 === 0 ? 'First' : 'Second';

    return AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => $code,
        'name' => $code,
        'school_year' => "{$schoolYearStart}-".($schoolYearStart + 1),
        'semester' => $semester,
        'opens_on' => now()->subMonth()->toDateString(),
        'closes_on' => now()->addMonths(6)->toDateString(),
        'is_active' => false,
    ]);
}

test('Registrar quota grant issues only exact scoped read and discussion access and revokes immediately', function (): void {
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = registrarGrantCycle($institution, 'RQGG-2026', 0);
    $otherCycle = registrarGrantCycle($institution, 'RQGG-OTHER', 1);
    $otherInstitution = Institution::query()->create([
        'code' => 'RQGG-OTHER-INST',
        'name' => 'Other Institution',
    ]);
    $foreignProgram = Program::query()->create([
        'institution_id' => $otherInstitution->id,
        'department_id' => null,
        'code' => 'RQGG-FOREIGN',
        'name' => 'Foreign Program',
        'is_active' => true,
    ]);

    $issuer = User::factory()->create();
    registrarGrantStaff($issuer, $institution, 'registrar');
    $issuer->givePermissionTo([CapabilityCatalog::SETUP_INSTITUTION_MANAGE, CapabilityCatalog::SETUP_PROGRAMS_MANAGE]);

    $recipient = User::factory()->create();
    registrarGrantStaff($recipient, $institution, 'registrar');

    $authorizer = app(GovernanceAuthorizer::class);
    $action = app(ManageRegistrarQuotaGovernanceGrant::class);
    $quotaRead = CapabilityCatalog::PROGRAM_QUOTA_READ;
    $quotaTopicRead = CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ;
    $quotaTopicSend = CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND;

    expect($authorizer->allows($recipient, $quotaRead, $program, $cycle))->toBeFalse()
        ->and($authorizer->allows($recipient, $quotaTopicRead, $program, $cycle))->toBeFalse()
        ->and($authorizer->allows($recipient, $quotaTopicSend, $program, $cycle, write: true))->toBeFalse();

    $issued = $action->issue($issuer, $recipient, $program, $cycle, 0, 'Quota coordination required', 'rqgg-issue-1');
    $recipient->load('permissions');

    expect($issued->version)->toBe(1)
        ->and($issued->status)->toBe(RegistrarQuotaGovernanceGrant::STATUS_ACTIVE)
        ->and($issued->actor_user_id)->toBe($issuer->id)
        ->and($issued->reason)->toBe('Quota coordination required')
        ->and($issued->operation_id)->not->toBeNull()
        ->and($issued->audit_event_id)->not->toBeNull()
        ->and($issued->receipt['capabilities'])->toBe(ManageRegistrarQuotaGovernanceGrant::GRANTED_CAPABILITIES)
        ->and($authorizer->allows($recipient, $quotaRead, $program, $cycle))->toBeTrue()
        ->and($authorizer->allows($recipient, $quotaTopicRead, $program, $cycle))->toBeTrue()
        ->and($authorizer->allows($recipient, $quotaTopicSend, $program, $cycle, write: true))->toBeTrue()
        ->and($authorizer->allows($recipient, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $cycle, write: true))->toBeFalse()
        ->and($authorizer->allows($recipient, CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_MANAGE_STATE, $program, $cycle, write: true))->toBeFalse()
        ->and($authorizer->allows($recipient, CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ, $program, $cycle))->toBeFalse()
        ->and($authorizer->allows($recipient, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $cycle))->toBeFalse()
        ->and($authorizer->allows($recipient, CapabilityCatalog::ADMISSION_BENCHMARK_PREVIEW, $program, $cycle))->toBeFalse()
        ->and($authorizer->allows($recipient, CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH, $program, $cycle, write: true))->toBeFalse()
        ->and($authorizer->allows($recipient, CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW, $program, $cycle))->toBeFalse()
        ->and($authorizer->allows($recipient, CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT, $program, $cycle, write: true))->toBeFalse()
        ->and($authorizer->allows($recipient, CapabilityCatalog::RECORD_THREAD_PARTICIPATE, $program, $cycle, write: true))->toBeFalse()
        ->and($authorizer->allows($recipient, $quotaRead, $program, $otherCycle))->toBeFalse()
        ->and($authorizer->allows($recipient, $quotaRead, $foreignProgram, $cycle))->toBeFalse();
    expect(fn () => $action->issue($issuer, $recipient, $program, $cycle, 0, 'Changed payload', 'rqgg-issue-1'))
        ->toThrow(RuntimeException::class);

    expect(fn () => $action->issue($issuer, $recipient, $program, $cycle, 0, 'stale issue', 'rqgg-issue-stale'))
        ->toThrow(RuntimeException::class);
    expect(fn () => $action->issue($issuer, $recipient, $program, $cycle, 1, 'another active grant', 'rqgg-issue-active'))
        ->toThrow(RuntimeException::class);

    $revoked = $action->revoke($issuer, $recipient, $program, $cycle, 1, 'Grant no longer needed', 'rqgg-revoke-1');
    expect($revoked->version)->toBe(2)
        ->and($revoked->status)->toBe(RegistrarQuotaGovernanceGrant::STATUS_REVOKED)
        ->and($revoked->predecessor_id)->toBe($issued->id)
        ->and($authorizer->allows($recipient, $quotaRead, $program, $cycle))->toBeFalse()
        ->and($authorizer->allows($recipient, $quotaTopicRead, $program, $cycle))->toBeFalse()
        ->and($authorizer->allows($recipient, $quotaTopicSend, $program, $cycle, write: true))->toBeFalse()
        ->and($recipient->hasPermissionTo($quotaRead))->toBeTrue();

    $replayedIssue = $action->issue($issuer, $recipient, $program, $cycle, 0, 'Quota coordination required', 'rqgg-issue-1');
    expect($replayedIssue->is($issued))->toBeTrue()
        ->and($authorizer->allows($recipient, $quotaRead, $program, $cycle))->toBeFalse();

    $replayedRevoke = $action->revoke($issuer, $recipient, $program, $cycle, 1, 'Grant no longer needed', 'rqgg-revoke-1');
    expect($replayedRevoke->is($revoked))->toBeTrue();

    expect(RegistrarQuotaGovernanceGrant::query()
        ->where('recipient_user_id', $recipient->id)
        ->where('program_id', $program->id)
        ->where('academic_cycle_id', $cycle->id)
        ->count())->toBe(2);
});

test('only active in-institution Setup issuers and active same-institution Registrar recipients can manage grants', function (): void {
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = registrarGrantCycle($institution, 'RQGG-AUTH', 0);
    $issuerWithoutSetup = User::factory()->create();
    registrarGrantStaff($issuerWithoutSetup, $institution, 'guidance');

    $inactiveIssuer = User::factory()->create();
    registrarGrantStaff($inactiveIssuer, $institution, 'registrar');
    $inactiveIssuer->staffProfile->update(['status' => StaffProfile::STATUS_INACTIVE]);
    $inactiveIssuer->givePermissionTo([CapabilityCatalog::SETUP_INSTITUTION_MANAGE, CapabilityCatalog::SETUP_PROGRAMS_MANAGE]);

    $issuer = User::factory()->create();
    registrarGrantStaff($issuer, $institution, 'guidance');
    $issuer->givePermissionTo([CapabilityCatalog::SETUP_INSTITUTION_MANAGE, CapabilityCatalog::SETUP_PROGRAMS_MANAGE]);

    $recipient = User::factory()->create();
    registrarGrantStaff($recipient, $institution, 'registrar');
    $foreignRecipient = User::factory()->create();
    $otherInstitution = Institution::query()->create([
        'code' => 'RQGG-AUTH-FOREIGN',
        'name' => 'Other Institution',
    ]);
    registrarGrantStaff($foreignRecipient, $otherInstitution, 'registrar');

    $action = app(ManageRegistrarQuotaGovernanceGrant::class);
    expect(fn () => $action->issue($issuerWithoutSetup, $recipient, $program, $cycle, 0, 'Missing setup authority', 'rqgg-deny-capability'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $action->issue($inactiveIssuer, $recipient, $program, $cycle, 0, 'Inactive issuer', 'rqgg-deny-inactive-issuer'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $action->issue($issuer, $foreignRecipient, $program, $cycle, 0, 'Wrong institution', 'rqgg-deny-recipient-scope'))
        ->toThrow(InvalidArgumentException::class);

    $recipient->staffProfile->update(['status' => StaffProfile::STATUS_INACTIVE]);
    expect(fn () => $action->issue($issuer, $recipient, $program, $cycle, 0, 'Inactive recipient', 'rqgg-deny-inactive-recipient'))
        ->toThrow(InvalidArgumentException::class);
});
