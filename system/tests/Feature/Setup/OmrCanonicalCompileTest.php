<?php

use App\Assessment\Actions\Omr\Canonical\CompileOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function omrCanonicalCompileActor(): User
{
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    return $actor;
}

function createPassingDigitalRun(OmrTemplateAuthoringDraft $draft, User $actor, ?string $artifactHash = null): OmrTemplateVerificationRun
{
    $artifactSet = (array) ($draft->authoring_json['canonical']['artifact_set'] ?? []);
    $hash = $artifactHash ?? (string) ($artifactSet['artifact_hash'] ?? $draft->authoring_json['canonical']['artifact_hash'] ?? hash('sha256', 'artifact'));
    $mappingHash = (string) ($artifactSet['geometry_identity'] ?? $artifactSet['mapping_hash'] ?? $draft->authoring_json['mapping']['mapping_hash'] ?? hash('sha256', 'mapping'));
    $isDocBound = $draft->registration_mode === OmrRegistrationMode::DocumentBound;
    $variants = OmrNoGoMatrixRunner::variantKeys((int) $draft->requested_page_count, $draft->registration_mode);
    $variantSummary = [];
    foreach ($variants as $v) {
        if ($isDocBound) {
            $parsed = OmrNoGoMatrixRunner::parseVariantKey($v, OmrRegistrationMode::DocumentBound);
            $exact = OmrNoGoMatrixRunner::expectsExactRecovery(OmrRegistrationMode::DocumentBound, $parsed['variant']);
            $expected = $exact ? 'pass' : 'typed_rejection';
            $rectNode = [
                'attempted' => true,
                'applied' => $exact,
                'mode' => 'document_bound',
                'status' => $exact ? 'success' : 'no_candidates',
                'plausible_candidate_count' => $exact ? 1 : 0,
                'selected_candidate_index' => $exact ? 0 : -1,
                'homography_count' => $exact ? 1 : 0,
                'warp_count' => $exact ? 1 : 0,
                'document_gates' => ['accepted' => $exact],
                'content_gate' => ['accepted' => $exact],
                'homography_hash' => 'sha256:'.hash('sha256', 'homography:'.$v),
                'quad_normalized' => [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]],
            ];
            $rect = [
                'accepted' => $exact,
                'same_selected_warp' => $exact,
                'layout' => $rectNode,
                'detection' => $rectNode,
            ];
            $variantSummary[$v] = [
                'status' => 'pass',
                'expected' => $expected,
                'actual' => $expected,
                'registration_mode' => 'document_bound',
                'rectification' => $rect,
                ...($exact ? [] : ['worker_codes' => ['layout' => OmrNoGoMatrixRunner::expectedDocumentRejectionCodes($parsed['variant'])[0], 'detection' => OmrNoGoMatrixRunner::expectedDocumentRejectionCodes($parsed['variant'])[0]]]),
            ];
        } else {
            $variantSummary[$v] = ['status' => 'pass', 'expected' => 'pass', 'actual' => 'pass'];
        }
    }

    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => hash('sha256', 'plan:'.$hash),
        'expected_vector_hash' => hash('sha256', 'expected_vector:'.$hash),
        'clean_vector_hash' => hash('sha256', 'clean_vector:'.$hash),
        'proof_hash' => hash('sha256', 'proof:'.$hash),
        'artifact_hash' => $hash,
        'native_bundle_hash' => hash('sha256', 'native_bundle:'.$hash),
        'manifest_hash' => hash('sha256', 'manifest:'.$hash),
        'config_hash' => hash('sha256', 'config:'.$hash),
        'mapping_hash' => $mappingHash,
        'progress_done' => count($variants),
        'progress_total' => count($variants),
        'result_summary' => [
            'matrix_hash' => hash('sha256', json_encode($variantSummary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'variants' => $variantSummary,
            'proof_hash' => hash('sha256', 'proof:'.$hash),
            'expected_vector_hash' => hash('sha256', 'expected_vector:'.$hash),
            'clean_vector_hash' => hash('sha256', 'clean_vector:'.$hash),
            'threshold_status' => 'Unqualified',
        ],
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'finished_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    app(OmrTemplateAssetStore::class)->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_DIGITAL_PROOF,
        'proof bytes',
        'image/png',
        1,
        ['run_id' => $run->id, 'attempt' => 1, 'proof_hash' => hash('sha256', 'proof:'.$hash)]
    );
    foreach ($variants as $v) {
        $captureIdentity = OmrNoGoMatrixRunner::parseVariantKey($v, $draft->registration_mode);
        $summary = $variantSummary[$v];
        OmrTemplateVerificationCapture::query()->create([
            'run_id' => $run->id,
            'attempt' => 1,
            'page_no' => $captureIdentity['page_no'],
            'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
            'variant' => $v,
            'expected' => $summary['expected'],
            'actual' => $summary['actual'],
            'status' => 'pass',
            'summary' => $summary,
            'input_hash' => hash('sha256', $v.':input'),
            'evidence_hash' => hash('sha256', $v.':evidence'),
            'vector_match' => $isDocBound ? ($summary['expected'] === 'pass' ? true : null) : true,
            'captured_by_user_id' => $actor->id,
            'captured_at' => now(),
        ]);
    }

    return $run;
}
test('digital verified draft compiles into marker-bearing immutable version and pins assets', function (): void {
    Storage::fake('local');
    seedE2Foundation();
    AptitudeArea::query()->updateOrCreate(
        ['code' => 'LOGIC'],
        ['name' => 'Logic', 'max_items' => 2, 'display_order' => 1, 'is_active' => true],
    );
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo([
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_SCORING_OMR,
    ]);
    $institution = Institution::current();
    $booklet = app(CreateBookletDraft::class)->handle(
        'Compiled candidate selection',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $actor,
        isSynthetic: false,
    );
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'compile.marker',
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
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'CANONICAL-COMPILE', 'Compile', 'html');
    $assets = app(OmrTemplateAssetStore::class);
    $source = $assets->putDraftAsset($draft, OmrTemplateAsset::KIND_SOURCE, 'source', 'text/plain');
    $raster = $assets->putDraftAsset($draft, OmrTemplateAsset::KIND_RASTER_PAGE, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true), 'image/png', 1);
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'source' => ['sha256' => $source->sha256, 'converter_version' => 'test', 'settings_hash' => hash('sha256', 'settings')],
            'pages' => [['page_no' => 1, 'asset_id' => $raster->id, 'confirmed' => true]],
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'studio' => ['document_fields' => [[
                'id' => 'applicant-name',
                'type' => 'candidate_name',
                'label' => 'Applicant Name',
                'page_no' => 1,
                'x_mm' => 22,
                'y_mm' => 22,
                'w_mm' => 20,
                'h_mm' => 10,
            ]]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297], 'composition_hash' => hash('sha256', 'composition'), 'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]]],
            'mapping' => ['mapping_hash' => hash('sha256', 'mapping'), 'field_blocks' => ['Questions' => ['fieldType' => 'QTYPE_MCQ4', 'fieldLabels' => ['LOGIC_Q_001', 'LOGIC_Q_002'], 'bubblesGap' => 80, 'labelsGap' => 80, 'origin' => [200, 300], 'bubbleDimensions' => [50, 50]]]],
            'verification' => [],
        ],
    ])->save();
    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    createPassingDigitalRun($materialized, $actor);
    $authoring = (array) $materialized->authoring_json;
    $authoring['verification'] = [
        'status' => 'passed',
        'artifact_hash' => $authoring['canonical']['artifact_set']['artifact_hash'],
        'hashes' => ['digital_verification' => hash('sha256', 'verification')],
    ];
    $materialized->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED, 'authoring_json' => $authoring])->save();

    $saved = app(CompileOmrCanonicalDraft::class)->handle($materialized->fresh(), $actor);
    $resolvedBooklet = app(CreateBookletDraft::class)->handle(
        'Compiled candidate default selection',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $actor,
        isSynthetic: false,
    );

    expect($saved->compiledVersion->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_COMPILED)
        ->and($saved->compiledVersion->native_bundle_hash)->not->toBeEmpty()
        ->and($saved->compiledVersion->native_bundle_json)->toBeArray()
        ->and($saved->compiledVersion->activated_at)->toBeNull()
        ->and($saved->compiledVersion->template->institution_id)->toBe($institution->id)
        ->and($booklet->fresh()->template_version_id)->toBeNull()
        ->and($resolvedBooklet->template_version_id)->toBeNull();

    $bundle = NativeBundle::loadForVersion($saved->compiledVersion);
    Storage::disk('local')->put($saved->compiledVersion->native_bundle_json['relative_dir'].'/omr_marker.jpg', 'tampered marker bytes');

    expect($saved->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_COMPILED)
        ->and($saved->compiled_version_id)->not->toBeNull()
        ->and($saved->compiledVersion->native_bundle_json['template']['pages'][0]['markers']['centers'])->toHaveCount(4)
        ->and($saved->compiledVersion->review_manifest_json['document_fields'][0]['region'])->toBe([
            'x' => 0.0538,
            'y' => 0.0369,
            'w' => 0.1075,
            'h' => 0.0369,
        ])
        ->and($saved->compiledVersion->canonicalAssets)->not->toBeEmpty()
        ->and(fn () => $bundle->assetBytes('omr_marker.jpg'))
        ->toThrow(RuntimeException::class, 'hash mismatch after load');
});

test('candidate profile materialization uses stable provisional geometry', function (): void {
    $actor = omrCanonicalCompileActor();
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'materialize.marker',
        'version' => 1,
        'family' => 'nexiam.fiducial',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'size_mm' => null,
        'quiet_zone_mm' => null,
        'matching_config' => [],
        'status' => OmrMarkerProfile::STATUS_CANDIDATE,
        'qualification_evidence_hash' => null,
        'created_by_user_id' => $actor->id,
        'qualified_by_user_id' => null,
        'qualified_at' => null,
    ]);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'CANONICAL-MATERIALIZE', 'Materialize', 'html');
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'pages' => [['page_no' => 1, 'confirmed' => true]],
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'studio' => ['components' => [[
                'id' => 'Part_1',
                'type' => 'mcq',
                'name' => 'Part 1',
                'subjectName' => 'General Ability',
                'posX' => 25,
                'posY' => 70,
                'w_mm' => 40,
                'h_mm' => 50,
                'questionsCount' => 2,
                'startQ' => 1,
                'optionsCount' => 4,
                'bubbleDiameterMm' => 4.5,
                'bubblePitchMm' => 6,
                'rowPitchMm' => 6.8,
                'showSectionHeader' => true,
            ]]],
            'mapping' => [
                'input' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => 1],
                'field_blocks' => ['Part_1' => [
                    'fieldType' => 'QTYPE_MCQ4',
                    'direction' => 'horizontal',
                    'fieldLabels' => ['q1', 'q2'],
                    'bubblesGap' => 71,
                    'labelsGap' => 80,
                    'origin' => [295, 826],
                    'bubbleDimensions' => [53, 53],
                ]],
            ],
        ],
    ])->save();

    $first = app(CanonicalArtifactMaterializer::class)->materialize($draft->fresh(['template', 'markerProfile']), $profile, 1);
    $second = app(CanonicalArtifactMaterializer::class)->materialize($draft->fresh(['template', 'markerProfile']), $profile, 1);

    expect($first->pages[0]['width'])->toBe(2480)
        ->and($first->pages[0]['height'])->toBe(3508)
        ->and($first->template['pages'][0]['markers']['centers'])->toHaveCount(4)
        ->and($first->template['pages'][0]['markers']['geometry_status'])->toBe('provisional')
        ->and($first->manifest['marker_profile']['status'])->toBe(OmrMarkerProfile::STATUS_CANDIDATE)
        ->and($first->template['pages'][0]['fieldBlocks']['Part_1']['fieldLabels'])->toBe(['q1', 'q2'])
        ->and($first->reviewManifest['fields'])->not->toBeEmpty()
        ->and($first->file('reference-page-1.svg'))->toContain('<circle')
        ->and($first->templateHash)->toBe($second->templateHash)
        ->and($first->configHash)->toBe($second->configHash)
        ->and($first->referenceHash)->toBe($second->referenceHash)
        ->and($first->reviewManifestHash)->toBe($second->reviewManifestHash)
        ->and($first->artifactHash)->toBe($second->artifactHash);
});

test('compiled version promotes the materialized native artifact bytes', function (): void {
    seedE2Foundation();
    $actor = omrCanonicalCompileActor();
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'promote.marker',
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
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'CANONICAL-PROMOTE', 'Promote', 'html');
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'pages' => [['page_no' => 1, 'confirmed' => true]],
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'studio' => ['components' => [[
                'id' => 'Part_1', 'type' => 'mcq', 'name' => 'Part 1', 'subjectName' => 'General Ability',
                'posX' => 25, 'posY' => 70, 'w_mm' => 40, 'h_mm' => 50, 'aptitudeAreaCode' => 'LOGIC', 'questionsCount' => 2,
                'startQ' => 1, 'optionsCount' => 4, 'bubbleDiameterMm' => 4.5, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8,
            ]]],
            'mapping' => [
                'input' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => 1],
                'field_blocks' => ['Part_1' => [
                    'fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => ['LOGIC_Q_1..2'],
                    'bubblesGap' => 71, 'labelsGap' => 80, 'origin' => [295, 826], 'bubbleDimensions' => [53, 53],
                ]],
            ],
        ],
    ])->save();
    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    createPassingDigitalRun($materialized, $actor);
    $authoring = (array) $materialized->authoring_json;
    $authoring['verification'] = ['status' => 'passed', 'artifact_hash' => $authoring['canonical']['artifact_set']['artifact_hash'], 'hashes' => ['digital_verification' => hash('sha256', 'verification')]];
    $materialized->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED, 'authoring_json' => $authoring])->save();
    $compiled = app(CompileOmrCanonicalDraft::class)->handle($materialized->fresh(), $actor);

    $nativeTemplateAsset = $compiled->compiledVersion->canonicalAssets->firstWhere('kind', OmrTemplateAsset::KIND_NATIVE_TEMPLATE);
    $nativeBytes = $nativeTemplateAsset === null ? '' : Storage::disk($nativeTemplateAsset->disk)->get($nativeTemplateAsset->path);
    $nativeTemplate = json_decode($nativeBytes, true, 512, JSON_THROW_ON_ERROR);
    expect($nativeTemplateAsset)->not->toBeNull()
        ->and($nativeTemplate['definitionHash'])->toBe($authoring['canonical']['artifact_set']['definition_hash'])
        ->and($nativeTemplate['visualComponents'][0]['subjectName'])->toBe('General Ability')
        ->and($compiled->compiledVersion->native_bundle_json['manifest']['files']['template.json'])->toBe($authoring['canonical']['artifact_set']['template_hash'])
        ->and($compiled->compiledVersion->canonicalAssets)->not->toBeEmpty();
});

test('compile rejects a candidate materialized before marker qualification geometry changed', function (): void {
    $actor = omrCanonicalCompileActor();
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'stale-marker',
        'version' => 1,
        'family' => 'nexiam.fiducial',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'matching_config' => [],
        'status' => OmrMarkerProfile::STATUS_CANDIDATE,
        'created_by_user_id' => $actor->id,
    ]);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'STALE-MARKER', 'Stale marker', 'html');
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'pages' => [['page_no' => 1, 'confirmed' => true]],
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'studio' => ['components' => [[
                'id' => 'Part_1', 'type' => 'mcq', 'name' => 'Part 1', 'subjectName' => 'General Ability',
                'posX' => 25, 'posY' => 70, 'w_mm' => 40, 'h_mm' => 50, 'aptitudeAreaCode' => 'LOGIC', 'questionsCount' => 2,
                'startQ' => 1, 'optionsCount' => 4, 'bubbleDiameterMm' => 4.5, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8,
            ]]],
            'mapping' => [
                'input' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => 1],
                'field_blocks' => ['Part_1' => [
                    'fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => ['LOGIC_Q_1..2'],
                    'bubblesGap' => 71, 'labelsGap' => 80, 'origin' => [295, 826], 'bubbleDimensions' => [53, 53],
                ]],
            ],
        ],
    ])->save();
    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    createPassingDigitalRun($materialized, $actor);
    $authoring = (array) $materialized->authoring_json;
    $authoring['verification'] = [
        'status' => 'passed',
        'artifact_hash' => $authoring['canonical']['artifact_set']['artifact_hash'],
        'hashes' => ['digital_verification' => hash('sha256', 'verification')],
    ];
    $materialized->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED, 'authoring_json' => $authoring])->save();
    $profile->forceFill([
        'status' => OmrMarkerProfile::STATUS_QUALIFIED,
        'size_mm' => 13,
        'quiet_zone_mm' => 3.5,
        'qualification_evidence_hash' => hash('sha256', 'qualified'),
        'qualified_by_user_id' => $actor->id,
        'qualified_at' => now(),
    ])->save();

    expect(fn () => app(CompileOmrCanonicalDraft::class)->handle($materialized->fresh(), $actor))
        ->toThrow(OmrAuthoringDraftException::class, 'compile_candidate_stale');
});

test('document-bound QR-disabled candidate uses full-paper pages without fiducial or production QR', function (): void {
    seedE2Foundation();
    AptitudeArea::query()->create([
        'code' => 'GENERAL',
        'name' => 'General Ability',
        'max_items' => 2,
        'display_order' => 1,
        'is_active' => true,
    ]);
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'DOCUMENT-BOUND-COMPILE',
        'Document-bound two-page',
        'html',
        [
            'pages' => [['page_no' => 1, 'confirmed' => true], ['page_no' => 2, 'confirmed' => true]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'mapping' => ['field_blocks' => [
                'Page_1' => [
                    'page_no' => 1, 'fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal',
                    'fieldLabels' => ['GENERAL_Q_1'], 'bubblesGap' => 70, 'labelsGap' => 80,
                    'origin' => [250, 800], 'bubbleDimensions' => [50, 50],
                ],
                'Page_2' => [
                    'page_no' => 2, 'fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal',
                    'fieldLabels' => ['GENERAL_Q_2'], 'bubblesGap' => 70, 'labelsGap' => 80,
                    'origin' => [250, 800], 'bubbleDimensions' => [50, 50],
                ],
            ]],
        ],
        OmrRegistrationMode::DocumentBound,
        OmrQrMode::Disabled,
        2,
    );
    $saved = app(MaterializeOmrCanonicalDraft::class)->handle($draft, $actor, 0);
    $artifactSet = $saved->authoring_json['canonical']['artifact_set'];
    $artifact = app(CanonicalArtifactMaterializer::class)->loadPersisted($saved, null, true);

    expect($artifactSet['compiled_pages'])->toBe([['page_no' => 1], ['page_no' => 2]])
        ->and($artifact->template['pages'])->toHaveCount(2)
        ->and($artifact->template['pages'][0]['pageDimensions'])->toBe([2480, 3508])
        ->and($artifact->template['pages'][0]['preProcessors'])->toBe([])
        ->and($artifact->template['pages'][0])->not->toHaveKey('markers')
        ->and($artifact->manifest)->not->toHaveKey('marker_profile')
        ->and($artifact->reviewManifest)->not->toHaveKey('qr_reservation')
        ->and($artifact->files)->not->toHaveKey('fiducial.jpg')
        ->and($artifact->pdfBytes)->toStartWith('%PDF-')
        ->and($artifact->artifactHash)->toBe($artifactSet['artifact_hash']);

    createPassingDigitalRun($saved, $actor);
    $authoring = (array) $saved->authoring_json;
    $authoring['verification'] = [
        'status' => 'passed',
        'artifact_hash' => $artifactSet['artifact_hash'],
        'hashes' => ['digital_verification' => hash('sha256', 'verification:'.$artifactSet['artifact_hash'])],
    ];
    $saved->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
        'authoring_json' => $authoring,
    ])->save();
    $compiledDraft = app(CompileOmrCanonicalDraft::class)->handle($saved->fresh(), $actor);
    $compiledVersion = $compiledDraft->compiledVersion;
    $compiledBundle = NativeBundle::loadForVersion($compiledVersion);
    expect($compiledVersion->page_count)->toBe(2)
        ->and($compiledVersion->native_bundle_json['manifest']['registration_mode'])->toBe('document_bound')
        ->and($compiledVersion->review_manifest_json['compiled_pages'])->toBe([['page_no' => 1], ['page_no' => 2]])
        ->and($compiledBundle->hasMarkerPages())->toBeFalse();
    $diagnosticTarget = new CompiledVersionDiagnosticTarget($compiledVersion);
    expect($diagnosticTarget->qualificationCapable())->toBeTrue();
    $authoring = $saved->authoring_json;
    $authoring['canonical']['artifact_set']['compiled_pages'] = [['page_no' => 2], ['page_no' => 1]];
    $saved->forceFill(['authoring_json' => $authoring])->save();
    expect(fn () => app(CanonicalArtifactMaterializer::class)->loadPersisted($saved->fresh(), null))
        ->toThrow(RuntimeException::class, 'canonical_persisted_page_mismatch');
});

test('two rematerializations pin only current assets with page-bound identity geometry', function (): void {
    Storage::fake('local');
    AptitudeArea::query()->create([
        'code' => 'GENERAL',
        'name' => 'General Ability',
        'max_items' => 2,
        'display_order' => 1,
        'is_active' => true,
    ]);
    $actor = omrCanonicalCompileActor();
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'rematerialize.marker',
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
    $fieldBlocks = [];
    foreach ([1, 2] as $pageNo) {
        $offset = ($pageNo - 1) * 25;
        $fieldBlocks['Applicant_'.$pageNo] = [
            'page_no' => $pageNo,
            'fieldType' => 'QTYPE_INT',
            'direction' => 'horizontal',
            'fieldLabels' => ['APPLICANT_NUMBER_1..10'],
            'bubblesGap' => 65,
            'labelsGap' => 80,
            'origin' => [220, 180 + $offset],
            'bubbleDimensions' => [50, 50],
        ];
        $fieldBlocks['Birthdate_'.$pageNo] = [
            'page_no' => $pageNo,
            'fieldType' => 'QTYPE_INT',
            'direction' => 'horizontal',
            'fieldLabels' => ['BIRTHDATE_1..6'],
            'bubblesGap' => 65,
            'labelsGap' => 80,
            'origin' => [220, 1250 + $offset],
            'bubbleDimensions' => [50, 50],
        ];
        $fieldBlocks['Questions_'.$pageNo] = [
            'page_no' => $pageNo,
            'fieldType' => 'QTYPE_MCQ4',
            'direction' => 'horizontal',
            'fieldLabels' => ['GENERAL_Q_'.$pageNo],
            'bubblesGap' => 70,
            'labelsGap' => 80,
            'origin' => [220, 2100 + $offset],
            'bubbleDimensions' => [50, 50],
        ];
    }
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'REMATERIALIZE-ASSET-IDENTITY',
        'Two-page rematerialization',
        'html',
        [
            'pages' => [['page_no' => 1, 'confirmed' => true], ['page_no' => 2, 'confirmed' => true]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'mapping' => ['field_blocks' => $fieldBlocks],
        ],
        OmrRegistrationMode::FiducialAnchored,
        OmrQrMode::Signed,
        2,
    );
    $draft->forceFill(['marker_profile_id' => $profile->id])->save();
    $first = app(MaterializeOmrCanonicalDraft::class)->handle($draft, $actor, 0);
    $firstArtifactSet = (array) $first->authoring_json['canonical']['artifact_set'];

    $changedAuthoring = (array) $first->authoring_json;
    $changedAuthoring['mapping']['field_blocks']['Questions_2']['origin'][0] += 90;
    $first->forceFill([
        'authoring_json' => $changedAuthoring,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'lock_version' => (int) $first->lock_version + 1,
    ])->save();
    $second = app(MaterializeOmrCanonicalDraft::class)->handle(
        $first->fresh(),
        $actor,
        (int) $first->lock_version,
    );
    $artifactSet = (array) $second->authoring_json['canonical']['artifact_set'];
    $run = createPassingDigitalRun($second, $actor);
    $authoring = (array) $second->authoring_json;
    $authoring['verification'] = [
        'status' => 'passed',
        'artifact_hash' => $artifactSet['artifact_hash'],
        'hashes' => ['digital_verification' => hash('sha256', 'verification:'.$artifactSet['artifact_hash'])],
    ];
    $second->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
        'authoring_json' => $authoring,
    ])->save();
    $compiled = app(CompileOmrCanonicalDraft::class)->handle($second->fresh(), $actor);
    $version = $compiled->compiledVersion->fresh();
    $nativeBundle = NativeBundle::loadForVersion($version);
    $versionAssets = $version->canonicalAssets()->get();

    $expectedAssetIds = [
        (int) $artifactSet['native_template_asset_id'],
        (int) $artifactSet['native_config_asset_id'],
        (int) $artifactSet['native_manifest_asset_id'],
        (int) $artifactSet['pdf_asset_id'],
        ...($artifactSet['marker_asset_id'] ? [(int) $artifactSet['marker_asset_id']] : []),
        ...array_map(static fn (array $asset): int => (int) $asset['asset_id'], (array) $artifactSet['raster_assets']),
        ...array_map(static fn (array $asset): int => (int) $asset['asset_id'], (array) $artifactSet['reference_assets']),
    ];
    $expectedAssetIds = array_values(array_unique($expectedAssetIds));
    sort($expectedAssetIds);
    $pinnedAssetIds = $versionAssets
        ->map(static fn (OmrTemplateAsset $asset): int => (int) ($asset->metadata['pinned_from_asset_id'] ?? 0))
        ->all();
    sort($pinnedAssetIds);
    $printAssets = $versionAssets->where('kind', OmrTemplateAsset::KIND_PRINT_PDF)->values();
    $reviewManifestFields = (array) $artifactSet['review_manifest']['fields'];
    $regionsForPage = static function (string $labelPrefix) use ($reviewManifestFields): array {
        $regions = [];
        foreach ($reviewManifestFields as $field) {
            if ((int) ($field['page'] ?? 0) === 2 && str_starts_with((string) ($field['label'] ?? ''), $labelPrefix)) {
                $regions[] = $field['region'];
            }
        }

        return OmrTemplateGeometryProjector::unionRegions($regions);
    };

    expect($firstArtifactSet['artifact_hash'])->not->toBe($artifactSet['artifact_hash'])
        ->and($firstArtifactSet['pdf_asset_id'])->not->toBe($artifactSet['pdf_asset_id'])
        ->and($pinnedAssetIds)->toBe($expectedAssetIds)
        ->and($pinnedAssetIds)->not->toContain((int) $firstArtifactSet['pdf_asset_id'])
        ->and($printAssets)->toHaveCount(1)
        ->and($printAssets[0]->sha256)->toBe($artifactSet['pdf_hash'])
        ->and((int) $printAssets[0]->metadata['pinned_from_asset_id'])->toBe((int) $artifactSet['pdf_asset_id'])
        ->and($run->artifact_hash)->toBe($artifactSet['artifact_hash'])
        ->and($version->authoring_manifest['artifact_set']['artifact_hash'])->toBe($run->artifact_hash)
        ->and($version->native_bundle_hash)->toBe($nativeBundle->bundleHash)
        ->and($version->native_bundle_json['files'])->toBe($nativeBundle->fileHashes)
        ->and($nativeBundle->fileHashes['template.json'])->toBe($artifactSet['template_hash'])
        ->and($nativeBundle->fileHashes['config.json'])->toBe($artifactSet['config_hash'])
        ->and($version->identity_regions_json[2]['applicant_number'])->toBe($regionsForPage('APPLICANT_NUMBER_'))
        ->and($version->identity_regions_json[2]['birthdate'])->toBe($regionsForPage('BIRTHDATE_'))
        ->and($version->identity_regions_json[2])->not->toBeEmpty();
});

test('native bundle validation rejects a fifth ordered page', function (): void {
    Storage::fake('local');
    $disk = Storage::disk('local');
    $directory = 'native-bundle-page-limit';
    $disk->makeDirectory($directory);
    $disk->put($directory.'/template.json', json_encode([
        'bubbleDimensions' => [50, 50],
        'pages' => array_map(
            static fn (int $pageNo): array => ['page_no' => $pageNo],
            range(1, 5),
        ),
    ], JSON_THROW_ON_ERROR));
    $disk->put($directory.'/config.json', '{}');
    $disk->put($directory.'/manifest.json', '{}');
    $disk->put($directory.'/reference-page-1.svg', '<svg/>');

    expect(fn () => NativeBundle::loadFromDirectory($disk->path($directory), 'PAGE-LIMIT', 1))
        ->toThrow(RuntimeException::class, 'between one and four ordered pages');
});

test('draft with matching template code compiles to next version when template_id is null', function (): void {
    Storage::fake('local');
    seedE2Foundation();
    AptitudeArea::query()->updateOrCreate(
        ['code' => 'LOGIC'],
        ['name' => 'Logic', 'max_items' => 2, 'display_order' => 1, 'is_active' => true],
    );
    $actor = omrCanonicalCompileActor();
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'compile.marker.v2',
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
    $institution = Institution::query()->firstOrFail();
    $template = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CODE-COLLISION-TEST',
        'name' => 'Code Collision Test',
        'is_synthetic' => false,
    ]);
    OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
        'registration_mode' => OmrRegistrationMode::FiducialAnchored,
        'qr_mode' => OmrQrMode::Signed,
        'page_count' => 1,
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => [],
        'provider_config_json' => ['driver' => 'omrchecker'],
        'config_hash' => hash('sha256', 'config'),
        'template_json_hash' => hash('sha256', 'template'),
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => ['template' => [], 'config' => [], 'manifest' => ['bundle_version' => 'nexiam.omr.native-bundle.v1', 'engine' => ['name' => 'OMRChecker']], 'files' => []],
        'native_bundle_hash' => hash('sha256', 'bundle'),
        'review_manifest_json' => [],
        'review_manifest_hash' => hash('sha256', 'review'),
        'authoring_manifest' => [],
    ]);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'CODE-COLLISION-TEST', 'Compile Next', 'html');

    $assets = app(OmrTemplateAssetStore::class);
    $source = $assets->putDraftAsset($draft, OmrTemplateAsset::KIND_SOURCE, 'source', 'text/plain');
    $raster = $assets->putDraftAsset($draft, OmrTemplateAsset::KIND_RASTER_PAGE, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true), 'image/png', 1);
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'source' => ['sha256' => $source->sha256, 'converter_version' => 'test', 'settings_hash' => hash('sha256', 'settings')],
            'pages' => [['page_no' => 1, 'asset_id' => $raster->id, 'confirmed' => true]],
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297], 'composition_hash' => hash('sha256', 'composition'), 'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]]],
            'mapping' => ['mapping_hash' => hash('sha256', 'mapping'), 'field_blocks' => ['Questions' => ['fieldType' => 'QTYPE_MCQ4', 'fieldLabels' => ['LOGIC_Q_001', 'LOGIC_Q_002'], 'bubblesGap' => 80, 'labelsGap' => 80, 'origin' => [200, 300], 'bubbleDimensions' => [50, 50]]]],
            'verification' => [],
        ],
    ])->save();

    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    createPassingDigitalRun($materialized, $actor);
    $authoring = (array) $materialized->authoring_json;
    $authoring['verification'] = [
        'status' => 'passed',
        'artifact_hash' => $authoring['canonical']['artifact_set']['artifact_hash'],
        'hashes' => ['digital_verification' => hash('sha256', 'verification')],
    ];
    $materialized->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED, 'authoring_json' => $authoring])->save();

    $saved = app(CompileOmrCanonicalDraft::class)->handle($materialized->fresh(), $actor);
    expect($saved->compiledVersion->version)->toBe(2);

    // Compile is also idempotent when already compiled to the current bundle hash
    $recompiled = app(CompileOmrCanonicalDraft::class)->handle($saved->fresh(), $actor);
    expect($recompiled->id)->toBe($saved->id)
        ->and($recompiled->compiled_version_id)->toBe($saved->compiled_version_id);
});
