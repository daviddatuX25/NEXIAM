<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Examination Thresholds
    |--------------------------------------------------------------------------
    |
    | Configurable limits governing exam session lifecycle operations:
    | reminder dispatch windows, auto-close grace, door check-in lead time,
    | and absence alerting.
    |
    */

    'thresholds' => [

        // Windows at which reminders are dispatched to assigned applicants.
        // Each entry is a human-readable duration string (e.g. '72h', '24h', '2h').
        'reminder_windows' => ['72h', '24h', '2h'],

        // Minutes past effective_end_at before a session is auto-closed.
        'auto_close_grace_minutes' => 15,

        // Minutes before starts_at that door check-in QR scanning opens.
        'door_checkin_lead_minutes' => 30,

        // Percentage of assigned applicants absent that triggers a warning.
        'absence_warning_threshold_percent' => 20,

    ],

];
