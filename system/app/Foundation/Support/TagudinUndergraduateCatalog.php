<?php

namespace App\Foundation\Support;

final class TagudinUndergraduateCatalog
{
    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     college: string,
     *     discipline: string,
     *     summary: string,
     *     description: string
     * }>
     */
    public function programs(): array
    {
        return [
            [
                'code' => 'BSEd',
                'name' => 'Bachelor of Secondary Education',
                'college' => 'CTE',
                'discipline' => 'Secondary teaching and pedagogy',
                'summary' => 'Prepares future high school educators in curriculum design, adolescent learning, and subject-matter mastery.',
                'description' => "The Bachelor of Secondary Education (BSEd) program under the College of Teacher Education (CTE) prepares future secondary school teachers with strong pedagogical foundations, instructional technology integration, and classroom leadership.\n\nAccredited at AACCUP Level III Re-accredited status, the curriculum combines learner-centered teaching methodologies, assessment design, action research, and supervised field study with extensive practice teaching internships in partner secondary schools.\n\nGraduates are prepared for the PRC Licensure Examination for Teachers (LET) and pursue careers as Junior and Senior High School Teachers, Curriculum Developers, Academic Coordinators, and Educational Trainers.",
            ],
            [
                'code' => 'BSEd-Eng',
                'name' => 'Bachelor of Secondary Education major in English',
                'college' => 'CTE',
                'discipline' => 'Language, literature, and education',
                'summary' => 'Equips future secondary educators in English linguistics, world literature, and communicative language teaching.',
                'description' => "The Bachelor of Secondary Education major in English (BSEd-Eng) under the College of Teacher Education develops secondary educators with deep proficiency in English linguistics, literary analysis, discourse studies, and language curriculum design.\n\nStudents train in communicative language teaching, reading and writing remediation, speech and theater arts, and instructional materials development within an AACCUP Level III Re-accredited teacher education environment.\n\nGraduates take the PRC Licensure Examination for Teachers (LET) and advance into roles as Secondary English Educators, ESL/EFL Instructors, Campus Journalism Advisers, Curriculum Writers, and Communications Specialists.",
            ],
            [
                'code' => 'BSEd-Math',
                'name' => 'Bachelor of Secondary Education major in Mathematics',
                'college' => 'CTE',
                'discipline' => 'Mathematics and secondary pedagogy',
                'summary' => 'Trains future secondary mathematics teachers in calculus, geometry, statistics, and modern mathematical pedagogy.',
                'description' => "The Bachelor of Secondary Education major in Mathematics (BSEd-Math) under the College of Teacher Education equips future educators with rigorous mastery of algebra, calculus, geometry, probability, and statistics paired with modern mathematics pedagogy.\n\nThe program emphasizes problem-solving heuristics, mathematical modeling, digital graphing and instructional technologies, and classroom assessment under AACCUP Level III Re-accredited standards.\n\nGraduates sit for the PRC Licensure Examination for Teachers (LET) and build careers as Secondary Mathematics Teachers, STEM Academic Coaches, Statistical Analysts, and Instructional Content Developers.",
            ],
            [
                'code' => 'BEEd',
                'name' => 'Bachelor of Elementary Education',
                'college' => 'CTE',
                'discipline' => 'Childhood and primary education',
                'summary' => 'Focuses on foundational primary education, literacy and numeracy development, and holistic child pedagogy.',
                'description' => "The Bachelor of Elementary Education (BEEd) under the College of Teacher Education prepares generalist elementary school educators skilled in child development, early literacy, foundational numeracy, and multi-grade instruction.\n\nSupported by AACCUP Level III Re-accredited laboratory and field-study immersion, students master inclusive classroom management, mother-tongue and bilingual pedagogy, and integrative teaching across all elementary learning areas.\n\nGraduates qualify for the PRC Licensure Examination for Teachers (LET) and serve as Elementary School Educators, Primary Grade Coordinators, Reading and Literacy Specialists, and Community Education Leaders.",
            ],
            [
                'code' => 'BPEd',
                'name' => 'Bachelor of Physical Education',
                'college' => 'CTE',
                'discipline' => 'Physical literacy, sports, and wellness',
                'summary' => 'Comprehensive training in physical education pedagogy, athletic coaching, sports officiating, and movement science.',
                'description' => "The Bachelor of Physical Education (BPEd) under the College of Teacher Education develops educators and sports specialists grounded in kinesiology, exercise physiology, physical literacy,Philippine traditional dances, and athletic coaching.\n\nApplicants complete a Physical Fitness Assessment and medical clearance as part of program qualification, followed by hands-on practicum in sports officiating, intramural administration, and wellness instruction.\n\nGraduates are eligible for the PRC Licensure Examination for Teachers (LET) and pursue careers as Physical Education Teachers, Athletic Coaches, Sports Officiating Directors, and Community Fitness Coordinators.",
            ],
            [
                'code' => 'BSBA',
                'name' => 'Bachelor of Science in Business Administration',
                'college' => 'CBME',
                'discipline' => 'Management, finance, and marketing',
                'summary' => 'Develops versatile business professionals across Human Resource Management, Marketing, and Financial Management.',
                'description' => "The Bachelor of Science in Business Administration (BSBA) under the College of Business Management and Entrepreneurship (CBME) prepares industry-ready management professionals with strong analytical, financial, and organizational leadership capabilities.\n\nThe program offers specialized tracks in Human Resource Development Management (HRDM), Marketing Management, and Financial Management, backed by AACCUP Level III/IV re-accreditation and industry internship placements.\n\nGraduates excel as Human Resource Specialists, Financial and Credit Analysts, Marketing Account Executives, Banking Operations Officers, and Corporate Management Trainees.",
            ],
            [
                'code' => 'BSEntrep',
                'name' => 'Bachelor of Science in Entrepreneurship',
                'college' => 'CBME',
                'discipline' => 'Venture creation and enterprise growth',
                'summary' => 'Incubates innovative business founders through venture feasibility, enterprise incubation, and supply chain management.',
                'description' => "The Bachelor of Science in Entrepreneurship (BSEntrep) under the College of Business Management and Entrepreneurship (CBME) trains students to identify market opportunities, validate business models, and launch sustainable commercial or agribusiness enterprises.\n\nThrough experiential venture incubation, product development laboratories, financial forecasting, and regional trade fairs, students build and operate real micro-enterprises before graduation.\n\nGraduates launch their own businesses or lead as Business Development Consultants, SME Operations Managers, Commercial Account Officers, and Innovation Project Leads.",
            ],
            [
                'code' => 'BPA',
                'name' => 'Bachelor in Public Administration',
                'college' => 'CBME',
                'discipline' => 'Public governance and civil service',
                'summary' => 'Prepares public-sector leaders in policy formulation, local government administration, and public fiscal management.',
                'description' => "The Bachelor in Public Administration (BPA) under the College of Business Management and Entrepreneurship (CBME) prepares ethical, competent professionals for governance, civil service administration, and public policy implementation.\n\nCoursework covers Philippine administrative law, public fiscal management, local and regional governance, development planning, and public-sector human resource management, complemented by practicum placements in government agencies and LGUs.\n\nGraduates pursue careers as Local Government Administrative Officers, Civil Service Specialists, Legislative and Policy Analysts, Public Procurement Officers, and Development Program Managers.",
            ],
            [
                'code' => 'BSIT',
                'name' => 'Bachelor of Science in Information Technology',
                'college' => 'CAS',
                'discipline' => 'Computing, software, and network systems',
                'summary' => 'Hands-on computing education in Web and Mobile Application Development, Networking, and Cybersecurity.',
                'description' => "The Bachelor of Science in Information Technology (BSIT) under the College of Arts and Sciences (CAS) equips students to design, develop, deploy, and secure modern software systems and enterprise network infrastructure.\n\nAccredited at AACCUP Level III Re-accredited status and housed in the Information Technology Building laboratories, the curriculum offers specialization tracks in Web and Mobile Application Development as well as Networking and Cybersecurity, culminating in industry OJT and capstone engineering projects.\n\nGraduates build careers as Full-Stack Software Developers, Network and Systems Administrators, Cybersecurity Analysts, Database Engineers, and IT Project Leads.",
            ],
            [
                'code' => 'BSMath',
                'name' => 'Bachelor of Science in Mathematics',
                'college' => 'CAS',
                'discipline' => 'Applied mathematics and quantitative modeling',
                'summary' => 'Rigorous training in mathematical modeling, statistical computation, optimization, and quantitative analysis.',
                'description' => "The Bachelor of Science in Mathematics (BSMath) under the College of Arts and Sciences (CAS) provides intensive training in pure and applied mathematics, statistical inference, numerical computation, and operations research.\n\nStudents engage in mathematical modeling, algorithmic problem-solving, actuarial foundations, and undergraduate quantitative research addressing scientific, financial, and industrial challenges.\n\nGraduates thrive as Data and Quantitative Analysts, Actuarial Assistants, Operations Researchers, Statistical Modelers, and University Mathematics Instructors.",
            ],
            [
                'code' => 'BA-EL',
                'name' => 'Bachelor of Arts in English Language',
                'college' => 'CAS',
                'discipline' => 'Applied linguistics and professional communication',
                'summary' => 'Develops expertise in applied linguistics, discourse analysis, technical writing, and cross-cultural communication.',
                'description' => "The Bachelor of Arts in English Language (BA-EL) under the College of Arts and Sciences (CAS) focuses on the scientific study of the English language, sociolinguistics, discourse analysis, and professional communication across media and institutional settings.\n\nStudents build advanced competencies in technical and creative writing, copyediting, translation studies, organizational communication, and language research.\n\nGraduates pursue careers as Corporate Communications Officers, Technical and Content Writers, Public Relations Specialists, Editors, Foreign Service Communicators, and Legal or Graduate School Candidates.",
            ],
            [
                'code' => 'BA-Psych',
                'name' => 'Bachelor of Arts in Psychology',
                'college' => 'CAS',
                'discipline' => 'Human behavior, psychometrics, and counseling',
                'summary' => 'Explores human behavior, psychological assessment, industrial-organizational dynamics, and counseling foundations.',
                'description' => "The Bachelor of Arts in Psychology (BA-Psych) under the College of Arts and Sciences (CAS) provides a comprehensive foundation in behavioral science, psychological testing and measurement, developmental and abnormal psychology, and Filipino psychology (Sikolohiyang Pilipino).\n\nStudents gain practical laboratory and field experience in psychometric assessment, counseling techniques, experimental research, and industrial-organizational human resource practices.\n\nGraduates are prepared for the PRC Board Licensure Examination for Psychometricians (BLEPP) and work as Licensed Psychometricians, HR Talent and Organizational Specialists, Guidance Associates, Community Mental Health Advocates, and Behavioral Researchers.",
            ],
            [
                'code' => 'BASS',
                'name' => 'Bachelor of Arts in Social Science',
                'college' => 'CAS',
                'discipline' => 'Sociology, governance, and community development',
                'summary' => 'Interdisciplinary study of sociology, political institutions, cultural anthropology, and social policy research.',
                'description' => "The Bachelor of Arts in Social Science (BASS) under the College of Arts and Sciences (CAS) offers an interdisciplinary investigation of society, political dynamics, cultural heritage, economics, and Philippine social realities.\n\nThe curriculum emphasizes qualitative and quantitative social research methods, community immersion, policy evaluation, and civic advocacy.\n\nGraduates serve as Social Policy Researchers, Community Development Officers, NGO Project Coordinators, Cultural Heritage Specialists, and Pre-Law or Public Service Professionals.",
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function retiredCodes(): array
    {
        return ['BSN', 'BSPSY', 'BSCS', 'BSCE', 'BAT', 'BSHM'];
    }

    /**
     * @return array{sources: list<string>, accessed_on: string, basis: string}
     */
    public function retirementProvenance(): array
    {
        return [
            'sources' => [
                'https://ispsc.edu.ph/campuses/tagudin-campus',
                'https://ispsc.edu.ph/academics/program-offerings',
            ],
            'accessed_on' => '2026-10-05',
            'basis' => 'The official Tagudin undergraduate lists omit these six codes; no code or department mapping is inferred.',
        ];
    }
}
