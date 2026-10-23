<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\ApplicantAccessKey;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Actions\CancelConsultationAssignment;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Communications\PublicPolicy;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\StaffProfile;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use Illuminate\Database\Seeder;
use RuntimeException;

final class OnlineConsultationDemoSeeder extends Seeder
{
    private const EMAIL = 'online.walkin@nexiam.test';

    private const ACCESS_KEY = 'ONLN-CNSL';

    private const BIRTHDATE = '2004-05-15';

    private const COUNSELOR_EMAIL = 'online.counselor@nexiam.test';

    private const COUNSELOR_PASSWORD = 'password';

    public function run(): void
    {
        if (! ApplicantAccessKey::isValidShape(self::ACCESS_KEY)) {
            throw new RuntimeException('The synthetic online consultation access key is invalid.');
        }

        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
        $room = Room::query()->whereIn('code', ['ITBR-105', 'RM-101'])->firstOrFail();
        $guidance = $this->counselor($institution, $cycle);
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

        $attempt = $this->attempt($institution, $cycle, $window, $registrar);
        $this->pinAccessKey($attempt);
        if (! $attempt->identity?->applicantAccount()->exists()) {
            app(AuthenticateApplicantWithAccessKey::class)->handle(self::ACCESS_KEY, self::BIRTHDATE);
        }

        $this->ensureScored($cycle, $room, $attempt, $guidance);
        $document = ResultDocument::query()
            ->whereKey($attempt->current_result_document_id)
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', ResultDocument::STATUS_READY)
            ->first();
        if ($document === null) {
            $document = app(PrepareResultDocument::class)->handle($attempt->fresh(), $guidance);
            $attempt->forceFill(['current_result_document_id' => $document->id])->save();
        }

        $this->preserveWalkInPath($attempt, $guidance);

        $this->command?->info('Online consultation walk-in applicant fixture is ready; no consultation encounter was created and online policy was not changed.');
        $this->command?->line('Guidance counselor login: '.self::COUNSELOR_EMAIL.' / '.self::COUNSELOR_PASSWORD);
        $this->command?->line('Applicant login: '.self::EMAIL.' · access key: '.self::ACCESS_KEY.' · birthdate: '.self::BIRTHDATE);
        $this->command?->line('Accepted prepared attempt ID: '.$attempt->id.' · cycle: '.$cycle->code);
        $this->command?->line('Use the applicant portal, then have an authorized Guidance counselor explicitly Start online consultation for attempt '.$attempt->id.'.');
    }

    private function counselor(Institution $institution, AcademicCycle $cycle): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => self::COUNSELOR_EMAIL],
            ['name' => 'Synthetic Online Guidance Counselor', 'password' => self::COUNSELOR_PASSWORD],
        );
        if ($user->name !== 'Synthetic Online Guidance Counselor') {
            throw new RuntimeException('The online demo counselor email is already owned by a different identity.');
        }

        $profile = StaffProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'institution_id' => $institution->id,
                'office' => 'guidance',
                'department_id' => null,
                'program_codes' => [],
                'active_cycle_code' => $cycle->code,
                'status' => StaffProfile::STATUS_ACTIVE,
            ],
        );
        if ((int) $profile->institution_id !== (int) $institution->id
            || $profile->office !== 'guidance'
            || $profile->status !== StaffProfile::STATUS_ACTIVE
            || $profile->active_cycle_code !== $cycle->code) {
            throw new RuntimeException('The synthetic online counselor profile is not active Guidance staff in the demo scope.');
        }

        $authentication = StaffAuthenticationState::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'activation_completed_at' => now(),
                'password_set_at' => now(),
                'must_change_password' => false,
                'session_version' => 1,
                'mfa_required' => false,
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
            ],
        );
        if ($authentication->activation_completed_at === null
            || $authentication->password_set_at === null
            || $authentication->must_change_password
            || $authentication->mfa_required) {
            throw new RuntimeException('The dedicated synthetic counselor authentication state is not ready for repeatable demo login.');
        }
        $user->syncRoles(['guidance_counselor']);

        if (! app(CapabilityAuthorizer::class)->decideFresh($user, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE, [
            'institution_id' => (int) $institution->id,
            'office' => 'guidance',
        ])->allowed) {
            throw new RuntimeException('The seeded guidance_counselor role does not grant the scoped consultation decision capability.');
        }

        return $user;
    }

    private function attempt(Institution $institution, AcademicCycle $cycle, ApplicationWindow $window, User $registrar): AdmissionAttempt
    {
        $attempt = AdmissionAttempt::query()
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->whereHas('identity', fn ($query) => $query->where('email', self::EMAIL))
            ->first();

        if ($attempt === null) {
            $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
                ?? throw new RuntimeException('A designated published PublicPolicy revision is required for the online consultation demo applicant.');
            $revision = $policy->publishedRevision
                ?? throw new RuntimeException('The designated intake policy has no published revision.');
            $submitted = app(SubmitAdmissionAttempt::class)->handle(
                $institution,
                $cycle,
                $window,
                new AdmissionIntakeData(
                    firstName: 'Online',
                    lastName: 'WalkInDemo',
                    birthdate: self::BIRTHDATE,
                    sex: 'female',
                    contactEmail: self::EMAIL,
                    applicantType: 'new',
                    programPreferenceCodes: ['BSIT'],
                    middleName: null,
                    suffix: null,
                    contactPhone: '09170000001',
                    lastSchoolEnrolled: 'NEXIAM Synthetic Demo School',
                    strand: 'STEM',
                    addressLine: '100 University Ave',
                    city: 'Tagudin',
                    province: 'Ilocos Sur',
                    zipCode: '2714',
                    termsAccepted: true,
                    publicPolicyId: (int) $policy->getKey(),
                    publicPolicyRevisionId: (int) $revision->getKey(),
                    publicPolicyRevisionDigest: $revision->contentDigest(),
                ),
                'online-consultation-demo-seeder',
            );
            $attempt = $submitted['attempt'];
        } else {
            $identity = $attempt->identity;
            if ($identity === null
                || $identity->email !== self::EMAIL
                || $identity->first_name !== 'Online'
                || $identity->last_name !== 'WalkInDemo'
                || $identity->birthdate?->format('Y-m-d') !== self::BIRTHDATE
                || (int) $attempt->institution_id !== (int) $institution->id
                || (int) $attempt->academic_cycle_id !== (int) $cycle->id) {
                throw new RuntimeException('The existing online demo identity no longer matches its owned synthetic fixture; no relinking was performed.');
            }
        }

        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            $attempt = app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
        }

        return $attempt->fresh(['identity.applicantAccount']);
    }

    private function pinAccessKey(AdmissionAttempt $attempt): void
    {
        $challenge = ApplicantAccessChallenge::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();
        if ($challenge === null) {
            ApplicantAccessChallenge::query()->create([
                'applicant_identity_id' => $attempt->applicant_identity_id,
                'admission_attempt_id' => $attempt->id,
                'purpose' => 'access_key',
                'token_hash' => ApplicantAccessKey::hash(self::ACCESS_KEY),
                'request_key' => 'online-consult-demo-key-'.$attempt->id,
                'expires_at' => now()->addYears(5),
            ]);

            return;
        }

        $challenge->update([
            'token_hash' => ApplicantAccessKey::hash(self::ACCESS_KEY),
            'expires_at' => now()->addYears(5),
        ]);
    }

    private function ensureScored(AcademicCycle $cycle, Room $room, AdmissionAttempt $attempt, User $guidance): void
    {
        $assignment = ExamAssignment::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', '!=', ExamAssignment::STATUS_CANCELLED)
            ->first();
        if ($assignment === null) {
            $session = ExamSession::query()->firstOrCreate(
                ['academic_cycle_id' => $cycle->id, 'code' => 'EX-ONLN-WALKIN-'.$attempt->id],
                [
                    'room_id' => $room->id,
                    'status' => ExamSession::STATUS_COMPLETED,
                    'starts_at' => now()->subDays(3),
                    'effective_end_at' => now()->subDays(3)->addHours(2),
                    'closed_at' => now()->subDays(3),
                ],
            );
            $assignment = ExamAssignment::query()->firstOrCreate(
                ['exam_session_id' => $session->id, 'admission_attempt_id' => $attempt->id],
                ['status' => ExamAssignment::STATUS_ASSIGNED, 'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED],
            );
        }

        $grading = GradingSession::query()->firstOrCreate(
            ['exam_session_id' => $assignment->exam_session_id],
            [
                'status' => GradingSession::STATUS_FINALIZED,
                'opened_at' => now()->subDays(2),
                'finalized_at' => now()->subDays(1),
            ],
        );
        $aptitudeAreas = AptitudeArea::query()->where('is_active', true)->get();
        if ($aptitudeAreas->isEmpty()) {
            throw new RuntimeException('Active aptitude areas are required to prepare the online consultation demo result.');
        }
        foreach ($aptitudeAreas as $area) {
            ScoreRecord::query()->updateOrCreate(
                [
                    'grading_session_id' => $grading->id,
                    'admission_attempt_id' => $attempt->id,
                    'aptitude_area_id' => $area->id,
                ],
                [
                    'score' => 88,
                    'converted_score' => 88,
                    'recorded_by_user_id' => $guidance->id,
                    'idempotency_key' => 'online-demo-score-'.$attempt->id.'-'.$area->id,
                    'source' => ScoreRecord::SOURCE_MANUAL,
                    'version' => 1,
                ],
            );
        }
    }

    private function preserveWalkInPath(AdmissionAttempt $attempt, User $guidance): void
    {
        $assignment = ConsultationAssignment::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ConsultationAssignment::ACTIVE_STATUSES)
            ->first();
        if ($assignment === null) {
            return;
        }
        if ($assignment->origin !== ConsultationAssignment::ORIGIN_CONTINUOUS) {
            throw new RuntimeException('The synthetic online demo applicant has a non-demo active consultation booking; no booking was changed.');
        }

        $session = ConsultationSession::query()->findOrFail($assignment->consultation_session_id);
        app(CancelConsultationAssignment::class)->handle(
            $session,
            $assignment,
            $guidance,
            'The dedicated online demo fixture must remain a direct walk-in until explicit online start.',
        );
    }
}
