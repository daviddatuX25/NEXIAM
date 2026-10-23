<?php

return [

    'upload' => [
        'max_bytes' => (int) env('OMR_UPLOAD_MAX_BYTES', 10 * 1024 * 1024),
        'max_width' => (int) env('OMR_UPLOAD_MAX_WIDTH', 5000),
        'max_height' => (int) env('OMR_UPLOAD_MAX_HEIGHT', 5000),
        'max_pixels' => (int) env('OMR_UPLOAD_MAX_PIXELS', 20_000_000),
        'max_orientation_degrees' => (int) env('OMR_UPLOAD_MAX_ORIENTATION_DEGREES', 2),
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'provider' => [
        'driver' => (string) env('OMR_PROVIDER', config('app.env') === 'testing' ? 'fake' : 'omrchecker'),
        'allow_fake' => (bool) env('OMR_ALLOW_FAKE_PROVIDER', config('app.env') === 'testing'),
        'name' => (string) env('OMR_PROVIDER_NAME', 'nexiam.fake'),
        'version' => (string) env('OMR_PROVIDER_VERSION', '1'),
        'commit' => env('OMR_PROVIDER_COMMIT'),
        'request_version' => (string) env('OMR_PROVIDER_REQUEST_VERSION', 'nexiam.omr.detection.v3'),
        'deadline_seconds' => (int) env('OMR_PROVIDER_DEADLINE_SECONDS', 30),
    ],

    'omrchecker' => [
        'enabled' => (bool) env('OMR_WORKER_ENABLED', false),
        'endpoint' => rtrim((string) env('OMR_WORKER_ENDPOINT', 'http://inference:8100'), '/'),
        'signing_secret' => (string) env('OMR_WORKER_SIGNING_SECRET', ''),
        'connect_timeout_seconds' => (float) env('OMR_WORKER_CONNECT_TIMEOUT_SECONDS', 2),
        'max_clock_skew_seconds' => (int) env('OMR_WORKER_MAX_CLOCK_SKEW_SECONDS', 30),
        'max_response_bytes' => (int) env('OMR_WORKER_MAX_RESPONSE_BYTES', 25 * 1024 * 1024),
        'max_result_bytes' => (int) env('OMR_WORKER_MAX_RESULT_BYTES', 8 * 1024 * 1024),
        'max_aligned_bytes' => (int) env('OMR_WORKER_MAX_ALIGNED_BYTES', 15 * 1024 * 1024),
        'contract_version' => (string) env('OMR_WORKER_CONTRACT_VERSION', 'nexiam.omr.detection.v3'),
        'engine_name' => (string) env('OMR_WORKER_ENGINE_NAME', 'OMRChecker'),
        'engine_commit' => (string) env('OMR_WORKER_ENGINE_COMMIT', 'de3f6982822895491be760325fd43e65e90fa293'),
        'patch_identity' => (string) env('OMR_WORKER_PATCH_IDENTITY', 'nexiam-explicit-bubble-positions-v1'),
    ],

    'retry' => [
        'tries' => (int) env('OMR_RETRY_TRIES', 3),
        'timeout' => (int) env('OMR_RETRY_TIMEOUT', 60),
        'backoff' => [5, 20, 60],
        'claim_lease_seconds' => (int) env('OMR_CLAIM_LEASE_SECONDS', 80),
    ],

    'camera_enabled' => (bool) env('OMR_CAMERA_ENABLED', true),

    'keyring' => [
        'active_key_id' => (string) env('OMR_ACTIVE_KEY_ID', 'omr-demo-v1'),
        'keys' => array_filter([
            (string) env('OMR_KEY_OMR_DEMO_V1'),
        ]),
        'retired_key_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('OMR_RETIRED_KEY_IDS', ''))))),
        'provenance_hmac_key' => (string) env('OMR_PROVENANCE_HMAC_KEY', ''),
    ],

    'fake' => [
        'applicant_number' => env('OMR_FAKE_APPLICANT_NUMBER'),
        'ground_truth' => [],
        'evidence_cases' => [],
    ],

];
