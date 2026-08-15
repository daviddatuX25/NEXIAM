<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paper configuration
    |--------------------------------------------------------------------------
    | Physical paper the institution prints on. Bundle geometry is authored in
    | the matching pixel space and NEVER scaled: a mismatch refuses rendering
    | with a typed error instead of silently changing bubble positions.
    */

    'paper' => (string) env('PRINT_PAPER', 'long'),

    'paper_sizes' => [
        'long' => ['width_mm' => 216.0, 'height_mm' => 330.0, 'label' => 'Long bond 8.5 × 13 in'],
        'legal' => ['width_mm' => 216.0, 'height_mm' => 356.0, 'label' => 'US Legal 8.5 × 14 in'],
    ],

    'retention_days' => (int) env('PRINT_RETENTION_DAYS', 30),

];
