<?php

use App\Assessment\Actions\Omr\Canonical\CompileOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalStudioState;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrOverlaySpec;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Canonical\Queries\OmrCanonicalWorkflowProjection;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Assessment\Omr\Verification\OmrVerificationEvidenceService;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createDraftForAuthorityTest(User $actor, string $code = 'AUTH-TEST'): array
{
    $profile = OmrMarkerProfile::query()->create([
        'code' => $code.'.marker',
        'version' => 1,
        'family' => 'nexiam.fiducial',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'size_mm' => 12,
        'quiet_zone_mm' => 3,
        'matching_config' => [],
        'status' => OmrMarkerProfile::STATUS_QUALIFIED,
        'qualification_evidence_hash' => hash('sha256', 'calibration'),
        'created_by_user_id' => $actor->id,
        'qualified_by_user_id' => $actor->id,
        'qualified_at' => now(),
    ]);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, $code, 'Authority Test Draft', 'html');
    $assets = app(OmrTemplateAssetStore::class);
    $source = $assets->putDraftAsset($draft, OmrTemplateAsset::KIND_SOURCE, 'source content', 'text/plain');
    $raster = $assets->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_RASTER_PAGE,
        (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        'image/png',
        1
    );

    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'source' => ['sha256' => $source->sha256, 'converter_version' => 'test', 'settings_hash' => hash('sha256', 'settings')],
            'pages' => [['page_no' => 1, 'asset_id' => $raster->id, 'confirmed' => true]],
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => [
                'settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297],
                'composition_hash' => hash('sha256', 'composition'),
                'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]],
            ],
            'studio' => [
                'components' => [[
                    'id' => 'Part_1', 'type' => 'mcq', 'name' => 'Part 1', 'subjectName' => 'General Ability',
                    'posX' => 25, 'posY' => 70, 'w_mm' => 40, 'h_mm' => 50, 'aptitudeAreaCode' => 'LOGIC', 'questionsCount' => 2,
                    'startQ' => 1, 'optionsCount' => 4, 'bubbleDiameterMm' => 4.5, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8,
                ]],
            ],
            'mapping' => [
                'input' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => 1],
                'field_blocks' => [],
            ],
            'overlay' => app(OmrOverlaySpec::class)->validate(['boxes' => []]),
            'verification' => [],
        ],
    ])->save();
    $authoring = (array) $draft->fresh()->authoring_json;
    $authoring['mapping']['mapping_hash'] = OmrCanonicalGeometryIdentity::forDraft($draft->fresh(), $profile);
    $draft->forceFill(['authoring_json' => $authoring])->save();

    return [$draft->fresh(), $profile];
}

function seedPassingRunForDraft(OmrTemplateAuthoringDraft $draft, User $actor, ?string $artifactHash = null, array $failedVariants = [], array $missingVariants = [], int $pageCount = 1): OmrTemplateVerificationRun
{
    $authoring = (array) $draft->authoring_json;
    $artifactSet = (array) ($authoring['canonical']['artifact_set'] ?? []);
    $hash = $artifactHash ?? (string) ($artifactSet['artifact_hash'] ?? $authoring['canonical']['artifact_hash'] ?? hash('sha256', 'artifact'));
    $mappingHash = (string) ($artifactSet['geometry_identity'] ?? $artifactSet['mapping_hash'] ?? '');
    if ($mappingHash === '') {
        try {
            $mappingHash = OmrCanonicalGeometryIdentity::forDraft($draft);
        } catch (Throwable) {
            $mappingHash = hash('sha256', 'mapping');
        }
    }
    $variants = OmrNoGoMatrixRunner::variantKeys($pageCount);
    $variantSummary = [];
    foreach ($variants as $v) {
        $status = in_array($v, $failedVariants, true) ? 'fail' : 'pass';
        $variantSummary[$v] = ['status' => $status, 'expected' => 'pass', 'actual' => $status];
    }

    $proofHash = hash('sha256', 'proof:'.$hash);
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => hash('sha256', 'plan:'.$hash),
        'expected_vector_hash' => hash('sha256', 'expected_vector:'.$hash),
        'clean_vector_hash' => hash('sha256', 'clean_vector:'.$hash),
        'proof_hash' => $proofHash,
        'artifact_hash' => $hash,
        'native_bundle_hash' => hash('sha256', 'native_bundle:'.$hash),
        'manifest_hash' => hash('sha256', 'manifest:'.$hash),
        'config_hash' => hash('sha256', 'config:'.$hash),
        'mapping_hash' => $mappingHash,
        'progress_done' => count($variants),
        'progress_total' => count($variants),
        'result_summary' => [
            'matrix_hash' => hash('sha256', json_encode($variantSummary)),
            'variants' => $variantSummary,
            'proof_hash' => $proofHash,
            'expected_vector_hash' => hash('sha256', 'expected_vector:'.$hash),
            'clean_vector_hash' => hash('sha256', 'clean_vector:'.$hash),
            'threshold_status' => 'Unqualified',
        ],
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'finished_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    // Create a proof asset for every requested page.
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        app(OmrTemplateAssetStore::class)->putDraftAsset(
            $draft,
            OmrTemplateAsset::KIND_DIGITAL_PROOF,
            'dummy proof png bytes',
            'image/png',
            $pageNo,
            ['run_id' => $run->id, 'attempt' => 1, 'proof_hash' => $proofHash]
        );
    }

    foreach ($variants as $v) {
        if (in_array($v, $missingVariants, true)) {
            continue;
        }
        $status = in_array($v, $failedVariants, true) ? 'fail' : 'pass';
        OmrTemplateVerificationCapture::query()->create([
            'run_id' => $run->id,
            'attempt' => 1,
            'page_no' => OmrNoGoMatrixRunner::parseVariantKey($v)['page_no'],
            'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
            'variant' => $v,
            'expected' => 'pass',
            'actual' => $status === 'pass' ? OmrTemplateVerificationCapture::ACTUAL_PASS : OmrTemplateVerificationCapture::ACTUAL_FAILED,
            'status' => $status,
            'input_hash' => hash('sha256', $v.':input'),
            'evidence_hash' => hash('sha256', $v.':evidence'),
            'vector_match' => $status === 'pass',
            'captured_by_user_id' => $actor->id,
            'captured_at' => now(),
        ]);
    }

    return $run;
}

test('verification evidence records the requested page number', function (): void {
    Storage::fake('local');
    $actor = User::factory()->create();
    [$draft] = createDraftForAuthorityTest($actor, 'PAGE-EVIDENCE-TEST');
    $run = seedPassingRunForDraft($draft, $actor);
    $inputAsset = app(OmrTemplateAssetStore::class)->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_DIGITAL_PROOF,
        'page two input bytes',
        'image/png',
        2,
    );
    $capture = app(OmrVerificationEvidenceService::class)->storeCapture(
        $run,
        'page-2:clean',
        2,
        [
            'input_hash' => $inputAsset->sha256,
            'evidence_assets' => [[
                'role' => 'input.png',
                'asset_id' => $inputAsset->id,
                'sha256' => $inputAsset->sha256,
            ]],
            'status' => 'pass',
            'expected' => 'pass',
            'actual' => 'pass',
        ],
        [],
        null,
        null,
    );

    expect($capture->page_no)->toBe(2)
        ->and($capture->summary['evidence']['page_no'])->toBe(2);
});

test('digital verification rejects captures persisted under the wrong page', function (): void {
    Storage::fake('local');
    $actor = User::factory()->create();
    [$draft] = createDraftForAuthorityTest($actor, 'PAGE-AUTHORITY-TEST');
    $draft->forceFill(['requested_page_count' => 2])->save();
    $draft = $draft->fresh();
    $run = seedPassingRunForDraft($draft, $actor, pageCount: 2);
    $authority = app(CurrentOmrDigitalVerification::class);
    expect($authority->findForTarget((int) $draft->id, (string) $run->artifact_hash, (string) $run->mapping_hash, $draft))->not->toBeNull();

    $run->captures()->where('variant', 'page-2:clean')->firstOrFail()
        ->forceFill(['page_no' => 1])
        ->save();

    expect($authority->findForTarget((int) $draft->id, (string) $run->artifact_hash, (string) $run->mapping_hash, $draft->fresh()))->toBeNull();
});
test('exact reported sequence: pass -> identical materialize -> refresh -> compile', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draft, $profile] = createDraftForAuthorityTest($actor, 'SEQ-TEST');

    // 1. Initial materialization
    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    $initialArtifactHash = (string) $materialized->authoring_json['canonical']['artifact_set']['artifact_hash'];

    // 2. Verification passes
    $run = seedPassingRunForDraft($materialized, $actor);
    // Stale legacy payload with wrong run_id and stale hashes
    $materialized->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
        'authoring_json' => [
            ...((array) $materialized->authoring_json),
            'verification' => [
                'status' => 'passed',
                'run_id' => 99999,
                'artifact_hash' => $initialArtifactHash,
                'hashes' => [
                    'digital_verification' => hash('sha256', 'stale-verification'),
                    'matrix_hash' => hash('sha256', 'stale-matrix'),
                    'proof_hash' => hash('sha256', 'stale-proof'),
                    'run_id' => '99999',
                ],
            ],
        ],
    ])->save();

    // 3. Identical materialization (rematerialize with unchanged definition)
    $rematerialized = app(MaterializeOmrCanonicalDraft::class)->handle($materialized->fresh(['markerProfile']), $actor, (int) $materialized->lock_version);
    $newArtifactHash = (string) $rematerialized->authoring_json['canonical']['artifact_set']['artifact_hash'];

    expect($newArtifactHash)->toBe($initialArtifactHash)
        ->and($rematerialized->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED)
        ->and($rematerialized->authoring_json['verification'])->not->toBeNull()
        ->and($rematerialized->authoring_json['verification']['status'])->toBe('passed')
        ->and($rematerialized->authoring_json['verification']['run_id'])->toBe($run->id)
        ->and($rematerialized->authoring_json['verification']['artifact_hash'])->toBe($initialArtifactHash)
        ->and($rematerialized->authoring_json['verification']['hashes']['matrix_hash'])->toBe((string) $run->result_summary['matrix_hash'])
        ->and($rematerialized->authoring_json['verification']['hashes']['proof_hash'])->toBe((string) $run->proof_hash)
        ->and($rematerialized->authoring_json['verification']['hashes']['run_id'])->toBe((string) $run->id);
    // 4. Studio save / refresh keeps digital_verified stage
    $savedStudio = app(SaveOmrCanonicalStudioState::class)->handle(
        $rematerialized->fresh(),
        $actor,
        (array) ($rematerialized->authoring_json['overlay'] ?? ['boxes' => []]),
        (array) ($rematerialized->authoring_json['mapping'] ?? null),
        (array) ($rematerialized->authoring_json['studio'] ?? null),
        (int) $rematerialized->lock_version,
    );

    expect($savedStudio->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED)
        ->and($savedStudio->authoring_json['verification']['status'])->toBe('passed');

    // 5. Compile succeeds
    $compiled = app(CompileOmrCanonicalDraft::class)->handle($savedStudio->fresh(), $actor);

    expect($compiled->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_COMPILED)
        ->and($compiled->compiled_version_id)->not->toBeNull();

    $version = $compiled->compiledVersion;
    expect($version)->not->toBeNull();
    $manifest = (array) $version->authoring_manifest;
    $vHashes = (array) ($manifest['verification_hashes'] ?? []);

    expect($vHashes)->toHaveKeys([
        'digital_verification',
        'matrix_hash',
        'proof_hash',
        'expected_vector_hash',
        'clean_vector_hash',
        'run_id',
    ])
        ->and($vHashes['run_id'])->toBe((string) $run->id)
        ->and($vHashes['matrix_hash'])->toBe((string) $run->result_summary['matrix_hash'])
        ->and($vHashes['proof_hash'])->toBe((string) $run->proof_hash);
});

test('changed materialize invalidates verification and demotes stage', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draft, $profile] = createDraftForAuthorityTest($actor, 'CHANGE-TEST');

    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    $initialArtifactHash = (string) $materialized->authoring_json['canonical']['artifact_set']['artifact_hash'];

    seedPassingRunForDraft($materialized, $actor);
    $materialized->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
        'authoring_json' => [
            ...((array) $materialized->authoring_json),
            'verification' => [
                'status' => 'passed',
                'artifact_hash' => $initialArtifactHash,
                'hashes' => ['digital_verification' => hash('sha256', 'verification')],
            ],
        ],
    ])->save();

    // Change marker insets to force a different artifact hash
    $authoring = (array) $materialized->authoring_json;
    $authoring['marker']['insets_mm'] = ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15];
    $materialized->forceFill(['authoring_json' => $authoring])->save();

    $rematerialized = app(MaterializeOmrCanonicalDraft::class)->handle($materialized->fresh(['markerProfile']), $actor, (int) $materialized->lock_version);
    $changedArtifactHash = (string) $rematerialized->authoring_json['canonical']['artifact_set']['artifact_hash'];

    expect($changedArtifactHash)->not->toBe($initialArtifactHash)
        ->and($rematerialized->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE)
        ->and($rematerialized->authoring_json['verification'])->toBeNull();

    // Authority query returns null
    $authority = app(CurrentOmrDigitalVerification::class);
    expect($authority->find($rematerialized))->toBeNull();

    // Compile fails with compile_gate_missing
    expect(fn () => app(CompileOmrCanonicalDraft::class)->handle($rematerialized->fresh(), $actor))
        ->toThrow(OmrAuthoringDraftException::class, 'compile_gate_missing');
});

test('run from other artifact or draft is strictly excluded', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draftA] = createDraftForAuthorityTest($actor, 'DRAFT-A');
    [$draftB] = createDraftForAuthorityTest($actor, 'DRAFT-B');

    $matA = app(MaterializeOmrCanonicalDraft::class)->handle($draftA->fresh(['markerProfile']), $actor, 0);
    $matB = app(MaterializeOmrCanonicalDraft::class)->handle($draftB->fresh(['markerProfile']), $actor, 0);

    // Pass run for Draft A only
    seedPassingRunForDraft($matA, $actor);

    $authority = app(CurrentOmrDigitalVerification::class);
    expect($authority->find($matA))->not->toBeNull()
        ->and($authority->find($matB))->toBeNull();

    expect(fn () => app(CompileOmrCanonicalDraft::class)->handle($matB->fresh(), $actor))
        ->toThrow(OmrAuthoringDraftException::class, 'compile_gate_missing');
});

test('invalid current geometry cannot reuse a cached passing digital artifact', function (): void {
    seedE2Foundation();
    $actor = physicalChallengeActor();
    [$draft] = createDraftForAuthorityTest($actor, 'INVALID-CURRENT-GEOMETRY');
    $draft = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    seedPassingRunForDraft($draft, $actor);
    $authority = app(CurrentOmrDigitalVerification::class);
    expect($authority->find($draft))->not->toBeNull();
    $authoring = (array) $draft->authoring_json;
    $cachedArtifact = $authoring['canonical']['artifact_set'];
    $authoring['pages'] = [];
    $draft->forceFill(['authoring_json' => $authoring])->save();
    expect($draft->fresh()->authoring_json['canonical']['artifact_set'])->toBe($cachedArtifact)
        ->and($authority->find($draft->fresh()))->toBeNull();
});

test('missing capture or failed variant is rejected by digital verification authority', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draftFail] = createDraftForAuthorityTest($actor, 'FAIL-VAR');
    [$draftMissing] = createDraftForAuthorityTest($actor, 'MISS-VAR');

    $matFail = app(MaterializeOmrCanonicalDraft::class)->handle($draftFail->fresh(['markerProfile']), $actor, 0);
    $matMissing = app(MaterializeOmrCanonicalDraft::class)->handle($draftMissing->fresh(['markerProfile']), $actor, 0);

    // 1. Run with 1 failed variant
    seedPassingRunForDraft($matFail, $actor, null, ['rotation']);

    // 2. Run missing 1 variant capture
    seedPassingRunForDraft($matMissing, $actor, null, [], ['perspective']);

    $authority = app(CurrentOmrDigitalVerification::class);
    expect($authority->find($matFail))->toBeNull()
        ->and($authority->find($matMissing))->toBeNull();

    expect(fn () => app(CompileOmrCanonicalDraft::class)->handle($matFail->fresh(), $actor))
        ->toThrow(OmrAuthoringDraftException::class, 'compile_gate_missing');

    expect(fn () => app(CompileOmrCanonicalDraft::class)->handle($matMissing->fresh(), $actor))
        ->toThrow(OmrAuthoringDraftException::class, 'compile_gate_missing');
});

test('idempotent identical materialize does not perform extra invalidation', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draft] = createDraftForAuthorityTest($actor, 'IDEM-TEST');

    $mat = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    seedPassingRunForDraft($mat, $actor);
    $mat->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED])->save();

    // Materialize 3 times in succession
    for ($i = 1; $i <= 3; $i++) {
        $mat = app(MaterializeOmrCanonicalDraft::class)->handle($mat->fresh(['markerProfile']), $actor, (int) $mat->lock_version);
        expect($mat->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED)
            ->and($mat->authoring_json['verification']['status'])->toBe('passed');
    }
});

test('compile succeeds when legacy verification JSON is null by reconstructing verification manifest', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draft] = createDraftForAuthorityTest($actor, 'NULL-JSON');

    $mat = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    $run = seedPassingRunForDraft($mat, $actor);

    // Intentionally wipe legacy verification JSON and keep stage mapping_complete
    $authoring = (array) $mat->authoring_json;
    $authoring['verification'] = null;
    $mat->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => $authoring,
    ])->save();

    // Compile succeeds from durable run authority
    $compiled = app(CompileOmrCanonicalDraft::class)->handle($mat->fresh(), $actor);

    expect($compiled->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_COMPILED)
        ->and($compiled->compiled_version_id)->not->toBeNull();

    $version = $compiled->compiledVersion;
    $manifest = (array) $version->authoring_manifest;
    expect($manifest['verification_hashes']['run_id'])->toBe((string) $run->id)
        ->and($manifest['verification_hashes']['matrix_hash'])->toBe((string) $run->result_summary['matrix_hash']);
});

test('workflow projection projects digital_verified boolean and matching run summary', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draft] = createDraftForAuthorityTest($actor, 'PROJ-TEST');

    $mat = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    $projection = app(OmrCanonicalWorkflowProjection::class);

    // 1. Before verification: digital_verified is false, matching_verification_run_id is null
    $propsUnverified = $projection->workflowProps($mat->fresh(), $actor);
    expect($propsUnverified['draft']['digital_verified'])->toBeFalse()
        ->and($propsUnverified['draft']['matching_verification_run_id'])->toBeNull()
        ->and($propsUnverified['verification_run'])->toBeNull();

    // 2. After passing run: digital_verified is true, matching run is projected
    $run = seedPassingRunForDraft($mat, $actor);
    $propsVerified = $projection->workflowProps($mat->fresh(), $actor);
    expect($propsVerified['draft']['digital_verified'])->toBeTrue()
        ->and($propsVerified['draft']['matching_verification_run_id'])->toBe($run->id)
        ->and($propsVerified['verification_run']['run_id'])->toBe($run->id)
        ->and($propsVerified['verification_run']['status'])->toBe('passed');

    // 3. When an active run exists: live progress is projected
    $activeRun = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $mat->id,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'status' => OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX,
        'attempt' => 2,
        'plan_version' => 'v1',
        'plan_hash' => hash('sha256', 'plan2'),
        'expected_vector_hash' => hash('sha256', 'expected_vector2'),
        'clean_vector_hash' => hash('sha256', 'clean_vector2'),
        'artifact_hash' => (string) $mat->authoring_json['canonical']['artifact_set']['artifact_hash'],
        'native_bundle_hash' => hash('sha256', 'nb2'),
        'manifest_hash' => hash('sha256', 'man2'),
        'config_hash' => hash('sha256', 'cfg2'),
        'mapping_hash' => (string) $mat->authoring_json['canonical']['artifact_set']['geometry_identity'],
        'progress_done' => 5,
        'progress_total' => 14,
        'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => 'perspective'],
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    $propsActive = $projection->workflowProps($mat->fresh(), $actor);
    expect($propsActive['verification_run']['run_id'])->toBe($activeRun->id)
        ->and($propsActive['verification_run']['status'])->toBe('variant_matrix')
        ->and($propsActive['verification_run']['progress']['done'])->toBe(5);
});

test('workflow projection omits stale physical run with passed selected captures', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
    ]);
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $authoring = (array) $draft->authoring_json;
    $artifactHash = (string) $authoring['canonical']['artifact_set']['artifact_hash'];
    $mappingHash = (string) $authoring['canonical']['artifact_set']['geometry_identity'];
    $targetVersion = (string) ($authoring['canonical']['artifact_set']['candidate_version'] ?? '1');
    $profile = $draft->markerProfile()->firstOrFail();
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
        'attempt' => 1,
        'plan_version' => 'v2',
        'plan_hash' => hash('sha256', 'stale-run-plan'),
        'expected_vector_hash' => hash('sha256', 'stale-run-vector'),
        'clean_vector_hash' => hash('sha256', 'stale-run-clean'),
        'proof_hash' => hash('sha256', 'stale-run-proof'),
        'artifact_hash' => $artifactHash,
        'native_bundle_hash' => hash('sha256', 'stale-run-bundle'),
        'manifest_hash' => hash('sha256', 'stale-run-manifest'),
        'config_hash' => hash('sha256', 'stale-run-config'),
        'mapping_hash' => $mappingHash,
        'progress_done' => 0,
        'progress_total' => 2,
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    foreach ([
        OmrTemplateVerificationMethod::METHOD_PRE_SHADED,
        OmrTemplateVerificationMethod::METHOD_HAND_SHADED,
    ] as $methodName) {
        $planHash = hash('sha256', 'stale-plan-'.$methodName);
        $vectorHash = hash('sha256', 'stale-vector-'.$methodName);
        $method = OmrTemplateVerificationMethod::query()->create([
            'run_id' => $run->id,
            'method' => $methodName,
            'method_state' => OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE,
            'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
            'target_id' => $draft->id,
            'target_version' => $targetVersion,
            'target_hash' => $artifactHash,
            'plan_version' => 'v2',
            'plan_seed' => 'stale-seed-'.$methodName,
            'plan_hash' => $planHash,
            'expected_vector_hash' => $vectorHash,
            'required_page_count' => 1,
        ]);
        $challenge = OmrPhysicalChallenge::query()->create([
            'run_id' => $run->id,
            'attempt' => 1,
            'method' => $methodName,
            'mode' => OmrPhysicalChallenge::MODE_QUICK,
            'challenge_code' => 'STALE-'.$methodName,
            'target_kind' => OmrTemplateVerificationMethod::TARGET_CANDIDATE,
            'target_id' => $draft->id,
            'target_version' => $targetVersion,
            'target_hash' => $artifactHash,
            'plan_version' => 'v2',
            'plan_seed' => 'stale-seed-'.$methodName,
            'plan_hash' => $planHash,
            'expected_vector_hash' => $vectorHash,
            'renderer_version' => 'stale-renderer',
            'renderer_hash' => hash('sha256', 'stale-renderer'),
            'marker_profile_id' => $profile->id,
            'marker_profile_version' => $profile->version,
            'marker_profile_hash' => $profile->asset_sha256,
            'page_manifest' => [],
            'page_manifest_hash' => hash('sha256', '[]'),
            'status' => OmrPhysicalChallenge::STATUS_PRINTED,
            'created_by_user_id' => $actor->id,
        ]);
        $method->forceFill(['challenge_id' => $challenge->id])->save();
        $page = OmrTemplateVerificationMethodPage::query()->create([
            'method_id' => $method->id,
            'page_no' => 1,
            'required' => true,
            'page_state' => OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE,
        ]);
        $gates = [
            'marker_count' => 'pass',
            'registration' => 'pass',
            'dimensions' => 'pass',
            'shifts' => 'pass',
        ];
        $capture = OmrTemplateVerificationCapture::query()->create([
            'run_id' => $run->id,
            'method_id' => $method->id,
            'challenge_id' => $challenge->id,
            'attempt' => 1,
            'attempt_no' => 1,
            'page_no' => 1,
            'method' => $methodName,
            'mode' => $run->mode,
            'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
            'target_kind' => $method->target_kind,
            'target_id' => $method->target_id,
            'target_version' => $method->target_version,
            'target_hash' => $method->target_hash,
            'plan_version' => $method->plan_version,
            'plan_hash' => $planHash,
            'plan_seed' => $method->plan_seed,
            'challenge_hash' => hash('sha256', 'stale-challenge-'.$methodName),
            'renderer_version' => $challenge->renderer_version,
            'renderer_hash' => $challenge->renderer_hash,
            'marker_profile_id' => $challenge->marker_profile_id,
            'marker_profile_version' => $challenge->marker_profile_version,
            'marker_profile_hash' => $challenge->marker_profile_hash,
            'page_manifest_hash' => $challenge->page_manifest_hash,
            'expected' => OmrTemplateVerificationCapture::ACTUAL_PASS,
            'actual' => OmrTemplateVerificationCapture::ACTUAL_PASS,
            'status' => OmrTemplateVerificationCapture::STATUS_PASS,
            'input_hash' => hash('sha256', 'stale-input-'.$methodName),
            'evidence_hash' => hash('sha256', 'stale-evidence-'.$methodName),
            'expected_vector_hash' => $vectorHash,
            'detected_vector_hash' => $vectorHash,
            'vector_match' => true,
            'gate_result' => $gates,
            'summary' => ['gates' => $gates],
            'medium' => $methodName === OmrTemplateVerificationMethod::METHOD_HAND_SHADED
                ? OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL
                : null,
            'selected_by_user_id' => $actor->id,
            'selected_at' => now(),
            'selection_reason' => 'Stale authority regression fixture.',
            'captured_by_user_id' => $actor->id,
            'captured_at' => now(),
        ]);
        $page->forceFill([
            'page_state' => OmrTemplateVerificationMethodPage::STATE_PASSED,
            'selected_attempt_id' => $capture->id,
            'page_version' => 1,
        ])->save();
        $method->forceFill([
            'method_state' => OmrTemplateVerificationMethod::STATE_PASSED,
            'completed_at' => now(),
        ])->save();
        $challenge->forceFill(['status' => OmrPhysicalChallenge::STATUS_PASSED])->save();
    }
    $run->forceFill([
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'progress_done' => 2,
        'finished_at' => now(),
    ])->save();

    $authoring = (array) $draft->authoring_json;
    $currentArtifactHash = (string) ($authoring['canonical']['artifact_set']['artifact_hash'] ?? '');
    $authoring['canonical']['artifact_hash'] = hash('sha256', 'draft-after-quick-run');
    $authoring['canonical']['artifact_set']['artifact_hash'] = $authoring['canonical']['artifact_hash'];
    $authoring['verification'] = [
        'status' => 'passed',
        'run_id' => $run->id,
        'artifact_hash' => $currentArtifactHash,
        'hashes' => [
            'digital_verification' => hash('sha256', 'stale-digital'),
            'matrix_hash' => hash('sha256', 'stale-matrix'),
            'proof_hash' => hash('sha256', 'stale-proof'),
        ],
    ];
    $draft->forceFill(['authoring_json' => $authoring])->save();

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);

    expect($props['verification_run'])->toBeNull()
        ->and($props['physical_run_id'])->toBeNull();

    $status = $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk();
    expect($status->json('run_id'))->toBe($run->id)
        ->and($status->json('target_stale'))->toBeTrue()
        ->and($status->json('evidence'))->toHaveCount(2)
        ->and($status->json('methods.0.state'))->toBe('passed')
        ->and($status->json('methods.1.state'))->toBe('passed')
        ->and($status->json('physical_assurance.checklist.0.passed'))->toBeFalse();
});

test('empty plan hash is rejected by digital verification authority', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draft] = createDraftForAuthorityTest($actor, 'EMPTY-PLAN');

    $mat = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    $run = seedPassingRunForDraft($mat, $actor);
    $run->forceFill(['plan_hash' => ''])->save();

    $authority = app(CurrentOmrDigitalVerification::class);
    expect($authority->find($mat))->toBeNull();
});

test('tampered summary hash or vector/proof hash mismatch is rejected by authority', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draft] = createDraftForAuthorityTest($actor, 'TAMPER-HASH');

    $mat = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    $run = seedPassingRunForDraft($mat, $actor);

    // Case 1: Tampered summary proof_hash
    $summary = $run->result_summary;
    $summary['proof_hash'] = hash('sha256', 'tampered-proof');
    $run->forceFill(['result_summary' => $summary])->save();
    $authority = app(CurrentOmrDigitalVerification::class);
    expect($authority->find($mat))->toBeNull();

    // Case 2: Tampered matrix_hash (does not equal recomputed variants hash)
    $summary['proof_hash'] = $run->proof_hash;
    $summary['matrix_hash'] = hash('sha256', 'tampered-matrix');
    $run->forceFill(['result_summary' => $summary])->save();
    expect($authority->find($mat))->toBeNull();
});

test('unknown extra variant capture is rejected by authority', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draft] = createDraftForAuthorityTest($actor, 'EXTRA-VAR');

    $mat = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    $run = seedPassingRunForDraft($mat, $actor);

    // Add an unknown 15th variant capture
    OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'page_no' => 1,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'variant' => 'unsupported_extra_variant',
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => 'pass',
        'input_hash' => hash('sha256', 'extra:input'),
        'evidence_hash' => hash('sha256', 'extra:evidence'),
        'vector_match' => true,
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    $authority = app(CurrentOmrDigitalVerification::class);
    expect($authority->find($mat))->toBeNull();
});

test('missing proof asset is rejected by authority and compile fails', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draft] = createDraftForAuthorityTest($actor, 'NO-PROOF');

    $mat = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    $run = seedPassingRunForDraft($mat, $actor);

    // Delete all proof assets
    OmrTemplateAsset::query()
        ->where('authoring_draft_id', $mat->id)
        ->where('kind', OmrTemplateAsset::KIND_DIGITAL_PROOF)
        ->delete();

    $authority = app(CurrentOmrDigitalVerification::class);
    expect($authority->find($mat))->toBeNull();

    expect(fn () => app(CompileOmrCanonicalDraft::class)->handle($mat->fresh(), $actor))
        ->toThrow(OmrAuthoringDraftException::class, 'compile_gate_missing');
});

test('unrelated proof asset with wrong run_id or attempt among <= 10 assets is strictly rejected', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    [$draft] = createDraftForAuthorityTest($actor, 'WRONG-PROOF');

    $mat = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    $run = seedPassingRunForDraft($mat, $actor);

    // Delete matching proof asset and insert an unrelated proof asset
    OmrTemplateAsset::query()
        ->where('authoring_draft_id', $mat->id)
        ->where('kind', OmrTemplateAsset::KIND_DIGITAL_PROOF)
        ->delete();

    app(OmrTemplateAssetStore::class)->putDraftAsset(
        $mat,
        OmrTemplateAsset::KIND_DIGITAL_PROOF,
        'unrelated proof bytes',
        'image/png',
        1,
        ['run_id' => 99999, 'attempt' => 1, 'proof_hash' => $run->proof_hash]
    );

    $authority = app(CurrentOmrDigitalVerification::class);
    expect($authority->find($mat))->toBeNull();
});
