<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Consultation\Actions\AssignApplicantToConsultationSession;
use App\Consultation\Actions\CreateConsultationSession;
use App\Consultation\Actions\OpenConsultationSessionReservation;
use App\Consultation\Actions\PublishConsultationSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use Illuminate\Database\Seeder;

/**
 * Developer-ready consultation demo (consultation-plan-aggregate HANDOFF).
 *
 * Creates two consultation sessions:
 *   - CONS-DEMO-1  staff_only, published, one assigned applicant
 *   - CONS-DEMO-2  open_for_reservation, published, booking open
 * Both demo applicants are result-ready (grading finalized + document
 * prepared), so assign/reserve flows work end-to-end.
 *
 * Run AFTER `foundation:reset --force`:
 *   php artisan db:seed --class=Database\\Seeders\\ConsultationDemoSeeder
 *
 * Idempotent: re-running when CONS-DEMO-1 exists is a no-op.
 */
class ConsultationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
        $room = Room::query()->where('code', 'RM-101')->firstOrFail();
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

        if (ConsultationSession::query()->where('code', 'CONS-DEMO-1')->exists()) {
            $this->command?->info('Consultation demo already seeded (CONS-DEMO-1).');

            return;
        }

        $applicantA = AdmissionAttempt::query()
            ->whereHas('identity', fn ($q) => $q->where('email', (string) config('seed.applicants.accepted.email')))
            ->where('academic_cycle_id', $cycle->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $applicantB = $this->ensureAcceptedApplicant($institution, $cycle, $window, $registrar);

        $this->makeResultReady($applicantA, $guidance);
        $this->makeResultReady($applicantB, $guidance);

        $staffOnly = app(CreateConsultationSession::class)->handle(
            $cycle,
            $room,
            'CONS-DEMO-1',
            now()->addDays(7)->setTime(9, 0)->toDateTimeString(),
            ConsultationSession::BOOKING_STAFF_ONLY,
            $guidance,
        );
        app(AssignApplicantToConsultationSession::class)->handle($staffOnly, $applicantA, $guidance);
        $staffOnly = $staffOnly->fresh();
        app(PublishConsultationSession::class)->handle($staffOnly, $guidance, (int) $staffOnly->version);

        // A is fully advised + released: the Registrar card must show ELIGIBLE.
        $documentA = \App\Models\Release\ResultDocument::query()
            ->where('admission_attempt_id', $applicantA->id)
            ->where('status', \App\Models\Release\ResultDocument::STATUS_READY)
            ->latest()
            ->firstOrFail();
        app(\App\Release\Actions\ReleaseResult::class)->handle(
            $applicantA,
            $documentA,
            $guidance,
            'consultation-demo-release-'.$applicantA->id,
            \App\Release\Actions\ReleaseResult::MODE_F2F,
        );
        app(\App\Release\Actions\RecordConsultationDecision::class)->handle(
            $applicantA,
            $guidance,
            'approve',
            null,
            'consultation-demo-decision-'.$applicantA->id,
        );

        $openBooking = app(CreateConsultationSession::class)->handle(
            $cycle,
            $room,
            'CONS-DEMO-2',
            now()->addDays(8)->setTime(13, 0)->toDateTimeString(),
            ConsultationSession::BOOKING_OPEN_FOR_RESERVATION,
            $guidance,
        );


        app(AssignApplicantToConsultationSession::class)->handle($openBooking, $applicantB, $guidance);
        $openBooking = $openBooking->fresh();
        $published = app(PublishConsultationSession::class)->handle($openBooking, $guidance, (int) $openBooking->version);
    }

    private function makeResultReady(AdmissionAttempt $attempt, User $guidance): void
    {
        $existing = ExamSession::query()
            ->whereHas('assignments', fn ($q) => $q->where('admission_attempt_id', $attempt->id))
            ->where('status', ExamSession::STATUS_COMPLETED)
            ->exists();

        if (! $existing) {
            $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
            $room = Room::query()->where('code', 'RM-101')->firstOrFail();

            $examSession = ExamSession::query()->create([
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'code' => 'EX-CONS-RDY-'.$attempt->id,
                'status' => ExamSession::STATUS_COMPLETED,
                'starts_at' => now()->subDays(5),
                'effective_end_at' => now()->subDays(5)->addHours(2),
                'closed_at' => now()->subDays(5),
            ]);

            ExamAssignment::query()->create([
                'exam_session_id' => $examSession->id,
                'admission_attempt_id' => $attempt->id,
                'status' => ExamAssignment::STATUS_ASSIGNED,
                'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
            ]);

            GradingSession::query()->create([
                'exam_session_id' => $examSession->id,
                'status' => GradingSession::STATUS_FINALIZED,
                'opened_at' => now()->subDays(4),
                'finalized_at' => now()->subDays(3),
            ]);
        }

        $document = ResultDocument::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', ResultDocument::STATUS_READY)
            ->exists();

        if (! $document) {
            app(PrepareResultDocument::class)->handle($attempt, $guidance);
        }
    }

    private function ensureAcceptedApplicant(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        User $registrar,
    ): AdmissionAttempt {
        $email = 'consultation.demo.two@nexiam.test';

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
                    firstName: 'Consultation',
                    lastName: 'Demo Two',
                    birthdate: '2002-03-03',
                    sex: 'male',
                    contactEmail: $email,
                    applicantType: 'new',
                    programPreferenceCodes: ['BSN'],
                    grades: [
                        'GWA' => '1.85',
                        'ENG' => '88.00',
                        'FIL' => '89.00',
                        'MATH' => '87.00',
                        'SCI' => '86.00',
                    ],
                    middleName: null,
                    suffix: null,
                    contactPhone: '09171234569',
                    lastSchoolEnrolled: 'Tagudin National High School',
                    strand: 'STEM',
                    addressLine: '125 Demo St',
                    city: 'Tagudin',
                    province: 'Ilocos Sur',
                    zipCode: '2714',
                    termsAccepted: true,
                ),
                'consultation-demo-seeder',
            );

            $attempt = $result['attempt'];
        }

        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
        }

        return $attempt;
    }
}
