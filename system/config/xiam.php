<?php

return [
    'enabled' => env('XIAM_ENABLED', true),
    'mode' => env('XIAM_MODE', 'degraded'),
    'disclosure' => [
        'key' => env('XIAM_DISCLOSURE_KEY', 'xiam.foundation.disclosure'),
        'version' => (int) env('XIAM_DISCLOSURE_VERSION', 1),
        'text' => env(
            'XIAM_DISCLOSURE',
            'Xiam is an assistant. It cannot grant access or change records without a confirmed typed command.'
        ),
    ],
    'usage' => [
        'admin_limit' => (int) env('XIAM_USAGE_ADMIN_LIMIT', -1),
        'staff_limit' => (int) env('XIAM_USAGE_STAFF_LIMIT', 200),
        'applicant_limit' => (int) env('XIAM_USAGE_APPLICANT_LIMIT', 15),
        'unit' => env('XIAM_USAGE_UNIT', 'asks'),
        'period' => env('XIAM_USAGE_PERIOD', 'today'),
    ],
    'emergency_stop' => (bool) env('XIAM_EMERGENCY_STOP', false),
    'process_inline' => (bool) env('XIAM_PROCESS_INLINE', true),
    'eval' => [
        'enabled' => (bool) env('XIAM_EVAL_ENABLED', false),
        'database' => env('XIAM_EVAL_DATABASE'),
        'database_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('XIAM_EVAL_DATABASE_HOSTS', ''))
        ))),
        'allow_remote_provider' => (bool) env('XIAM_EVAL_ALLOW_REMOTE_PROVIDER', false),
        'local_provider_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('XIAM_EVAL_LOCAL_PROVIDER_HOSTS', ''))
        ))),
    ],
    'ai' => [
        'gateway' => env('XIAM_AI_GATEWAY', 'fake'),
        'provider' => env('XIAM_AI_PROVIDER', 'openai'),
        'model' => env('XIAM_AI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('XIAM_AI_TIMEOUT', 45),
    ],
    'execution_budget' => [
        'provider_generations' => (int) env('XIAM_MAX_PROVIDER_GENERATIONS', 4),
        'tool_calls' => (int) env('XIAM_MAX_TOOL_CALLS', 4),
        'run_deadline_seconds' => (int) env('XIAM_RUN_DEADLINE_SECONDS', 90),
        'provider_step_timeout_seconds' => (int) env('XIAM_PROVIDER_STEP_TIMEOUT_SECONDS', 45),
        'tool_result_bytes' => (int) env('XIAM_TOOL_RESULT_BYTES', 8192),
        'tool_results_bytes' => (int) env('XIAM_TOOL_RESULTS_BYTES', 32768),
    ],
    'connectors' => [
        'allowlist' => [
            'anthropic' => [
                'driver' => 'anthropic',
                'base_url' => 'https://api.anthropic.com',
                'allowed_hosts' => ['api.anthropic.com'],
                'region' => 'global',
            ],
            'openai' => [
                'driver' => 'openai',
                'base_url' => 'https://api.openai.com/v1',
                'allowed_hosts' => ['api.openai.com'],
                'region' => 'global',
            ],
            'gemini' => [
                'driver' => 'gemini',
                'base_url' => 'https://generativelanguage.googleapis.com',
                'allowed_hosts' => ['generativelanguage.googleapis.com'],
                'region' => 'global',
            ],
            'local-ollama' => [
                'driver' => 'local-ollama',
                'base_url' => 'http://ollama:11434',
                'allowed_hosts' => ['ollama'],
                'region' => 'deployment',
            ],
        ],
    ],
];
