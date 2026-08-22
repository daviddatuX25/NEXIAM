<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public experience preview content
    |--------------------------------------------------------------------------
    |
    | This is intentionally replaceable content for the institutional public
    | surface. Set PUBLIC_SITE_PREVIEW=false once approved copy and local media
    | are available; the page structure does not depend on these fixtures.
    |
    */
    'preview' => [
        'enabled' => (bool) env('PUBLIC_SITE_PREVIEW', env('APP_ENV', 'production') === 'local'),
        'label' => 'Preview content',
        'notice' => 'Simulated copy and photography are shown while approved institutional materials are prepared.',
        'source' => 'simulated',
    ],

    'institution' => [
        'name' => 'Ilocos Sur Polytechnic State College',
        'short_name' => 'ISPSC',
        'campus' => 'Tagudin Campus',
        'location' => 'Tagudin, Ilocos Sur',
        'kicker' => 'A campus to study, belong, and begin',
        'welcome' => 'Start with a question. Find a direction. Make this campus part of your story.',
        'mission' => 'At Tagudin Campus, your first step can be practical: explore a program, ask a question, and meet the people who can help you decide.',
        'visit_note' => 'Come see Tagudin Campus, meet the people who guide students, and ask the questions that matter before you begin.',
    ],

    'hero' => [
        'eyebrow' => 'Ilocos Sur Polytechnic State College · Tagudin Campus',
        'title' => 'Find your next beginning.',
        'lede' => 'Explore programs at ISPSC Tagudin, meet the people who can help you choose, and take the next step when you are ready.',
        'image' => [
            'src' => 'https://images.unsplash.com/photo-1498243691581-b145c3f54a5a?auto=format&fit=crop&w=2200&q=85',
            'alt' => 'A quiet library aisle with tall shelves and warm overhead light',
            'source' => 'simulated stock photography',
        ],
    ],

    'stories' => [
        [
            'eyebrow' => 'The campus feeling',
            'title' => 'Small moments become a wider horizon.',
            'body' => 'A first conversation after class. A new idea tested with friends. A mentor who sees what you could become. Those small moments are where a campus starts to feel like yours.',
            'image' => [
                'src' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1600&q=82',
                'alt' => 'Students collaborating together around a table',
                'source' => 'simulated stock photography',
            ],
            'align' => 'right',
        ],
        [
            'eyebrow' => 'Learning in motion',
            'title' => 'Learning looks different when you can try it.',
            'body' => 'In classrooms and shared spaces, questions become practice. Programs, people, and place should feel connected from the first visit.',
            'image' => [
                'src' => 'https://images.unsplash.com/photo-1517486808906-6ca8b3f04846?auto=format&fit=crop&w=1600&q=82',
                'alt' => 'A group of students smiling together outdoors',
                'source' => 'simulated stock photography',
            ],
            'align' => 'left',
        ],
    ],

    'programs' => [
        [
            'code' => 'BSN',
            'name' => 'Bachelor of Science in Nursing',
            'discipline' => 'Health and human care',
            'summary' => 'For students drawn to careful practice, service, and people-centered work.',
        ],
        [
            'code' => 'BSPSY',
            'name' => 'Bachelor of Science in Psychology',
            'discipline' => 'Mind and community',
            'summary' => 'For students curious about people, behavior, context, and compassionate support.',
        ],
    ],

    'announcements' => [
        [
            'eyebrow' => 'Admissions',
            'title' => 'Your next step starts with a question.',
            'body' => 'Explore the available pathways, review the requirements, and keep your application details close as you plan your visit.',
            'href' => '#admissions',
            'label' => 'See the admissions path',
        ],
        [
            'eyebrow' => 'Visit the campus',
            'title' => 'Bring your curiosity to Tagudin.',
            'body' => 'Use the contact details below as the first placeholder for an approved campus visit and inquiry destination.',
            'href' => '#contact',
            'label' => 'Plan a visit',
        ],
    ],
];
