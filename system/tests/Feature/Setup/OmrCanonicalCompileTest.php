<?php

use App\Assessment\Actions\Omr\Canonical\CompileOmrCanonicalDraft;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('digital verified draft compiles into marker-bearing immutable version and pins assets', function (): void {
    $actor = User::factory()->create();
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
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297], 'composition_hash' => hash('sha256', 'composition'), 'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]]],
            'mapping' => ['mapping_hash' => hash('sha256', 'mapping'), 'field_blocks' => ['Questions' => ['fieldType' => 'QTYPE_MCQ4', 'fieldLabels' => ['Q_001', 'Q_002'], 'bubblesGap' => 80, 'labelsGap' => 80, 'origin' => [200, 300], 'bubbleDimensions' => [50, 50]]]],
            'verification' => [],
        ],
    ])->save();
    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    $authoring = (array) $materialized->authoring_json;
    $authoring['verification'] = [
        'status' => 'passed',
        'artifact_hash' => $authoring['canonical']['artifact_set']['artifact_hash'],
        'hashes' => ['digital_verification' => hash('sha256', 'verification')],
    ];
    $materialized->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED, 'authoring_json' => $authoring])->save();


    $saved = app(CompileOmrCanonicalDraft::class)->handle($materialized->fresh(), $actor);

    expect($saved->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_COMPILED)
        ->and($saved->compiled_version_id)->not->toBeNull()
        ->and($saved->compiledVersion->native_bundle_json['template']['pages'][0]['markers']['centers'])->toHaveCount(4)
        ->and($saved->compiledVersion->canonicalAssets)->not->toBeEmpty();
});

test('candidate profile materialization uses stable provisional geometry', function (): void {
    $actor = User::factory()->create();
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
        ->and($first->template['pages'][0]['fieldBlocks']['Part_1']['fieldLabels'])->toBe(['PART_1_Q_1', 'PART_1_Q_2'])
        ->and($first->reviewManifest['fields'])->not->toBeEmpty()
        ->and($first->file('reference-page-1.svg'))->toContain('<circle')
        ->and($first->templateHash)->toBe($second->templateHash)
        ->and($first->configHash)->toBe($second->configHash)
        ->and($first->referenceHash)->toBe($second->referenceHash)
        ->and($first->reviewManifestHash)->toBe($second->reviewManifestHash)
        ->and($first->artifactHash)->toBe($second->artifactHash);
});

test('compiled version promotes the materialized native artifact bytes', function (): void {
    $actor = User::factory()->create();
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
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'studio' => ['components' => [[
                'id' => 'Part_1', 'type' => 'mcq', 'name' => 'Part 1', 'subjectName' => 'General Ability',
                'posX' => 25, 'posY' => 70, 'w_mm' => 40, 'h_mm' => 50, 'questionsCount' => 2,
                'startQ' => 1, 'optionsCount' => 4, 'bubbleDiameterMm' => 4.5, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8,
            ]]],
            'mapping' => [
                'input' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => 1],
                'field_blocks' => ['Part_1' => [
                    'fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => ['GENERAL_Q_1..2'],
                    'bubblesGap' => 71, 'labelsGap' => 80, 'origin' => [295, 826], 'bubbleDimensions' => [53, 53],
                ]],
            ],
        ],
    ])->save();
    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
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
    $actor = User::factory()->create();
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
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'studio' => ['components' => [[
                'id' => 'Part_1', 'type' => 'mcq', 'name' => 'Part 1', 'subjectName' => 'General Ability',
                'posX' => 25, 'posY' => 70, 'w_mm' => 40, 'h_mm' => 50, 'questionsCount' => 2,
                'startQ' => 1, 'optionsCount' => 4, 'bubbleDiameterMm' => 4.5, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8,
            ]]],
            'mapping' => [
                'input' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => 1],
                'field_blocks' => ['Part_1' => [
                    'fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => ['GENERAL_Q_1..2'],
                    'bubblesGap' => 71, 'labelsGap' => 80, 'origin' => [295, 826], 'bubbleDimensions' => [53, 53],
                ]],
            ],
        ],
    ])->save();
    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
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
        ->toThrow(\App\Assessment\Omr\Canonical\OmrAuthoringDraftException::class, 'compile_candidate_stale');
});
