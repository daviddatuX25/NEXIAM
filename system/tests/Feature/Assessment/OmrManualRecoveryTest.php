<?php

use App\Assessment\Actions\Omr\Canonical\ResolveOmrBlankInstanceManually;
use App\Assessment\Omr\Canonical\OmrBlankInstanceException;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('manual recovery never overrides a present invalid signature and audits absent QR confirmation', function (): void {
    config(['omr.qr-keyring.current' => 'current-v1', 'omr.qr-keyring.keys' => ['current-v1' => 'test-secret']]);
    $actor = User::factory()->create();
    $template = OmrAnswerSheetTemplate::query()->create(['code' => 'MANUAL-RECOVERY', 'name' => 'Manual recovery']);
    $version = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id, 'version' => 1, 'status' => 'active', 'page_width' => 100, 'page_height' => 100,
        'identity_regions_json' => [], 'provider_config_json' => [], 'config_hash' => hash('sha256', 'c'), 'template_json_hash' => hash('sha256', 't'),
        'native_bundle_version' => 'v1', 'native_bundle_json' => ['template' => ['pages' => [['page_no' => 1]]]], 'native_bundle_hash' => hash('sha256', 'b'),
        'engine_name' => 'OMRChecker', 'engine_commit' => 'test', 'patch_identity' => 'test', 'review_manifest_json' => [], 'review_manifest_hash' => hash('sha256', 'r'),
    ]);
    $instance = app(OmrBlankInstanceService::class)->createPrintedSet($version, $actor, 1, [hash('sha256', 'page')]);
    $action = app(ResolveOmrBlankInstanceManually::class);

    expect(fn () => $action->handle($actor, 'present_invalid', $instance->display_code, true))
        ->toThrow(OmrBlankInstanceException::class, 'qr_signature_invalid');

    $resolved = $action->handle($actor, 'absent', $instance->display_code, true);
    expect($resolved['instance']->id)->toBe($instance->id)
        ->and($resolved['recognition_blocked'])->toBeTrue()
        ->and(AuditEvent::query()->where('event_type', 'instance_resolved_manually')->exists())->toBeTrue();
});
