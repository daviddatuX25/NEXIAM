<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Models\Application;
use App\Models\AptitudeArea;
use App\Models\ConsultationSummary;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\GradingSession;
use App\Models\Role;
use App\Models\Room;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DefenseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = CarbonImmutable::today();

        $courses = Course::query()->where('is_active', true)->orderBy('id')->take(3)->get();
        if ($courses->count() < 3) {
            $this->command?->warn('DefenseDemoSeeder: need at least 3 active courses. Run DatabaseSeeder first.');

            return;
        }

        $domains = AptitudeArea::query()->where('is_active', true)->orderBy('display_order')->get();
        if ($domains->count() < 3) {
            $this->command?->warn('DefenseDemoSeeder: need at least 3 active aptitude areas. Run DatabaseSeeder first.');

            return;
        }

        DB::transaction(function () use ($today, $courses, $domains) {
            $academicYear = $this->seedAcademicYear($today);
            $users = $this->seedUsers();
            $rooms = $this->seedRooms();
            $appMap = $this->seedApplications($today, $academicYear, $courses, $users);
            $this->seedSessionA($today, $academicYear, $rooms, $appMap, $users, $domains);
            $this->seedSessionB($today, $academicYear, $rooms, $appMap, $users, $domains);
            $this->seedSessionC($today, $academicYear, $rooms, $appMap, $users, $domains);
            $this->seedSessionD($today, $academicYear, $rooms, $appMap, $users);
            $this->seedSystemSettings();
        });
    }

    private function seedAcademicYear(CarbonImmutable $today): AcademicYear
    {
        $year = $today->month >= 6 ? $today->year : $today->year - 1;
        $academicYear = $year.'-'.($year + 1);

        $academicYearModel = AcademicYear::query()->updateOrCreate(
            ['academic_year' => $academicYear, 'semester' => '1'],
            [
                'is_active' => true,
                'application_start_date' => $today->subDays(45)->toDateString(),
                'application_end_date' => $today->addDays(14)->toDateString(),
            ]
        );

        // Deactivate all other seasons
        AcademicYear::query()->where('id', '!=', $academicYearModel->id)->update(['is_active' => false]);

        return $academicYearModel;
    }

    private function seedUsers(): array
    {
        return [
            'super_admin' => $this->upsertUserWithRole('admin@securecat.local', 'Ricardo Dela Cruz', 'super_admin'),
            'registrar_admin' => $this->upsertUserWithRole('josefina@securecat.local', 'Josefina Gaerlan', 'registrar_administrator'),
            'staff' => $this->upsertUserWithRole('maria@securecat.local', 'Maria Corpuz', 'staff'),
            'proctor' => $this->upsertUserWithRole('eduardo@securecat.local', 'Eduardo Fariñas', 'proctor'),
            'test_admin' => $this->upsertUserWithRole('analiza@securecat.local', 'Analiza Barroga', 'test_administrator'),
        ];
    }

    private function seedRooms(): Collection
    {
        $specs = [
            ['building' => 'Main Building',       'name' => 'Room 101',  'floor' => '1st Floor',    'capacity' => 30],
            ['building' => 'Main Building',       'name' => 'Room 102',  'floor' => '1st Floor',    'capacity' => 30],
            ['building' => 'Academic Building',   'name' => 'Room 201',  'floor' => '2nd Floor',    'capacity' => 40],
            ['building' => 'Vocational Building', 'name' => 'Lab Room 1', 'floor' => 'Ground Floor', 'capacity' => 25],
        ];

        return collect($specs)->map(fn ($r) => Room::query()->updateOrCreate(
            ['building' => $r['building'], 'name' => $r['name']],
            ['floor' => $r['floor'], 'capacity' => $r['capacity'], 'is_active' => true]
        )
        );
    }

    private function seedApplications(CarbonImmutable $today, AcademicYear $academicYear, $courses, array $users): array
    {
        $year = $today->year;

        $specs = [
            // [idx, first, middle, last, suffix, sex, birthdate, city, province, zip, phone, status, daysAgo, slot]
            [1,  'Juan Carlo', null,     'Agustin',    null,   'male',   '2006-03-12', 'Tagudin',     'Ilocos Sur', '2714', '09171001001', 'accepted',             28, 'A'],
            [2,  'Maricel',    null,     'Dacumos',    null,   'female', '2005-07-24', 'Tagudin',     'Ilocos Sur', '2714', '09171001002', 'accepted',             26, 'A'],
            [3,  'Reynaldo',   null,     'Soriano',    null,   'male',   '2006-01-08', 'Candon City', 'Ilocos Sur', '2802', '09171001003', 'accepted',             25, 'A'],
            [4,  'Rowena',     null,     'Ballesteros', null,   'female', '2005-11-15', 'Narvacan',    'Ilocos Sur', '2704', '09171001004', 'accepted',             18, 'B'],
            [5,  'Danilo',     null,     'Espiritu',   'Jr.',  'male',   '2006-05-30', 'Tagudin',     'Ilocos Sur', '2714', '09171001005', 'accepted',             17, 'B'],
            [6,  'Lorena',     null,     'Tamayo',     null,   'female', '2007-02-17', 'Santiago',    'Ilocos Sur', '2712', '09171001006', 'accepted',             10, 'C'],
            [7,  'Roberto',    null,     'Libed',      null,   'male',   '2006-09-03', 'Tagudin',     'Ilocos Sur', '2714', '09171001007', 'accepted',              9, 'C'],
            [8,  'Maribel',    null,     'Pagulayan',  null,   'female', '2005-12-21', 'Sudipen',     'La Union',   '2507', '09171001008', 'accepted',              8, 'C'],
            [9,  'Arturo',     null,     'Madriaga',   null,   'male',   '2006-08-14', 'Tagudin',     'Ilocos Sur', '2714', '09171001009', 'accepted',              7, 'C'],
            [10, 'Natividad',  null,     'Ramirez',    null,   'female', '2005-04-07', 'Candon City', 'Ilocos Sur', '2802', '09171001010', 'accepted',              6, 'unassigned'],
            [11, 'Virgilio',   null,     'Castillo',   null,   'male',   '2007-01-19', 'Vigan City',  'Ilocos Sur', '2700', '09171001011', 'accepted',              6, 'unassigned'],
            [12, 'Erlinda',    null,     'De Vera',    null,   'female', '2006-06-25', 'Tagudin',     'Ilocos Sur', '2714', '09171001012', 'accepted',              5, 'unassigned'],
            [13, 'Nestor',     null,     'Domingo',    null,   'male',   '2006-04-11', 'Tagudin',     'Ilocos Sur', '2714', '09171001013', 'pending',               3, 'pending'],
            [14, 'Imelda',     null,     'Gaerlan',    null,   'female', '2005-08-29', 'Candon City', 'Ilocos Sur', '2802', '09171001014', 'pending',               1, 'pending'],
            [15, 'Ferdinand',  null,     'Molina',     null,   'male',   '2007-03-05', 'Sinait',      'Ilocos Sur', '2721', '09171001015', 'pending',               5, 'pending'],
            [16, 'Rosalinda',  null,     'Aquino',     null,   'female', '2006-10-17', 'Tagudin',     'Ilocos Sur', '2714', '09171001016', 'pending',               0, 'pending'],
            [17, 'Carlos',     null,     'Vargas',     null,   'male',   '2005-06-14', 'Vigan City',  'Ilocos Sur', '2700', '09171001017', 'dismissed',             12, 'dismissed'],
            [18, 'Analiza',    null,     'Marcos',     null,   'female', '2006-02-28', 'Tagudin',     'Ilocos Sur', '2714', '09171001018', 'dismissed',             10, 'dismissed'],
            [19, 'Rodolfo',    null,     'Lacsamana',  null,   'male',   '2007-04-01', 'Narvacan',    'Ilocos Sur', '2704', '09171001019', 'incomplete_documents',  9, 'dismissed'],
            [20, 'Teresita',   null,     'Mirasol',    null,   'female', '2005-09-18', 'Santiago',    'Ilocos Sur', '2712', '09171001020', 'incomplete_documents',  8, 'dismissed'],
        ];

        // Ensure BSCS and BSDS exist (inactive) for the demo seeder reference
        foreach (['BSCS' => 'Bachelor of Science in Computer Science', 'BSDS' => 'Bachelor of Science in Data Science'] as $code => $name) {
            Course::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => false]);
        }

        $courseIdMap = Course::query()
            ->whereIn('code', ['BSIT', 'BSCS', 'BSDS'])
            ->pluck('id', 'code');

        $appMap = [];

        foreach ($specs as [$idx, $first, $middle, $last, $suffix, $sex, $birthdate, $city, $province, $zip, $phone, $status, $daysAgo, $slot]) {
            $ref = 'ISPSC-'.$year.'-'.str_pad((string) $idx, 4, '0', STR_PAD_LEFT);
            $email = strtolower(str_replace(' ', '.', $first).'.'.strtolower(str_replace(' ', '', $last))).'@ispsc-demo.local';
            $submittedAt = $today->subDays($daysAgo)->startOfDay()->addHours(9);

            $processedBy = null;
            $processedAt = null;
            $rejectionReason = null;

            if ($status === 'accepted') {
                $processedBy = $users['staff']->id;
                $processedAt = $submittedAt->addDays(2);
            }

            if ($status === 'dismissed') {
                $processedBy = $users['staff']->id;
                $processedAt = $submittedAt->addDays(2);
                $rejectionReason = 'Did not appear for scheduled appointment.';
            }

            if ($status === 'incomplete_documents') {
                $processedBy = $users['staff']->id;
                $processedAt = $submittedAt->addDays(1);
                $rejectionReason = $idx === 19
                    ? 'Missing PSA birth certificate.'
                    : 'Missing Form 138 (Report Card).';
            }

            $app = Application::query()->updateOrCreate(
                ['reference_number' => $ref],
                [
                    'academic_year_id' => $academicYear->id,
                    'first_name' => $first,
                    'middle_name' => $middle,
                    'last_name' => $last,
                    'suffix' => $suffix,
                    'birthdate' => $birthdate,
                    'age' => $today->year - (int) substr($birthdate, 0, 4),
                    'sex' => $sex,
                    'email' => $email,
                    'phone' => $phone,
                    'address_line' => '123 Rizal St.',
                    'city' => $city,
                    'province' => $province,
                    'zip_code' => $zip,
                    'course_preference_1' => $courseIdMap['BSIT'],
                    'course_preference_2' => $courseIdMap['BSCS'],
                    'course_preference_3' => $courseIdMap['BSDS'],
                    'status' => $status,
                    'pipeline_status' => match ($slot) {
                        'A' => 'released',
                        'B' => 'graded',
                        'C' => 'submitted',
                        'unassigned' => 'accepted',
                        'dismissed' => 'dismissed',
                        default => 'pending',
                    },
                    'processed_by' => $processedBy,
                    'processed_at' => $processedAt,
                    'rejection_reason' => $rejectionReason,
                    'applicant_type' => in_array($idx, [2, 5, 8, 14]) ? 'transferee' : 'new',
                    'last_school_enrolled' => match ($idx) {
                        2 => 'University of Northern Philippines',
                        5 => 'Don Mariano Marcos Memorial State University',
                        8 => 'University of the Philippines Baguio',
                        14 => 'Saint Louis University',
                        default => null,
                    },
                    'appointment_id' => null,
                    'submitted_at' => $submittedAt,
                ]
            );

            $applicant = null;

            if ($status === 'accepted') {
                $applicant = Applicant::query()->updateOrCreate(
                    ['email' => $app->email],
                    [
                        'application_id' => $app->id,
                        'password' => Hash::make('password'),
                        'setup_token' => null,
                        'setup_token_expires_at' => null,
                    ]
                );
            }

            $appMap[$idx] = ['application' => $app, 'applicant' => $applicant, 'slot' => $slot];
        }

        return $appMap;
    }

    private function seedSessionA(CarbonImmutable $today, AcademicYear $academicYear, $rooms, array $appMap, array $users, $domains): void
    {
        $date = $today->subDays(14);
        $room = $rooms[0]; // Main Building Room 101

        $es = ExamSession::query()->updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'room_id' => $room->id, 'date' => $date->toDateString()],
            [
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'status' => ExamSession::STATUS_COMPLETED,
                'published_at' => $date->subDays(5),
                'started_at' => $date->setTimeFromTimeString('09:00:00'),
                'closed_at' => $date->setTimeFromTimeString('11:05:00'),
                'created_by' => $users['registrar_admin']->id,
            ]
        );

        $es->proctors()->syncWithoutDetaching([$users['proctor']->id]);

        $sessionApplicants = [
            $appMap[1]['applicant'],
            $appMap[2]['applicant'],
            $appMap[3]['applicant'],
        ];

        foreach ($sessionApplicants as $applicant) {
            $this->attachApplicant($es, $applicant, [
                'attendance_status' => 'present',
                'attendance_marked_at' => $date->setTimeFromTimeString('09:05:00'),
                'attendance_marked_by' => $users['proctor']->id,
                'submission_status' => 'submitted',
                'submitted_at' => $date->setTimeFromTimeString('10:55:00'),
                'submitted_to' => $users['proctor']->id,
            ]);
        }

        $gs = GradingSession::query()->updateOrCreate(
            ['exam_session_id' => $es->id],
            [
                'status' => GradingSession::STATUS_FINALIZED,
                'opened_at' => $date->addDays(1)->setTimeFromTimeString('08:00:00'),
                'opened_by' => $users['test_admin']->id,
                'finalized_at' => $date->addDays(3)->setTimeFromTimeString('16:00:00'),
                'finalized_by' => $users['test_admin']->id,
            ]
        );

        foreach ($sessionApplicants as $applicant) {
            $gs->applicants()->syncWithoutDetaching([$applicant->id]);
        }

        $scoreMap = [
            0 => ['SA' => 22, 'NA' => 20, 'VR' => 21, 'AR' => 17, 'LR' => 20, 'PSA' => 16], // Juan — high/passing
            1 => ['SA' => 14, 'NA' => 13, 'VR' => 15, 'AR' => 10, 'LR' => 13, 'PSA' => 11], // Maricel — borderline
            2 => ['SA' => 8, 'NA' => 9, 'VR' => 7, 'AR' => 6, 'LR' => 8, 'PSA' => 7], // Reynaldo — low/failing
        ];

        foreach ($sessionApplicants as $i => $applicant) {
            foreach ($domains as $domain) {
                $raw = $scoreMap[$i][$domain->code] ?? (int) round($domain->max_items * 0.5);
                ApplicantScore::query()->updateOrCreate(
                    ['grading_session_id' => $gs->id, 'applicant_id' => $applicant->id, 'aptitude_area_id' => $domain->id],
                    [
                        'raw_score' => $raw,
                        'max_score' => $domain->max_items,
                        'normalized_score' => null,
                        'scored_by' => $users['test_admin']->id,
                        'scored_at' => $date->addDays(2)->setTimeFromTimeString('14:00:00'),
                    ]
                );
            }
        }

        $consultationData = [
            0 => ['course' => 'BSIT', 'comments' => 'Excellent performance. Highly recommended for BSIT.'],
            1 => ['course' => 'BSCS', 'comments' => 'Borderline scores. Recommended to consider BSCS.'],
            2 => ['course' => 'BSIT', 'comments' => 'Low scores across domains. Advised to review and retake.'],
        ];

        foreach ($sessionApplicants as $i => $applicant) {
            $courseId = Course::query()->where('code', $consultationData[$i]['course'])->value('id');
            ConsultationSummary::query()->updateOrCreate(
                ['applicant_id' => $applicant->id],
                [
                    'status' => ConsultationSummary::STATUS_RELEASED,
                    'recommended_course_id' => $courseId,
                    'counselor_comments' => $consultationData[$i]['comments'],
                    'system_notes' => ['seed' => 'defense-demo'],
                    'counselor_id' => $users['test_admin']->id,
                    'released_at' => $date->addDays(5)->setTimeFromTimeString('10:00:00'),
                    'released_by' => $users['test_admin']->id,
                ]
            );
        }
    }

    private function seedSessionB(CarbonImmutable $today, AcademicYear $academicYear, $rooms, array $appMap, array $users, $domains): void
    {
        $date = $today->subDays(5);
        $room = $rooms[2]; // Academic Building Room 201

        $es = ExamSession::query()->updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'room_id' => $room->id, 'date' => $date->toDateString()],
            [
                'start_time' => '13:00:00',
                'end_time' => '15:00:00',
                'status' => ExamSession::STATUS_COMPLETED,
                'published_at' => $date->subDays(5),
                'started_at' => $date->setTimeFromTimeString('13:00:00'),
                'closed_at' => $date->setTimeFromTimeString('15:05:00'),
                'created_by' => $users['registrar_admin']->id,
            ]
        );

        $es->proctors()->syncWithoutDetaching([$users['proctor']->id]);

        $sessionApplicants = [
            $appMap[4]['applicant'], // Rowena
            $appMap[5]['applicant'], // Danilo
        ];

        foreach ($sessionApplicants as $applicant) {
            $this->attachApplicant($es, $applicant, [
                'attendance_status' => 'present',
                'attendance_marked_at' => $date->setTimeFromTimeString('13:05:00'),
                'attendance_marked_by' => $users['proctor']->id,
                'submission_status' => 'submitted',
                'submitted_at' => $date->setTimeFromTimeString('14:55:00'),
                'submitted_to' => $users['proctor']->id,
            ]);
        }

        $gs = GradingSession::query()->updateOrCreate(
            ['exam_session_id' => $es->id],
            [
                'status' => GradingSession::STATUS_FINALIZED,
                'opened_at' => $date->addDays(1)->setTimeFromTimeString('08:00:00'),
                'opened_by' => $users['test_admin']->id,
                'finalized_at' => $date->addDays(2)->setTimeFromTimeString('15:30:00'),
                'finalized_by' => $users['test_admin']->id,
            ]
        );

        foreach ($sessionApplicants as $applicant) {
            $gs->applicants()->syncWithoutDetaching([$applicant->id]);
        }

        $scoreMap = [
            0 => ['SA' => 18, 'NA' => 16, 'VR' => 17, 'AR' => 15, 'LR' => 16, 'PSA' => 14], // Rowena — good scores
            1 => ['SA' => 20, 'NA' => 19, 'VR' => 18, 'AR' => 18, 'LR' => 19, 'PSA' => 17], // Danilo — high scores
        ];

        foreach ($sessionApplicants as $i => $applicant) {
            foreach ($domains as $domain) {
                $raw = $scoreMap[$i][$domain->code] ?? (int) round($domain->max_items * 0.6);
                ApplicantScore::query()->updateOrCreate(
                    ['grading_session_id' => $gs->id, 'applicant_id' => $applicant->id, 'aptitude_area_id' => $domain->id],
                    [
                        'raw_score' => $raw,
                        'max_score' => $domain->max_items,
                        'normalized_score' => null,
                        'scored_by' => $users['test_admin']->id,
                        'scored_at' => $date->addDays(1)->setTimeFromTimeString('11:00:00'),
                    ]
                );
            }
        }

        $consultationData = [
            0 => ['course' => 'BSIT', 'comments' => 'Good aptitude across all areas. Recommended for BSIT with strong prospects.'],
            1 => ['course' => 'BSCS', 'comments' => 'Excellent scores. Highly recommended for BSCS.'],
        ];

        foreach ($sessionApplicants as $i => $applicant) {
            $courseId = Course::query()->where('code', $consultationData[$i]['course'])->value('id');
            ConsultationSummary::query()->updateOrCreate(
                ['applicant_id' => $applicant->id],
                [
                    'status' => ConsultationSummary::STATUS_DRAFT,
                    'recommended_course_id' => $courseId,
                    'counselor_comments' => $consultationData[$i]['comments'],
                    'system_notes' => ['seed' => 'defense-demo'],
                    'counselor_id' => $users['test_admin']->id,
                    'released_at' => null,
                    'released_by' => null,
                ]
            );
        }
    }

    private function seedSessionC(CarbonImmutable $today, AcademicYear $academicYear, $rooms, array $appMap, array $users, $domains): void
    {
        $date = $today->subDays(2);
        $room = $rooms[1]; // Main Building Room 102

        $es = ExamSession::query()->updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'room_id' => $room->id, 'date' => $date->toDateString()],
            [
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'status' => ExamSession::STATUS_COMPLETED,
                'published_at' => $today->subDays(5),
                'started_at' => $date->setTimeFromTimeString('09:00:00'),
                'closed_at' => $date->setTimeFromTimeString('11:05:00'),
                'created_by' => $users['registrar_admin']->id,
            ]
        );

        $es->proctors()->syncWithoutDetaching([$users['proctor']->id]);

        $sessionApplicants = [
            $appMap[6]['applicant'],  // Lorena
            $appMap[7]['applicant'],  // Roberto
            $appMap[8]['applicant'],  // Maribel
            $appMap[9]['applicant'],  // Arturo
        ];

        foreach ($sessionApplicants as $applicant) {
            $this->attachApplicant($es, $applicant, [
                'attendance_status' => 'present',
                'attendance_marked_at' => $date->setTimeFromTimeString('09:05:00'),
                'attendance_marked_by' => $users['proctor']->id,
                'submission_status' => 'submitted',
                'submitted_at' => $date->setTimeFromTimeString('10:55:00'),
                'submitted_to' => $users['proctor']->id,
            ]);
        }

        $gs = GradingSession::query()->updateOrCreate(
            ['exam_session_id' => $es->id],
            [
                'status' => GradingSession::STATUS_IN_PROGRESS,
                'opened_at' => $date->addDay()->setTimeFromTimeString('08:00:00'),
                'opened_by' => $users['test_admin']->id,
            ]
        );

        foreach ($sessionApplicants as $applicant) {
            $gs->applicants()->syncWithoutDetaching([$applicant->id]);
        }

        // Only score the first 2 applicants (Lorena & Roberto) — halfway graded
        $scoredApplicants = [$sessionApplicants[0], $sessionApplicants[1]];
        $scoreMap = [
            0 => ['SA' => 19, 'NA' => 17, 'VR' => 18, 'AR' => 14, 'LR' => 17, 'PSA' => 15], // Lorena
            1 => ['SA' => 12, 'NA' => 10, 'VR' => 11, 'AR' => 9, 'LR' => 10, 'PSA' => 8],  // Roberto
        ];

        foreach ($scoredApplicants as $i => $applicant) {
            foreach ($domains as $domain) {
                $raw = $scoreMap[$i][$domain->code] ?? (int) round($domain->max_items * 0.5);
                ApplicantScore::query()->updateOrCreate(
                    ['grading_session_id' => $gs->id, 'applicant_id' => $applicant->id, 'aptitude_area_id' => $domain->id],
                    [
                        'raw_score' => $raw,
                        'max_score' => $domain->max_items,
                        'normalized_score' => null,
                        'scored_by' => $users['test_admin']->id,
                        'scored_at' => $date->addDay()->setTimeFromTimeString('14:00:00'),
                    ]
                );
            }
        }
        // Maribel & Arturo remain unscored — demonstrates halfway grading
    }

    private function seedSessionD(CarbonImmutable $today, AcademicYear $academicYear, $rooms, array $appMap, array $users): void
    {
        $room = $rooms[3]; // Vocational Building Lab Room 1

        $es = ExamSession::query()->updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'room_id' => $room->id, 'date' => $today->toDateString()],
            [
                'start_time' => now()->addMinutes(10)->format('H:i:s'),
                'end_time' => now()->addMinutes(130)->format('H:i:s'),
                'status' => ExamSession::STATUS_PUBLISHED,
                'published_at' => $today->subDay(),
                'created_by' => $users['registrar_admin']->id,
            ]
        );

        $es->proctors()->syncWithoutDetaching([$users['proctor']->id]);

        // Assign the 3 unassigned accepted applicants
        foreach ([10, 11, 12] as $idx) {
            if ($appMap[$idx]['applicant']) {
                $this->attachApplicant($es, $appMap[$idx]['applicant'], [
                    'attendance_status' => 'pending',
                    'submission_status' => 'pending',
                ]);
            }
        }
    }

    private function upsertUserWithRole(string $email, string $name, string $roleName): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password')]
        );

        $role = Role::query()->where('name', $roleName)->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        return $user;
    }

    private function seedSystemSettings(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'notify_on_publish'],
            ['value' => '1'],
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'ai_exam_companion_enabled'],
            ['value' => '0'],
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'online_release_enabled'],
            ['value' => '1'],
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'release_mode'],
            ['value' => 'online'],
        );
    }

    private function attachApplicant(ExamSession $es, Applicant $applicant, array $pivot = []): void
    {
        DB::table('exam_session_applicant')->where('applicant_id', $applicant->id)->delete();
        DB::table('exam_session_applicant')->insert(array_merge([
            'exam_session_id' => $es->id,
            'applicant_id' => $applicant->id,
            'attendance_status' => 'pending',
            'submission_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ], $pivot));
    }
}
