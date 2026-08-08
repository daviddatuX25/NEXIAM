<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Synthetic seed identities (local / demo only)
    |--------------------------------------------------------------------------
    |
    | StaffIdentitySeeder creates the bootstrap HR account from these values.
    | JourneyFixtureSeeder creates demo applicant portal logins with fixed
    | access keys (D-085). Override via .env for local testing.
    | Never use production secrets here.
    |
    */

    'hr' => [
        'name' => env('SEED_HR_NAME', 'Synthetic HR Administrator'),
        'email' => env('SEED_HR_EMAIL', 'hr.admin@nexiam.test'),
        'password' => env('SEED_HR_PASSWORD', 'password'),
    ],

    'applicants' => [
        'accepted' => [
            'email' => env('SEED_APPLICANT_ACCEPTED_EMAIL', 'demo.accepted@nexiam.test'),
            'first_name' => 'Demo',
            'last_name' => 'Accepted',
            'birthdate' => env('SEED_APPLICANT_ACCEPTED_BIRTHDATE', '2003-09-25'),
            'access_key' => env('SEED_APPLICANT_ACCEPTED_KEY', 'DEMO-ACPT'),
            'sex' => 'female',
        ],
        'pending' => [
            'email' => env('SEED_APPLICANT_PENDING_EMAIL', 'demo.pending@nexiam.test'),
            'first_name' => 'Demo',
            'last_name' => 'Pending',
            'birthdate' => env('SEED_APPLICANT_PENDING_BIRTHDATE', '2003-09-25'),
            'access_key' => env('SEED_APPLICANT_PENDING_KEY', 'DEMO-PEND'),
            'sex' => 'male',
        ],
    ],

];
