<?php

/*
 * Frozen `nexiam.omr.capture-policy.v1` contract (OpenSpec change
 * omr-profile-test-readiness, design §Registration and Capture Preflight).
 *
 * This file is the single source for the marker/frame contract. The layout
 * solver derives the marker-bearing frame geometry from these primitives and
 * `OmrCapturePolicy::derive()` fills the per-version identity (solver, bundle,
 * manifest, engine hashes) when serving the policy for an immutable version.
 *
 * Values are calibration data, not qualified thresholds: `calibration_status`
 * stays `unqualified` until the protected labeled regression and the physical
 * acceptance matrix pass (tasks 10.10/10.11).
 */

return [
    'v1' => [
        'contract' => 'nexiam.omr.capture-policy.v1',
        'capture_mode' => 'upload_first',
        'target_dimensions' => [2060, 3144],
        'frame_anchor' => [
            // Inset ring must exceed the typical printer unprintable band plus
            // the marker plus its quiet zone; the fiducial-CENTER rectangle is
            // the engine pageDimensions (frame anchoring, invariant #1).
            //
            // Marker size derives from the official OMRChecker mobile recipe:
            // paper width 2060 / sheetToMarkerWidthRatio 17 ≈ 121px at 242 PPI.
            // The marker + quiet zone stay inside normal printable margins
            // (never a borderless dependency).
            'printer_unprintable_margin' => 50,
            'marker_size' => 121,
            'marker_quiet_zone' => 40,
            'inset' => 211,
        ],
        'fiducial' => [
            // The alignment fiducial is OMRChecker's own pinned bullseye
            // (samples/sample5/omr_marker.jpg), NOT a template identifier.
            // Wrong-form identification needs a separate NEXIAM gate.
            'family' => 'omrchecker.bullseye',
            'asset_name' => 'omr_marker.jpg',
            'sheet_to_marker_width_ratio' => 17.0,
            'marker_rescale_range' => [30, 140],
            'marker_rescale_steps' => 12,
            'min_matching_threshold' => 0.3,
            'max_matching_variation' => 0.41,
        ],
        'quadrant_placement' => [
            // Engine CropOnMarkers splits the frame at w/2 × h/3; corner
            // placement puts two fiducials in the top third and two in the
            // bottom two-thirds, one per quadrant (invariant #2).
            'split' => [2, 3],
            'corners' => ['top_left', 'top_right', 'bottom_left', 'bottom_right'],
            'required_marker_count' => 4,
        ],
        'calibration_status' => 'unqualified',
    ],
];
