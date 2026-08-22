<?php

return [
    'current' => env('OMR_QR_CURRENT_KEY_ID', 'dev-v1'),

    // Keys are supplied through environment/configuration, never committed.
    // Historical keys remain here while old printed sheets are capturable.
    'keys' => array_filter([
        'dev-v1' => env('OMR_QR_KEY_DEV_V1'),
        'legacy-v1' => env('OMR_QR_KEY_LEGACY_V1'),
    ], static fn (?string $key): bool => is_string($key) && $key !== ''),
];
