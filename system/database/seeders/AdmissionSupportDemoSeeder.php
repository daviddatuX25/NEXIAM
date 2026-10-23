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
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Communications\PublicPolicy;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
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
 *   1. Normal advised-default route → enrolling → enrolled (paper = BSIT).
 *   2. Enrolling with an open targeted PH follow-up (assigned PH resolves).
 *   3. Paper-different final program (default BSIT, signed sheet BA-Psych) with
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
        if (! $institution->usesSeatingArrangements()) {
            throw new \RuntimeException('Admission support demo requires Allow assigned seating to be enabled explicitly before seeding.');
        }
        if (! $institution->usesMultiSetBooklets()) {
            foreach (['RM-AS-1', 'RM-AS-2', 'RM-AS-3'] as $roomCode) {
                $room = Room::query()->where('institution_id', $institution->id)->where('code', $roomCode)->first();
                $arrangement = $room?->defaultSeatingArrangement()->first()
                    ?? SeatingArrangement::query()
                        ->where('availability_mode', SeatingArrangement::AVAILABILITY_ALL_ROOMS)
                        ->where('is_active', true)
                        ->whereRaw('(rows * columns) <= ?', [40])
                        ->firstOrFail();
                if ($arrangement->booklet_pattern !== SeatingArrangement::PATTERN_UNIFORM) {
                    throw new \RuntimeException('Admission support demo layout requires Allow alternate Booklet Sets to be enabled explicitly before seeding.');
                }
            }
        }
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
        $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

        $bsit = Program::query()->where('code', 'BSIT')->firstOrFail();
        $baPsych = Program::query()->where('code', 'BA-Psych')->firstOrFail();

        foreach ([$bsit, $baPsych] as $program) {
            ProgramQuota::query()->updateOrCreate(
                ['program_id' => $program->id, 'academic_cycle_id' => $cycle->id],
                ['slots' => 5],
            );
        }

        $already = ProgramPool::query()->where('admission_attempt_id', $this->attemptFor($institution, $cycle, 'demo.santos@nexiam.test')?->id ?? -1)->exists();

        if (! $already) {
            $this->buildJourneys($institution, $cycle, $window, $guidance, $registrar, $ph);
        }

        // Always ensure the dashboard schedule exists (idempotent).
        $santos = $this->attemptFor($institution, $cycle, 'demo.santos@nexiam.test');
        $cruz = $this->attemptFor($institution, $cycle, 'demo.cruz@nexiam.test');
        $reyes = $this->attemptFor($institution, $cycle, 'demo.reyes@nexiam.test');

        if ($santos !== null && $cruz !== null && $reyes !== null) {
            $this->consultationSchedule($santos, $cruz, $reyes, $guidance);
        }

        $this->call(AdmissionBenchmarkSeeder::class);
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
    ): void {
        $santos = $this->journey($institution, $cycle, $window, $this->room($institution, 'RM-AS-1'), $guidance, $registrar, [
            'email' => 'demo.santos@nexiam.test',
            'lastName' => 'Santos',
            'firstName' => 'Maria',
            'sessionCode' => 'EX-AS-1',
            'poolKey' => 'as-pool-santos',
        ]);
        $this->normalRoute($santos, $guidance, $registrar);

        $cruz = $this->journey($institution, $cycle, $window, $this->room($institution, 'RM-AS-2'), $guidance, $registrar, [
            'email' => 'demo.cruz@nexiam.test',
            'lastName' => 'Dela Cruz',
            'firstName' => 'Juan',
            'sessionCode' => 'EX-AS-2',
            'poolKey' => 'as-pool-cruz',
        ]);
        $this->followUpRoute($cruz, $guidance, $registrar, $ph);

        $reyes = $this->journey($institution, $cycle, $window, $this->room($institution, 'RM-AS-3'), $guidance, $registrar, [
            'email' => 'demo.reyes@nexiam.test',
            'lastName' => 'Reyes',
            'firstName' => 'Ana',
            'sessionCode' => 'EX-AS-3',
            'poolKey' => 'as-pool-reyes',
        ]);
        $this->paperDifferentRoute($reyes, $guidance, $registrar);
    }

    /**
     * Founder 2026-08-15: the /consultation dashboard reads consultation
     * SESSIONS with assigned applicants. Give the demo one today-session with
     * the demo applicants so the dashboard shows real numbers.
     */
    private function consultationSchedule(
        AdmissionAttempt $santos,
        AdmissionAttempt $cruz,
        AdmissionAttempt $reyes,
        User $guidance,
    ): void {
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $room = $this->room(Institution::query()->where('code', 'SYN-INST-001')->firstOrFail(), 'RM-AS-1');

        $session = ConsultationSession::query()->where('code', 'CON-AS-TODAY')->first();

        if ($session === null) {
            $session = ConsultationSession::query()->create([
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'code' => 'CON-AS-TODAY',
                'status' => ConsultationSession::STATUS_PUBLISHED,
                'booking_mode' => 'staff_only',
                'scheduled_at' => now()->startOfDay()->setTime(9, 0),
                'version' => 1,
                'published_by_user_id' => $guidance->id,
                'published_at' => now(),
            ]);
        }

        foreach ([$santos, $cruz, $reyes] as $attempt) {
            $exists = ConsultationAssignment::query()
                ->where('consultation_session_id', $session->id)
                ->where('admission_attempt_id', $attempt->id)
                ->exists();

            if (! $exists) {
                ConsultationAssignment::query()->create([
                    'consultation_session_id' => $session->id,
                    'admission_attempt_id' => $attempt->id,
                    'status' => ConsultationAssignment::STATUS_ASSIGNED,
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
            $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
                ?? throw new \RuntimeException('A designated published PublicPolicy revision is required for seeded intake.');
            $revision = $policy->publishedRevision
                ?? throw new \RuntimeException('The designated intake policy has no published revision.');
            $intake = new AdmissionIntakeData(
                firstName: $profile['firstName'],
                lastName: $profile['lastName'],
                birthdate: '2001-01-01',
                sex: 'female',
                contactEmail: $profile['email'],
                applicantType: 'new',
                programPreferenceCodes: ['BSIT', 'BA-Psych'],
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
                publicPolicyId: (int) $policy->getKey(),
                publicPolicyRevisionId: (int) $revision->getKey(),
                publicPolicyRevisionDigest: $revision->contentDigest(),
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

        if (! $attempt->resultDocuments()->where('status', 'ready')->exists()) {
            app(PrepareResultDocument::class)->handle($attempt, $guidance);
            $document = $attempt->resultDocuments()->where('status', 'ready')->latest()->firstOrFail();
            app(ReleaseResult::class)->handle($attempt, $document, $guidance, 'as-release-'.$profile['sessionCode']);
        }

        $advised = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->exists();

        if (! $advised) {
            app(RecordConsultationDecision::class)->handle($attempt, $guidance, 'approve', 'Demo consultation evidence.', 'as-consult-'.$profile['sessionCode']);
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
            ExamSession::SEATING_MODE_ASSIGNED_SEATING,
            OmrQuestionBookletVersion::query()
                ->whereHas('booklet', fn ($query) => $query->where('code', ExamMaterialRoutingDemoSeeder::BOOKLET_CODE))
                ->whereNotNull('finalized_at')
                ->firstOrFail(),
            $room->defaultSeatingArrangement()->first()
                ?? SeatingArrangement::query()
                    ->where('availability_mode', SeatingArrangement::AVAILABILITY_ALL_ROOMS)
                    ->where('is_active', true)
                    ->whereRaw('(rows * columns) <= ?', [$room->capacity])
                    ->firstOrFail(),
        );
    }

    private function poolAndDefault(
        AdmissionAttempt $attempt,
        User $guidance,
        string $poolKey,
    ): void {
        if (ProgramPool::currentFor($attempt->id) === null) {
            app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, 'BSIT', [
                ['program_code' => 'BSIT', 'display_order' => 1, 'basis' => 'preference'],
                ['program_code' => 'BA-Psych', 'display_order' => 2, 'basis' => 'guidance_option'],
            ], 'Demo pool with advised default.', $poolKey);
        }
    }

    private function normalRoute(
        AdmissionAttempt $attempt,
        User $guidance,
        User $registrar,
    ): void {
        $this->poolAndDefault($attempt, $guidance, 'as-pool-santos');

        if (! AttemptProgramFacts::isEnrolled($attempt)) {
            if (! AttemptProgramFacts::isEnrolling($attempt)) {
                app(StampAttemptEnrolling::class)->handle($attempt, $registrar, 'BSIT', 'ENR-DEMO-001', 'Demo sheet received.', 'as-enrolling-santos');
            }

            app(StampAttemptEnrolled::class)->handle(
                $attempt,
                $registrar,
                'BSIT',
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
    ): void {
        $this->poolAndDefault($attempt, $guidance, 'as-pool-cruz');

        // Guidance opens the targeted follow-up BEFORE enrollment processing starts.
        $open = ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->exists();

        if (! $open) {
            app(OpenProgramFollowUp::class)->handle($attempt, $guidance, 'BSIT', 'Program-fit edge case for demo.', 'as-followup-cruz');
        }

        if (! AttemptProgramFacts::isEnrolling($attempt)) {
            app(StampAttemptEnrolling::class)->handle($attempt, $registrar, 'BSIT', 'ENR-DEMO-002', 'Demo sheet received.', 'as-enrolling-cruz');
        }
    }

    private function paperDifferentRoute(
        AdmissionAttempt $attempt,
        User $guidance,
        User $registrar,
    ): void {
        $this->poolAndDefault($attempt, $guidance, 'as-pool-reyes');

        if (! AttemptProgramFacts::isEnrolled($attempt)) {
            if (! AttemptProgramFacts::isEnrolling($attempt)) {
                app(StampAttemptEnrolling::class)->handle($attempt, $registrar, 'BSIT', 'ENR-DEMO-003', 'Demo sheet received.', 'as-enrolling-reyes');
            }

            app(StampAttemptEnrolled::class)->handle(
                $attempt,
                $registrar,
                'BA-Psych',
                'ENR-DEMO-003',
                hash('sha256', 'demo-reyes-sheet'),
                'Registrar Clerk (demo)',
                'Signed sheet confirms BA-Psych (differs from provisional BSIT).',
                'as-enrolled-reyes',
            );
        }
    }
}
