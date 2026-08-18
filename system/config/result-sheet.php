<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paper configuration
    |--------------------------------------------------------------------------
    | Physical paper for result sheets. Sheet geometry is authored in the
    | matching pixel space and never scaled; a mismatch refuses rendering.
    */

    'paper_sizes' => [
        'a4' => ['width_mm' => 210.0, 'height_mm' => 297.0, 'label' => 'A4'],
        'legal' => ['width_mm' => 216.0, 'height_mm' => 356.0, 'label' => 'Legal 8.5 × 14 in'],
        'letter' => ['width_mm' => 216.0, 'height_mm' => 279.0, 'label' => 'Letter 8.5 × 11 in'],
    ],

    /*
    |--------------------------------------------------------------------------
    | LibreOffice conversion (DOCX/ODT → PDF)
    |--------------------------------------------------------------------------
    | Runs only inside queue workers. When the binary is unavailable, DOCX
    | render jobs fail with a typed `libreoffice_unavailable` error instead of
    | silently degrading.
    */

    'libreoffice_path' => (string) env('LIBREOFFICE_PATH', '/usr/bin/libreoffice'),

    'conversion_timeout' => (int) env('DOCX_CONVERSION_TIMEOUT', 120),

    'pdfunite_path' => (string) env('PDFUNITE_PATH', ''),

    'temp_dir' => storage_path('app/temp/libreoffice'),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */

    'retention_days' => (int) env('PRINT_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Institution display defaults for placeholders
    |--------------------------------------------------------------------------
    | Single-institution greenfield: name comes from the Institution row; the
    | remaining display fields default here until the Settings hub exists.
    */

    'institution' => [
        'campus' => env('INSTITUTION_CAMPUS', 'Main Campus'),
        'address' => env('INSTITUTION_ADDRESS', ''),
        'contact_number' => env('INSTITUTION_CONTACT_NUMBER', ''),
        'email' => env('INSTITUTION_EMAIL', ''),
        'website' => env('INSTITUTION_WEBSITE', ''),
        'exam_name' => env('INSTITUTION_EXAM_NAME', 'College Admission Test'),
        'exam_acronym' => env('INSTITUTION_EXAM_ACRONYM', 'CAT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Personnel defaults for signatory placeholders
    |--------------------------------------------------------------------------
    */

    'personnel' => [
        'guidance_counselor' => ['name' => '', 'title' => 'Guidance Counselor', 'credentials' => ''],
        'testing_coordinator' => ['name' => '', 'title' => 'Testing Coordinator', 'credentials' => ''],
        'dean' => ['name' => '', 'title' => 'Dean', 'credentials' => ''],
        'registrar' => ['name' => '', 'title' => 'Registrar', 'credentials' => ''],
        'vp_academic_affairs' => ['name' => '', 'title' => 'VP Academic Affairs', 'credentials' => ''],
        'campus_director' => ['name' => '', 'title' => 'Campus Director', 'credentials' => ''],
        'college_president' => ['name' => '', 'title' => 'College President', 'credentials' => ''],
    ],
];
