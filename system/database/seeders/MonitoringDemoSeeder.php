<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\AssignProctorDuty;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Developer-ready exam monitoring demo (Exam Navigation Organization HANDOFF).
 *
 * Creates one published exam session (EX-MON-1) inside its conduct window with
 * two accepted applicants, assigns active proctor duties to the Guidance clerk
 * and support staff, and records attendance + submission so both the Test
 * Administrator and the proctor-duty holder have clickable state on
 * /exams/monitoring and /exams/sessions/{id}/monitoring.
 *
 * Runs automatically via `foundation:reset --force` (part of DatabaseSeeder);
 * may also be run standalone:
 *   php artisan db:seed --class=Database\\Seeders\\MonitoringDemoSeeder
 *
 * Idempotent: re-running rebuilds EX-MON-1 to the same demo state.
 */
class MonitoringDemoSeeder extends Seeder
{
    public function run(): void
    {
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $room = Room::query()->where('code', 'RM-101')->firstOrFail();
        $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
        $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();

        $applicantA = $this->ensureAcceptedApplicant($cycle, 'monitoring.demo.one@nexiam.test', 'Monitoring', 'Demo One');
        $applicantB = $this->ensureAcceptedApplicant($cycle, 'monitoring.demo.two@nexiam.test', 'Monitoring', 'Demo Two');

        $session = ExamSession::query()->updateOrCreate(
            ['code' => 'EX-MON-1'],
            [
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'status' => ExamSession::STATUS_DRAFT,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'starts_at' => now()->subMinutes(30),
                'effective_end_at' => now()->addHours(2),
                'version' => 1,
                'published_at' => null,
            ],
        );

        // Rebuild the session body so re-runs land in the same demo state.
        $session->assignments()->with(['attendanceRecord', 'submissionRecord'])->get()
            ->each(function (ExamAssignment $assignment): void {
                $assignment->attendanceRecord()?->delete();
                $assignment->submissionRecord()?->delete();
                $assignment->delete();
            });
        $session->proctorDuties()->delete();

        foreach ([$applicantA, $applicantB] as $attempt) {
            app(AssignApplicantToExamSession::class)->handle($session, $attempt, $counselor);
        }

        app(PublishExamSession::class)->handle($session, $counselor, 1);

        $session = $session->fresh();

        app(AssignProctorDuty::class)->handle($session, $clerk, $counselor, 'mon-demo-duty-clerk');
        app(AssignProctorDuty::class)->handle($session, $support, $counselor, 'mon-demo-duty-support');

        // GRE-1/3 — one present + submitted, one absent: the monitoring roster
        // shows both a proctored attendee and a no-show.
        $session->assignments()->orderBy('admission_attempt_id')->each(function (ExamAssignment $assignment) use ($applicantA, $clerk): void {
            $outcome = $assignment->admission_attempt_id === $applicantA->id
                ? ExamAttendanceRecord::OUTCOME_PRESENT
                : ExamAttendanceRecord::OUTCOME_ABSENT;

            app(RecordExamAttendance::class)->handle($assignment, $clerk, $outcome, 'mon-demo-att-'.$assignment->id);

            if ($outcome === ExamAttendanceRecord::OUTCOME_PRESENT) {
                app(RecordExamSubmission::class)->handle($assignment, $clerk, 'mon-demo-sub-'.$assignment->id);
            }
        });

        $this->command?->info('Monitoring demo ready: EX-MON-1 published with proctor duties for guidance.clerk@ and guidance.support@.');
    }

    private function ensureAcceptedApplicant(
        AcademicCycle $cycle,
        string $email,
        string $firstName,
        string $lastName,
    ): AdmissionAttempt {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

        $attempt = AdmissionAttempt::query()
            ->whereHas('identity', fn ($q) => $q->where('email', $email))
            ->where('academic_cycle_id', $cycle->id)
            ->orderByDesc('id')
            ->first();

        if ($attempt === null) {
            $result = app(SubmitAdmissionAttempt::class)->handle(
                $institution,
                $cycle,
                $window,
                new AdmissionIntakeData(
                    firstName: $firstName,
                    lastName: $lastName,
                    birthdate: '2002-02-02',
                    sex: 'female',
                    contactEmail: $email,
                    applicantType: 'new',
                    programPreferenceCodes: ['BSN', 'BSPSY'],
                    grades: [
                        'GWA' => '1.90',
                        'ENG' => '89.00',
                        'FIL' => '90.00',
                        'MATH' => '86.00',
                        'SCI' => '88.00',
                    ],
                    middleName: null,
                    suffix: null,
                    contactPhone: '09171234569',
                    lastSchoolEnrolled: 'Tagudin National High School',
                    strand: 'STEM',
                    addressLine: '124 Demo St',
                    city: 'Tagudin',
                    province: 'Ilocos Sur',
                    zipCode: '2714',
                    termsAccepted: true,
                ),
                'monitoring-demo-seeder',
            );

            $attempt = $result['attempt'];
        }

        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
        }

        return $attempt;
    }
}
