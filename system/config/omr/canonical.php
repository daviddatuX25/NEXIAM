<?php

return [
    'enabled' => (bool) env('OMR_CANONICAL_ENABLED', true),
    'ppi' => 300,

    'limits' => [
        'max_upload_bytes' => 25 * 1024 * 1024,
        'max_pages_per_source' => 20,
        'max_pixels_per_page' => 20_000_000,
        'max_pixels_per_job' => 100_000_000,
        'conversion_timeout_seconds_per_page' => 300,
        'conversions_per_draft' => 1,
        'queue_concurrency' => 2,
        'blank_print_chunk' => 100,
        'undo_stack' => 200,
        'autosave_debounce_ms' => 800,
        'effective_dpi_floor' => 150.0,
        'minimum_bubble_mm' => 4.2,
        'minimum_option_edge_gap_mm' => 1.26,
        'snap_px' => 4,
        'keyboard_step_px' => 1,
        'keyboard_shift_step_px' => 10,
    ],

    'paper_mm' => [
        'min' => [127.0, 178.0],
        'max' => [216.0, 356.0],
    ],

    // Enlargement is source-type aware. A source may always be downscaled;
    // enlargement is accepted only when the resulting effective DPI remains
    // at or above this floor.
    'source_dpi_floor' => [
        'default' => 150.0,
        'html' => 300.0,
        'docx' => 300.0,
        'pdf' => 150.0,
        'image' => 150.0,
        'preset' => 242.0,
        'authoring_photo' => 150.0,
    ],

    'marker' => [
        'quadrant_split' => [2, 3],
        'required_count' => 4,
        'allowed_inset_range_mm' => [3.0, 40.0],
        // Candidate profiles may drive draft preview only. Compile and
        // activation still require measured, evidence-bound qualification.
        'candidate_preview_size_mm' => 12.0,
        'candidate_preview_quiet_zone_mm' => 3.0,
    ],

    'qr' => [
        'signature_version' => 1,
        'ecc' => 'M',
        'quiet_zone' => 4,
    ],

    'storage' => [
        'disk' => env('OMR_CANONICAL_DISK', 'local'),
        'draft_prefix' => 'omr-canonical/drafts',
        'version_prefix' => 'omr-canonical/versions',
        'temp_prefix' => 'omr-canonical/temp',
        'temp_ttl_hours' => 24,
    ],
];
