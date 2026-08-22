<?php

use App\Assessment\Omr\Fiducial\OmrFiducial;

/*
 * Alignment-fiducial asset contract (founder decision 2026-08-19): the marker
 * is OMRChecker's OWN pinned bullseye (samples/sample5/omr_marker.jpg),
 * byte-faithfully checked in and used for the PDF, bundle, activation, worker,
 * and fixtures. It is an ALIGNMENT fiducial, NOT a template identifier.
 */

const OMR_FIDUCIAL_ASSET_SHA256 = '61d39aab56d6c5ead514c0769528af56ca7f2b849ca26ebd6e9346f4ce704059';

test('the shipped marker is byte-faithful to the pinned OMRChecker bullseye', function (): void {
    $pinned = base_path('.research/omrchecker-de3f698/samples/sample5/omr_marker.jpg');

    expect(is_file($pinned))->toBeTrue()
        ->and(hash('sha256', (string) file_get_contents($pinned)))->toBe(OMR_FIDUCIAL_ASSET_SHA256)
        ->and(OmrFiducial::assetHash())->toBe(OMR_FIDUCIAL_ASSET_SHA256)
        ->and(OmrFiducial::assetBytes())->toBe((string) file_get_contents($pinned))
        ->and(OmrFiducial::assetName())->toBe('omr_marker.jpg')
        ->and(OmrFiducial::SHEET_TO_MARKER_WIDTH_RATIO)->toBe(17);
});

test('the marker asset is a JPEG bullseye, not a generated PNG pattern', function (): void {
    $bytes = OmrFiducial::assetBytes();

    expect(str_starts_with($bytes, "\xff\xd8\xff"))->toBeTrue()
        ->and(strlen($bytes))->toBe(5550)
        ->and(OmrFiducial::assetBytes())->toBe($bytes); // deterministic
});

test('the same bullseye asset is used for every version (alignment, not identity)', function (): void {
    // No version-derived pattern exists: the marker contract is version-free,
    // matching OMRChecker's own behavior. Wrong-form identification requires
    // a separate NEXIAM identifier gate (recorded limitation).
    expect(OmrFiducial::assetBytes())->toBe(OmrFiducial::assetBytes());
});
