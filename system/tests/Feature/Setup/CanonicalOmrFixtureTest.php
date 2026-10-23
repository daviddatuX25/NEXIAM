<?php

use App\Assessment\Omr\Canonical\CanonicalOmrFixture;

it('pins the approved canonical answer sheet definition and every required artifact', function (): void {
    $fixture = new CanonicalOmrFixture;

    $fixture->assertIntegrity();

    expect($fixture->manifest())
        ->toMatchArray([
            'schema' => 'nexiam.omr.canonical-fixture.v1',
            'stable_code' => 'CANONICAL-HTML',
            'captured_from' => [
                'draft_id' => 1,
                'lock_version' => 502,
                'stage' => 'digital_verified',
                'template_id' => null,
                'compiled_version_id' => null,
            ],
        ])
        ->and($fixture->identities())->toBe([
            'definition_hash' => '83b531326235c24bbc04f5fd472fa934886c4dd4f817bf7d208e191561a414a7',
            'artifact_hash' => '1a21323883252a99561abf99ac13cdc2bdd0b1bc0cfeaf9194f1aa11d48ad2c5',
            'geometry_identity' => 'a21f13dfea9c0fa948e715f4bfec98dc791c5bca558d0c8b059b3ad14d01c40f',
            'native_bundle_hash' => '2cb34ed4f0a6111cb91780b48eeb3c56d6d4df28be8e6d0fce59226ba2450935',
            'review_manifest_hash' => 'aadad9120af70688297bfffa2a64221ba507f34d419ea4d91f0b55e78c6daf01',
            'config_hash' => '00cd60cfa39f8b3db902f4680de37df9ea769af34e71001e66fb6b9b1c743a52',
            'template_hash' => 'a4cd6fb0c90b97c8117d792955edb88f2ef78cda1c870ed934621827228d4a73',
            'reference_hash' => '3f536752edb0c6fefd3d7fdf81af99e783a44f57fc7cebae489b1f95e4d1bfb4',
        ])
        ->and($fixture->manifest()['assets'])->toHaveCount(9)
        ->and($fixture->verification()['captures'])->toHaveCount(14);
});
