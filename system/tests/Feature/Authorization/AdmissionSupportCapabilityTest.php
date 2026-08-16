<?php

use App\Admission\Support\ApplicantCapabilityGrants;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
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
        ->toContain(CapabilityCatalog::PROGRAM_QUOTA_MANAGE)
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

test('registrar roles receive enrollment stamp and QR resolve', function (): void {
    foreach (['registrar_admin', 'registrar_clerk', 'registrar_support_staff'] as $role) {
        expect(CapabilityCatalog::roleHelperGrants()[$role])
            ->toContain(CapabilityCatalog::REGISTRAR_ENROLLMENT_STAMP)
            ->toContain(CapabilityCatalog::APPLICANT_QR_RESOLVE)
            ->not->toContain(CapabilityCatalog::PROGRAM_HEAD_VIEW);
    }
});

test('applicant portal grants include program selection and thread participation', function (): void {
    $keys = ApplicantCapabilityGrants::capabilityKeys();

    expect($keys)->toContain(CapabilityCatalog::APPLICANT_PROGRAM_SELECT)
        ->toContain(CapabilityCatalog::RECORD_THREAD_PARTICIPATE);
});

test('program head capability denies outside assigned program scope', function (): void {
    seedE2Foundation();

    $ph = User::factory()->create(['email' => 'ph.scope@example.test']);

    $authorizer = app(CapabilityAuthorizer::class);

    // No capability granted at all.
    expect($authorizer->decide($ph, CapabilityCatalog::PROGRAM_HEAD_VIEW, [
        'program' => 'BSIT',
    ])->allowed)->toBeFalse();

    $ph->givePermissionTo(CapabilityCatalog::PROGRAM_HEAD_VIEW);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Granted, but actor has no assigned program scope -> scope mismatch.
    expect($authorizer->decide($ph, CapabilityCatalog::PROGRAM_HEAD_VIEW, [
        'program' => 'BSIT',
    ])->reason)->toBe('scope_mismatch');

    // Actor scoped to BSIT may view BSIT, never BSN.
    $ph->syncRoles(['program_head']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $profile = $ph->staffProfile ?? $ph->staffProfile()->create([
        'institution_id' => Institution::query()->where('code', 'SYN-INST-001')->firstOrFail()->id,
        'office' => 'program_head',
        'program_codes' => ['BSIT'],
        'status' => 'active',
    ]);
    $profile->forceFill(['program_codes' => ['BSIT']])->save();

    $ph->refresh();

    $allowed = $authorizer->decide($ph, CapabilityCatalog::PROGRAM_HEAD_VIEW, ['program' => 'BSIT']);
    expect($allowed->allowed)->toBeTrue()
        ->and($authorizer->decide($ph, CapabilityCatalog::PROGRAM_HEAD_VIEW, ['program' => 'BSN'])->allowed)
        ->toBeFalse();
});
