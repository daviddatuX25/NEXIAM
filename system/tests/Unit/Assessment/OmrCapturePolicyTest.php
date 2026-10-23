<?php

use App\Assessment\Omr\OmrCapturePolicy;

/*
 * Frozen nexiam.omr.capture-policy.v1 contract: loaded from
 * config/omr/capture-policy.php and bound to immutable bundle identity by
 * OmrCapturePolicy::derive(). Calibration stays unqualified everywhere.
 */

test('the frozen capture policy is loaded and unqualified', function (): void {
    $frozen = OmrCapturePolicy::frozen();

    expect($frozen['contract'])->toBe('nexiam.omr.capture-policy.v1')
        ->and($frozen['capture_mode'])->toBe('upload_first')
        ->and($frozen['target_dimensions'])->toBe([2060, 3144])
        ->and($frozen['fiducial']['family'])->toBe('omrchecker.bullseye')
        ->and($frozen['fiducial']['asset_name'])->toBe('omr_marker.jpg')
        ->and($frozen['quadrant_placement']['split'])->toBe([2, 3])
        ->and($frozen['quadrant_placement']['required_marker_count'])->toBe(4)
        ->and($frozen['calibration_status'])->toBe('unqualified');
});

test('frame budget derives the fiducial-center frame from the frozen primitives', function (): void {
    $budget = OmrCapturePolicy::frameBudget([2060, 3144]);
    $frozen = OmrCapturePolicy::frozen();

    $inset = $frozen['frame_anchor']['printer_unprintable_margin']
        + $frozen['frame_anchor']['marker_size']
        + $frozen['frame_anchor']['marker_quiet_zone'];

    expect($inset)->toBe($frozen['frame_anchor']['inset'])
        ->and($budget['inset'])->toBe($inset)
        ->and($budget['frame'])->toBe([2060 - 2 * $inset, 3144 - 2 * $inset]);
});

test('quadrant placement asserts two fiducials per w/2 × h/3 band', function (): void {
    $frozen = OmrCapturePolicy::frozen();
    $inset = (int) $frozen['frame_anchor']['inset'];
    $frame = [2060 - 2 * $inset, 3144 - 2 * $inset];

    // Corner placement on the derived frame satisfies the split.
    OmrCapturePolicy::assertQuadrantPlacement([2060, 3144], $frame, $inset);

    // An inset that pushes the top markers below the top third must fail.
    expect(fn () => OmrCapturePolicy::assertQuadrantPlacement([2060, 3144], [2060 - 2 * 1200, 3144 - 2 * 1200], 1200))
        ->toThrow(RuntimeException::class, 'quadrant');
});

test('derive binds solver, bundle, manifest, and engine identity without qualifying', function (): void {
    $frozen = OmrCapturePolicy::frozen();
    $inset = (int) $frozen['frame_anchor']['inset'];
    $frame = [2060 - 2 * $inset, 3144 - 2 * $inset];
    $template = [
        'pages' => [[
            'pageDimensions' => $frame,
            'markers' => [
                'family' => 'omrchecker.bullseye',
                'asset_name' => 'omr_marker.jpg',
                'size' => 121,
                'quiet_zone' => 40,
                'inset' => $inset,
                'paper' => [2060, 3144],
                'centers' => [[$inset, $inset], [2060 - $inset, $inset], [$inset, 3144 - $inset], [2060 - $inset, 3144 - $inset]],
                'quadrants' => [0, 1, 2, 3],
            ],
        ]],
    ];

    $policy = OmrCapturePolicy::derive($template, [
        'solver_version' => 'nexiam.omr.layout-solver.v1',
        'bundle_hash' => str_repeat('a', 64),
        'manifest_hash' => str_repeat('b', 64),
        'engine' => ['name' => 'OMRChecker', 'commit' => str_repeat('c', 40), 'patch_identity' => 'nexiam-headless-overlay-v1'],
    ]);

    expect($policy['contract'])->toBe('nexiam.omr.capture-policy.v1')
        ->and($policy['frame_dimensions'])->toBe($frame)
        ->and($policy['quadrant_placement']['required_marker_count'])->toBe(4)
        ->and($policy['quadrant_placement']['centers'])->toBe([[$inset, $inset], [2060 - $inset, $inset], [$inset, 3144 - $inset], [2060 - $inset, 3144 - $inset]])
        ->and($policy['identity']['solver_version'])->toBe('nexiam.omr.layout-solver.v1')
        ->and($policy['identity']['native_bundle_hash'])->toBe(str_repeat('a', 64))
        ->and($policy['identity']['review_manifest_hash'])->toBe(str_repeat('b', 64))
        ->and($policy['identity']['engine']['patch_identity'])->toBe('nexiam-headless-overlay-v1')
        ->and($policy['calibration_status'])->toBe('unqualified');
});

test('marker metadata is null for marker-less templates and typed for malformed ones', function (): void {
    expect(OmrCapturePolicy::markerMetadata(['pages' => [['pageDimensions' => [2060, 3144]]]]))->toBeNull()
        ->and(OmrCapturePolicy::markerMetadata(['pageDimensions' => [2060, 3144], 'fieldBlocks' => []]))->toBeNull();

    expect(fn () => OmrCapturePolicy::markerMetadata(['pages' => [['markers' => ['family' => 'bogus']]]]))
        ->toThrow(RuntimeException::class, 'malformed marker metadata');
});

test('capture policy derive does not leak field geometry or answer keys', function (): void {
    $frozen = OmrCapturePolicy::frozen();
    $inset = (int) $frozen['frame_anchor']['inset'];
    $template = [
        'pages' => [[
            'pageDimensions' => [2060 - 2 * $inset, 3144 - 2 * $inset],
            'markers' => [
                'family' => 'omrchecker.bullseye',
                'asset_name' => 'omr_marker.jpg',
                'size' => 121,
                'quiet_zone' => 40,
                'inset' => $inset,
                'paper' => [2060, 3144],
                'centers' => [[$inset, $inset], [2060 - $inset, $inset], [$inset, 3144 - $inset], [2060 - $inset, 3144 - $inset]],
                'quadrants' => [0, 1, 2, 3],
            ],
        ]],
    ];

    $policy = OmrCapturePolicy::derive($template, [
        'solver_version' => 'nexiam.omr.layout-solver.v1',
        'bundle_hash' => str_repeat('a', 64),
        'manifest_hash' => str_repeat('b', 64),
        'engine' => ['name' => 'OMRChecker', 'commit' => str_repeat('c', 40), 'patch_identity' => 'nexiam-headless-overlay-v1'],
    ]);

    expect($policy)->not->toHaveKey('fieldBlocks')
        ->and($policy)->not->toHaveKey('items')
        ->and($policy)->not->toHaveKey('answer_key');
});
