<?php

declare(strict_types=1);

return [
    'google' => [
        'allowed_domains' => [
            'gmail.com',
        ],
    ],
    'mfa' => [
        'enabled' => filter_var(env('CIAM_MFA_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'enforced_roles' => [
            'institutional_admin',
            'hr_administrator',
            'registrar_admin',
            'registrar_clerk',
            'registrar_support_staff',
            'guidance_counselor',
            'guidance_support_staff',
            'program_head',
            'dean',
        ],
        'exempt_roles' => [],
    ],
];
