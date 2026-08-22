<?php

use App\Assessment\Omr\Canonical\OmrMarkerCalibrationArtifactGenerator;
use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('marker calibration artifact is template-independent and hash-bound', function (): void {
    Storage::fake('local');
    $actor = User::factory()->create();
    $profile = app(OmrMarkerProfileService::class)->seedCandidates($actor)->first();
    $artifact = app(OmrMarkerCalibrationArtifactGenerator::class)->generate($profile, [
        'renderer_version' => 'canonical-renderer-v1',
        'printer' => 'test-printer',
        'device' => 'test-camera',
        'paper' => 'long-bond-216x330',
    ]);

    $svg = Storage::disk('local')->get($artifact['path']);

    expect($artifact['asset_sha256'])->toBe($profile->asset_sha256)
        ->and($artifact['evidence_hash'])->toHaveLength(64)
        ->and(substr_count($svg, '<image '))->toBe(4)
        ->and($svg)->toContain('controlled-false-positive-corpus')
        ->and($svg)->toContain('imported_supplier_border')
        ->and($svg)->not->toContain('template_version_id');
});
