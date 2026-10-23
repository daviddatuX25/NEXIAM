<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\IssueApplicantAccessChallenge;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\ApplicantAccessKey;
use App\Assessment\Actions\ActivateTestKitVersion;
use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\RecordScores;
use App\Assessment\Actions\SaveTestKitPreparation;
use App\Assessment\Actions\StartTestKitPreparation;
use App\Assessment\Support\TestInstrumentDraftWriter;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Examination\Actions\CreateDirectAssessmentCohort;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Communications\PublicPolicy;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Private software-only demonstration for the connected external-final scoring path.
 * The false synthetic flags are used only in the strictly guarded isolated DB so the
 * real activation action can be exercised; every visible source reference says this
 * is not an institutional instrument, source-rights attestation, or validity claim.
 */
final class ExternalFinalDemoSeeder extends Seeder
{
    private const PROFILE_CODE = 'EXT-FINAL-PR-DEMO-2026';

    private const KIT_CODE = 'EXT-FINAL-PR-DEMO-2026';

    private const EMPTY_EMAIL = 'external.final.empty@nexiam.test';

    private const EMPTY_KEY = 'FNL6PR26';

    private const COMPLETE_EMAIL = 'external.final.complete@nexiam.test';

    private const COMPLETE_KEY = 'FNL6PR27';

    private const EMPTY_SESSION_LABEL = 'External Final Demo · Awaiting Entry';

    private const COMPLETE_SESSION_LABEL = 'External Final Demo · Six Zero Finals';

    /** @var list<string> */
    private const CONSTRUCT_CODES = ['VERBAL', 'QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];

    public function run(): void
    {
        $this->assertSafeTarget();
        $this->ensureBaseline();

        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('code', 'SYN-CYCLE-2026')
            ->where('is_active', true)
            ->firstOrFail();
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

        (new PublicIntakeConsentDemoSeeder)->run();
        $profile = $this->ensureApprovedProfile($institution, $guidance);
        $kitVersion = $this->ensureActiveKitVersion($institution, $cycle, $guidance, $profile);
        $window = $this->ensureApplicationWindow($cycle);
        $emptyAttempt = $this->ensureAcceptedApplicant($institution, $cycle, $window, $registrar, 'empty');
        $completeAttempt = $this->ensureAcceptedApplicant($institution, $cycle, $window, $registrar, 'complete');
        $emptySitting = $this->ensureDirectSitting($cycle, $guidance, $emptyAttempt, $kitVersion, self::EMPTY_SESSION_LABEL);
        $completeSitting = $this->ensureDirectSitting($cycle, $guidance, $completeAttempt, $kitVersion, self::COMPLETE_SESSION_LABEL);
        $this->recordZeroFinals($guidance, $completeSitting, $completeAttempt);

        $this->command?->info('External Final demo ready (private software-only fixture; no institutional source or rights claim).');
        $this->command?->table(
            ['Fixture', 'Identity'],
            [
                ['Test Setup', self::PROFILE_CODE.' · '.$profile->display_name],
                ['Test Kit', self::KIT_CODE.' · version '.$kitVersion->version_number.' · Active in isolated software fixture'],
                ['Awaiting entry applicant', $emptyAttempt->reference_code.' · '.$emptyAttempt->identity->first_name.' '.$emptyAttempt->identity->last_name],
                ['Awaiting entry sitting', self::EMPTY_SESSION_LABEL.' · session '.$emptySitting['session']->id],
                ['Zero-final applicant', $completeAttempt->reference_code.' · '.$completeAttempt->identity->first_name.' '.$completeAttempt->identity->last_name],
                ['Zero-final sitting', self::COMPLETE_SESSION_LABEL.' · session '.$completeSitting['session']->id],
            ],
        );
        $this->command?->line('Guidance login: guidance.counselor@nexiam.test / password');
        $this->command?->line('Registrar login: registrar.clerk@nexiam.test / password');
        $this->command?->line('Applicant awaiting entry: '.self::EMPTY_KEY.' / birthdate 2001-01-01');
        $this->command?->line('Applicant with six zero finals: '.self::COMPLETE_KEY.' / birthdate 2001-01-02');
        $this->command?->line('URLs: /setup/exam-materials/test-kits/'.$kitVersion->test_kit_id.'/test-setup ; /scoring/sessions/'.$emptySitting['session']->id.' ; /applicant/login');
        $this->command?->warn('All applicants, scores, source references, and final-only definitions are synthetic software data, not institutional evidence or scoring validity claims.');
    }

    private function assertSafeTarget(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('ExternalFinalDemoSeeder is restricted to local/testing environments.');
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $isolatedPostgres = $connection === 'pgsql' && str_starts_with($database, 'nexiam_test_kit_acceptance_');
        $sqlitePath = $database === ':memory:' ? $database : (realpath($database) ?: $database);
        $isolatedSqlite = $connection === 'sqlite'
            && ($sqlitePath === ':memory:'
                || str_starts_with($sqlitePath, '/tmp/nexiam-ad15-')
                || str_starts_with($sqlitePath, '/tmp/nexiam-final-only-'));

        if (! $isolatedPostgres && ! $isolatedSqlite) {
            throw new RuntimeException('ExternalFinalDemoSeeder requires private nexiam_test_kit_acceptance_* PostgreSQL or named /tmp/nexiam-ad15-* or /tmp/nexiam-final-only-* SQLite targets.');
        }
    }

    private function ensureBaseline(): void
    {
        $institutionExists = Institution::query()->where('code', 'SYN-INST-001')->exists();
        $cycleExists = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->exists();
        $guidanceExists = User::query()->where('email', 'guidance.counselor@nexiam.test')->exists();
        $registrarExists = User::query()->where('email', 'registrar.clerk@nexiam.test')->exists();

        if (! $institutionExists || ! $cycleExists || ! $guidanceExists || ! $registrarExists) {
            app(DatabaseSeeder::class)->run(DatabaseSeeder::testSeeders());
        }
    }

    private function ensureApprovedProfile(Institution $institution, User $actor): TestInstrumentVersion
    {
        $payload = $this->profilePayload();
        $validator = app(TestInstrumentProfileValidator::class);
        $expectedDigest = $validator->digest($validator->normalize($payload));
        $instrument = TestInstrument::query()
            ->where('institution_id', $institution->id)
            ->where('code', self::PROFILE_CODE)
            ->first();

        if ($instrument !== null && (bool) $instrument->is_synthetic) {
            throw new RuntimeException('The reserved demo profile code has synthetic=true; no activation eligibility is fabricated.');
        }
        if ($instrument === null) {
            $instrument = new TestInstrument([
                'institution_id' => (int) $institution->id,
                'code' => self::PROFILE_CODE,
            ]);
            // Isolated-only activation representation: the synthetic source and permitted-use references remain explicit.
            $instrument->forceFill(['is_synthetic' => false])->save();
        }

        $instrument->load(['currentApprovedVersion.metrics', 'draftVersion.metrics']);
        $approved = $instrument->currentApprovedVersion;
        if ($approved !== null) {
            if (! hash_equals($expectedDigest, $validator->digest($validator->normalizeVersion($approved)))) {
                throw new RuntimeException('The reserved demo profile code already has a different approved definition; no profile was overwritten.');
            }

            return $approved;
        }

        $draft = $instrument->draftVersion;
        if ($draft !== null) {
            if (! hash_equals($expectedDigest, $validator->digest($validator->normalizeVersion($draft)))) {
                throw new RuntimeException('The reserved demo profile code already has a different draft; no profile was overwritten.');
            }
        } else {
            $draft = new TestInstrumentVersion([
                'test_instrument_id' => (int) $instrument->id,
                'version_number' => (int) $instrument->versions()->max('version_number') + 1,
                'status' => TestInstrumentVersion::STATUS_DRAFT,
                'lock_version' => 1,
                'created_by_user_id' => (int) $actor->id,
            ]);
            app(TestInstrumentDraftWriter::class)->save($draft, $validator->normalize($payload));
        }

        return app(ApproveTestInstrumentVersion::class)->handle(
            $actor,
            (int) $instrument->id,
            (int) $draft->id,
            (int) $draft->lock_version,
            'c5b1d7a2-0e39-43ab-9c16-5d8f02a62026',
            sourceReviewAttestation: true,
            isDemo: false,
        );
    }

    /** @return array{profile:array<string,mixed>,subtests:list<never>,metrics:list<array<string,mixed>>} */
    private function profilePayload(): array
    {
        $constructs = AptitudeArea::query()
            ->where('is_active', true)
            ->whereIn('code', self::CONSTRUCT_CODES)
            ->get(['id', 'code', 'name'])
            ->keyBy('code');
        if ($constructs->count() !== count(self::CONSTRUCT_CODES)) {
            throw new RuntimeException('The six required active aptitude constructs are unavailable; no substitute constructs were selected.');
        }

        $sources = ['manual', 'import'];
        $metrics = [];
        foreach (self::CONSTRUCT_CODES as $index => $code) {
            $construct = $constructs->get($code);
            if ($construct === null) {
                throw new RuntimeException("The required active construct {$code} is unavailable.");
            }
            $metrics[] = [
                'code' => 'FINAL_'.($index + 1),
                'name' => (string) $construct->name.' final percentile rank',
                'rule_kind' => TestInstrumentMetric::RULE_EXTERNAL_FINAL,
                'subtest_code' => null,
                'unit' => null,
                'min_raw_value' => null,
                'max_raw_value' => null,
                'precision_scale' => 2,
                'is_required' => true,
                'required_output_scales' => ['percentile_rank'],
                'procedure_reference' => null,
                'construct_aptitude_area_id' => (int) $construct->id,
                'input_codes' => [],
                'input_contract_json' => [
                    'allowed_sources' => $sources,
                    'required_resources' => [],
                    'source_requirements' => [
                        'manual' => ['unit' => 'percentile_rank', 'identity_requirements' => ['administration', 'metric']],
                        'import' => ['unit' => 'percentile_rank', 'identity_requirements' => ['administration', 'metric']],
                    ],
                ],
            ];
        }

        return [
            'profile' => [
                'code' => self::PROFILE_CODE,
                'display_name' => 'Software-only External Aptitude Final Scores',
                'publisher_name' => null,
                'form_code' => null,
                'edition' => null,
                'language' => null,
                'source_reference' => 'software-only://external-final-demo/synthetic-report-fixture',
                'permitted_use_reference' => 'software-only://external-final-demo/no-institutional-source-or-rights-claim',
                'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
                'administration_procedure_reference' => 'Synthetic software demo intake of externally supplied percentile ranks; not an institutional administration or validity claim.',
            ],
            'subtests' => [],
            'metrics' => $metrics,
        ];
    }

    private function ensureActiveKitVersion(
        Institution $institution,
        AcademicCycle $cycle,
        User $actor,
        TestInstrumentVersion $profile,
    ): TestKitVersion {
        $kit = TestKit::query()
            ->where('institution_id', $institution->id)
            ->where('code', self::KIT_CODE)
            ->first();

        if ($kit !== null) {
            if ((bool) $kit->is_synthetic) {
                throw new RuntimeException('The reserved Kit is synthetic and cannot be activated.');
            }
            if ($kit->name !== 'Software-only External Aptitude Final Demo') {
                throw new RuntimeException('The reserved Kit code belongs to a different record; no Kit was overwritten.');
            }
        } else {
            $kit = TestKit::query()->create([
                'public_id' => (string) Str::uuid(),
                'institution_id' => (int) $institution->id,
                'code' => self::KIT_CODE,
                'name' => 'Software-only External Aptitude Final Demo',
                // Isolated-only activation representation; this seeder refuses every non-isolated DB.
                'is_synthetic' => false,
                'created_by_user_id' => (int) $actor->id,
            ]);
        }

        $version = TestKitVersion::query()
            ->where('test_kit_id', $kit->id)
            ->orderByDesc('version_number')
            ->first();
        if ($version !== null && (int) $version->test_instrument_version_id !== (int) $profile->id) {
            throw new RuntimeException('The reserved demo Kit already points to another immutable profile; no version was replaced.');
        }
        if ($version === null) {
            $preparation = TestKitPreparation::query()
                ->where('test_kit_id', $kit->id)
                ->where('state', TestKitPreparation::STATE_PREPARING)
                ->first();
            if ($preparation !== null
                && (($preparation->test_instrument_version_id !== null && (int) $preparation->test_instrument_version_id !== (int) $profile->id)
                    || $preparation->norm_package_version_id !== null
                    || $preparation->answer_sheet_draft_id !== null
                    || $preparation->template_version_id !== null
                    || $preparation->booklet_version_id !== null
                    || $preparation->booklet_set_id !== null)) {
                throw new RuntimeException('The reserved demo Kit has a different open preparation; no selection was replaced.');
            }
            $preparation ??= app(StartTestKitPreparation::class)->handle($actor, (int) $kit->id, [
                'expected_family_version' => $version === null ? 0 : (int) $version->version_number,
                'idempotency_key' => 'aeb22e4e-7b0f-475a-9481-1dbbf5652026',
            ]);
            $preparation = app(SaveTestKitPreparation::class)->handle($actor, (int) $kit->id, [
                'preparation_id' => (int) $preparation->id,
                'expected_revision' => (int) $preparation->revision,
                'idempotency_key' => 'b0dc1c38-5134-4d52-a951-266126922026',
                'test_instrument_version_id' => (int) $profile->id,
                'norm_package_version_id' => null,
                'answer_sheet_draft_id' => null,
                'template_version_id' => null,
                'booklet_version_id' => null,
                'booklet_set_id' => null,
            ]);
            $version = app(AssembleTestKitPreparation::class)->handle($actor, (int) $kit->id, [
                'preparation_id' => (int) $preparation->id,
                'expected_revision' => (int) $preparation->revision,
                'idempotency_key' => 'c944f387-ec8f-4df9-8e14-4de48b362026',
            ]);
        }

        if (! in_array($version->status, [TestKitVersion::STATUS_ACTIVE, TestKitVersion::STATUS_CANDIDATE], true)) {
            throw new RuntimeException('The reserved software-only Kit version is not activatable from its current state.');
        }
        if ($version->status === TestKitVersion::STATUS_CANDIDATE) {
            $version = app(ActivateTestKitVersion::class)->handle(
                $actor,
                (int) $kit->id,
                (int) $version->id,
                [
                    'expected_revision' => (int) $version->revision,
                    'idempotency_key' => 'd6ae282b-405e-4a49-80e0-333c8f442026',
                    'academic_cycle_id' => (int) $cycle->id,
                ],
            );
        }

        return $version->fresh(['testInstrumentVersion.metrics', 'testKit']);
    }

    private function ensureApplicationWindow(AcademicCycle $cycle): ApplicationWindow
    {
        $window = ApplicationWindow::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('code', 'SYN-EXT-FINAL-2026')
            ->first();
        $attributes = [
            'name' => 'Software-only External Final Demo Intake',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addYear(),
            'status' => 'published',
        ];
        if ($window === null) {
            return ApplicationWindow::query()->create([
                'academic_cycle_id' => (int) $cycle->id,
                'code' => 'SYN-EXT-FINAL-2026',
                ...$attributes,
            ]);
        }
        if ($window->name !== 'Software-only External Final Demo Intake') {
            throw new RuntimeException('The reserved demo application-window code belongs to a different record.');
        }

        $window->fill($attributes)->save();

        return $window->fresh();
    }

    private function ensureAcceptedApplicant(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        User $registrar,
        string $case,
    ): AdmissionAttempt {
        $isComplete = $case === 'complete';
        $email = $isComplete ? self::COMPLETE_EMAIL : self::EMPTY_EMAIL;
        $firstName = 'Synthetic';
        $lastName = $isComplete ? 'Final Complete' : 'Final Awaiting';
        $birthdate = $isComplete ? '2001-01-02' : '2001-01-01';
        $attempt = AdmissionAttempt::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('contact_email', $email)
            ->first();

        if ($attempt === null) {
            $policy = PublicPolicy::designatedIntake((int) $institution->id)
                ?? throw new RuntimeException('A published designated synthetic intake policy is required for the demo applicant.');
            $revision = $policy->publishedRevision
                ?? throw new RuntimeException('The demo applicant requires an immutable published consent revision.');
            $submitted = app(SubmitAdmissionAttempt::class)->handle(
                $institution,
                $cycle,
                $window,
                new AdmissionIntakeData(
                    firstName: $firstName,
                    lastName: $lastName,
                    birthdate: $birthdate,
                    sex: 'female',
                    contactEmail: $email,
                    applicantType: 'new',
                    programPreferenceCodes: ['BSIT'],
                    grades: ['GWA' => '1.75', 'ENG' => '88.00', 'FIL' => '90.00', 'MATH' => '85.00', 'SCI' => '87.00'],
                    middleName: null,
                    suffix: null,
                    contactPhone: $isComplete ? '09170002002' : '09170002001',
                    lastSchoolEnrolled: 'Synthetic Software Demo School',
                    strand: 'STEM',
                    addressLine: 'Software Demo Street',
                    city: 'Tagudin',
                    province: 'Ilocos Sur',
                    zipCode: '2714',
                    termsAccepted: true,
                    publicPolicyId: (int) $policy->id,
                    publicPolicyRevisionId: (int) $revision->id,
                    publicPolicyRevisionDigest: $revision->contentDigest(),
                ),
                'external-final-demo-seeder',
            );
            $attempt = $submitted['attempt'];
        }

        $attempt->loadMissing(['identity', 'academicCycle']);
        $consent = is_array($attempt->payload) ? ($attempt->payload['consent'] ?? null) : null;
        if ($attempt->identity?->email !== $email
            || $attempt->identity?->birthdate?->format('Y-m-d') !== $birthdate
            || (int) $attempt->institution_id !== (int) $institution->id
            || (int) $attempt->academic_cycle_id !== (int) $cycle->id
            || ! is_array($consent)
            || (int) ($consent['policy_id'] ?? 0) !== (int) ($attempt->public_policy_id ?? 0)
            || ! is_numeric($consent['revision_id'] ?? null)
            || ! is_string($consent['digest'] ?? null)
            || $attempt->public_policy_id === null) {
            throw new RuntimeException('The existing demo applicant does not match this seeder’s consented synthetic identity; no record was repaired.');
        }
        if ($attempt->status === AdmissionAttempt::STATUS_PENDING) {
            $attempt = app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
        } elseif ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            throw new RuntimeException('The synthetic demo applicant is not accepted and cannot be silently relabeled.');
        }

        $this->ensureStableApplicantKey($attempt, $isComplete ? self::COMPLETE_KEY : self::EMPTY_KEY);

        return $attempt->fresh(['identity']);
    }

    private function ensureStableApplicantKey(AdmissionAttempt $attempt, string $accessKey): void
    {
        $identity = $attempt->identity()->firstOrFail();
        $requestKey = 'external-final-demo-access-'.$attempt->id;
        $hash = ApplicantAccessKey::hash($accessKey);
        $stableChallenge = ApplicantAccessChallenge::query()->where('request_key', $requestKey)->first();

        if ($stableChallenge !== null) {
            if ((int) $stableChallenge->applicant_identity_id !== (int) $identity->id
                || (int) $stableChallenge->admission_attempt_id !== (int) $attempt->id
                || $stableChallenge->purpose !== 'access_key'
                || ! hash_equals((string) $stableChallenge->token_hash, $hash)
                || $stableChallenge->consumed_at !== null) {
                throw new RuntimeException('The stable synthetic applicant key is inconsistent or revoked; it will not be replaced.');
            }

            return;
        }

        ApplicantAccessChallenge::query()
            ->where('applicant_identity_id', $identity->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
        app(IssueApplicantAccessChallenge::class)->handle(
            $identity,
            $attempt,
            $hash,
            'access_key',
            $requestKey,
            now()->addYears(10),
        );
    }

    /** @return array{session:ExamSession,grading_session:GradingSession,administration:AssessmentAdministration,kit_version:TestKitVersion} */
    private function ensureDirectSitting(
        AcademicCycle $cycle,
        User $actor,
        AdmissionAttempt $attempt,
        TestKitVersion $kitVersion,
        string $label,
    ): array {
        $session = ExamSession::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('label', $label)
            ->first();
        $pinnedKitVersion = $kitVersion;
        if ($session === null) {
            $created = app(CreateDirectAssessmentCohort::class)->handle(
                cycle: $cycle,
                attemptIds: [(int) $attempt->id],
                actor: $actor,
                label: $label,
                startsAt: now()->subHours(2),
                effectiveEndAt: now()->subHour(),
                kitVersion: $kitVersion,
            );
            $session = $created['session'];
        } else {
            $pinnedKitVersion = TestKitVersion::query()->findOrFail($session->test_kit_version_id);
            $pinnedProfile = TestInstrumentVersion::query()->findOrFail($pinnedKitVersion->test_instrument_version_id);
            $currentProfile = TestInstrumentVersion::query()->findOrFail($kitVersion->test_instrument_version_id);
            if ((int) $pinnedKitVersion->test_kit_id !== (int) $kitVersion->test_kit_id
                || (int) $pinnedProfile->test_instrument_id !== (int) $currentProfile->test_instrument_id
                || ! $pinnedProfile->isFinalOnly()
                || (int) $session->test_instrument_version_id !== (int) $pinnedProfile->id) {
                throw new RuntimeException("The reserved session label {$label} has an inconsistent historical Kit/profile pin; no binding was replaced.");
            }
        }

        $gradingSession = GradingSession::query()
            ->where('exam_session_id', $session->id)
            ->firstOrFail();
        $assignment = ExamAssignment::query()
            ->where('exam_session_id', $session->id)
            ->where('admission_attempt_id', $attempt->id)
            ->active()
            ->firstOrFail();
        $administration = AssessmentAdministration::query()->active()
            ->where('exam_assignment_id', $assignment->id)
            ->firstOrFail();
        if ((int) $administration->test_kit_version_id !== (int) $pinnedKitVersion->id
            || ($administration->procedure_snapshot_json['operation'] ?? null) !== 'external_final') {
            throw new RuntimeException('The demo sitting is not bound to its exact pinned final-only Kit operation.');
        }

        return [
            'session' => $session,
            'grading_session' => $gradingSession,
            'administration' => $administration,
            'kit_version' => $pinnedKitVersion,
        ];
    }

    /** @param array{session:ExamSession,grading_session:GradingSession,administration:AssessmentAdministration,kit_version:TestKitVersion} $sitting */
    private function recordZeroFinals(User $actor, array $sitting, AdmissionAttempt $attempt): void
    {
        $administration = $sitting['administration'];
        if (AssessmentConversionRevision::query()
            ->where('administration_id', $administration->id)
            ->exists()) {
            return;
        }

        $kitVersion = $sitting['kit_version'];
        $profile = TestInstrumentVersion::query()
            ->with('metrics')
            ->findOrFail($administration->test_instrument_version_id);
        if ((int) $profile->id !== (int) $kitVersion->test_instrument_version_id
            || ! $profile->isFinalOnly()) {
            throw new RuntimeException('The zero-final demo must write only to the exact pinned final-only profile.');
        }

        $entries = [];
        foreach ($profile->metrics as $metric) {
            $entries[] = [
                'metric_code' => (string) $metric->code,
                'typed_outputs' => ['percentile_rank' => '0.00'],
                'external_provenance' => 'Software-only synthetic provider-report row for '.$metric->code.'; not an institutional assessment or source claim.',
            ];
        }

        if (count($entries) !== 6) {
            throw new RuntimeException('The exact external-final demo definition must contain all six required metrics.');
        }
        app(RecordScores::class)->handle(
            gradingSession: $sitting['grading_session'],
            attempt: $attempt,
            scores: $entries,
            actor: $actor,
            idempotencyKey: 'e5c7ef01-b5f8-4f9c-8496-2bafc5f22026',
            source: ScoreRecord::SOURCE_MANUAL,
            scoringMode: 'manual_converted',
            testKitVersionId: (int) $kitVersion->id,
        );
    }
}
