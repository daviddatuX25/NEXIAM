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

    'admin' => [
        'name' => env('SEED_ADMIN_NAME', 'Synthetic Institutional Administrator'),
        'email' => env('SEED_ADMIN_EMAIL', 'admin.lead@nexiam.test'),
        'password' => env('SEED_ADMIN_PASSWORD', 'password'),
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Isolated synthetic applicant messaging acceptance fixture
    |--------------------------------------------------------------------------
    |
    | Used only by MessagingAcceptanceDemoSeeder. Staff accounts are created
    | with these passwords only when absent; existing accounts are never reset.
    |
    */
    'messaging' => [
        'applicant' => [
            'email' => 'demo.portal.default@nexiam.test',
            'first_name' => 'Default',
            'last_name' => 'Portal',
            'birthdate' => '2003-09-25',
            'access_key' => 'PORT-DEFL',
        ],
        'staff' => [
            'ph' => [
                'email' => 'ph.messaging-demo@nexiam.test',
                'name' => 'Synthetic Messaging Program Head',
                'password' => 'MessagingDemo!2026',
            ],
            'dean' => [
                'email' => 'dean.messaging-demo@nexiam.test',
                'name' => 'Synthetic Messaging Dean',
                'password' => 'MessagingDemo!2026',
            ],
            'guidance' => [
                'email' => 'guidance.messaging-demo@nexiam.test',
                'name' => 'Synthetic Messaging Guidance Counselor',
                'password' => 'MessagingDemo!2026',
            ],
            'registrar' => [
                'email' => 'registrar.messaging-demo@nexiam.test',
                'name' => 'Synthetic Messaging Registrar',
                'password' => 'MessagingDemo!2026',
            ],
            'unrelated' => [
                'email' => 'unrelated.messaging-demo@nexiam.test',
                'name' => 'Synthetic Messaging Unrelated Program Head',
                'password' => 'MessagingDemo!2026',
            ],
        ],
    ],

    'pipeline_cohorts' => [
        'birthdate' => '2003-01-01',
        'rooms' => [
            'reservation' => ['code' => 'RM-STAGE-RESV', 'name' => 'Stage Reservation Room', 'capacity' => 40],
            'seating' => ['code' => 'RM-STAGE-SEAT', 'name' => 'Stage Seating Room', 'capacity' => 40],
            'conduct' => ['code' => 'RM-STAGE-LIVE', 'name' => 'Stage Conduct Room', 'capacity' => 40],
            'grading' => ['code' => 'RM-STAGE-GRAD', 'name' => 'Stage Grading Room', 'capacity' => 40],
        ],
        'sessions' => [
            'reservation' => 'EX-STAGE-RESV',
            'seating' => 'EX-STAGE-SEAT',
            'conduct' => 'EX-STAGE-LIVE',
            'grading' => 'EX-STAGE-GRAD',
        ],
        'stages' => [
            'review' => [
                ['key' => 'REVW-DEM1', 'email' => 'cohort.revw01@nexiam.test', 'first_name' => 'Rhea', 'last_name' => 'Reviewer', 'strand' => 'STEM', 'programs' => ['BSEd', 'BA-Psych']],
                ['key' => 'REVW-DEM2', 'email' => 'cohort.revw02@nexiam.test', 'first_name' => 'Robin', 'last_name' => 'Reviewer', 'strand' => 'ABM', 'programs' => ['BSBA', 'BSMath']],
                ['key' => 'REVW-DEM3', 'email' => 'cohort.revw03@nexiam.test', 'first_name' => 'Rowan', 'last_name' => 'Reviewer', 'strand' => 'HUMSS', 'programs' => ['BA-Psych', 'BPA']],
            ],
            'reservation' => [
                ['key' => 'RESV-DEM1', 'email' => 'cohort.resv01@nexiam.test', 'first_name' => 'Randy', 'last_name' => 'Reserver', 'strand' => 'STEM', 'programs' => ['BSEd']],
                ['key' => 'RESV-DEM2', 'email' => 'cohort.resv02@nexiam.test', 'first_name' => 'Rachel', 'last_name' => 'Reserver', 'strand' => 'HUMSS', 'programs' => ['BSEd', 'BEEd']],
                ['key' => 'RESV-DEM3', 'email' => 'cohort.resv03@nexiam.test', 'first_name' => 'Riley', 'last_name' => 'Reserver', 'strand' => 'STEM', 'programs' => ['BPEd']],
            ],
            'seating' => [
                ['key' => 'SEAT-DEM1', 'email' => 'cohort.seat01@nexiam.test', 'first_name' => 'Sam', 'last_name' => 'Seater', 'strand' => 'HUMSS', 'programs' => ['BEEd']],
                ['key' => 'SEAT-DEM2', 'email' => 'cohort.seat02@nexiam.test', 'first_name' => 'Sophia', 'last_name' => 'Seater', 'strand' => 'ABM', 'programs' => ['BSBA', 'BSEntrep']],
                ['key' => 'SEAT-DEM3', 'email' => 'cohort.seat03@nexiam.test', 'first_name' => 'Silas', 'last_name' => 'Seater', 'strand' => 'STEM', 'programs' => ['BPEd']],
            ],
            'conduct' => [
                ['key' => 'EXAM-DEM1', 'email' => 'cohort.exam01@nexiam.test', 'first_name' => 'Carlos', 'last_name' => 'Conductor', 'strand' => 'STEM', 'programs' => ['BSIT'], 'status' => 'unresolved', 'submitted' => false],
                ['key' => 'EXAM-DEM2', 'email' => 'cohort.exam02@nexiam.test', 'first_name' => 'Clara', 'last_name' => 'Conductor', 'strand' => 'ABM', 'programs' => ['BSBA'], 'status' => 'absent', 'submitted' => false],
                ['key' => 'EXAM-DEM3', 'email' => 'cohort.exam03@nexiam.test', 'first_name' => 'Caleb', 'last_name' => 'Conductor', 'strand' => 'HUMSS', 'programs' => ['BSEd'], 'status' => 'present', 'submitted' => true],
            ],
            'grading' => [
                ['key' => 'GRAD-DEM1', 'email' => 'cohort.grad01@nexiam.test', 'first_name' => 'Grace', 'last_name' => 'Grader', 'strand' => 'STEM', 'programs' => ['BSIT'], 'scored' => false],
                ['key' => 'GRAD-DEM2', 'email' => 'cohort.grad02@nexiam.test', 'first_name' => 'Gabriel', 'last_name' => 'Grader', 'strand' => 'ABM', 'programs' => ['BSBA'], 'scored' => true, 'score' => 85],
                ['key' => 'GRAD-DEM3', 'email' => 'cohort.grad03@nexiam.test', 'first_name' => 'Gemma', 'last_name' => 'Grader', 'strand' => 'HUMSS', 'programs' => ['BA-Psych'], 'scored' => false],
            ],
            'consultation' => [
                ['key' => 'CONS-DEM1', 'email' => 'cohort.cons01@nexiam.test', 'first_name' => 'Connie', 'last_name' => 'Consultant', 'strand' => 'HUMSS', 'programs' => ['BSEd'], 'score' => 88],
                ['key' => 'CONS-DEM2', 'email' => 'cohort.cons02@nexiam.test', 'first_name' => 'Colin', 'last_name' => 'Consultant', 'strand' => 'HUMSS', 'programs' => ['BA-Psych'], 'score' => 82],
                ['key' => 'CONS-DEM3', 'email' => 'cohort.cons03@nexiam.test', 'first_name' => 'Chloe', 'last_name' => 'Consultant', 'strand' => 'STEM', 'programs' => ['BSIT'], 'score' => 91],
            ],
            'program' => [
                ['key' => 'POOL-DEM1', 'email' => 'cohort.pool01@nexiam.test', 'first_name' => 'Paul', 'last_name' => 'Pooler', 'strand' => 'HUMSS', 'default_program' => 'BSEd', 'programs' => ['BSEd', 'BEEd']],
                ['key' => 'POOL-DEM2', 'email' => 'cohort.pool02@nexiam.test', 'first_name' => 'Penelope', 'last_name' => 'Pooler', 'strand' => 'HUMSS', 'default_program' => 'BA-Psych', 'programs' => ['BA-Psych', 'BASS']],
                ['key' => 'POOL-DEM3', 'email' => 'cohort.pool03@nexiam.test', 'first_name' => 'Preston', 'last_name' => 'Pooler', 'strand' => 'STEM', 'default_program' => 'BSIT', 'programs' => ['BSIT', 'BSMath']],
            ],
            'enrollment' => [
                ['key' => 'ENRL-DEM1', 'email' => 'cohort.enrl01@nexiam.test', 'first_name' => 'Edward', 'last_name' => 'Enroller', 'strand' => 'HUMSS', 'final_program' => 'BSEd', 'status' => 'ready'],
                ['key' => 'ENRL-DEM2', 'email' => 'cohort.enrl02@nexiam.test', 'first_name' => 'Elena', 'last_name' => 'Enroller', 'strand' => 'ABM', 'final_program' => 'BSBA', 'status' => 'enrolling'],
                ['key' => 'ENRL-DEM3', 'email' => 'cohort.enrl03@nexiam.test', 'first_name' => 'Ethan', 'last_name' => 'Enroller', 'strand' => 'HUMSS', 'final_program' => 'BA-Psych', 'status' => 'enrolled'],
            ],
        ],
    ],
];
