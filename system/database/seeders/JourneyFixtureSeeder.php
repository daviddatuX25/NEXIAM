<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\ApplicantAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class JourneyFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();

        // Legacy journey fixture (pending) — keep for older tests/docs.
        $this->ensureApplicant(
            $institution,
            $cycle,
            $window,
            email: 'journey.applicant@example.test',
            firstName: 'Journey',
            lastName: 'Applicant',
            birthdate: '2003-09-25',
            sex: 'female',
            accessKey: null,
            accept: false,
            actor: 'journey-fixture-seeder',
        );

        $accepted = config('seed.applicants.accepted');
        $pending = config('seed.applicants.pending');

        $this->ensureApplicant(
            $institution,
            $cycle,
            $window,
            email: (string) $accepted['email'],
            firstName: (string) $accepted['first_name'],
            lastName: (string) $accepted['last_name'],
            birthdate: (string) $accepted['birthdate'],
            sex: (string) $accepted['sex'],
            accessKey: (string) $accepted['access_key'],
            accept: true,
            actor: 'demo-applicant-seeder',
        );

        $this->ensureApplicant(
            $institution,
            $cycle,
            $window,
            email: (string) $pending['email'],
            firstName: (string) $pending['first_name'],
            lastName: (string) $pending['last_name'],
            birthdate: (string) $pending['birthdate'],
            sex: (string) $pending['sex'],
            accessKey: (string) $pending['access_key'],
            accept: false,
            actor: 'demo-applicant-seeder',
        );

        $this->command?->info('Demo applicant logins (local/synthetic):');
        $this->command?->line(sprintf(
            '  Accepted → key %s · birthdate %s · %s',
            $accepted['access_key'],
            $accepted['birthdate'],
            $accepted['email'],
        ));
        $this->command?->line(sprintf(
            '  Pending  → key %s · birthdate %s · %s',
            $pending['access_key'],
            $pending['birthdate'],
            $pending['email'],
        ));
    }

    private function ensureApplicant(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        string $email,
        string $firstName,
        string $lastName,
        string $birthdate,
        string $sex,
        ?string $accessKey,
        bool $accept,
        string $actor,
    ): void {
        $identity = ApplicantIdentity::query()
            ->where('institution_id', $institution->id)
            ->where('email', $email)
            ->first();

        if ($identity === null) {
            $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
                ?? throw new RuntimeException('A designated published PublicPolicy revision is required for seeded intake.');
            $revision = $policy->publishedRevision
                ?? throw new RuntimeException('The designated intake policy has no published revision.');
            $result = app(SubmitAdmissionAttempt::class)->handle(
                $institution,
                $cycle,
                $window,
                new AdmissionIntakeData(
                    firstName: $firstName,
                    lastName: $lastName,
                    birthdate: $birthdate,
                    sex: $sex,
                    contactEmail: $email,
                    applicantType: 'new',
                    programPreferenceCodes: ['BSIT', 'BA-Psych'],
                    middleName: null,
                    suffix: null,
                    contactPhone: '09171234567',
                    lastSchoolEnrolled: 'Tagudin National High School',
                    strand: 'STEM',
                    addressLine: '123 Demo St',
                    city: 'Tagudin',
                    province: 'Ilocos Sur',
                    zipCode: '2714',
                    termsAccepted: true,
                    publicPolicyId: (int) $policy->getKey(),
                    publicPolicyRevisionId: (int) $revision->getKey(),
                    publicPolicyRevisionDigest: $revision->contentDigest(),
                ),
                $actor,
            );

            $attempt = $result['attempt'];
            $mintedKey = $result['access_token'];
        } else {
            $identity->update([
                'birthdate' => $birthdate,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'sex' => $sex,
            ]);

            $attempt = AdmissionAttempt::query()
                ->where('applicant_identity_id', $identity->id)
                ->where('academic_cycle_id', $cycle->id)
                ->orderByDesc('id')
                ->first();

            if ($attempt === null) {
                throw new RuntimeException("Identity {$email} exists without an attempt for the active cycle.");
            }

            $mintedKey = null;
        }

        if ($accessKey !== null) {
            if (! ApplicantAccessKey::isValidShape($accessKey)) {
                throw new RuntimeException("Invalid seed access key shape: {$accessKey}");
            }

            $this->pinAccessKey($attempt, $accessKey);
        } elseif ($mintedKey !== null) {
            // Journey fixture keeps the minted key only in mail/outbox; no pin.
        }

        if ($accept && $attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
            app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
        }
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
                'request_key' => 'seed-pin-'.$attempt->id,
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
}
