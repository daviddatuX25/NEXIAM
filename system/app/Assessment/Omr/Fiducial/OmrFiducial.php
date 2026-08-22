<?php

namespace App\Assessment\Omr\Fiducial;

use RuntimeException;

use function file_get_contents;
use function hash;
use function is_file;
use function sprintf;

/**
 * Alignment-fiducial asset provider (founder decision 2026-08-19).
 *
 * The marker contract is OMRChecker's OWN pinned bullseye asset
 * (`samples/sample1` and `samples/sample5` ship the identical file):
 * `omr_marker.jpg`, a plain concentric-bullseye correlation fiducial matched
 * by `CropOnMarkers` at the four frame corners. The checked-in copy below is
 * byte-faithful to the pinned engine archive and is used for the PDF, the
 * native bundle, activation, the worker, and fixture generation.
 *
 * IMPORTANT CONTRACT BOUNDARY: this bullseye is an ALIGNMENT fiducial, not a
 * template identifier. It carries no version or form identity — photographing
 * the wrong form still registers. Wrong-form identification requires a
 * separate NEXIAM identifier gate (QR/form code or equivalent), which is
 * recorded as an open requirement, never silently claimed by this asset.
 */
final class OmrFiducial
{
    /** Upstream asset name, exactly as the pinned engine expects it. */
    public const ASSET_NAME = 'omr_marker.jpg';

    /** Sheet-to-marker width ratio from the upstream mobile recipe (sample5). */
    public const SHEET_TO_MARKER_WIDTH_RATIO = 17;

    private const ASSET_PATH = __DIR__.'/omr_marker.jpg';

    public static function assetName(): string
    {
        return self::ASSET_NAME;
    }

    /**
     * Byte-faithful copy of the pinned OMRChecker bullseye asset
     * (sha256 61d39aab56d6c5ead514c0769528af56ca7f2b849ca26ebd6e9346f4ce704059).
     */
    public static function assetBytes(): string
    {
        if (! is_file(self::ASSET_PATH)) {
            throw new RuntimeException(sprintf('The pinned OMRChecker marker asset is missing: %s.', self::ASSET_PATH));
        }

        $bytes = (string) file_get_contents(self::ASSET_PATH);

        return $bytes;
    }

    public static function assetHash(): string
    {
        return hash('sha256', self::assetBytes());
    }
}
