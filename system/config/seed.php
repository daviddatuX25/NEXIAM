<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Synthetic seed identities (local / demo only)
    |--------------------------------------------------------------------------
    |
    | StaffIdentitySeeder creates the bootstrap HR account from these values.
    | Override via .env for local testing. Never use production secrets here.
    |
    */

    'hr' => [
        'name' => env('SEED_HR_NAME', 'Synthetic HR Administrator'),
        'email' => env('SEED_HR_EMAIL', 'hr.admin@nexiam.test'),
        'password' => env('SEED_HR_PASSWORD', 'password'),
    ],

];
