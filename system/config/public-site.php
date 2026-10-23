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
        'enabled' => (bool) env('PUBLIC_SITE_PREVIEW', false),
        'label' => 'Preview content',
        'notice' => 'Simulated copy and photography are shown while approved institutional materials are prepared.',
        'source' => 'simulated',
    ],

    'institution' => [
        'name' => 'Ilocos Sur Polytechnic State College',
        'short_name' => 'ISPSC',
        'campus' => 'Tagudin Campus',
        'location' => 'Tagudin, Ilocos Sur',
        'address' => 'Quirino, Tagudin, Ilocos Sur 2714',
        'contact_number' => '+63 (77) 674-1474',
        'email' => 'tagudin@ispsc.edu.ph',
        'website' => 'https://ispsc.edu.ph/campuses/tagudin-campus',
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
            'code' => 'BSEd',
            'name' => 'Bachelor of Secondary Education',
            'discipline' => 'Secondary teaching and pedagogy',
            'summary' => 'Prepares future high school educators in curriculum design, adolescent learning, and subject-matter mastery.',
        ],
        [
            'code' => 'BSEd-Eng',
            'name' => 'Bachelor of Secondary Education major in English',
            'discipline' => 'Language, literature, and education',
            'summary' => 'Equips future secondary educators in English linguistics, world literature, and communicative language teaching.',
        ],
        [
            'code' => 'BSEd-Math',
            'name' => 'Bachelor of Secondary Education major in Mathematics',
            'discipline' => 'Mathematics and secondary pedagogy',
            'summary' => 'Trains future secondary mathematics teachers in calculus, geometry, statistics, and modern mathematical pedagogy.',
        ],
        [
            'code' => 'BEEd',
            'name' => 'Bachelor of Elementary Education',
            'discipline' => 'Childhood and primary education',
            'summary' => 'Focuses on foundational primary education, literacy and numeracy development, and holistic child pedagogy.',
        ],
        [
            'code' => 'BPEd',
            'name' => 'Bachelor of Physical Education',
            'discipline' => 'Physical literacy, sports, and wellness',
            'summary' => 'Comprehensive training in physical education pedagogy, athletic coaching, sports officiating, and movement science.',
        ],
        [
            'code' => 'BSBA',
            'name' => 'Bachelor of Science in Business Administration',
            'discipline' => 'Management, finance, and marketing',
            'summary' => 'Develops versatile business professionals across Human Resource Management, Marketing, and Financial Management.',
        ],
        [
            'code' => 'BSEntrep',
            'name' => 'Bachelor of Science in Entrepreneurship',
            'discipline' => 'Venture creation and enterprise growth',
            'summary' => 'Incubates innovative business founders through venture feasibility, enterprise incubation, and supply chain management.',
        ],
        [
            'code' => 'BPA',
            'name' => 'Bachelor in Public Administration',
            'discipline' => 'Public governance and civil service',
            'summary' => 'Prepares public-sector leaders in policy formulation, local government administration, and public fiscal management.',
        ],
        [
            'code' => 'BSIT',
            'name' => 'Bachelor of Science in Information Technology',
            'discipline' => 'Computing, software, and network systems',
            'summary' => 'Hands-on computing education in Web and Mobile Application Development, Networking, and Cybersecurity.',
        ],
        [
            'code' => 'BSMath',
            'name' => 'Bachelor of Science in Mathematics',
            'discipline' => 'Applied mathematics and quantitative modeling',
            'summary' => 'Rigorous training in mathematical modeling, statistical computation, optimization, and quantitative analysis.',
        ],
        [
            'code' => 'BA-EL',
            'name' => 'Bachelor of Arts in English Language',
            'discipline' => 'Applied linguistics and professional communication',
            'summary' => 'Develops expertise in applied linguistics, discourse analysis, technical writing, and cross-cultural communication.',
        ],
        [
            'code' => 'BA-Psych',
            'name' => 'Bachelor of Arts in Psychology',
            'discipline' => 'Human behavior, psychometrics, and counseling',
            'summary' => 'Explores human behavior, psychological assessment, industrial-organizational dynamics, and counseling foundations.',
        ],
        [
            'code' => 'BASS',
            'name' => 'Bachelor of Arts in Social Science',
            'discipline' => 'Sociology, governance, and community development',
            'summary' => 'Interdisciplinary study of sociology, political institutions, cultural anthropology, and social policy research.',
        ],
    ],

];
