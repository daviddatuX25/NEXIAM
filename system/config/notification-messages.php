<?php

declare(strict_types=1);

return [
    'builtins' => [
        'admissions.new-submission' => [
            'purpose' => 'Registrar review queue alert',
            'title' => 'New Applicant Submission',
            'body' => 'Application {{application_no}} is ready for Registrar review.',
            'fallback_title' => 'New applicant submission',
            'fallback_body' => 'Application {{application_no}} is ready for Registrar review.',
        ],
        'admissions.submitted' => [
            'purpose' => 'Application submission receipt',
            'title' => 'Application Received',
            'body' => 'Your application {{application_no}} has been submitted successfully.',
            'fallback_title' => 'Application received',
            'fallback_body' => 'Your application {{application_no}} was submitted. Sign in with your access key and birthdate to continue.',
        ],
        'examination.proctor-duty-assigned' => [
            'purpose' => 'Proctor duty assignment',
            'title' => 'Proctor Duty Assigned',
            'body' => 'You are assigned to proctor session {{session_code}} on {{exam_date}}.',
            'fallback_title' => 'You have been assigned proctor duty',
            'fallback_body' => 'You are assigned as proctor for exam session {{session_code}}. Open Exam Monitoring to record attendance and submission.',
        ],
        'examination.session-completed' => [
            'purpose' => 'Completed examination session notice',
            'title' => 'Session Concluded',
            'body' => 'Session {{session_code}} concluded. Answer sheets ready for OMR intake.',
            'fallback_title' => 'Exam session completed',
            'fallback_body' => 'Exam session {{session_code}} has been completed and grading is open.',
        ],
        'examination.slot-reserved' => [
            'purpose' => 'Examination slot reservation confirmation',
            'title' => 'Exam Slot Reserved',
            'body' => 'You have reserved session {{session_code}} on {{exam_date}}.',
            'fallback_title' => 'Your exam slot is reserved',
            'fallback_body' => 'Your slot for exam session {{session_code}} is reserved. See the NEXIAM portal for the schedule.',
        ],
        'results.consultation-online-invited' => [
            'purpose' => 'Online consultation invitation',
            'title' => 'Online consultation ready',
            'body' => 'Your counselor has started an online consultation. Sign in to join.',
            'fallback_title' => 'Your online consultation is ready',
            'fallback_body' => 'Your counselor has started an online consultation. Sign in to the NEXIAM portal to join during the access window.',
            'variants' => [
                'immediate' => [
                    'label' => 'Immediate invitation',
                    'title' => 'Online consultation ready',
                    'body' => 'Your counselor has started an online consultation. Sign in to join.',
                    'fallback_title' => 'Your online consultation is ready',
                    'fallback_body' => 'Your counselor has started an online consultation. Sign in to the NEXIAM portal to join during the access window.',
                ],
                'scheduled' => [
                    'label' => 'Scheduled invitation',
                    'title' => 'Online consultation ready',
                    'body' => 'Your counselor has started an online consultation. Sign in to join.',
                    'fallback_title' => 'Your online consultation is scheduled',
                    'fallback_body' => 'Your online consultation is scheduled for {{scheduled_at}}. Sign in to the NEXIAM portal to join during the access window.',
                ],
            ],
        ],
        'results.consultation-reserved' => [
            'purpose' => 'Release Day appointment confirmation',
            'title' => 'Release Day appointment confirmed',
            'body' => 'Your Release Day appointment is confirmed for {{consultation_date}} with {{counselor_name}}.',
            'fallback_title' => 'Your Release Day appointment is reserved',
            'fallback_body' => 'Your Release Day appointment in session {{session_code}} is reserved. See the NEXIAM portal for the schedule.',
        ],
    ],
];
