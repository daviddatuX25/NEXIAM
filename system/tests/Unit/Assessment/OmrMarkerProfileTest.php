<?php

use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('marker candidates seed from the pinned asset and remain candidates', function (): void {
    $actor = User::factory()->create();
    $profiles = app(OmrMarkerProfileService::class)->seedCandidates($actor);

    expect($profiles)->toHaveCount(2)
        ->and($profiles->pluck('status')->unique()->all())->toBe([OmrMarkerProfile::STATUS_CANDIDATE])
        ->and($profiles->pluck('asset_sha256')->unique()->all())->toBe([
            '61d39aab56d6c5ead514c0769528af56ca7f2b849ca26ebd6e9346f4ce704059',
        ]);
});

test('qualification promotes exactly one profile and retires the previous one', function (): void {
    $actor = User::factory()->create();
    $service = app(OmrMarkerProfileService::class);
    $profiles = $service->seedCandidates($actor);
    $evidence = [
        'evidence_hash' => hash('sha256', 'calibration-evidence'),
        'asset_sha256' => $profiles[0]->asset_sha256,
        'renderer_version' => 'canonical-renderer-v1',
        'printer' => 'test-printer',
        'device' => 'test-camera',
        'paper' => 'long-bond-216x330',
        'size_mm' => 12.0,
        'quiet_zone_mm' => 3.0,
    ];

    $first = $service->qualify($profiles[0], $evidence, $actor);
    $second = $service->qualify($profiles[1]->fresh(), [...$evidence, 'asset_sha256' => $profiles[1]->asset_sha256], $actor);

    expect($first->fresh()->status)->toBe(OmrMarkerProfile::STATUS_RETIRED)
        ->and($second->status)->toBe(OmrMarkerProfile::STATUS_QUALIFIED)
        ->and(OmrMarkerProfile::query()->where('status', OmrMarkerProfile::STATUS_QUALIFIED)->count())->toBe(1);
});

test('marker inset validation accepts bounded sides and rejects profile geometry edits', function (): void {
    $service = app(OmrMarkerProfileService::class);

    expect($service->validateInsets(['top' => 4, 'right' => 5, 'bottom' => 6, 'left' => 7]))
        ->toBe(['top' => 4.0, 'right' => 5.0, 'bottom' => 6.0, 'left' => 7.0])
        ->and(fn () => $service->validateInsets(['top' => 2, 'right' => 5, 'bottom' => 6, 'left' => 7]))
        ->toThrow(RuntimeException::class, 'marker_inset_invalid');
});
