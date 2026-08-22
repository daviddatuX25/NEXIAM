<?php

use App\Assessment\Actions\Omr\Canonical\ActivateOmrCanonicalTemplate;
use App\Assessment\Actions\Omr\Canonical\RunOmrPhysicalQualification;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('activation requires physical evidence and preserves the previous active version', function (): void {
    $actor = User::factory()->create();
    $template = OmrAnswerSheetTemplate::query()->create(['code' => 'ACTIVATION-TEST', 'name' => 'Activation']);
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'activation.marker', 'version' => 1, 'family' => 'test', 'asset_path' => 'marker.jpg', 'asset_sha256' => hash('sha256', 'marker'),
        'size_mm' => 12, 'quiet_zone_mm' => 3, 'matching_config' => [], 'status' => OmrMarkerProfile::STATUS_QUALIFIED,
        'qualification_evidence_hash' => hash('sha256', 'calibration'), 'created_by_user_id' => $actor->id, 'qualified_by_user_id' => $actor->id, 'qualified_at' => now(),
    ]);
    $base = [
        'template_id' => $template->id, 'version' => 1, 'page_width' => 2000, 'page_height' => 3000, 'identity_regions_json' => [], 'provider_config_json' => [],
        'config_hash' => hash('sha256', 'config'), 'template_json_hash' => hash('sha256', 'template'), 'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => ['template' => ['pages' => [['page_no' => 1, 'markers' => ['asset_name' => 'marker.jpg', 'centers' => [[0, 0], [1, 0], [0, 1], [1, 1]]]]]]],
        'native_bundle_hash' => hash('sha256', 'bundle'), 'engine_name' => 'OMRChecker', 'engine_commit' => 'test', 'patch_identity' => 'test', 'review_manifest_json' => [], 'review_manifest_hash' => hash('sha256', 'review'),
    ];
    $previous = OmrAnswerSheetTemplateVersion::query()->create([...$base, 'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE]);
    $version = OmrAnswerSheetTemplateVersion::query()->create([...$base, 'version' => 2, 'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT, 'template_json_hash' => hash('sha256', 'template-2'), 'authoring_manifest' => ['marker_profile' => ['id' => $profile->id], 'paper' => ['width_mm' => 216, 'height_mm' => 330], 'marker_insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]]]);

    expect(fn () => app(ActivateOmrCanonicalTemplate::class)->handle($version, $actor))->toThrow(RuntimeException::class, 'activation_gate_missing');

    $qualified = app(RunOmrPhysicalQualification::class)->handle($version, $actor, [
        'renderer_version' => 'canonical-renderer-v1', 'printer' => 'printer', 'device' => 'camera', 'paper' => '216x330',
        'measured_marker_center_mm' => [192, 304], 'outcomes' => ['pass'],
    ]);
    $active = app(ActivateOmrCanonicalTemplate::class)->handle($qualified, $actor);

    expect($active->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
        ->and($previous->fresh()->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_RETIRED);
});
