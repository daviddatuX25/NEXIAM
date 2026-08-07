<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nexi assistant presentation seam
    |--------------------------------------------------------------------------
    |
    | Master reference §8.4–8.5 / GF-22: Nexi is an assistant with allowlisted
    | typed actions only. Provider/RAG remain gated for E3. Use mode=disabled
    | to hide the shell entirely.
    |
    | interaction_modes: page/matrix may later restrict which keys are allowed.
    | usage: presentation of quota — real metering lands with E3 provider work.
    |
    */
    'enabled' => env('NEXI_ENABLED', true),

    /** assistive | degraded | disabled — runtime/provider posture */
    'mode' => env('NEXI_MODE', 'degraded'),

    'disclosure' => env(
        'NEXI_DISCLOSURE',
        'Nexi is an assistant. It cannot grant access or change records without a confirmed typed command.'
    ),

    /** assistant = task/page help; inquiry = policy/own-data Q&A (GF-06 §10) */
    'default_interaction' => env('NEXI_DEFAULT_INTERACTION', 'assistant'),

    'interaction_modes' => [
        'assistant' => [
            'key' => 'assistant',
            'label' => 'Assistant',
            'enabled' => true,
        ],
        'inquiry' => [
            'key' => 'inquiry',
            'label' => 'Inquiry',
            'enabled' => true,
        ],
    ],

    /*
     * Demo quota surface for the chrome. Server will own real counters later.
     */
    'usage' => [
        'used' => (int) env('NEXI_USAGE_USED', 3),
        'limit' => (int) env('NEXI_USAGE_LIMIT', 50),
        'unit' => env('NEXI_USAGE_UNIT', 'asks'),
        'period' => env('NEXI_USAGE_PERIOD', 'today'),
    ],
];
