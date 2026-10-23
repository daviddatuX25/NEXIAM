<?php

use App\Admission\Support\ApplicantCapabilityGrants;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

test('admission-support capability rows exist after seed', function (): void {
    seedE2Foundation();

    $keys = [
        CapabilityCatalog::PROGRAM_HEAD_VIEW,
        CapabilityCatalog::PROGRAM_HEAD_FOLLOW_UP_RESOLVE,
        CapabilityCatalog::PROGRAM_HEAD_SELECTION_DECLINE,
        CapabilityCatalog::PROGRAM_HEAD_MESSAGE,
        CapabilityCatalog::GUIDANCE_SELECTION_MANAGE,
        CapabilityCatalog::GUIDANCE_FOLLOW_UP_MANAGE,
        CapabilityCatalog::RECORD_THREAD_PARTICIPATE,
        CapabilityCatalog::APPLICANT_PROGRAM_SELECT,
        CapabilityCatalog::REGISTRAR_ENROLLMENT_STAMP,
        CapabilityCatalog::APPLICANT_QR_RESOLVE,
    ];

    foreach ($keys as $key) {
        expect(Permission::query()->where('name', $key)->exists())->toBeTrue();
    }
});

test('program head role receives assigned-program admission-support grants', function (): void {
    $grants = CapabilityCatalog::roleHelperGrants()['program_head'];

    expect($grants)->toContain(CapabilityCatalog::PROGRAM_HEAD_VIEW)
        ->toContain(CapabilityCatalog::PROGRAM_HEAD_FOLLOW_UP_RESOLVE)
        ->toContain(CapabilityCatalog::PROGRAM_HEAD_SELECTION_DECLINE)
        ->toContain(CapabilityCatalog::PROGRAM_HEAD_MESSAGE)
        ->toContain(CapabilityCatalog::RECORD_THREAD_PARTICIPATE)
        ->toContain(CapabilityCatalog::APPLICANT_QR_RESOLVE)
        // PH never gains Registrar or Guidance authority.
        ->not->toContain(CapabilityCatalog::REGISTRAR_ENROLLMENT_STAMP)
        ->not->toContain(CapabilityCatalog::GUIDANCE_SELECTION_MANAGE)
        ->not->toContain(CapabilityCatalog::GUIDANCE_FOLLOW_UP_MANAGE);
});

test('guidance counselor receives pool, follow-up, and thread grants; clerk does not', function (): void {
    $counselor = CapabilityCatalog::roleHelperGrants()['guidance_counselor'];
    $clerk = CapabilityCatalog::roleHelperGrants()['guidance_clerk'];

    expect($counselor)->toContain(CapabilityCatalog::GUIDANCE_SELECTION_MANAGE)
        ->toContain(CapabilityCatalog::GUIDANCE_FOLLOW_UP_MANAGE)
        ->toContain(CapabilityCatalog::RECORD_THREAD_PARTICIPATE)
        ->toContain(CapabilityCatalog::APPLICANT_QR_RESOLVE)
        ->not->toContain(CapabilityCatalog::REGISTRAR_ENROLLMENT_STAMP)
        ->and($clerk)
        ->not->toContain(CapabilityCatalog::GUIDANCE_SELECTION_MANAGE)
        ->not->toContain(CapabilityCatalog::GUIDANCE_FOLLOW_UP_MANAGE);
});

test('registrar capability authority follows support, clerk, and administrator boundaries', function (): void {
    seedE2Foundation();

    $support = User::query()->where('email', 'registrar.support@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $admin = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $authorizer = app(CapabilityAuthorizer::class);

    expect($authorizer->allows($support, CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY))->toBeTrue()
        ->and($authorizer->allows($support, CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_CONFIRM))->toBeTrue()
        ->and($authorizer->allows($support, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY))->toBeTrue()
        ->and($authorizer->allows($support, CapabilityCatalog::OFFICE_INQUIRY_HANDLE))->toBeTrue()
        ->and($authorizer->allows($support, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT))->toBeFalse()
        ->and($authorizer->allows($support, CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_REOPEN))->toBeFalse()
        ->and($authorizer->allows($clerk, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT))->toBeTrue()
        ->and($authorizer->allows($clerk, CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_REOPEN))->toBeFalse()
        ->and($authorizer->allows($admin, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT))->toBeTrue()
        ->and($authorizer->allows($admin, CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_REOPEN))->toBeTrue();
});
test('applicant portal grants include program selection and thread participation', function (): void {
    $keys = ApplicantCapabilityGrants::capabilityKeys();

    expect($keys)->toContain(CapabilityCatalog::APPLICANT_PROGRAM_SELECT)
        ->toContain(CapabilityCatalog::RECORD_THREAD_PARTICIPATE)
        ->toContain(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);
});

test('program head capability denies outside assigned program scope', function (): void {
    seedE2Foundation();

    $authorizer = app(CapabilityAuthorizer::class);
    $unassigned = User::factory()->create(['email' => 'ph.unassigned@example.test']);

    expect($authorizer->decide($unassigned, CapabilityCatalog::PROGRAM_HEAD_VIEW, [
        'program' => 'BSIT',
    ])->allowed)->toBeFalse();

    $unassigned->givePermissionTo(CapabilityCatalog::PROGRAM_HEAD_VIEW);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($authorizer->decide($unassigned, CapabilityCatalog::PROGRAM_HEAD_VIEW, [
        'program' => 'BSIT',
    ])->reason)->toBe('scope_mismatch');

    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $allowed = $authorizer->decide($programHead, CapabilityCatalog::PROGRAM_HEAD_VIEW, ['program' => 'BSIT']);

    expect($allowed->allowed)->toBeTrue()
        ->and($authorizer->decide($programHead, CapabilityCatalog::PROGRAM_HEAD_VIEW, ['program' => 'BSN'])->allowed)
        ->toBeFalse();
});
