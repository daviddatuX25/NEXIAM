<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\ApplicantAccessKey;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\RecordScores;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Actions\ReleaseResult;
use Illuminate\Database\Seeder;

/**
 * Developer-ready applicant-portal demo (2026-08-15-applicant-portal-interface).
 *
 * Three pinned portal logins that exercise the status-spine hub states:
 *   1. PORT-ONLN — full journey, released MODE_ONLINE, consultation booked
 *      (hub shows the released score artifact inline).
 *   2. PORT-F2F2 — full journey, released MODE_F2F, no scores serialized
 *      (hub shows "claim at your consultation" + book action).
 *   3. PORT-DEFL — released online with Guidance advised default
 *      (hub shows the ✦ auto program-choice card; ack optional).
 *
 * Idempotent: re-running skips journeys that already exist.
 * Credentials/URLs land in HANDOFF.md.
 */
class ApplicantPortalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

        $this->releasedJourney(
            $institution,
            $cycle,
            $window,
            $guidance,
            $registrar,
            firstName: 'Online',
            lastName: 'Portal',
            email: 'demo.portal.online@nexiam.test',
            accessKey: 'PORT-ONLN',
            sessionCode: 'EX-PL-ONL',
            releaseMode: ReleaseResult::MODE_ONLINE,
            advisedDefault: false,
        );

        $this->releasedJourney(
            $institution,
            $cycle,
            $window,
            $guidance,
            $registrar,
            firstName: 'F2F',
            lastName: 'Portal',
            email: 'demo.portal.f2f@nexiam.test',
            accessKey: 'PORT-F2F2',
            sessionCode: 'EX-PL-F2F',
            releaseMode: ReleaseResult::MODE_F2F,
            advisedDefault: false,
        );

        $this->releasedJourney(
            $institution,
            $cycle,
            $window,
            $guidance,
            $registrar,
            firstName: 'Default',
            lastName: 'Portal',
            email: 'demo.portal.default@nexiam.test',
            accessKey: 'PORT-DEFL',
            sessionCode: 'EX-PL-DEF',
            releaseMode: ReleaseResult::MODE_ONLINE,
            advisedDefault: true,
        );

        $this->command?->info('Applicant portal demo ready (keys PORT-ONLN / PORT-F2F2 / PORT-DEFL · birthdate 2003-09-25).');
    }

    private function releasedJourney(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        User $guidance,
        User $registrar,
        string $firstName,
        string $lastName,
        string $email,
        string $accessKey,
        string $sessionCode,
        string $releaseMode,
        bool $advisedDefault,
    ): void {
        if (! ApplicantAccessKey::isValidShape($accessKey)) {
            throw new \RuntimeException("Invalid portal demo access key shape: {$accessKey}");
        }

        $attempt = $this->attemptFor($institution, $cycle, $email);

        if ($attempt === null) {
            $result = app(SubmitAdmissionAttempt::class)->handle(
                $institution,
                $cycle,
                $window,
                new AdmissionIntakeData(
                    firstName: $firstName,
                    lastName: $lastName,
                    birthdate: '2003-09-25',
                    sex: 'female',
                    contactEmail: $email,
                    applicantType: 'new',
                    programPreferenceCodes: ['BSN', 'BSPSY'],
                    grades: ['GWA' => '1.80', 'ENG' => '90.00', 'FIL' => '88.00', 'MATH' => '86.00', 'SCI' => '87.00'],
                    middleName: null,
                    suffix: null,
                    contactPhone: '09171239999',
                    lastSchoolEnrolled: 'Demo Senior High School',
                    strand: 'STEM',
                    addressLine: '123 Portal St',
                    city: 'Tagudin',
                    province: 'Ilocos Sur',
                    zipCode: '2714',
                    termsAccepted: true,
                ),
                'applicant-portal-seeder',
            );
            $attempt = $result['attempt'];
        }

        $this->pinAccessKey($attempt, $accessKey);

        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) ($attempt->version ?? 1));
            $attempt->refresh();
        }

        if (! $attempt->examAssignments()->exists()) {
            $session = $this->session($cycle, $guidance, $sessionCode, 'RM-PL-'.substr($sessionCode, -3));
            app(AssignApplicantToExamSession::class)->handle($session, $attempt, $guidance);
            app(PublishExamSession::class)->handle($session, $guidance, (int) ($session->version ?? 1));
            $session->refresh();

            $assignment = $session->assignments()->where('admission_attempt_id', $attempt->id)->firstOrFail();
            app(RecordExamAttendance::class)->handle($assignment, $guidance, 'present', 'portal-att-'.$sessionCode);
            app(RecordExamSubmission::class)->handle($assignment, $guidance, 'portal-sub-'.$sessionCode);
            app(CloseExamSession::class)->handle($session, $guidance, (int) ($session->version ?? 1));

            $grading = GradingSession::query()
                ->where('exam_session_id', $session->id)
                ->firstOrFail();

            $scores = AptitudeArea::query()->where('is_active', true)->get()
                ->map(fn ($area) => ['aptitude_area_id' => $area->id, 'score' => 85])
                ->all();

            app(RecordScores::class)->handle($grading, $attempt, $scores, $guidance, 'portal-scores-'.$sessionCode);
            app(FinalizeGradingSession::class)->handle($grading, $guidance, (int) ($grading->version ?? 1));
        }

        if (! ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->exists()) {
            app(RecordConsultationDecision::class)->handle(
                $attempt,
                $guidance,
                'approve',
                'Portal demo consultation evidence.',
                'portal-consult-'.$sessionCode,
            );
        }

        if (! $attempt->resultDocuments()->where('status', 'ready')->exists()) {
            app(PrepareResultDocument::class)->handle($attempt, $guidance);
            $document = $attempt->resultDocuments()->where('status', 'ready')->latest()->firstOrFail();
            app(ReleaseResult::class)->handle(
                $attempt,
                $document,
                $guidance,
                'portal-release-'.$sessionCode,
                $releaseMode,
            );
        }

        if ($advisedDefault && ! $attempt->programSelections()->exists()) {
            app(SetGuidanceProgramPool::class)->handle(
                $attempt,
                $guidance,
                'BSN',
                [
                    ['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference'],
                    ['program_code' => 'BSPSY', 'display_order' => 2, 'basis' => 'guidance_option'],
                ],
                'Portal demo advised default.',
                'portal-pool-'.$sessionCode,
            );
        }

        // Book the online/demo applicants into the existing today consultation
        // session so the spine shows a booked consultation card (f2f applicant
        // stays unassigned so the claim-at-consultation action remains live).
        if ($releaseMode === ReleaseResult::MODE_ONLINE) {
            $this->bookTodayConsultation($attempt, $guidance);
        }
    }

    private function bookTodayConsultation(AdmissionAttempt $attempt, User $guidance): void
    {
        $session = \App\Models\Consultation\ConsultationSession::query()
            ->where('code', 'CON-AS-TODAY')
            ->first();

        if ($session === null) {
            return;
        }

        \App\Models\Consultation\ConsultationAssignment::query()->updateOrCreate(
            [
                'consultation_session_id' => $session->id,
                'admission_attempt_id' => $attempt->id,
            ],
            [
                'status' => \App\Models\Consultation\ConsultationAssignment::STATUS_ASSIGNED,
                'source' => 'staff_assigned',
                'assigned_by_user_id' => $guidance->id,
                'version' => 1,
            ],
        );
    }

    private function session(AcademicCycle $cycle, User $guidance, string $code, string $roomCode): ExamSession
    {
        $session = ExamSession::query()->where('code', $code)->first();

        if ($session !== null) {
            return $session;
        }

        $room = Room::query()->updateOrCreate(
            ['institution_id' => $cycle->institution_id, 'code' => $roomCode],
            ['name' => 'Portal Demo '.$roomCode, 'capacity' => 40, 'is_active' => true],
        );

        return app(CreateExamSession::class)->handle(
            $cycle,
            $room,
            $code,
            now()->subHours(2)->toDateTimeString(),
            now()->addMinutes(30)->toDateTimeString(),
            $guidance,
        );
    }

    private function pinAccessKey(AdmissionAttempt $attempt, string $accessKey): void
    {
        $hash = ApplicantAccessKey::hash($accessKey);

        $challenge = ApplicantAccessChallenge::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if ($challenge === null) {
            ApplicantAccessChallenge::query()->create([
                'applicant_identity_id' => $attempt->applicant_identity_id,
                'admission_attempt_id' => $attempt->id,
                'purpose' => 'access_key',
                'token_hash' => $hash,
                'request_key' => 'portal-seed-pin-'.$attempt->id,
                'expires_at' => now()->addYears(5),
            ]);

            return;
        }

        $challenge->update([
            'token_hash' => $hash,
            'expires_at' => now()->addYears(5),
            'consumed_at' => null,
        ]);
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
}
