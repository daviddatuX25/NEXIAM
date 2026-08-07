<?php

return [
    'name' => env('INSTITUTION_NAME', 'My Institution'),
    'campus' => env('INSTITUTION_CAMPUS', ''),
    'address' => env('INSTITUTION_ADDRESS', ''),
    'contact_number' => env('INSTITUTION_CONTACT_NUMBER', ''),
    'email' => env('INSTITUTION_EMAIL', ''),
    'website' => env('INSTITUTION_WEBSITE', ''),

    'exam_name' => env('INSTITUTION_EXAM_NAME', 'College Admission Test'),
    'exam_acronym' => env('INSTITUTION_EXAM_ACRONYM', 'CAT'),

    'personnel' => [
        'guidance_counselor' => [
            'name' => env('INSTITUTION_GUIDANCE_COUNSELOR', ''),
            'title' => env('INSTITUTION_GUIDANCE_COUNSELOR_TITLE', 'Guidance Counselor'),
            'credentials' => env('INSTITUTION_GUIDANCE_COUNSELOR_CREDENTIALS', ''),
        ],
        'registrar' => [
            'name' => env('INSTITUTION_REGISTRAR', ''),
            'title' => env('INSTITUTION_REGISTRAR_TITLE', 'Registrar'),
            'credentials' => env('INSTITUTION_REGISTRAR_CREDENTIALS', ''),
        ],
        'college_president' => [
            'name' => env('INSTITUTION_COLLEGE_PRESIDENT', ''),
            'title' => env('INSTITUTION_COLLEGE_PRESIDENT_TITLE', 'College President'),
            'credentials' => env('INSTITUTION_COLLEGE_PRESIDENT_CREDENTIALS', ''),
        ],
        'campus_director' => [
            'name' => env('INSTITUTION_CAMPUS_DIRECTOR', ''),
            'title' => env('INSTITUTION_CAMPUS_DIRECTOR_TITLE', 'Campus Director'),
            'credentials' => env('INSTITUTION_CAMPUS_DIRECTOR_CREDENTIALS', ''),
        ],
        'vp_academic_affairs' => [
            'name' => env('INSTITUTION_VP_ACADEMIC_AFFAIRS', ''),
            'title' => env('INSTITUTION_VP_ACADEMIC_AFFAIRS_TITLE', 'VP for Academic Affairs'),
            'credentials' => env('INSTITUTION_VP_ACADEMIC_AFFAIRS_CREDENTIALS', ''),
        ],
        'dean' => [
            'name' => env('INSTITUTION_DEAN', ''),
            'title' => env('INSTITUTION_DEAN_TITLE', 'Dean'),
            'credentials' => env('INSTITUTION_DEAN_CREDENTIALS', ''),
        ],
        'testing_coordinator' => [
            'name' => env('INSTITUTION_TESTING_COORDINATOR', ''),
            'title' => env('INSTITUTION_TESTING_COORDINATOR_TITLE', 'Testing Coordinator'),
            'credentials' => env('INSTITUTION_TESTING_COORDINATOR_CREDENTIALS', ''),
        ],
    ],
];
