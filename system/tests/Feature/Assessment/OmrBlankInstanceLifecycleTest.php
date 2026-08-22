<?php

use App\Assessment\Omr\Canonical\OmrBlankInstanceException;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\Canonical\OmrQrPayload;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function omrActiveVersionForBlankTest(): OmrAnswerSheetTemplateVersion
{
    $template = OmrAnswerSheetTemplate::query()->create(['code' => 'BLANK-LIFECYCLE', 'name' => 'Blank lifecycle']);

    return OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'page_width' => 2551,
        'page_height' => 3898,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'config'),
        'template_json_hash' => hash('sha256', 'template'),
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => ['template' => ['pages' => [['page_no' => 1, 'pageDimensions' => [2551, 3898], 'fieldBlocks' => []]]]],
        'native_bundle_hash' => hash('sha256', 'bundle'),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'test',
        'patch_identity' => 'test',
        'review_manifest_json' => [],
        'review_manifest_hash' => hash('sha256', 'review'),
    ]);
}

test('blank instance codes carry high entropy and signed page manifest hashes', function (): void {
    config(['omr.qr-keyring.current' => 'current-v1', 'omr.qr-keyring.keys' => ['current-v1' => 'test-secret']]);
    $actor = User::factory()->create();
    $version = omrActiveVersionForBlankTest();
    $service = app(OmrBlankInstanceService::class);
    $instance = $service->createPrintedSet($version, $actor, 2, [hash('sha256', 'page-1'), hash('sha256', 'page-2')]);

    expect($instance->instance_code)->toMatch('/^NI-[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($instance->display_code)->toMatch('/^NI-[0-9A-HJKMNP-TV-Z]{11}$/')
        ->and($instance->status)->toBe('printed')
        ->and($instance->pages)->toHaveCount(2)
        ->and($instance->pages[0]->qr_payload_hash)->not->toBe($instance->pages[1]->qr_payload_hash)
        ->and($instance->qr_manifest_hash)->toHaveLength(64);
});

test('printed sets may be voided but captured and superseded sets cannot', function (): void {
    config(['omr.qr-keyring.current' => 'current-v1', 'omr.qr-keyring.keys' => ['current-v1' => 'test-secret']]);
    $actor = User::factory()->create();
    $version = omrActiveVersionForBlankTest();
    $service = app(OmrBlankInstanceService::class);
    $instance = $service->createPrintedSet($version, $actor, 1, [hash('sha256', 'page')]);

    expect($service->void($instance)->status)->toBe('void')
        ->and(fn () => $service->void($instance->fresh()))
        ->toThrow(OmrBlankInstanceException::class, 'lifecycle_invalid');
});
