<?php

declare(strict_types=1);

$turnUrls = array_values(array_filter(array_map('trim', explode(',', (string) env('OMR_CAMERA_TURN_URLS', '')))));

return [
    'protocol_version' => 'nexiam.omr.camera.v1',
    'remote_enabled' => (bool) env('OMR_CAMERA_REMOTE_ENABLED', env('APP_ENV') === 'testing'),
    'pairing_ttl_seconds' => 3600,
    'pair_code_ttl_seconds' => 300,
    'command_execution_ttl_seconds' => 10,
    'frame_upload_ttl_seconds' => 600,
    'frame_staging_retention_seconds' => 600,
    'preview_stale_seconds' => 2,
    'max_frame_bytes' => 20 * 1024 * 1024,
    'max_signal_bytes' => 64 * 1024,
    'max_observation_bytes' => 8 * 1024,
    'max_signals_per_pair' => 64,
    'max_observations_per_pair' => 32,
    'ice_servers' => [],
    'turn' => [
        'urls' => $turnUrls,
        'shared_secret' => (string) env('OMR_CAMERA_TURN_SHARED_SECRET', ''),
        'ttl_seconds' => max(60, min(3600, (int) env('OMR_CAMERA_TURN_TTL_SECONDS', 600))),
    ],
    'frame_disk' => 'sensitive',
    'frame_directory' => 'omr/camera',
];
