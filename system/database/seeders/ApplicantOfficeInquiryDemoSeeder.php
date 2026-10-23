<?php

namespace Database\Seeders;

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\CreateApplicantOfficeInquiry;
use App\Admission\Actions\IssueApplicantAccessChallenge;
use App\Admission\Actions\ResolveApplicantOfficeInquiry;
use App\Admission\Actions\SendApplicantOfficeInquiryMessage;
use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\NormalizedIdentityKey;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicantOfficeInquiryDemoActor;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Additive synthetic-only fixtures. Existing credentials, grants, MFA and workflow are retained.
 * Requires the existing SYN foundation; never invokes a foundation/reset seeder.
 */
class ApplicantOfficeInquiryDemoSeeder extends Seeder
{
    public const APPLICANT_EMAIL = 'applicant.office-inquiry-demo@nexiam.test';

    public const REGISTRAR_EMAIL = 'registrar.office-inquiry-demo@nexiam.test';

    public const GUIDANCE_EMAIL = 'guidance.office-inquiry-demo@nexiam.test';

    public const PASSWORD = 'InquiryDemo!2026';

    public const ACCESS_KEY = 'SYNQ-7K9A';

    public const BIRTHDATE = '2004-05-15';

    public const REFERENCE_CODE = 'SYN-OFFICE-INQUIRY-DEMO-001';

    public function run(): void
    {
        if (! app()->environment('local', 'testing') || ! app()->runningInConsole()) {
            throw new RuntimeException('Office inquiry demo requires local/testing console execution.');
        }

        DB::transaction(function (): void {
            $this->syncOfficeInquiryRoleHelpers();

            $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
            $cycle = AcademicCycle::query()->where('institution_id', $institution->id)
                ->where('code', 'SYN-CYCLE-2026')->where('is_active', true)->firstOrFail();
            $window = ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)
                ->where('code', 'SYN-WINDOW-2026-A')->where('status', 'published')->firstOrFail();

            $registrar = $this->staff($institution, $cycle, self::REGISTRAR_EMAIL, 'registrar', 'registrar_clerk', 'RegistrarClerk');
            $this->staff($institution, $cycle, self::GUIDANCE_EMAIL, 'guidance', 'guidance_clerk', 'GuidanceClerk');
            $applicant = $this->applicant($institution, $cycle, $window);
            $this->registerOwnedPortalApplicants($institution, $cycle, $window);

            CreateApplicantOfficeInquiry::handle($applicant, 'registrar', 'Synthetic application requirements question',
                'Synthetic demo only: where can I review the application requirements? Do not include personal information.',
                '7ce329b4-7bc4-42c5-83a7-6e4b7eb99001');
            CreateApplicantOfficeInquiry::handle($applicant, 'guidance', 'Synthetic examination guidance question',
                'Synthetic demo only: where can I review examination instructions? This message does not reserve a slot.',
                '7ce329b4-7bc4-42c5-83a7-6e4b7eb99002');
            $closed = CreateApplicantOfficeInquiry::handle($applicant, 'registrar', 'Synthetic resolved portal question',
                'Synthetic demo only: please explain how to return to the applicant portal.',
                '7ce329b4-7bc4-42c5-83a7-6e4b7eb99003');
            if (! $closed['replayed']) {
                SendApplicantOfficeInquiryMessage::handle($registrar, (int) $closed['inquiry_id'],
                    'Synthetic demo reply: use the applicant portal navigation. This inquiry can now be closed.',
                    '7ce329b4-7bc4-42c5-83a7-6e4b7eb99004');
                ResolveApplicantOfficeInquiry::handle($registrar, (int) $closed['inquiry_id'],
                    '7ce329b4-7bc4-42c5-83a7-6e4b7eb99005');
            }
        });

        $this->command?->info('Synthetic office inquiry actors and examples created or retained. Existing credentials and workflow were not reset.');
    }

    private function staff(Institution $institution, AcademicCycle $cycle, string $email, string $office, string $role, string $label): User
    {
        $name = 'Synthetic Office Inquiry '.$label;
        $user = $this->ownedUser($institution, $email, $name, ApplicantOfficeInquiryDemoActor::TYPE_STAFF);
        if ($user !== null) {
            $profile = $user->staffProfile;
            if ($profile === null || (int) $profile->institution_id !== (int) $institution->id
                || $profile->office !== $office || $profile->department_id !== null || $profile->active_cycle_code !== $cycle->code
                || $profile->program_codes !== [] || ! $profile->isActive() || $profile->deactivated_at !== null
                || $user->applicantAccount()->exists() || ! $user->hasRole($role)
                || ! StaffAuthenticationState::query()->where('user_id', $user->id)->exists()) {
                throw new RuntimeException('Owned office inquiry staff scope/authentication changed; no repair or reset was performed.');
            }
            app(ApplicantOfficeInquiryAccess::class)->actor($user);

            return $user;
        }

        $roleHelper = Role::query()->where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $user = User::query()->create(['email' => $email, 'name' => $name, 'password' => self::PASSWORD, 'email_verified_at' => now()]);
        StaffProfile::query()->create([
            'user_id' => $user->id, 'institution_id' => $institution->id, 'office' => $office, 'department_id' => null,
            'active_cycle_code' => $cycle->code, 'program_codes' => [], 'status' => StaffProfile::STATUS_ACTIVE,
        ]);
        StaffAuthenticationState::query()->create([
            'user_id' => $user->id, 'activation_completed_at' => now(), 'password_set_at' => now(),
            'must_change_password' => false, 'session_version' => 1, 'mfa_required' => false,
        ]);
        $user->assignRole($roleHelper);
        $this->register($user, $institution, ApplicantOfficeInquiryDemoActor::TYPE_STAFF);
        $this->grant($user, [CapabilityCatalog::OFFICE_INQUIRY_HANDLE, CapabilityCatalog::FOUNDATION_PROBE, CapabilityCatalog::NOTIFY_READ_OWN]);

        return $user;
    }

    private function applicant(Institution $institution, AcademicCycle $cycle, ApplicationWindow $window): User
    {
        $name = 'Synthetic Office Inquiry Applicant';
        $normalizedKey = NormalizedIdentityKey::make('Synthetic', 'Office Inquiry Applicant', self::BIRTHDATE, 'female');
        $user = $this->ownedUser($institution, self::APPLICANT_EMAIL, $name, ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT);
        $identity = ApplicantIdentity::query()->where('institution_id', $institution->id)->where('normalized_key', $normalizedKey)->first();
        $attempt = AdmissionAttempt::query()->where('reference_code', self::REFERENCE_CODE)->first();
        $challenge = ApplicantAccessChallenge::query()->where('token_hash', ApplicantAccessKey::hash(self::ACCESS_KEY))->first();

        if ($user !== null) {
            if ($identity === null || $attempt === null || $challenge === null
                || $identity->email !== self::APPLICANT_EMAIL || $identity->fullName() !== $name
                || $identity->birthdate?->format('Y-m-d') !== self::BIRTHDATE
                || (int) $user->applicantAccount?->applicant_identity_id !== (int) $identity->id
                || $user->staffProfile()->exists()
                || (int) $attempt->applicant_identity_id !== (int) $identity->id
                || (int) $attempt->institution_id !== (int) $institution->id
                || (int) $attempt->academic_cycle_id !== (int) $cycle->id
                || (int) $attempt->application_window_id !== (int) $window->id
                || ($attempt->payload['source'] ?? null) !== self::class
                || (int) $challenge->applicant_identity_id !== (int) $identity->id
                || (int) $challenge->admission_attempt_id !== (int) $attempt->id
                || $challenge->request_key !== 'applicant-office-inquiry-demo-access-v1'
                || $challenge->purpose !== 'access_key'
                || $challenge->consumed_at !== null || $challenge->expires_at->isPast()) {
                throw new RuntimeException('Owned office inquiry applicant credentials or fixture changed; no repair or reset was performed.');
            }
            app(ApplicantOfficeInquiryAccess::class)->applicantAttempt($user);

            return $user;
        }

        if ($identity !== null || $attempt !== null || $challenge !== null
            || ApplicantAccessChallenge::query()->where('request_key', 'applicant-office-inquiry-demo-access-v1')->exists()
            || ApplicantIdentity::query()->where('email', self::APPLICANT_EMAIL)->exists()) {
            throw new RuntimeException('Office inquiry applicant identity/reference/access key collision; no existing records were adopted.');
        }
        $identity = ApplicantIdentity::query()->create([
            'institution_id' => $institution->id, 'normalized_key' => $normalizedKey,
            'first_name' => 'Synthetic', 'last_name' => 'Office Inquiry Applicant',
            'birthdate' => self::BIRTHDATE, 'sex' => 'female', 'email' => self::APPLICANT_EMAIL,
        ]);
        $attempt = AdmissionAttempt::query()->create([
            'institution_id' => $institution->id, 'academic_cycle_id' => $cycle->id,
            'application_window_id' => $window->id, 'applicant_identity_id' => $identity->id,
            'reference_code' => self::REFERENCE_CODE, 'status' => AdmissionAttempt::STATUS_PENDING,
            'applicant_type' => 'new', 'contact_email' => self::APPLICANT_EMAIL,
            'payload' => ['synthetic_demo' => true, 'source' => self::class],
        ]);
        app(IssueApplicantAccessChallenge::class)->handle($identity, $attempt, ApplicantAccessKey::hash(self::ACCESS_KEY),
            'access_key', 'applicant-office-inquiry-demo-access-v1');
        $user = app(AuthenticateApplicantWithAccessKey::class)->handle(self::ACCESS_KEY, self::BIRTHDATE);
        $this->register($user, $institution, ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT);
        $this->grant($user, [CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE]);

        return $user;
    }

    private function registerOwnedPortalApplicants(Institution $institution, AcademicCycle $cycle, ApplicationWindow $window): void
    {
        foreach ([
            ['demo.portal.online@nexiam.test', 'Online', 'PORT-ONLN'],
            ['demo.portal.f2f@nexiam.test', 'F2F', 'PORT-F2F2'],
            ['demo.portal.default@nexiam.test', 'Default', 'PORT-DEFL'],
        ] as [$email, $firstName, $accessKey]) {
            $user = User::query()->with('applicantAccount')->where('email', $email)->first();
            if ($user === null) {
                continue;
            }

            $identity = $user->applicantAccount === null
                ? null
                : ApplicantIdentity::query()->find($user->applicantAccount->applicant_identity_id);
            $attempt = $identity === null ? null : AdmissionAttempt::query()
                ->where('applicant_identity_id', $identity->id)
                ->where('institution_id', $institution->id)
                ->where('academic_cycle_id', $cycle->id)
                ->latest('id')->first();
            $owned = $identity !== null && $attempt !== null
                && $user->name === $firstName.' Portal'
                && ! $user->staffProfile()->exists()
                && $identity->email === $email
                && $identity->first_name === $firstName
                && $identity->last_name === 'Portal'
                && $identity->birthdate?->format('Y-m-d') === '2003-09-25'
                && (int) $identity->institution_id === (int) $institution->id
                && (int) $attempt->application_window_id === (int) $window->id
                && $attempt->contact_email === $email
                && AuditEvent::query()->where('event_type', 'admission.attempt_submitted')
                    ->where('actor', 'applicant-portal-seeder')
                    ->where('payload->attempt_id', $attempt->id)
                    ->where('payload->identity_id', $identity->id)
                    ->where('payload->reference_code', $attempt->reference_code)->exists()
                && ApplicantAccessChallenge::query()
                    ->where('applicant_identity_id', $identity->id)
                    ->where('admission_attempt_id', $attempt->id)
                    ->where('purpose', 'access_key')
                    ->where('token_hash', ApplicantAccessKey::hash($accessKey))
                    ->whereNull('consumed_at')->where('expires_at', '>', now())->exists();

            if (! $owned) {
                throw new RuntimeException('Portal inquiry fixture ownership could not be verified; no account changes were made.');
            }

            $marker = ApplicantOfficeInquiryDemoActor::query()->where('user_id', $user->id)->first();
            if ($marker !== null && ((int) $marker->institution_id !== (int) $institution->id
                || $marker->namespace !== ApplicantOfficeInquiryDemoActor::NAMESPACE
                || $marker->actor_type !== ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT)) {
                throw new RuntimeException('Portal inquiry fixture has conflicting registration; no account changes were made.');
            }

            if ($marker === null) {
                $this->register($user, $institution, ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT);
                $this->grant($user, [CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE]);
            }
            app(ApplicantOfficeInquiryAccess::class)->applicantAttempt($user);
        }
    }

    private function ownedUser(Institution $institution, string $email, string $name, string $type): ?User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user !== null && ($user->name !== $name || ! ApplicantOfficeInquiryDemoActor::query()
            ->where('user_id', $user->id)->where('institution_id', $institution->id)
            ->where('namespace', ApplicantOfficeInquiryDemoActor::NAMESPACE)->where('actor_type', $type)->exists())) {
            throw new RuntimeException('Office inquiry demo email belongs to an unowned account; no account changes were made.');
        }

        return $user;
    }

    private function register(User $user, Institution $institution, string $type): void
    {
        ApplicantOfficeInquiryDemoActor::query()->create([
            'user_id' => $user->id, 'institution_id' => $institution->id,
            'namespace' => ApplicantOfficeInquiryDemoActor::NAMESPACE, 'actor_type' => $type,
        ]);
    }

    /** @param list<string> $capabilities */
    private function grant(User $user, array $capabilities): void
    {
        foreach ($capabilities as $capability) {
            Permission::findOrCreate($capability, 'web');
        }
        $user->givePermissionTo($capabilities);
    }

    private function syncOfficeInquiryRoleHelpers(): void
    {
        foreach ([
            'registrar_admin',
            'registrar_clerk',
            'registrar_support_staff',
            'guidance_counselor',
            'guidance_clerk',
            'guidance_support_staff',
        ] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role === null) {
                continue;
            }

            Permission::findOrCreate(CapabilityCatalog::OFFICE_INQUIRY_HANDLE, 'web');
            $role->givePermissionTo(CapabilityCatalog::OFFICE_INQUIRY_HANDLE);
        }
    }
}
