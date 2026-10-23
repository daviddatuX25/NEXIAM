<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'passkeys' => false,
    'views' => false,
    'lowercase_usernames' => false,
    'username' => 'email',
    'features' => [
        Features::twoFactorAuthentication(['confirm' => true]),
    ],
];
