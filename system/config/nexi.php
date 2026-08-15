<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nexi assistant presentation seam (OpenSpec: ai-assistant-foundation)
    |--------------------------------------------------------------------------
    |
    | Server-owned SurfaceProfile + PageCapabilityPack + NexiSessionView are
    | authority. OpenSpec 3 adds FakeAiGateway runs/SSE/usage/TaskGrants. Live provider/RAG/Apply remain later.
    |
    | Presentation stack: Inertia 3 + Svelte 5 (do not reintroduce React).
    |
    */
    'enabled' => env('NEXI_ENABLED', true),

    /** degraded | disabled | suspended — runtime posture (no live provider in this cut) */
    'mode' => env('NEXI_MODE', 'degraded'),

    'disclosure' => [
        'key' => env('NEXI_DISCLOSURE_KEY', 'nexi.foundation.disclosure'),
        'version' => (int) env('NEXI_DISCLOSURE_VERSION', 1),
        'text' => env(
            'NEXI_DISCLOSURE',
            'Nexi is an assistant. It cannot grant access or change records without a confirmed typed command.'
        ),
    ],

    /** Foundation default is Inquiry; Assistant activation requires a later consent OpenSpec. */
    'default_interaction' => env('NEXI_DEFAULT_INTERACTION', 'inquiry'),

    'interaction_modes' => [
        'inquiry' => [
            'key' => 'inquiry',
            'label' => 'Inquiry',
            'enabled' => true,
        ],
        'assistant' => [
            'key' => 'assistant',
            'label' => 'Assistant',
            // Present in the type catalog but not activatable in OpenSpec 1.
            'enabled' => true,
        ],
    ],

    /*
     * Usage presentation stub — durable ledger lands in nexi-run-and-usage-governance.
     * used/limit here are display hints only; NexiSessionView.usage.used stays null until then.
     */
    'usage' => [
        'limit' => (int) env('NEXI_USAGE_LIMIT', 50),
        'unit' => env('NEXI_USAGE_UNIT', 'asks'),
        'period' => env('NEXI_USAGE_PERIOD', 'today'),
    ],

    /** Emergency stop — blocks new runs and revokes TaskGrants when activated. */
    'emergency_stop' => (bool) env('NEXI_EMERGENCY_STOP', false),

    /** Process runs inline after dispatch (local demo when queue worker lagging). */
    'process_inline' => (bool) env('NEXI_PROCESS_INLINE', true),

    /*
    |--------------------------------------------------------------------------
    | Provider gateway spike (OpenSpec: ai-provider-live-spike)
    |--------------------------------------------------------------------------
    |
    | Default remains fake. Set NEXI_AI_GATEWAY=laravel + provider API key for
    | live synthetic traffic. SDK stays behind App\Ai\Adapters\LaravelAi.
    |
    */
    'ai' => [
        'gateway' => env('NEXI_AI_GATEWAY', 'fake'),
        'provider' => env('NEXI_AI_PROVIDER', 'openai'),
        'model' => env('NEXI_AI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('NEXI_AI_TIMEOUT', 30),
    ],
];
