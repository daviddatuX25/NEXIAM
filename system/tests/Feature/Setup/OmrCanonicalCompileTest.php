<?php

use App\Assessment\Actions\Omr\Canonical\CompileOmrCanonicalDraft;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
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
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'CANONICAL-COMPILE', 'Compile', 'image');
    $assets = app(OmrTemplateAssetStore::class);
    $source = $assets->putDraftAsset($draft, OmrTemplateAsset::KIND_SOURCE, 'source', 'text/plain');
    $raster = $assets->putDraftAsset($draft, OmrTemplateAsset::KIND_RASTER_PAGE, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true), 'image/png', 1);
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
        'authoring_json' => [
            'source' => ['sha256' => $source->sha256, 'converter_version' => 'test', 'settings_hash' => hash('sha256', 'settings')],
            'pages' => [['page_no' => 1, 'asset_id' => $raster->id, 'confirmed' => true]],
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => ['settings' => ['paper_width_mm' => 216, 'paper_height_mm' => 330], 'composition_hash' => hash('sha256', 'composition'), 'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]]],
            'mapping' => ['mapping_hash' => hash('sha256', 'mapping'), 'field_blocks' => ['Questions' => ['fieldType' => 'QTYPE_MCQ4', 'fieldLabels' => ['Q_001', 'Q_002'], 'bubblesGap' => 80, 'labelsGap' => 80, 'origin' => [200, 300], 'bubbleDimensions' => [50, 50]]]],
            'verification' => ['status' => 'passed', 'hashes' => ['digital_verification' => hash('sha256', 'verification')]],
        ],
    ])->save();

    $saved = app(CompileOmrCanonicalDraft::class)->handle($draft->fresh(), $actor);

    expect($saved->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_COMPILED)
        ->and($saved->compiled_version_id)->not->toBeNull()
        ->and($saved->compiledVersion->native_bundle_json['template']['pages'][0]['markers']['centers'])->toHaveCount(4)
        ->and($saved->compiledVersion->canonicalAssets)->not->toBeEmpty();
});
