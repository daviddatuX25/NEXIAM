<?php

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\CanonicalOmrFixture;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\RestoreCanonicalOmrFixture;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function canonicalOmrFixtureOwner(Institution $institution): User
{
    $owner = User::factory()->create();
    $owner->staffProfile()->create([
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $owner->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    return $owner;
}

it('reconstructs the approved canonical draft and current proof in a clean database', function (): void {
    Storage::fake('local');
    $institution = Institution::query()->create([
        'code' => 'CANONICAL-OMR-FIXTURE',
        'name' => 'Canonical OMR Fixture University',
    ]);
    $owner = canonicalOmrFixtureOwner($institution);

    $draft = app(RestoreCanonicalOmrFixture::class)->handle($owner);
    $fixture = app(CanonicalOmrFixture::class);
    $identities = $fixture->identities();
    $authoring = (array) $draft->authoring_json;

    expect($draft->code)->toBe(CanonicalOmrFixture::STABLE_CODE)
        ->and($draft->institution_id)->toBe($institution->id)
        ->and($draft->is_synthetic)->toBeTrue()
        ->and($draft->owner_user_id)->toBe($owner->id)
        ->and($draft->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED)
        ->and($draft->template_id)->toBeNull()
        ->and($draft->compiled_version_id)->toBeNull()
        ->and(data_get($authoring, 'canonical.artifact_set.definition_hash'))->toBe($identities['definition_hash'])
        ->and(data_get($authoring, 'canonical.artifact_set.artifact_hash'))->toBe($identities['artifact_hash'])
        ->and(data_get($authoring, 'canonical.artifact_set.review_manifest.bundle_hash'))->toBe($identities['native_bundle_hash'])
        ->and(data_get($authoring, 'canonical.artifact_set.review_manifest_hash'))->toBe($identities['review_manifest_hash'])
        ->and(data_get($authoring, 'canonical.artifact_set.config_hash'))->toBe($identities['config_hash'])
        ->and(data_get($authoring, 'canonical.superseded_artifact_sets'))->toBeNull();

    $geometryIdentity = OmrCanonicalGeometryIdentity::forDraft($draft);
    $pages = (array) data_get($authoring, 'pages', []);
    $page = (array) ($pages[0] ?? []);
    $pageAsset = OmrTemplateAsset::query()->find(data_get($page, 'asset_id'));

    expect($draft->registration_mode)->toBe(OmrRegistrationMode::FiducialAnchored)
        ->and($draft->qr_mode)->toBe(OmrQrMode::Signed)
        ->and($draft->requested_page_count)->toBe(1)
        ->and($pages)->toHaveCount(1)
        ->and($page)->toMatchArray([
            'page_no' => 1,
            'width' => 600,
            'height' => 900,
            'width_mm' => 216,
            'height_mm' => 330,
            'orientation' => 'portrait',
            'confirmed' => true,
            'rejection_reason' => null,
        ])
        ->and($pageAsset)->toBeInstanceOf(OmrTemplateAsset::class)
        ->and($pageAsset->authoring_draft_id)->toBe($draft->id)
        ->and($pageAsset->kind)->toBe(OmrTemplateAsset::KIND_INTERMEDIATE_PAGE)
        ->and($pageAsset->page_no)->toBe(1)
        ->and($page['sha256'])->toBe($pageAsset->sha256)
        ->and($geometryIdentity)->toMatch('/^[a-f0-9]{64}$/')
        ->and(OmrCanonicalGeometryIdentity::forDraft($draft->fresh()))->toBe($geometryIdentity);
    $restoredAssetHashes = OmrTemplateAsset::query()
        ->where('authoring_draft_id', $draft->id)
        ->pluck('sha256')
        ->sort()
        ->values()
        ->all();
    $fixtureAssetHashes = collect($fixture->assets())
        ->pluck('sha256')
        ->sort()
        ->values()
        ->all();
    expect($restoredAssetHashes)->toBe($fixtureAssetHashes)
        ->and(OmrTemplateAsset::query()->where('authoring_draft_id', $draft->id)->count())->toBe(9)
        ->and(OmrTemplateVerificationCapture::query()->where('run_id', data_get($authoring, 'verification.run_id'))->count())->toBe(14);

    $artifact = app(CanonicalArtifactMaterializer::class)->loadPersisted(
        $draft,
        $draft->markerProfile,
        includePrintPdf: true,
    );
    expect($artifact->artifactHash)->toBe($identities['artifact_hash'])
        ->and($artifact->geometryIdentity)->toBe($identities['geometry_identity'])
        ->and($artifact->templateHash)->toBe($identities['template_hash'])
        ->and($artifact->pdfBytes)->not->toBe('')
        ->and(app(CurrentOmrDigitalVerification::class)->find($draft))->not->toBeNull();

    $snapshot = $draft->only([
        'id',
        'institution_id',
        'is_synthetic',
        'owner_user_id',
        'stage',
        'lock_version',
        'authoring_json',
    ]);
    $updatedAt = $draft->getRawOriginal('updated_at');
    $restoredAgain = app(RestoreCanonicalOmrFixture::class)->handle(canonicalOmrFixtureOwner($institution));

    expect($restoredAgain->only(array_keys($snapshot)))->toBe($snapshot)
        ->and($restoredAgain->getRawOriginal('updated_at'))->toBe($updatedAt)
        ->and(OmrTemplateAuthoringDraft::query()->where('code', CanonicalOmrFixture::STABLE_CODE)->count())->toBe(1);
});
