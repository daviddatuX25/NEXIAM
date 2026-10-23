<?php

return [
    'tabfm' => [
        'driver' => env('ADMISSION_ENGINE_TABFM_DRIVER', 'http'),
        'endpoint' => rtrim((string) env('ADMISSION_ENGINE_TABFM_ENDPOINT', 'http://tabfm-inference:8101'), '/'),
        'timeout_seconds' => (float) env('ADMISSION_ENGINE_TABFM_TIMEOUT', 3.0),
        'version' => 'tabfm-pairwise-v1',
        'institutional_use_approved' => env('TABPFN_INSTITUTIONAL_USE_APPROVED', false) === true
            || (is_string(env('TABPFN_INSTITUTIONAL_USE_APPROVED', false)) && strtolower(env('TABPFN_INSTITUTIONAL_USE_APPROVED')) === 'true'),
        'hosted_use_approved' => env('TABPFN_HOSTED_USE_APPROVED', false) === true
            || (is_string(env('TABPFN_HOSTED_USE_APPROVED', false)) && strtolower(env('TABPFN_HOSTED_USE_APPROVED')) === 'true'),
        'checkpoint_id' => (string) env('TABPFN_CHECKPOINT_ID', ''),
        'checkpoint_sha256' => (string) env('TABPFN_CHECKPOINT_SHA256', ''),
        'package_version' => (string) env('TABPFN_PACKAGE_VERSION', '9.1.0'),
    ],
    'yukee' => [
        'artifact_path' => env('ADMISSION_ENGINE_YUKEE_ARTIFACT', storage_path('app/models/yukee_kmeans.joblib')),
        'version' => 'yukee-kmeans-v1',
    ],
];
