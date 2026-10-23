<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Examination\Actions\StartExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Communications\PublicPolicy;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Developer-ready exam monitoring demo (Exam Navigation Organization HANDOFF).
 *
 * Creates one started exam session (EX-MON-1) inside its conduct window with
 * two accepted applicants and attendance + submission so the Test
 * Administrator has clickable state on /exams/monitoring and
 * /exams/sessions/{id}/monitoring.
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
        $room = Room::query()->whereIn('code', ['ITBR-105', 'RM-101'])->firstOrFail();
        $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

        $applicantA = $this->ensureAcceptedApplicant($cycle, 'monitoring.demo.one@nexiam.test', 'Monitoring', 'Demo One');
        $applicantB = $this->ensureAcceptedApplicant($cycle, 'monitoring.demo.two@nexiam.test', 'Monitoring', 'Demo Two');

        $booklet = OmrQuestionBookletVersion::query()->whereNotNull('finalized_at')->first();
        if ($booklet === null) {
            (new ExamMaterialRoutingDemoSeeder)->run();
            $booklet = OmrQuestionBookletVersion::query()->whereNotNull('finalized_at')->first();
        }
        $arrangement = SeatingArrangement::query()
            ->where('is_active', true)
            ->whereRaw('(rows * columns) <= ?', [$room->capacity])
            ->first();

        $session = ExamSession::query()->updateOrCreate(
            ['code' => 'EX-MON-1'],
            [
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'booklet_version_id' => $booklet?->id,
                'seating_arrangement_id' => $arrangement?->id,
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
                $assignment->seatAllocations()->delete();
                $assignment->delete();
            });

        foreach ([$applicantA, $applicantB] as $attempt) {
            app(AssignApplicantToExamSession::class)->handle($session, $attempt, $counselor);
        }

        app(PublishExamSession::class)->handle($session, $counselor, (int) $session->version);
        $session = $session->fresh();
        app(StartExamSession::class)->handle($session, $counselor, (int) $session->version);
        $session = $session->fresh();

        // GRE-1/3 — one present + submitted, one absent: the monitoring roster
        // shows both a present attendee and a no-show.
        $session->assignments()->orderBy('admission_attempt_id')->each(function (ExamAssignment $assignment) use ($applicantA, $counselor): void {
            $outcome = $assignment->admission_attempt_id === $applicantA->id
                ? ExamAttendanceRecord::OUTCOME_PRESENT
                : ExamAttendanceRecord::OUTCOME_ABSENT;

            app(RecordExamAttendance::class)->handle($assignment, $counselor, $outcome, 'mon-demo-att-'.$assignment->id);

            if ($outcome === ExamAttendanceRecord::OUTCOME_PRESENT) {
                app(RecordExamSubmission::class)->handle($assignment, $counselor, 'mon-demo-sub-'.$assignment->id);
            }
        });

        $this->command?->info('Monitoring demo ready: EX-MON-1 started for the Test Administrator.');
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
            $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
                ?? throw new \RuntimeException('A designated published PublicPolicy revision is required for seeded intake.');
            $revision = $policy->publishedRevision
                ?? throw new \RuntimeException('The designated intake policy has no published revision.');
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
                    programPreferenceCodes: ['BSIT', 'BA-Psych'],
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
                    publicPolicyId: (int) $policy->getKey(),
                    publicPolicyRevisionId: (int) $revision->getKey(),
                    publicPolicyRevisionDigest: $revision->contentDigest(),
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
