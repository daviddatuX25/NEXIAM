<?php

declare(strict_types=1);

return [
    'domain' => env('JITSI_DOMAIN'),
    'origin' => env('JITSI_ORIGIN'),
    'app_id' => env('JITSI_APP_ID'),
    'app_secret' => env('JITSI_APP_SECRET'),
    'audience' => env('JITSI_JWT_AUDIENCE'),
    'jwt_ttl_seconds' => (int) env('JITSI_JWT_TTL_SECONDS', 900),
    'direct_ttl_seconds' => 1800,
    'scheduled_window_seconds' => 3600,
    'admission_secret' => env('JITSI_ADMISSION_SECRET'),
    'control_url' => env('JITSI_CONTROL_URL'),
    'admission_url' => env('JITSI_ADMISSION_URL'),
    'control_token' => env('JITSI_CONTROL_TOKEN'),
];
