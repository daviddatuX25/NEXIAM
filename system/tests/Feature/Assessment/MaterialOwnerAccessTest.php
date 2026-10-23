<?php

use App\Assessment\Support\MaterialOwnerAccess;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function materialOwnerInstitution(string $code): Institution
{
    return Institution::query()->create(['code' => $code, 'name' => $code.' University']);
}

function materialOwnerActor(Institution $institution, array $capabilities = []): User
{
    $actor = User::factory()->create();
    $actor->staffProfile()->create([
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    foreach ([
        CapabilityCatalog::GUIDANCE_SCORING_OMR,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
    ] as $capability) {
        Permission::findOrCreate($capability, 'web');
    }

    if ($capabilities !== []) {
        $actor->givePermissionTo($capabilities);
    }

    return $actor;
}

test('persisted OMR authority owns a sheet without catalog governance', function (): void {
    $institution = materialOwnerInstitution('MATERIAL-OMR-OWNER');
    $actor = materialOwnerActor($institution, [CapabilityCatalog::GUIDANCE_SCORING_OMR]);

    expect(app(MaterialOwnerAccess::class)->institutionId($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR))
        ->toBe($institution->id);
});

test('persisted booklet management authority owns a material without catalog governance', function (): void {
    $institution = materialOwnerInstitution('MATERIAL-BOOKLET-OWNER');
    $actor = materialOwnerActor($institution, [CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE]);

    expect(app(MaterialOwnerAccess::class)->institutionId($actor, CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE))
        ->toBe($institution->id);
});

test('staff with neither ownership nor catalog authority is denied', function (): void {
    $institution = materialOwnerInstitution('MATERIAL-NO-AUTHORITY');
    $actor = materialOwnerActor($institution);

    expect(fn () => app(MaterialOwnerAccess::class)->institutionId($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR))
        ->toThrow(AuthorizationException::class);
});

test('inactive and missing staff authority are denied', function (): void {
    $institution = materialOwnerInstitution('MATERIAL-INACTIVE');
    $inactiveActor = materialOwnerActor($institution, [CapabilityCatalog::GUIDANCE_SCORING_OMR]);
    $inactiveActor->staffProfile()->update(['status' => StaffProfile::STATUS_INACTIVE]);

    $missingProfileActor = User::factory()->create();
    $missingProfileActor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    expect(fn () => app(MaterialOwnerAccess::class)->institutionId($inactiveActor, CapabilityCatalog::GUIDANCE_SCORING_OMR))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(MaterialOwnerAccess::class)->institutionId($missingProfileActor, CapabilityCatalog::GUIDANCE_SCORING_OMR))
        ->toThrow(AuthorizationException::class);
});

test('owner capability is closed to unsupported caller-selected grants', function (): void {
    $institution = materialOwnerInstitution('MATERIAL-UNSUPPORTED');
    $actor = materialOwnerActor($institution, [CapabilityCatalog::GUIDANCE_SCORING_OMR]);

    expect(fn () => app(MaterialOwnerAccess::class)->institutionId($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE))
        ->toThrow(AuthorizationException::class);
});

test('stale loaded staff scope cannot select a different material owner', function (): void {
    $persistedInstitution = materialOwnerInstitution('MATERIAL-PERSISTED-SCOPE');
    $staleInstitution = materialOwnerInstitution('MATERIAL-STALE-SCOPE');
    $actor = materialOwnerActor($persistedInstitution, [CapabilityCatalog::GUIDANCE_SCORING_OMR]);
    $actor->load('staffProfile');
    $actor->staffProfile->institution_id = $staleInstitution->id;

    expect(app(MaterialOwnerAccess::class)->institutionId($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR))
        ->toBe($persistedInstitution->id);
});
