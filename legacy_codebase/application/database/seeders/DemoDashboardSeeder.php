<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Models\Application;
use App\Models\Appointment;
use App\Models\AptitudeArea;
use App\Models\ConsultationSummary;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\GradingSession;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();
        if (! $academicYear) {
            $this->command?->warn('DemoDashboardSeeder: no active Season found; skipping.');

            return;
        }

        $courses = Course::query()->where('is_active', true)->take(5)->get();
        if ($courses->count() < 3) {
            $this->command?->warn('DemoDashboardSeeder: not enough courses; skipping.');

            return;
        }

        $domains = AptitudeArea::query()->where('is_active', true)->orderBy('display_order')->get();
        if ($domains->count() < 3) {
            $this->command?->warn('DemoDashboardSeeder: not enough aptitude areas; skipping.');

            return;
        }

        DB::transaction(function () use ($academicYear, $courses, $domains) {
            // Users for key roles (stable emails for repeatable seeding)
            $users = [
                'staff' => $this->upsertUserWithRole('staff@demo.local', 'Staff Demo', 'staff'),
                'registrar_admin' => $this->upsertUserWithRole('admin@demo.local', 'Admin Demo', 'registrar_administrator'),
                'proctor' => $this->upsertUserWithRole('proctor@demo.local', 'Proctor Demo', 'proctor'),
                'test_admin' => $this->upsertUserWithRole('testadmin@demo.local', 'Test Admin Demo', 'test_administrator'),
            ];

            // Rooms
            $rooms = collect([
                ['building' => 'ITBR', 'name' => 'Room 101', 'floor' => '1st Floor', 'capacity' => 30],
                ['building' => 'ITBR', 'name' => 'Room 201', 'floor' => '2nd Floor', 'capacity' => 40],
                ['building' => 'MAIN', 'name' => 'Room 12', 'floor' => '1st Floor', 'capacity' => 25],
            ])->map(function ($r) {
                return Room::query()->updateOrCreate(
                    ['building' => $r['building'], 'name' => $r['name']],
                    [
                        'floor' => $r['floor'],
                        'capacity' => $r['capacity'],
                        'facilities' => ['projector' => true, 'ac' => true, 'whiteboard' => true],
                        'is_active' => true,
                    ]
                );
            });

            // Appointments (for face-to-face channel inference)
            $today = CarbonImmutable::today();
            $appointments = collect([
                ['date' => $today->addDays(1)->toDateString(), 'time_slot' => '09:00:00'],
                ['date' => $today->addDays(2)->toDateString(), 'time_slot' => '10:00:00'],
                ['date' => $today->addDays(3)->toDateString(), 'time_slot' => '13:00:00'],
            ])->map(function ($a) {
                return Appointment::query()->updateOrCreate(
                    ['date' => $a['date'], 'time_slot' => $a['time_slot']],
                    ['duration_minutes' => 30, 'max_slots' => 10, 'booked_count' => 0, 'is_active' => true]
                );
            });

            // Applications + applicants
            // Mix: pending (older/newer), accepted, rejected; mix: with/without appointment_id
            $appsSpec = [
                ['ref' => 'DEMO-APP-0001', 'status' => 'pending', 'submitted_days_ago' => 10, 'appointment' => true],
                ['ref' => 'DEMO-APP-0002', 'status' => 'pending', 'submitted_days_ago' => 5, 'appointment' => false],
                ['ref' => 'DEMO-APP-0003', 'status' => 'pending', 'submitted_days_ago' => 1, 'appointment' => true],
                ['ref' => 'DEMO-APP-0004', 'status' => 'accepted', 'submitted_days_ago' => 12, 'appointment' => false],
                // Note: on MySQL, applications.status enum may not include 'rejected' (migrated to dismissed/incomplete_documents)
                ['ref' => 'DEMO-APP-0005', 'status' => 'dismissed', 'submitted_days_ago' => 8, 'appointment' => true],
                ['ref' => 'DEMO-APP-0006', 'status' => 'accepted', 'submitted_days_ago' => 2, 'appointment' => false],
                ['ref' => 'DEMO-APP-0007', 'status' => 'pending', 'submitted_days_ago' => 0, 'appointment' => false],
            ];

            $applications = collect($appsSpec)->map(function ($spec, $idx) use ($academicYear, $courses, $appointments, $users) {
                $submittedAt = CarbonImmutable::now()->subDays((int) $spec['submitted_days_ago']);
                $course1 = $courses[0]->id;
                $course2 = $courses[1]->id;
                $course3 = $courses[2]->id;
                $appointmentId = $spec['appointment'] ? ($appointments[$idx % $appointments->count()]->id) : null;

                $processedBy = null;
                $processedAt = null;
                $rejectionReason = null;

                if ($spec['status'] === 'accepted') {
                    $processedBy = $users['staff']->id;
                    $processedAt = $submittedAt->addDays(1);
                }
                if ($spec['status'] === 'rejected') {
                    $processedBy = $users['staff']->id;
                    $processedAt = $submittedAt->addDays(1);
                    $rejectionReason = 'Demo rejection reason.';
                }
                if ($spec['status'] === 'dismissed' || $spec['status'] === 'incomplete_documents') {
                    $processedBy = $users['staff']->id;
                    $processedAt = $submittedAt->addDays(1);
                    $rejectionReason = 'Demo dismissal reason.';
                }

                $app = Application::query()->updateOrCreate(
                    ['reference_number' => $spec['ref']],
                    [
                        'academic_year_id' => $academicYear->id,
                        'first_name' => 'Demo',
                        'middle_name' => null,
                        'last_name' => 'Applicant '.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'suffix' => null,
                        'birthdate' => '2006-01-15',
                        'age' => 19,
                        'sex' => ($idx % 2 === 0) ? 'female' : 'male',
                        'email' => "applicant{$idx}@demo.local",
                        'phone' => '09170000000',
                        'address_line' => 'Demo Address',
                        'city' => 'Demo City',
                        'province' => 'Demo Province',
                        'zip_code' => '1000',
                        'course_preference_1' => $course1,
                        'course_preference_2' => $course2,
                        'course_preference_3' => $course3,
                        'status' => $spec['status'],
                        'processed_by' => $processedBy,
                        'processed_at' => $processedAt,
                        'rejection_reason' => $rejectionReason,
                        'applicant_type' => ($idx % 3 === 1) ? 'transferee' : 'new',
                        'last_school_enrolled' => ($idx % 3 === 1) ? 'Demo Previous School' : null,
                        'appointment_id' => $appointmentId,
                        'submitted_at' => $submittedAt,
                    ]
                );

                Applicant::query()->updateOrCreate(
                    ['email' => $app->email],
                    [
                        'application_id' => $app->id,
                        'password' => Hash::make('password'),
                        'setup_token' => null,
                        'setup_token_expires_at' => null,
                    ]
                );

                return $app;
            });

            $applicants = Applicant::query()
                ->whereIn('application_id', $applications->pluck('id')->all())
                ->get();

            // Exam sessions (next week) + proctor assignments + applicant assignments
            $sessionSpecs = [
                ['key' => 'DEMO-EXAM-001', 'room' => $rooms[0], 'date' => $today->addDays(2), 'start' => '09:00', 'status' => ExamSession::STATUS_PUBLISHED],
                ['key' => 'DEMO-EXAM-002', 'room' => $rooms[1], 'date' => $today->addDays(3), 'start' => '13:00', 'status' => ExamSession::STATUS_PUBLISHED],
                ['key' => 'DEMO-EXAM-003', 'room' => $rooms[2], 'date' => $today->addDays(5), 'start' => '09:00', 'status' => ExamSession::STATUS_PUBLISHED],
            ];

            $examSessions = collect($sessionSpecs)->map(function ($s) use ($academicYear, $users) {
                return ExamSession::query()->updateOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'room_id' => $s['room']->id,
                        'date' => $s['date']->toDateString(),
                        'start_time' => $s['start'].':00',
                    ],
                    [
                        'end_time' => '11:00:00',
                        'status' => $s['status'],
                        'published_at' => CarbonImmutable::now()->subDay(),
                        'score_release_date' => $s['date']->addDays(7)->toDateString(),
                        'created_by' => $users['registrar_admin']->id,
                    ]
                );
            });

            foreach ($examSessions as $es) {
                $es->proctors()->syncWithoutDetaching([$users['proctor']->id]);
            }

            // Assign 1 applicant per session (per spec) + leave some unassigned for realism
            $assignPool = $applicants->values();
            foreach ($examSessions as $i => $es) {
                $applicant = $assignPool->get($i);
                if ($applicant) {
                    $es->applicants()->syncWithoutDetaching([$applicant->id]);
                }
            }

            // Grading sessions: one finalized (completed), one in_progress (partial), one open (none)
            $gradingSessions = $examSessions->map(function ($es, $idx) use ($users) {
                $status = match ($idx) {
                    0 => GradingSession::STATUS_FINALIZED,
                    1 => GradingSession::STATUS_IN_PROGRESS,
                    default => GradingSession::STATUS_OPEN,
                };

                return GradingSession::query()->updateOrCreate(
                    ['exam_session_id' => $es->id],
                    [
                        'status' => $status,
                        'opened_at' => CarbonImmutable::now()->subDays(2),
                        'opened_by' => $users['test_admin']->id,
                        'finalized_at' => $status === GradingSession::STATUS_FINALIZED ? CarbonImmutable::now()->subDay() : null,
                        'finalized_by' => $status === GradingSession::STATUS_FINALIZED ? $users['test_admin']->id : null,
                    ]
                );
            });

            // Attach applicants into grading workload + create scores
            foreach ($gradingSessions as $idx => $gs) {
                $esApplicantIds = DB::table('exam_session_applicant')->where('exam_session_id', $gs->exam_session_id)->pluck('applicant_id')->all();
                foreach ($esApplicantIds as $applicantId) {
                    $gs->applicants()->syncWithoutDetaching([$applicantId]);

                    // idx 0: full scores; idx 1: partial; idx 2: none
                    if ($idx === 2) {
                        continue;
                    }

                    $take = ($idx === 0) ? $domains->count() : max(1, (int) floor($domains->count() / 2));
                    $domains->take($take)->each(function (AptitudeArea $d) use ($gs, $applicantId, $users) {
                        ApplicantScore::query()->updateOrCreate(
                            [
                                'grading_session_id' => $gs->id,
                                'applicant_id' => $applicantId,
                                'aptitude_area_id' => $d->id,
                            ],
                            [
                                'raw_score' => 10,
                                'max_score' => $d->max_items,
                                'normalized_score' => null,
                                'scored_by' => $users['test_admin']->id,
                                'scored_at' => CarbonImmutable::now()->subHours(6),
                            ]
                        );
                    });
                }
            }

            // Consultation summaries: make some pending so dashboard list + count show
            $consultationApplicants = $applicants->take(3);
            foreach ($consultationApplicants as $i => $a) {
                ConsultationSummary::query()->updateOrCreate(
                    ['applicant_id' => $a->id],
                    [
                        'status' => ConsultationSummary::STATUS_PENDING,
                        'recommended_course_id' => $courses[$i % $courses->count()]->id,
                        'counselor_comments' => null,
                        'system_notes' => ['seed' => 'demo'],
                        'counselor_id' => $users['test_admin']->id,
                        'released_at' => null,
                        'released_by' => null,
                    ]
                );
            }
        });
    }

    private function upsertUserWithRole(string $email, string $name, string $roleName): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
            ]
        );

        $role = Role::query()->where('name', $roleName)->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        return $user;
    }
}
