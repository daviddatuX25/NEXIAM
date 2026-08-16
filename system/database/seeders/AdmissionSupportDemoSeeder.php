<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\OpenProgramFollowUp;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\StampAttemptEnrolled;
use App\Admission\Actions\StampAttemptEnrolling;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\AttemptProgramFacts;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\RecordScores;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Actions\ReleaseResult;
use Illuminate\Database\Seeder;

/**
 * Developer-ready admission-support demo (HANDOFF Phase 9).
 *
 * Builds three released+advised applicants on the SYN foundation:
 *   1. Normal advised-default route → enrolling → enrolled (paper = BSN).
 *   2. Enrolling with an open targeted PH follow-up (assigned PH resolves).
 *   3. Paper-different final program (default BSN, signed sheet BSPSY) with
 *      quota variance recorded.
 *
 * Idempotent: re-running is a no-op when the demo pool already exists.
 * Credentials/URLs land in HANDOFF.md.
 */
class AdmissionSupportDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
        $ph = User::query()->where('email', 'program.head.bsn@nexiam.test')->firstOrFail();

        $bsn = Program::query()->where('code', 'BSN')->firstOrFail();
        $bspsy = Program::query()->where('code', 'BSPSY')->firstOrFail();

        foreach ([$bsn, $bspsy] as $program) {
            ProgramQuota::query()->updateOrCreate(
                ['program_id' => $program->id, 'academic_cycle_id' => $cycle->id],
                ['slots' => 5],
            );
        }

        $already = ProgramPool::query()->where('admission_attempt_id', $this->attemptFor($institution, $cycle, 'demo.santos@nexiam.test')?->id ?? -1)->exists();

        if (! $already) {
            $this->buildJourneys($institution, $cycle, $window, $guidance, $registrar, $ph, $bsn, $bspsy);
        }

        // Always ensure the dashboard schedule exists (idempotent).
        $santos = $this->attemptFor($institution, $cycle, 'demo.santos@nexiam.test');
        $cruz = $this->attemptFor($institution, $cycle, 'demo.cruz@nexiam.test');
        $reyes = $this->attemptFor($institution, $cycle, 'demo.reyes@nexiam.test');

        if ($santos !== null && $cruz !== null && $reyes !== null) {
            $this->consultationSchedule($santos, $cruz, $reyes, $guidance);
        }

        $this->command?->info($already
            ? 'AdmissionSupportDemoSeeder: journeys already applied; consultation schedule ensured.'
            : 'AdmissionSupportDemoSeeder: 3 journeys + consultation schedule ready.');
    }

    private function buildJourneys(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        User $guidance,
        User $registrar,
        User $ph,
        Program $bsn,
        Program $bspsy,
    ): void {
        $santos = $this->journey($institution, $cycle, $window, $this->room($institution, 'RM-AS-1'), $guidance, $registrar, [
            'email' => 'demo.santos@nexiam.test',
            'lastName' => 'Santos',
            'firstName' => 'Maria',
            'sessionCode' => 'EX-AS-1',
            'poolKey' => 'as-pool-santos',
        ]);
        $this->normalRoute($santos, $guidance, $registrar, $bsn, $bspsy);

        $cruz = $this->journey($institution, $cycle, $window, $this->room($institution, 'RM-AS-2'), $guidance, $registrar, [
            'email' => 'demo.cruz@nexiam.test',
            'lastName' => 'Dela Cruz',
            'firstName' => 'Juan',
            'sessionCode' => 'EX-AS-2',
            'poolKey' => 'as-pool-cruz',
        ]);
        $this->followUpRoute($cruz, $guidance, $registrar, $ph, $bsn, $bspsy);

        $reyes = $this->journey($institution, $cycle, $window, $this->room($institution, 'RM-AS-3'), $guidance, $registrar, [
            'email' => 'demo.reyes@nexiam.test',
            'lastName' => 'Reyes',
            'firstName' => 'Ana',
            'sessionCode' => 'EX-AS-3',
            'poolKey' => 'as-pool-reyes',
        ]);
        $this->paperDifferentRoute($reyes, $guidance, $registrar, $bsn, $bspsy);
    }

    /**
     * Founder 2026-08-15: the /consultation dashboard reads consultation
     * SESSIONS with assigned applicants. Give the demo one today-session with
     * the demo applicants so the dashboard shows real numbers.
     */
    private function consultationSchedule(
        \App\Models\Admission\AdmissionAttempt $santos,
        \App\Models\Admission\AdmissionAttempt $cruz,
        \App\Models\Admission\AdmissionAttempt $reyes,
        User $guidance,
    ): void {
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $room = $this->room(Institution::query()->where('code', 'SYN-INST-001')->firstOrFail(), 'RM-AS-1');

        $session = \App\Models\Consultation\ConsultationSession::query()->where('code', 'CON-AS-TODAY')->first();

        if ($session === null) {
            $session = \App\Models\Consultation\ConsultationSession::query()->create([
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'code' => 'CON-AS-TODAY',
                'status' => \App\Models\Consultation\ConsultationSession::STATUS_PUBLISHED,
                'booking_mode' => 'staff_only',
                'scheduled_at' => now()->startOfDay()->setTime(9, 0),
                'version' => 1,
                'published_by_user_id' => $guidance->id,
                'published_at' => now(),
            ]);
        }

        foreach ([$santos, $cruz, $reyes] as $attempt) {
            $exists = \App\Models\Consultation\ConsultationAssignment::query()
                ->where('consultation_session_id', $session->id)
                ->where('admission_attempt_id', $attempt->id)
                ->exists();

            if (! $exists) {
                \App\Models\Consultation\ConsultationAssignment::query()->create([
                    'consultation_session_id' => $session->id,
                    'admission_attempt_id' => $attempt->id,
                    'status' => \App\Models\Consultation\ConsultationAssignment::STATUS_ASSIGNED,
                    'source' => 'staff_assigned',
                    'assigned_by_user_id' => $guidance->id,
                    'version' => 1,
                ]);
            }
        }
    }

    private function room(Institution $institution, string $code): Room
    {
        return Room::query()->updateOrCreate(
            ['institution_id' => $institution->id, 'code' => $code],
            ['name' => 'Admission Support '.$code, 'capacity' => 40, 'is_active' => true],
        );
    }

    private function attemptFor(Institution $institution, AcademicCycle $cycle, string $email): ?AdmissionAttempt
    {
        return AdmissionAttempt::query()
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->where('contact_email', $email)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, string>  $profile
     */
    private function journey(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        Room $room,
        User $guidance,
        User $registrar,
        array $profile,
    ): AdmissionAttempt {
        $existing = $this->attemptFor($institution, $cycle, $profile['email']);

        if ($existing !== null) {
            $attempt = $existing;
        } else {
            $intake = new AdmissionIntakeData(
                firstName: $profile['firstName'],
                lastName: $profile['lastName'],
                birthdate: '2001-01-01',
                sex: 'female',
                contactEmail: $profile['email'],
                applicantType: 'new',
                programPreferenceCodes: ['BSN', 'BSPSY'],
                grades: ['GWA' => '1.80', 'ENG' => '90.00', 'FIL' => '88.00', 'MATH' => '86.00', 'SCI' => '87.00'],
                middleName: null,
                suffix: null,
                contactPhone: '09171230001',
                lastSchoolEnrolled: 'Demo Senior High School',
                strand: 'STEM',
                addressLine: '123 Demo St',
                city: 'Tagudin',
                province: 'Ilocos Sur',
                zipCode: '2714',
                termsAccepted: true,
            );

            $result = app(SubmitAdmissionAttempt::class)->handle($institution, $cycle, $window, $intake, 'demo');
            $attempt = $result['attempt'];
        }

        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) ($attempt->version ?? 1));
            $attempt->refresh();
        }

        if (! $attempt->examAssignments()->exists()) {
            $session = $this->session($cycle, $room, $guidance, $profile['sessionCode']);
            app(AssignApplicantToExamSession::class)->handle($session, $attempt, $guidance);
            app(PublishExamSession::class)->handle($session, $guidance, (int) ($session->version ?? 1));
            $session->refresh();

            $assignment = $session->assignments()->where('admission_attempt_id', $attempt->id)->firstOrFail();
            app(RecordExamAttendance::class)->handle($assignment, $guidance, 'present', 'as-att-'.$profile['sessionCode']);
            app(RecordExamSubmission::class)->handle($assignment, $guidance, 'as-sub-'.$profile['sessionCode']);
            app(CloseExamSession::class)->handle($session, $guidance, (int) ($session->version ?? 1));

            $grading = GradingSession::query()
                ->where('exam_session_id', $session->id)
                ->firstOrFail();

            $scores = AptitudeArea::query()->where('is_active', true)->get()
                ->map(fn ($area) => ['aptitude_area_id' => $area->id, 'score' => 85])
                ->all();

            app(RecordScores::class)->handle($grading, $attempt, $scores, $guidance, 'as-scores-'.$profile['sessionCode']);
            app(FinalizeGradingSession::class)->handle($grading, $guidance, (int) ($grading->version ?? 1));
        }

        $advised = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->exists();

        if (! $advised) {
            app(RecordConsultationDecision::class)->handle($attempt, $guidance, 'approve', 'Demo consultation evidence.', 'as-consult-'.$profile['sessionCode']);
        }

        if (! $attempt->resultDocuments()->where('status', 'ready')->exists()) {
            app(PrepareResultDocument::class)->handle($attempt, $guidance);
            $document = $attempt->resultDocuments()->where('status', 'ready')->latest()->firstOrFail();
            app(ReleaseResult::class)->handle($attempt, $document, $guidance, 'as-release-'.$profile['sessionCode']);
        }

        return $attempt->fresh();
    }

    private function session(AcademicCycle $cycle, Room $room, User $guidance, string $code): ExamSession
    {
        $session = ExamSession::query()->where('code', $code)->first();

        if ($session !== null) {
            return $session;
        }

        return app(CreateExamSession::class)->handle(
            $cycle,
            $room,
            $code,
            now()->subHours(2)->toDateTimeString(),
            now()->addMinutes(30)->toDateTimeString(),
            $guidance,
        );
    }

    private function poolAndDefault(
        AdmissionAttempt $attempt,
        User $guidance,
        string $poolKey,
    ): void {
        if (ProgramPool::currentFor($attempt->id) === null) {
            app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, 'BSN', [
                ['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference'],
                ['program_code' => 'BSPSY', 'display_order' => 2, 'basis' => 'guidance_option'],
            ], 'Demo pool with advised default.', $poolKey);
        }
    }

    private function normalRoute(
        AdmissionAttempt $attempt,
        User $guidance,
        User $registrar,
        Program $bsn,
        Program $bspsy,
    ): void {
        $this->poolAndDefault($attempt, $guidance, 'as-pool-santos');

        if (! AttemptProgramFacts::isEnrolled($attempt)) {
            if (! AttemptProgramFacts::isEnrolling($attempt)) {
                app(StampAttemptEnrolling::class)->handle($attempt, $registrar, 'BSN', 'ENR-DEMO-001', 'Demo sheet received.', 'as-enrolling-santos');
            }

            app(StampAttemptEnrolled::class)->handle(
                $attempt,
                $registrar,
                'BSN',
                'ENR-DEMO-001',
                hash('sha256', 'demo-santos-sheet'),
                'Registrar Clerk (demo)',
                'Signed demo sheet verified.',
                'as-enrolled-santos',
            );
        }
    }

    private function followUpRoute(
        AdmissionAttempt $attempt,
        User $guidance,
        User $registrar,
        User $ph,
        Program $bsn,
        Program $bspsy,
    ): void {
        $this->poolAndDefault($attempt, $guidance, 'as-pool-cruz');

        // Guidance opens the targeted follow-up BEFORE enrollment processing starts.
        $open = ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->exists();

        if (! $open) {
            app(OpenProgramFollowUp::class)->handle($attempt, $guidance, 'BSN', 'Program-fit edge case for demo.', 'as-followup-cruz');
        }

        if (! AttemptProgramFacts::isEnrolling($attempt)) {
            app(StampAttemptEnrolling::class)->handle($attempt, $registrar, 'BSN', 'ENR-DEMO-002', 'Demo sheet received.', 'as-enrolling-cruz');
        }
    }

    private function paperDifferentRoute(
        AdmissionAttempt $attempt,
        User $guidance,
        User $registrar,
        Program $bsn,
        Program $bspsy,
    ): void {
        $this->poolAndDefault($attempt, $guidance, 'as-pool-reyes');

        if (! AttemptProgramFacts::isEnrolled($attempt)) {
            if (! AttemptProgramFacts::isEnrolling($attempt)) {
                app(StampAttemptEnrolling::class)->handle($attempt, $registrar, 'BSN', 'ENR-DEMO-003', 'Demo sheet received.', 'as-enrolling-reyes');
            }

            app(StampAttemptEnrolled::class)->handle(
                $attempt,
                $registrar,
                'BSPSY',
                'ENR-DEMO-003',
                hash('sha256', 'demo-reyes-sheet'),
                'Registrar Clerk (demo)',
                'Signed sheet confirms BSPSY (differs from provisional BSN).',
                'as-enrolled-reyes',
            );
        }
    }
}
