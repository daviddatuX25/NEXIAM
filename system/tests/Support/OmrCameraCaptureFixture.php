<?php

declare(strict_types=1);

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Operational\OmrProductionWorkbenchProjector;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateBookletPair;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

function cameraCaptureTargetProductionFixture(TestCase $test, ?array $context = null): array
{
    $fixture = $context ?? buildOmrGradingFixture($test);
    $template = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $fixture['guidance']->staffProfile->institution_id,
        'is_synthetic' => true,
        'code' => 'TARGET-'.strtoupper(substr(hash('sha256', (string) Str::uuid()), 0, 8)),
        'name' => 'Signed camera target template',
    ]);
    $version = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'registration_mode' => OmrRegistrationMode::FiducialAnchored,
        'qr_mode' => OmrQrMode::Signed,
        'page_count' => 1,
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'config'),
        'template_json_hash' => hash('sha256', 'template'),
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => [
            'template' => ['pages' => [[
                'page_no' => 1,
                'pageDimensions' => [2480, 3508],
                'fieldBlocks' => [],
                'markers' => ['family' => 'nexiam.fiducial', 'asset_name' => 'marker.jpg', 'paper' => ['width_mm' => 210, 'height_mm' => 297], 'centers' => [[0, 0], [1, 0], [0, 1], [1, 1]], 'quadrants' => [[0, 0], [1, 0], [0, 1], [1, 1]]],
            ]]],
        ],
        'native_bundle_hash' => hash('sha256', 'bundle'),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'test',
        'patch_identity' => 'test',
        'review_manifest_json' => [],
        'review_manifest_hash' => hash('sha256', 'review'),
        'authoring_manifest' => ['artifact_set' => ['artifact_hash' => str_repeat('a', 64)], 'page_count' => 1],
    ]);
    $bookletVersion = $fixture['grading']->examSession->currentMaterialSnapshot->bookletVersion;
    $pair = OmrTemplateBookletPair::query()->create([
        'template_version_id' => $version->id,
        'booklet_version_id' => $bookletVersion->id,
        'status' => OmrTemplateBookletPair::STATUS_ACTIVE,
        'approved_by_user_id' => $fixture['guidance']->id,
        'approved_at' => now(),
    ]);
    $pair->setRelation('templateVersion', $version);
    $pair->setRelation('bookletVersion', $bookletVersion);
    $snapshot = $fixture['grading']->examSession->currentMaterialSnapshot;
    if ($snapshot !== null) {
        DB::table('exam_session_material_snapshots')
            ->where('id', $snapshot->id)
            ->update([
                'template_version_id' => $version->id,
                'booklet_version_id' => $bookletVersion->id,
            ]);
        $fixture['grading']->examSession->refresh();
    }
    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $fixture['grading'],
        $fixture['guidance'],
        'camera-target-'.Str::uuid(),
    );
    $batch->setRelation('templateVersion', $version);
    $batch->setRelation('bookletVersion', $bookletVersion);
    $projection = app(OmrProductionWorkbenchProjector::class)->project(
        'production:'.$fixture['grading']->id.':'.$batch->id,
        $batch,
    );

    return compact('fixture', 'pair', 'batch', 'projection');
}
