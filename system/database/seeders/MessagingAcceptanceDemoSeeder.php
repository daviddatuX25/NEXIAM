<?php

namespace Database\Seeders;

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\OpenApplicantSupportConversation;
use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\NormalizedIdentityKey;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantAccount;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramPoolOption;
use App\Models\Admission\ProgramSelection;
use App\Models\Admission\RecordThread;
use App\Models\Assessment\GradingSession;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Communications\PublicPolicy;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\StaffProfile;
use App\Models\User;
use App\Release\Actions\ReleaseResult;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Isolated synthetic applicant messaging acceptance fixtures.
 * Admission/result/selection rows are explicitly synthetic prerequisites, not
 * workflow-proof; every case root and message uses its ordinary domain Action.
 */
class MessagingAcceptanceDemoSeeder extends Seeder
{
    private const DATABASE_PREFIX = 'nexiam_messaging_';

    private const PROGRAM_CODE = 'MSG-DEMO';

    private const REVOKED_REFERENCE = 'SYN-MSG-DEMO-REVOKED-001';

    private const REVOKED_EMAIL = 'applicant.messaging-revoked@nexiam.test';

    private const REVOKED_BIRTHDATE = '2004-05-15';

    private const REVOKED_ACCESS_KEY = 'MSGR-8B2K';

    public function run(): void
    {
        $this->assertIsolatedDatabase();
        $this->ensureFoundation();

        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('code', 'SYN-CYCLE-2026')
            ->where('is_active', true)
            ->firstOrFail();
        $window = ApplicationWindow::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('code', 'SYN-WINDOW-2026-A')
            ->where('status', 'published')
            ->firstOrFail();

        $department = $this->department($institution, 'MSG-DEMO-DEPT', 'Synthetic messaging demo department');
        $program = $this->program($institution, $department, self::PROGRAM_CODE, 'Synthetic messaging demo program', admissionOffered: true);
        [$attempt, $applicant] = $this->portalApplicant($institution, $cycle, $window);
        // The office owner validates source audit, identity, challenge, and registration marker.
        $this->call(ApplicantOfficeInquiryDemoSeeder::class);

        DB::transaction(function () use ($institution, $cycle, $attempt, $applicant, $department, $program): void {
            $unrelatedDepartment = $this->department($institution, 'MSG-OTHER-DEPT', 'Synthetic messaging other department');
            $unrelatedProgram = $this->program($institution, $unrelatedDepartment, 'MSG-OTHER', 'Synthetic messaging unrelated program');

            $dean = $this->staff($institution, $cycle, 'dean', 'program', 'program_head', []);
            $ph = $this->staff($institution, $cycle, 'ph', 'program', 'program_head', [self::PROGRAM_CODE]);
            $guidance = $this->staff($institution, $cycle, 'guidance', 'guidance', 'guidance_counselor', []);
            $registrar = $this->staff($institution, $cycle, 'registrar', 'registrar', 'registrar_clerk', []);
            $unrelated = $this->staff($institution, $cycle, 'unrelated', 'program', 'program_head', ['MSG-OTHER']);

            $this->ownDepartmentForDean($institution, $department, $dean);
            $this->ownDepartmentForDean($institution, $unrelatedDepartment, $unrelated);
            foreach ([$ph, $dean, $guidance, $unrelated] as $staffActor) {
                $this->ensureThreadPermission($staffActor);
            }

            $attempt->refresh();
            if ($attempt->status === AdmissionAttempt::STATUS_PENDING) {
                $attempt->forceFill([
                    'status' => AdmissionAttempt::STATUS_ACCEPTED,
                    'accepted_at' => now(),
                    'version' => (int) $attempt->version + 1,
                    'payload' => [...($attempt->payload ?? []), 'synthetic_fixture' => 'messaging-acceptance', 'workflow_proof' => false],
                ])->save();
            } elseif ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED
                || ($attempt->payload['synthetic_fixture'] ?? null) !== 'messaging-acceptance') {
                throw new RuntimeException('Messaging applicant attempt changed outside this synthetic fixture; no workflow state was repaired.');
            }

            $attempt->refresh();
            $this->releasedResultPrerequisites($attempt, $cycle, $registrar);
            $this->selection($attempt, $program, $guidance, $applicant);

            $support = app(OpenApplicantSupportConversation::class)->handle(
                $attempt,
                (int) $program->id,
                'Synthetic case support conversation',
                'Synthetic fixture message: I would like to ask about the program information shown in my applicant portal.',
                $ph,
                $this->uuid(1),
            );

            $sender = $applicant;
            for ($sequence = 2; $sequence <= 102; $sequence++) {
                $sender = $sender->is($applicant) ? $ph : $applicant;
                app(SendAdmissionSupportMessage::class)->handle(
                    $support,
                    $sender,
                    sprintf('Synthetic fixture history entry %03d: this message exists to exercise ordinary ordered conversation history.', $sequence),
                    $this->uuid($sequence),
                );
            }

            for ($index = 1; $index <= 21; $index++) {
                $internal = app(SendAdmissionSupportMessage::class)->openInternalThread(
                    $attempt,
                    (int) $program->id,
                    sprintf('Synthetic staff coordination topic %02d', $index),
                    sprintf('Synthetic fixture staff message %02d: coordinate the next authorized case review step.', $index),
                    $index % 2 === 0 ? $dean : $guidance,
                    $this->uuid(1000 + $index),
                );
                if ($index === 21) {
                    app(SendAdmissionSupportMessage::class)->handle(
                        $internal,
                        $ph,
                        'Synthetic fixture reply: Guidance and the Program Head can both participate in this internal case discussion.',
                        $this->uuid(2001),
                    );
                }
            }

            $closed = RecordThread::query()
                ->where('admission_attempt_id', $attempt->id)
                ->where('thread_type', RecordThread::TYPE_INTERNAL_PH_GUIDANCE)
                ->orderBy('id')
                ->firstOrFail();
            if ($closed->status === RecordThread::STATUS_OPEN) {
                $closed->forceFill(['status' => RecordThread::STATUS_CLOSED])->save();
            }

            $revokedAttempt = $this->revokedAttempt($institution, $cycle, $program);
            $revokedThread = RecordThread::query()
                ->where('admission_attempt_id', $revokedAttempt->id)
                ->where('thread_type', RecordThread::TYPE_INTERNAL_PH_GUIDANCE)
                ->where('program_id', $program->id)
                ->first();
            $hasEnrollmentStamp = $revokedAttempt->enrollmentStamps()
                ->whereIn('stamp_type', [EnrollmentStamp::STAMP_ENROLLED, EnrollmentStamp::STAMP_ENROLLING])
                ->exists();
            $historicalStamp = $revokedAttempt->enrollmentStamps()
                ->where('idempotency_key', 'messaging-demo-legacy-enrolled-'.$revokedAttempt->reference_code)
                ->first();
            if ($hasEnrollmentStamp && ($revokedThread === null
                || $revokedAttempt->enrollmentStamps()
                    ->whereIn('stamp_type', [EnrollmentStamp::STAMP_ENROLLED, EnrollmentStamp::STAMP_ENROLLING])
                    ->count() !== 1
                || $historicalStamp === null
                || $historicalStamp->stamp_type !== EnrollmentStamp::STAMP_ENROLLED
                || (int) $historicalStamp->provisional_program_id !== (int) $program->id
                || (int) $historicalStamp->final_program_id !== (int) $program->id
                || (int) $historicalStamp->stamped_by_user_id !== (int) $registrar->id
                || $historicalStamp->reason !== 'Synthetic legacy history fixture for the messaging actual-stamp cutoff; no enrollment workflow or quota commitment is claimed.')) {
                throw new RuntimeException('Revoked messaging history is missing or changed; no post-stamp root was created.');
            }
            if (! $hasEnrollmentStamp) {
                $this->releasedResultPrerequisites($revokedAttempt, $cycle, $registrar);
                $this->revokedCaseRelationship($revokedAttempt, $program, $guidance);
                try {
                    $revokedThread ??= app(SendAdmissionSupportMessage::class)->openInternalThread(
                        $revokedAttempt,
                        (int) $program->id,
                        'Synthetic revoked case history',
                        'Synthetic fixture message: retained internal history before the synthetic enrollment-stamp cutoff.',
                        $ph,
                        $this->uuid(3001),
                    );
                } catch (\Throwable $exception) {
                    throw new RuntimeException(sprintf(
                        'Synthetic messaging fixture internal-root opener failed for revoked-history (attempt %d, program %d, actor %d): %s',
                        $revokedAttempt->id,
                        $program->id,
                        $ph->id,
                        $exception->getMessage(),
                    ), previous: $exception);
                }
                $this->legacyHistoricalEnrollmentStamp($revokedAttempt, $program, $registrar);
            }
        });

        $this->command?->info('Synthetic messaging fixture created or retained on the explicitly isolated database. Admission facts are synthetic prerequisites; case conversations use ordinary Actions.');
    }

    private function assertIsolatedDatabase(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config('database.connections.'.$connection.'.database');
        $optIn = getenv('NEXIAM_MESSAGING_DEMO_DATABASE');
        $isolationMarker = getenv('NEXIAM_MESSAGING_DEMO_ISOLATED');
        $driver = DB::connection()->getDriverName();
        $postgresIsolated = $driver === 'pgsql'
            && preg_match('/^nexiam_messaging_[a-z0-9_]+$/', $database) === 1;
        $sqliteIsolated = $driver === 'sqlite'
            && str_starts_with($database, '/tmp/nexiam-messaging-')
            && str_ends_with($database, '.sqlite')
            && realpath($database) === $database
            && ! is_link($database);
        $testingMemory = app()->environment('testing') && $driver === 'sqlite' && $database === ':memory:';

        if (! app()->environment('local', 'testing') || ! app()->runningInConsole()
            || $isolationMarker !== '1' || $optIn !== $database
            || (! $postgresIsolated && ! $sqliteIsolated && ! $testingMemory)) {
            throw new RuntimeException('Messaging demo refused: use local/testing console execution, a dedicated nexiam_messaging_* PostgreSQL database or canonical /tmp/nexiam-messaging-*.sqlite file, NEXIAM_MESSAGING_DEMO_ISOLATED=1, and NEXIAM_MESSAGING_DEMO_DATABASE equal to the configured database name/path.');
        }
    }

    private function ensureFoundation(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();

        if ($institution === null) {
            if (Institution::query()->exists() || User::query()->exists()) {
                throw new RuntimeException('Messaging demo requires the synthetic foundation on this isolated database; it will not seed a nonempty unrelated database.');
            }

            app(DatabaseSeeder::class)->run(DatabaseSeeder::testSeeders());
            $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        }

        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('code', 'SYN-CYCLE-2026')
            ->where('is_active', true)
            ->first();
        $window = $cycle === null ? null : ApplicationWindow::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('code', 'SYN-WINDOW-2026-A')
            ->where('status', 'published')
            ->first();

        if ($cycle === null || $window === null || ! $this->foundationStaffSeedIsOwned($institution, $cycle)) {
            throw new RuntimeException('Messaging demo found no exact source-owned synthetic foundation; it will not adopt or repair unrelated records.');
        }

        // The previous run may have completed the foundation seeders and then
        // failed while publishing the designated synthetic intake policy.
        app(PublicIntakeConsentDemoSeeder::class)->run();
    }

    private function foundationStaffSeedIsOwned(Institution $institution, AcademicCycle $cycle): bool
    {
        $staff = [
            ['registrar.clerk@nexiam.test', 'Synthetic Registrar Clerk', 'registrar_clerk', 'registrar', ['BSIT']],
            ['registrar.admin@nexiam.test', 'Synthetic Registrar Admin', 'registrar_admin', 'registrar', ['BSIT']],
            ['guidance.clerk@nexiam.test', 'Synthetic Guidance Clerk', 'guidance_clerk', 'guidance', ['BSIT']],
            ['guidance.support@nexiam.test', 'Synthetic Guidance Support', 'guidance_support_staff', 'guidance', ['BSIT']],
            ['guidance.counselor@nexiam.test', 'Synthetic Guidance Counselor', 'guidance_counselor', 'guidance', ['BSIT']],
            ['program.head.bsit@nexiam.test', 'Synthetic Program Head BSIT', 'program_head_bsit', 'program', ['BSIT']],
            ['dean.ccit@nexiam.test', 'Dean of Computing & IT (CCIT)', 'program_head', 'program', ['BSIT']],
            ['dean.cas@nexiam.test', 'Dean of Arts & Sciences (CAS)', 'program_head', 'program', ['BA-EL', 'BA-Psych']],
            ['dean.cte@nexiam.test', 'Dean of Teacher Education (CTE)', 'program_head', 'program', ['BSEd-Eng', 'BSEd-Math']],
            ['dean.cbme@nexiam.test', 'Dean of Business Management & Entrepreneurship (CBME)', 'program_head', 'program', ['BSBA', 'BSEntrep']],
            ['admin.lead@nexiam.test', 'Synthetic Institutional Administrator', 'institutional_admin', 'admin', []],
            [
                (string) config('seed.hr.email'),
                (string) config('seed.hr.name'),
                'hr_administrator',
                'admin',
                [],
            ],
        ];

        foreach ($staff as [$email, $name, $role, $office, $programCodes]) {
            $user = User::query()->where('email', $email)->first();
            $profile = $user?->staffProfile;
            $authentication = $user === null ? null : StaffAuthenticationState::query()->where('user_id', $user->id)->first();

            if ($user === null || $user->name !== $name || ! $user->hasRole($role)
                || $profile === null || (int) $profile->institution_id !== (int) $institution->id
                || $profile->office !== $office || $profile->active_cycle_code !== $cycle->code
                || $profile->program_codes !== $programCodes || ! $profile->isActive()
                || $profile->deactivated_at !== null || $authentication === null
                || $authentication->activation_completed_at === null || $authentication->password_set_at === null) {
                return false;
            }
        }

        return true;
    }

    /** @return array{AdmissionAttempt, User} */
    private function portalApplicant(Institution $institution, AcademicCycle $cycle, ApplicationWindow $window): array
    {
        $portal = config('seed.messaging.applicant');
        if (! is_array($portal) || ! isset($portal['email'], $portal['first_name'], $portal['last_name'], $portal['birthdate'], $portal['access_key'])) {
            throw new RuntimeException('Messaging demo applicant configuration is incomplete.');
        }

        $identity = ApplicantIdentity::query()
            ->where('institution_id', $institution->id)
            ->where('email', $portal['email'])
            ->first();
        if ($identity === null) {
            if (User::query()->where('email', $portal['email'])->exists()) {
                throw new RuntimeException('Messaging portal applicant email collision; no account or credentials were adopted.');
            }
            $policy = PublicPolicy::designatedIntake((int) $institution->id)
                ?? throw new RuntimeException('The synthetic foundation has no designated published intake policy.');
            $revision = $policy->publishedRevision
                ?? throw new RuntimeException('The designated intake policy has no published revision.');
            $submission = app(SubmitAdmissionAttempt::class)->handle(
                $institution,
                $cycle,
                $window,
                new AdmissionIntakeData(
                    firstName: $portal['first_name'],
                    lastName: $portal['last_name'],
                    birthdate: $portal['birthdate'],
                    sex: 'female',
                    contactEmail: $portal['email'],
                    applicantType: 'new',
                    programPreferenceCodes: [self::PROGRAM_CODE],
                    middleName: null,
                    suffix: null,
                    contactPhone: '09171234567',
                    lastSchoolEnrolled: 'Synthetic Messaging Demo School',
                    strand: 'STEM',
                    addressLine: '1 Synthetic Demo Street',
                    city: 'Tagudin',
                    province: 'Ilocos Sur',
                    zipCode: '2714',
                    termsAccepted: true,
                    publicPolicyId: (int) $policy->id,
                    publicPolicyRevisionId: (int) $revision->id,
                    publicPolicyRevisionDigest: $revision->contentDigest(),
                ),
                'applicant-portal-seeder',
            );
            $attempt = $submission['attempt'];
            $attempt->forceFill([
                'payload' => [...($attempt->payload ?? []), 'synthetic_fixture' => 'messaging-acceptance', 'workflow_proof' => false],
            ])->save();
            $challenge = ApplicantAccessChallenge::query()
                ->where('admission_attempt_id', $attempt->id)
                ->where('purpose', 'access_key')
                ->whereNull('consumed_at')
                ->latest('id')
                ->firstOrFail();
            $challenge->forceFill([
                'token_hash' => ApplicantAccessKey::hash($portal['access_key']),
                'expires_at' => now()->addYears(5),
            ])->save();
            $identity = $attempt->identity()->firstOrFail();
        } else {
            if ($identity->first_name !== $portal['first_name'] || $identity->last_name !== $portal['last_name']
                || $identity->birthdate?->format('Y-m-d') !== $portal['birthdate']) {
                throw new RuntimeException('Messaging portal applicant identity changed; no existing credentials or data were reset.');
            }
            $attempt = AdmissionAttempt::query()
                ->where('applicant_identity_id', $identity->id)
                ->where('academic_cycle_id', $cycle->id)
                ->firstOrFail();
            if (($attempt->payload['synthetic_fixture'] ?? null) !== 'messaging-acceptance'
                || ! AuditEvent::query()
                    ->where('event_type', 'admission.attempt_submitted')
                    ->where('actor', 'applicant-portal-seeder')
                    ->where('payload->attempt_id', $attempt->id)
                    ->where('payload->identity_id', $identity->id)
                    ->where('payload->reference_code', $attempt->reference_code)
                    ->exists()) {
                throw new RuntimeException('Messaging portal attempt is not the exact owned synthetic fixture; it was not adopted.');
            }
        }

        $challenge = ApplicantAccessChallenge::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('purpose', 'access_key')
            ->whereNull('consumed_at')
            ->first();
        if ($challenge === null || $challenge->token_hash !== ApplicantAccessKey::hash($portal['access_key']) || $challenge->expires_at->isPast()) {
            throw new RuntimeException('Messaging portal access challenge changed or expired; it was not reset.');
        }
        $account = ApplicantAccount::query()->where('applicant_identity_id', $identity->id)->first();
        $user = User::query()->where('email', $portal['email'])->first();
        if ($user !== null && ($account === null || (int) $account->user_id !== (int) $user->id)) {
            throw new RuntimeException('Messaging applicant email belongs to an unrelated account; no credentials or identity links were changed.');
        }
        if ($account !== null) {
            $applicant = $account->user;
            if ($applicant === null || $applicant->email !== $portal['email']) {
                throw new RuntimeException('Messaging applicant account link changed; no credentials or identity links were repaired.');
            }
        } else {
            $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($portal['access_key'], $portal['birthdate']);
        }

        return [$attempt, $applicant];
    }

    private function department(Institution $institution, string $code, string $name): Department
    {
        $department = Department::query()->firstOrCreate(
            ['institution_id' => $institution->id, 'code' => $code],
            ['name' => $name, 'is_active' => true],
        );
        if ($department->name !== $name || ! $department->is_active) {
            throw new RuntimeException('Messaging fixture department ownership changed; no repair was performed.');
        }

        return $department;
    }

    private function program(Institution $institution, Department $department, string $code, string $name, bool $admissionOffered = false): Program
    {
        $program = Program::query()->firstOrCreate(
            ['institution_id' => $institution->id, 'code' => $code],
            [
                'name' => $name,
                'department_id' => $department->id,
                'is_active' => true,
                'is_admission_offered' => $admissionOffered,
            ],
        );
        if ($program->name !== $name || (int) $program->department_id !== (int) $department->id
            || ! $program->is_active || (bool) $program->is_admission_offered !== $admissionOffered) {
            throw new RuntimeException('Messaging fixture program ownership changed; no repair was performed.');
        }

        return $program;
    }

    private function ownDepartmentForDean(Institution $institution, Department $department, User $dean): void
    {
        $ownedFixtureDepartments = [
            'MSG-DEMO-DEPT' => 'Synthetic messaging demo department',
            'MSG-OTHER-DEPT' => 'Synthetic messaging other department',
        ];
        if ($department->dean_user_id === null
            && ($ownedFixtureDepartments[$department->code] ?? null) === $department->name
            && (int) $department->institution_id === (int) $institution->id
            && $department->is_active) {
            $department->forceFill(['dean_user_id' => $dean->id])->save();
        }
        if ((int) $department->dean_user_id !== (int) $dean->id
            || (int) $department->institution_id !== (int) $institution->id
            || ! $department->is_active) {
            throw new RuntimeException('Messaging fixture Dean ownership changed; no repair was performed.');
        }
    }

    /** @param list<string> $programCodes */
    private function staff(Institution $institution, AcademicCycle $cycle, string $label, string $office, string $role, array $programCodes): User
    {
        $identity = config('seed.messaging.staff.'.$label);
        if (! is_array($identity) || ! isset($identity['email'], $identity['name'], $identity['password'])) {
            throw new RuntimeException("Messaging demo staff configuration missing for {$label}.");
        }
        $existing = User::query()->where('email', $identity['email'])->first();
        if ($existing !== null && $existing->name !== $identity['name']) {
            throw new RuntimeException('Messaging demo email collision; existing account and credentials were not changed.');
        }
        $user = $existing ?? User::query()->create([
            'email' => $identity['email'],
            'name' => $identity['name'],
            'password' => $identity['password'],
            'email_verified_at' => now(),
        ]);
        if ($existing === null) {
            $user->assignRole(Role::query()->where('name', $role)->where('guard_name', 'web')->firstOrFail());
        } elseif (! $user->hasRole($role)) {
            throw new RuntimeException('Messaging demo account role changed; existing grants were not repaired.');
        }

        $profile = StaffProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'institution_id' => $institution->id,
                'office' => $office,
                'department_id' => null,
                'active_cycle_code' => $cycle->code,
                'program_codes' => $office === 'program' ? $programCodes : [],
                'status' => StaffProfile::STATUS_ACTIVE,
            ],
        );
        if ((int) $profile->institution_id !== (int) $institution->id || $profile->office !== $office
            || $profile->active_cycle_code !== $cycle->code || $profile->program_codes !== $programCodes
            || ! $profile->isActive() || $profile->deactivated_at !== null) {
            throw new RuntimeException('Messaging demo staff scope changed; no account, profile, password, MFA, or grants were overwritten.');
        }
        $auth = StaffAuthenticationState::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'activation_completed_at' => now(),
                'password_set_at' => now(),
                'must_change_password' => false,
                'session_version' => 1,
                'mfa_required' => false,
            ],
        );
        if ($auth->activation_completed_at === null || $auth->password_set_at === null) {
            throw new RuntimeException('Messaging demo staff authentication state is incomplete; it was not repaired.');
        }

        return $user;
    }

    private function ensureThreadPermission(User $user): void
    {
        $permission = Permission::findOrCreate(CapabilityCatalog::RECORD_THREAD_PARTICIPATE, 'web');
        if ($user->wasRecentlyCreated) {
            $user->givePermissionTo($permission);

            return;
        }
        if (! $user->hasPermissionTo($permission)) {
            throw new RuntimeException('Messaging demo staff capability was revoked; no grant was restored.');
        }
    }

    private function revokedAttempt(Institution $institution, AcademicCycle $cycle, Program $program): AdmissionAttempt
    {
        $normalizedKey = NormalizedIdentityKey::make('Revoked', 'Applicant', self::REVOKED_BIRTHDATE, 'female');
        $identity = ApplicantIdentity::query()->firstOrCreate(
            ['institution_id' => $institution->id, 'normalized_key' => $normalizedKey],
            [
                'first_name' => 'Revoked', 'last_name' => 'Applicant', 'birthdate' => self::REVOKED_BIRTHDATE,
                'sex' => 'female', 'email' => self::REVOKED_EMAIL,
            ],
        );
        if ($identity->email !== self::REVOKED_EMAIL || $identity->birthdate?->format('Y-m-d') !== self::REVOKED_BIRTHDATE) {
            throw new RuntimeException('Messaging revoked applicant identity collision; no existing data was adopted.');
        }
        $attempt = AdmissionAttempt::query()->firstOrCreate(
            ['reference_code' => self::REVOKED_REFERENCE],
            [
                'applicant_identity_id' => $identity->id, 'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id, 'status' => AdmissionAttempt::STATUS_ACCEPTED,
                'preferred_program_code' => $program->code, 'applicant_type' => 'new',
                'contact_email' => self::REVOKED_EMAIL,
                'payload' => ['synthetic_fixture' => 'messaging-acceptance', 'workflow_proof' => false],
                'version' => 1, 'accepted_at' => now(),
            ],
        );
        if ((int) $attempt->applicant_identity_id !== (int) $identity->id
            || $attempt->status !== AdmissionAttempt::STATUS_ACCEPTED
            || ($attempt->payload['synthetic_fixture'] ?? null) !== 'messaging-acceptance') {
            throw new RuntimeException('Messaging revoked attempt changed; no workflow state was repaired.');
        }

        return $attempt;
    }

    private function revokedCaseRelationship(AdmissionAttempt $attempt, Program $program, User $guidance): void
    {
        $followUp = ProgramFollowUp::query()->firstOrCreate(
            [
                'admission_attempt_id' => $attempt->id,
                'program_id' => $program->id,
                'idempotency_key' => 'messaging-demo-revoked-follow-up-'.$attempt->reference_code,
            ],
            [
                'opened_by_user_id' => $guidance->id,
                'status' => ProgramFollowUp::STATUS_OPEN,
                'reason' => 'Synthetic messaging fixture targeted program relationship; no quota commitment.',
                'version' => 1,
                'opened_at' => now(),
            ],
        );

        if ((int) $followUp->admission_attempt_id !== (int) $attempt->id
            || (int) $followUp->program_id !== (int) $program->id
            || (int) $followUp->opened_by_user_id !== (int) $guidance->id
            || $followUp->status !== ProgramFollowUp::STATUS_OPEN
            || $followUp->reason !== 'Synthetic messaging fixture targeted program relationship; no quota commitment.') {
            throw new RuntimeException('Messaging revoked case relationship changed; it was not repaired.');
        }
    }

    private function legacyHistoricalEnrollmentStamp(
        AdmissionAttempt $attempt,
        Program $program,
        User $registrar,
    ): EnrollmentStamp {
        $stamp = EnrollmentStamp::query()->firstOrCreate(
            [
                'admission_attempt_id' => $attempt->id,
                'idempotency_key' => 'messaging-demo-legacy-enrolled-'.$attempt->reference_code,
            ],
            [
                'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
                'provisional_program_id' => $program->id,
                'final_program_id' => $program->id,
                'stamped_by_user_id' => $registrar->id,
                'paper_reference' => 'SYN-MSG-LEGACY-HISTORY',
                'paper_hash' => hash('sha256', 'synthetic-messaging-legacy-enrollment-history'),
                'verifier' => 'Synthetic historical fixture; not an enrollment workflow result.',
                'reason' => 'Synthetic legacy history fixture for the messaging actual-stamp cutoff; no enrollment workflow or quota commitment is claimed.',
                'version' => 1,
                'created_at' => now(),
            ],
        );

        if ($stamp->stamp_type !== EnrollmentStamp::STAMP_ENROLLED
            || (int) $stamp->admission_attempt_id !== (int) $attempt->id
            || (int) $stamp->provisional_program_id !== (int) $program->id
            || (int) $stamp->final_program_id !== (int) $program->id
            || (int) $stamp->stamped_by_user_id !== (int) $registrar->id
            || $stamp->reason !== 'Synthetic legacy history fixture for the messaging actual-stamp cutoff; no enrollment workflow or quota commitment is claimed.') {
            throw new RuntimeException('Messaging cutoff history stamp changed; it was not adopted or repaired.');
        }

        return $stamp;
    }

    private function releasedResultPrerequisites(AdmissionAttempt $attempt, AcademicCycle $cycle, User $registrar): void
    {
        $room = Room::query()->where('institution_id', $cycle->institution_id)->orderBy('id')->first();
        if ($room === null) {
            $room = Room::query()->forceCreate([
                'institution_id' => $cycle->institution_id, 'code' => 'MSG-DEMO-ROOM',
                'name' => 'Synthetic messaging fixture room', 'capacity' => 1, 'is_active' => true,
            ]);
        }
        $session = ExamSession::query()->firstOrCreate(
            ['academic_cycle_id' => $cycle->id, 'code' => 'MSG-DEMO-EXAM'],
            [
                'room_id' => $room->id, 'status' => ExamSession::STATUS_COMPLETED,
                'starts_at' => now()->subDays(3), 'effective_end_at' => now()->subDays(3)->addHour(),
                'version' => 1, 'published_at' => now()->subDays(3), 'closed_at' => now()->subDays(3)->addHour(),
            ],
        );
        ExamAssignment::query()->firstOrCreate(
            ['exam_session_id' => $session->id, 'admission_attempt_id' => $attempt->id],
            ['status' => 'submitted', 'version' => 1],
        );
        GradingSession::query()->firstOrCreate(
            ['exam_session_id' => $session->id],
            ['status' => GradingSession::STATUS_FINALIZED, 'version' => 1, 'opened_at' => now()->subDays(2), 'finalized_at' => now()->subDays(2)],
        );

        if (! $attempt->resultDocuments()->where('status', ResultDocument::STATUS_READY)->exists()) {
            $document = ResultDocument::query()->forceCreate([
                'admission_attempt_id' => $attempt->id,
                'template_version' => 'synthetic-messaging-fixture-v1',
                'checksum' => hash('sha256', 'synthetic-messaging-result-'.$attempt->reference_code),
                'status' => ResultDocument::STATUS_READY,
                'storage_path' => 'synthetic/messaging/'.$attempt->reference_code.'.pdf',
                'version' => 1,
            ]);
            app(ReleaseResult::class)->handle(
                $attempt,
                $document,
                $registrar,
                $this->uuid($attempt->id + 4000),
                ReleaseResult::MODE_ONLINE,
            );
        }
        if (! ResultRelease::query()->where('admission_attempt_id', $attempt->id)->where('is_current', true)->exists()) {
            throw new RuntimeException('Messaging fixture requires a current result release; no release was fabricated.');
        }
    }

    private function selection(AdmissionAttempt $attempt, Program $program, User $guidance, User $applicant): ProgramSelection
    {
        $pool = ProgramPool::query()->firstOrCreate(
            ['admission_attempt_id' => $attempt->id, 'version' => 1],
            [
                'actor_user_id' => $guidance->id,
                'reason' => 'Synthetic messaging prerequisite: current program selection.',
                'source' => 'guidance',
                'idempotency_key' => 'messaging-demo-pool-'.$attempt->reference_code,
            ],
        );
        $option = ProgramPoolOption::query()->firstOrCreate(
            ['program_pool_id' => $pool->id, 'program_id' => $program->id],
            ['display_order' => 1, 'basis' => 'preference'],
        );
        $selection = ProgramSelection::query()->firstOrCreate(
            ['admission_attempt_id' => $attempt->id, 'idempotency_key' => 'messaging-demo-selection-'.$attempt->reference_code],
            [
                'program_pool_id' => $pool->id, 'program_pool_option_id' => $option->id,
                'program_id' => $program->id, 'academic_cycle_id' => $attempt->academic_cycle_id,
                'status' => ProgramSelection::STATUS_ACTIVE, 'source' => ProgramSelection::SOURCE_STUDENT_SELF,
                'actor_user_id' => $applicant->id, 'applicant_acknowledged_at' => now(),
                'commitment_identity' => hash('sha256', 'messaging-demo-'.$attempt->reference_code), 'version' => 1,
            ],
        );
        if ($selection->status !== ProgramSelection::STATUS_ACTIVE || (int) $selection->program_id !== (int) $program->id
            || (int) $selection->academic_cycle_id !== (int) $attempt->academic_cycle_id) {
            throw new RuntimeException('Messaging fixture current selection changed; no workflow state was overwritten.');
        }

        return $selection;
    }

    private function uuid(int $ordinal): string
    {
        return sprintf('0a11ce00-0000-4000-8000-%012d', $ordinal);
    }
}
