<?php

return [
    'enabled' => (bool) env('APPLICATION_OPENING_ALERTS_ENABLED', false),
    'scheduler_only' => (bool) env('APPLICATION_OPENING_ALERTS_SCHEDULER_ONLY', false),
    'verification_token_hours' => 24,
    'unverified_retention_days' => 7,
    'verified_retention_months' => 12,
    'terminal_address_erasure_days' => 90,
    'resend_cooldown_seconds' => 60,
    'token_handoff_minutes' => 15,
    'fanout_chunk_size' => 100,
];
