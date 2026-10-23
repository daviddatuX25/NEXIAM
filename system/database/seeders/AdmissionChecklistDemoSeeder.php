<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\IssueApplicantAccessChallenge;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Admission\Support\NormalizedIdentityKey;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementItem;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Operation;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Permission;

/**
 * Local/testing synthetic checklist fixtures; not public intake or consent evidence.
 * Attempts carry workflow_proof=false and no policy links. Credentials/history are retained;
 * identity, reference, and access collisions fail closed.
 */
final class AdmissionChecklistDemoSeeder extends Seeder
{
    public const STAFF_EMAIL = 'registrar.checklist-readonly@nexiam.test';

    public const STAFF_PASSWORD = 'ChecklistReadOnly!2026';

    public const APPLICANT_EMAIL = 'applicant.checklist-demo@nexiam.test';

    public const APPLICANT_ACCESS_KEY = 'CHKL-7N4P';

    public const APPLICANT_BIRTHDATE = '2004-05-15';

    private const APPLICANT_FIRST_NAME = 'Synthetic';

    private const APPLICANT_LAST_NAME = 'Checklist Demo';

    private const REFERENCE_CODE = 'SYN-CHK-2026-001';

    private const INITIALIZE_EMAIL = 'applicant.checklist-init-demo@nexiam.test';

    private const INITIALIZE_FIRST_NAME = 'Synthetic';

    private const INITIALIZE_LAST_NAME = 'Checklist Initialize Demo';

    private const INITIALIZE_BIRTHDATE = '2004-06-16';

    private const INITIALIZE_ACCESS_KEY = 'CHKI-8Q5R';

    private const INIT_KEY = 'admission-checklist-demo-init-v1';

    private const INITIALIZE_REFERENCE_CODE = 'SYN-CHK-2026-002';

    private const WRITER_EMAIL = 'registrar.checklist-writer@nexiam.test';

    private const WRITER_PASSWORD = 'ChecklistWriter!2026';

    private const WRITER_NAME = 'Synthetic Admission Checklist Writer Registrar';

    public function run(): void
    {
        if (! app()->environment('local', 'testing') || ! app()->runningInConsole()) {
            throw new RuntimeException('Admission checklist demo requires local/testing console execution.');
        }

        DB::transaction(function (): void {
            $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
            $cycle = AcademicCycle::query()
                ->where('institution_id', $institution->getKey())
                ->where('code', 'SYN-CYCLE-2026')
                ->where('is_active', true)
                ->firstOrFail();
            $window = ApplicationWindow::query()
                ->where('academic_cycle_id', $cycle->getKey())
                ->where('code', 'SYN-WINDOW-2026-A')
                ->where('status', 'published')
                ->firstOrFail();
            $program = Program::query()
                ->where('institution_id', $institution->getKey())
                ->where('code', 'BSIT')
                ->where('is_active', true)
                ->where('is_admission_offered', true)
                ->firstOrFail();
            $this->command?->line('No policy is published/designated by this feature. These explicit synthetic staff-owned attempts use no policy links or consent and do not waive public intake requirements.');
            if ((int) $window->academic_cycle_id !== (int) $cycle->getKey()) {
                throw new RuntimeException('Checklist demo requires the published window for the current active cycle.');
            }
            if (! $program->department()->where('institution_id', $institution->getKey())->exists()) {
                throw new RuntimeException('BSIT is not attached to the synthetic institution.');
            }
            $registrar = $this->checklistWriter($institution, $cycle);
            $readOnly = $this->readOnlyRegistrar($institution, $cycle);
            $attempt = $this->acceptedAttempt($institution, $cycle, $window, $registrar);
            $this->pinApplicantAccess($attempt);

            $set = AdmissionAttemptRequirementSet::query()
                ->where('admission_attempt_id', $attempt->getKey())
                ->where('is_current', true)
                ->first();
            if ($set === null) {
                $attempt->refresh();
                $set = app(InitializeAttemptRequirementSet::class)->handle(
                    $attempt,
                    $registrar,
                    (int) $attempt->version,
                    self::INIT_KEY,
                );
            }

            if (! $set->is_current
                || $set->applicant_track !== app(AttemptRequirementChecklistBoundary::class)->currentTrack($attempt)
                || $set->items()->doesntExist()) {
                throw new RuntimeException('Checklist demo requirement set is stale or empty; no repair was performed.');
            }

            $items = $set->items()->where('is_mandatory', true)->orderBy('sort_order')->orderBy('id')->get();
            if ($items->isEmpty()) {
                throw new RuntimeException('The active synthetic catalog has no mandatory checklist item.');
            }

            $this->ensureInspection(
                $attempt,
                $set,
                $items[0],
                AdmissionAttemptDocumentVerification::STATUS_MISSING,
                'Please present '.$items[0]->label.' to the Registrar for inspection.',
                'Synthetic checklist fixture: this document has not yet been presented.',
                $registrar,
                'admission-checklist-demo-missing-v1',
            );

            if ($items->count() > 1) {
                $this->ensureInspection(
                    $attempt,
                    $set,
                    $items[1],
                    AdmissionAttemptDocumentVerification::STATUS_DEFICIENT,
                    'Please bring the original '.$items[1]->label.' for comparison.',
                    'Synthetic checklist fixture: an incomplete copy was presented.',
                    $registrar,
                    'admission-checklist-demo-deficient-v1',
                );
            }

            $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
                self::APPLICANT_ACCESS_KEY,
                self::APPLICANT_BIRTHDATE,
            );
            if ((int) $applicant->applicantAccount?->applicant_identity_id !== (int) $attempt->applicant_identity_id) {
                throw new RuntimeException('Checklist demo applicant credential resolved to a different applicant identity.');
            }
            $initializeAttempt = $this->uninitializedAttempt($institution, $cycle, $window, $registrar);
            $this->pinInitializerAccess($initializeAttempt);
            $initializeApplicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
                self::INITIALIZE_ACCESS_KEY,
                self::INITIALIZE_BIRTHDATE,
            );
            if ((int) $initializeApplicant->applicantAccount?->applicant_identity_id !== (int) $initializeAttempt->applicant_identity_id) {
                throw new RuntimeException('Checklist initializer credential resolved to a different applicant identity.');
            }
            $itemIds = $set->items()->orderBy('id')->pluck('id')->all();
            $this->command?->info('Synthetic admission checklist fixture is ready; existing accounts and workflow were retained.');
            $this->command?->line('Staff view-only: '.self::STAFF_EMAIL.' / '.self::STAFF_PASSWORD.' → /admissions/'.$attempt->getKey());
            $this->command?->line('Applicant: '.self::APPLICANT_ACCESS_KEY.' / '.self::APPLICANT_BIRTHDATE.' → /applicant/portal');
            $this->command?->line(sprintf(
                'Attempt ID %d (%s), requirement set ID %d, item IDs [%s], status accepted with Missing%s outstanding.',
                $attempt->getKey(),
                $attempt->reference_code,
                $set->getKey(),
                implode(', ', $itemIds),
                $items->count() > 1 ? ' and Deficient' : '',
            ));
            $this->command?->line('Checklist writer: '.self::WRITER_EMAIL.' / '.self::WRITER_PASSWORD.' → /admissions/'.$attempt->getKey().' (accept, initialize, inspect, and send demo instructions).');
            $this->command?->line('Initializer applicant: '.self::INITIALIZE_ACCESS_KEY.' / '.self::INITIALIZE_BIRTHDATE.' → /applicant/portal');
            $this->command?->line('Main accepted attempt ID '.$attempt->getKey().' / '.$attempt->reference_code.'; initializer accepted attempt ID '.$initializeAttempt->getKey().' / '.$initializeAttempt->reference_code.' (no requirement set).');
            $this->command?->line('Main credential DOB '.self::APPLICANT_BIRTHDATE.'; initializer credential DOB '.self::INITIALIZE_BIRTHDATE.'. Main: accepted with current checklist; initializer: accepted with no requirement set.');
        });
    }

    private function acceptedAttempt(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        User $registrar,
    ): AdmissionAttempt {
        $identity = ApplicantIdentity::query()
            ->where('institution_id', $institution->getKey())
            ->where('email', self::APPLICANT_EMAIL)
            ->first();
        $normalizedKey = NormalizedIdentityKey::make(self::APPLICANT_FIRST_NAME, self::APPLICANT_LAST_NAME, self::APPLICANT_BIRTHDATE, 'female');

        if ($identity === null) {

            // Synthetic staff-owned fixture, not public intake; no consent is represented.
            if (ApplicantIdentity::query()->where('normalized_key', $normalizedKey)->exists()
                || ApplicantIdentity::query()->where('email', self::APPLICANT_EMAIL)->exists()
                || User::query()->where('email', self::APPLICANT_EMAIL)->exists()
                || AdmissionAttempt::query()->where('reference_code', self::REFERENCE_CODE)->exists()
                || ApplicantAccessChallenge::query()->where('request_key', 'admission-checklist-demo-access-v1')->exists()
                || ApplicantAccessChallenge::query()->where('token_hash', ApplicantAccessKey::hash(self::APPLICANT_ACCESS_KEY))->exists()) {
                throw new RuntimeException('Checklist demo identity, attempt reference, or access key collision; no existing records were adopted.');
            }
            $identity = ApplicantIdentity::query()->create([
                'institution_id' => $institution->getKey(),
                'normalized_key' => $normalizedKey,
                'first_name' => self::APPLICANT_FIRST_NAME,
                'middle_name' => null,
                'last_name' => self::APPLICANT_LAST_NAME,
                'suffix' => null,
                'birthdate' => self::APPLICANT_BIRTHDATE,
                'sex' => 'female',
                'email' => self::APPLICANT_EMAIL,
            ]);
            $attempt = AdmissionAttempt::query()->create([
                'institution_id' => $institution->getKey(),
                'academic_cycle_id' => $cycle->getKey(),
                'application_window_id' => $window->getKey(),
                'applicant_identity_id' => $identity->getKey(),
                'reference_code' => self::REFERENCE_CODE,
                'status' => AdmissionAttempt::STATUS_PENDING,
                'version' => 1,
                'applicant_type' => 'new',
                'preferred_program_code' => 'BSIT',
                'program_preference_1_code' => 'BSIT',
                'privacy_policy_id' => null,
                'public_policy_id' => null,
                'contact_email' => self::APPLICANT_EMAIL,
                'payload' => [
                    'synthetic_demo' => true,
                    'source' => self::class,
                    'workflow_proof' => false,
                    'consent_receipt' => null,
                    'consent' => null,
                    'terms_accepted' => false,
                    'program_preference_codes' => ['BSIT'],
                ],
            ]);
            app(IssueApplicantAccessChallenge::class)->handle(
                $identity,
                $attempt,
                ApplicantAccessKey::hash(self::APPLICANT_ACCESS_KEY),
                'access_key',
                'admission-checklist-demo-access-v1',
            );
            $attempt = $attempt->fresh();
            $attempt = app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
        } else {
            if ($identity->normalized_key !== $normalizedKey
                || $identity->first_name !== self::APPLICANT_FIRST_NAME
                || $identity->middle_name !== null
                || $identity->last_name !== self::APPLICANT_LAST_NAME
                || $identity->suffix !== null
                || $identity->sex !== 'female'
                || (string) ($identity->birthdate?->format('Y-m-d') ?? '') !== self::APPLICANT_BIRTHDATE
                || ApplicantIdentity::query()->where('normalized_key', $normalizedKey)->whereKeyNot($identity->getKey())->exists()) {
                throw new RuntimeException('Owned checklist demo applicant identity changed; no repair or reset was performed.');
            }
            $attempt = AdmissionAttempt::query()
                ->where('applicant_identity_id', $identity->getKey())
                ->where('academic_cycle_id', $cycle->getKey())
                ->get();
            if ($attempt->count() !== 1) {
                throw new RuntimeException('Owned checklist demo applicant must have exactly one active-cycle attempt.');
            }
            $attempt = $attempt->firstOrFail();
            $challenge = ApplicantAccessChallenge::query()->where('request_key', 'admission-checklist-demo-access-v1')->first();
            if ($challenge === null
                || $challenge->token_hash !== ApplicantAccessKey::hash(self::APPLICANT_ACCESS_KEY)
                || (int) $challenge->applicant_identity_id !== (int) $identity->getKey()
                || (int) $challenge->admission_attempt_id !== (int) $attempt->getKey()
                || $challenge->purpose !== 'access_key'
                || $challenge->consumed_at !== null
                || $challenge->expires_at === null
                || $challenge->expires_at->isPast()
                || $challenge->admissionAttempt?->status !== AdmissionAttempt::STATUS_ACCEPTED) {
                throw new RuntimeException('Owned checklist access challenge changed; credentials are never reset.');
            }
        }
        if (ApplicantIdentity::query()->where('normalized_key', $normalizedKey)->whereKeyNot($identity->getKey())->exists()
            || ApplicantIdentity::query()->where('email', self::APPLICANT_EMAIL)->whereKeyNot($identity->getKey())->exists()
            || AdmissionAttempt::query()->where('reference_code', self::REFERENCE_CODE)->whereKeyNot($attempt->getKey())->exists()) {
            throw new RuntimeException('Checklist demo identity or attempt reference has a conflicting owner.');
        }

        if ((int) $attempt->institution_id !== (int) $institution->getKey()
            || (int) $attempt->academic_cycle_id !== (int) $cycle->getKey()
            || (int) $attempt->application_window_id !== (int) $window->getKey()
            || (int) $attempt->applicant_identity_id !== (int) $identity->getKey()
            || $attempt->reference_code !== self::REFERENCE_CODE
            || ($attempt->payload['source'] ?? null) !== self::class
            || ($attempt->payload['workflow_proof'] ?? null) !== false
            || ($attempt->payload['consent_receipt'] ?? null) !== null
            || ($attempt->payload['consent'] ?? null) !== null
            || ($attempt->payload['terms_accepted'] ?? null) !== false
            || $attempt->privacy_policy_id !== null
            || $attempt->public_policy_id !== null
            || $attempt->preferred_program_code !== 'BSIT'
            || $attempt->program_preference_1_code !== 'BSIT'
            || $attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            throw new RuntimeException('Owned checklist demo attempt scope/provenance changed; no repair or reset was performed.');
        }

        return $attempt->fresh();
    }

    private function uninitializedAttempt(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        User $registrar,
    ): AdmissionAttempt {
        $email = self::INITIALIZE_EMAIL;
        $reference = self::INITIALIZE_REFERENCE_CODE;
        $requestKey = 'admission-checklist-demo-init-access-v1';
        $normalizedKey = NormalizedIdentityKey::make(self::INITIALIZE_FIRST_NAME, self::INITIALIZE_LAST_NAME, self::INITIALIZE_BIRTHDATE, 'female');
        $identity = ApplicantIdentity::query()->where('institution_id', $institution->getKey())->where('email', $email)->first();
        if ($identity === null) {
            if (ApplicantIdentity::query()->where('normalized_key', $normalizedKey)->exists()
                || ApplicantIdentity::query()->where('email', $email)->exists()
                || User::query()->where('email', $email)->exists()
                || AdmissionAttempt::query()->where('reference_code', $reference)->exists()
                || ApplicantAccessChallenge::query()->where('request_key', $requestKey)->exists()
                || ApplicantAccessChallenge::query()->where('token_hash', ApplicantAccessKey::hash(self::INITIALIZE_ACCESS_KEY))->exists()) {
                throw new RuntimeException('Checklist initializer identity/reference/access collision; no existing records were adopted.');
            }
            $identity = ApplicantIdentity::query()->create([
                'institution_id' => $institution->getKey(),
                'normalized_key' => $normalizedKey,
                'first_name' => self::INITIALIZE_FIRST_NAME,
                'middle_name' => null,
                'last_name' => self::INITIALIZE_LAST_NAME,
                'suffix' => null,
                'birthdate' => self::INITIALIZE_BIRTHDATE,
                'sex' => 'female',
                'email' => $email,
            ]);
            $attempt = AdmissionAttempt::query()->create([
                'institution_id' => $institution->getKey(),
                'academic_cycle_id' => $cycle->getKey(),
                'application_window_id' => $window->getKey(),
                'applicant_identity_id' => $identity->getKey(),
                'reference_code' => $reference,
                'status' => AdmissionAttempt::STATUS_PENDING,
                'version' => 1,
                'applicant_type' => 'new',
                'preferred_program_code' => 'BSIT',
                'program_preference_1_code' => 'BSIT',
                'privacy_policy_id' => null,
                'public_policy_id' => null,
                'contact_email' => $email,
                'payload' => [
                    'synthetic_demo' => true,
                    'source' => self::class,
                    'workflow_proof' => false,
                    'consent_receipt' => null,
                    'consent' => null,
                    'terms_accepted' => false,
                    'program_preference_codes' => ['BSIT'],
                ],
            ]);
            app(IssueApplicantAccessChallenge::class)->handle(
                $identity,
                $attempt,
                ApplicantAccessKey::hash(self::INITIALIZE_ACCESS_KEY),
                'access_key',
                $requestKey,
            );
            $attempt = $attempt->fresh();
            $attempt = app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
        } else {
            if ($identity->normalized_key !== $normalizedKey
                || $identity->first_name !== self::INITIALIZE_FIRST_NAME
                || $identity->middle_name !== null
                || $identity->last_name !== self::INITIALIZE_LAST_NAME
                || $identity->suffix !== null
                || $identity->sex !== 'female'
                || (string) ($identity->birthdate?->format('Y-m-d') ?? '') !== self::INITIALIZE_BIRTHDATE
                || ApplicantIdentity::query()->where('normalized_key', $normalizedKey)->whereKeyNot($identity->getKey())->exists()) {
                throw new RuntimeException('Owned checklist initializer identity changed; no repair or reset was performed.');
            }
            $attempt = AdmissionAttempt::query()->where('reference_code', $reference)->where('applicant_identity_id', $identity->getKey())->firstOrFail();
            $challenge = ApplicantAccessChallenge::query()->where('request_key', $requestKey)->first();
            if ($challenge === null
                || $challenge->token_hash !== ApplicantAccessKey::hash(self::INITIALIZE_ACCESS_KEY)
                || (int) $challenge->applicant_identity_id !== (int) $identity->getKey()
                || (int) $challenge->admission_attempt_id !== (int) $attempt->getKey()
                || $challenge->purpose !== 'access_key'
                || $challenge->consumed_at !== null
                || $challenge->expires_at === null
                || $challenge->expires_at->isPast()
                || $challenge->admissionAttempt?->status !== AdmissionAttempt::STATUS_ACCEPTED) {
                throw new RuntimeException('Owned initializer access challenge changed; credentials are never reset.');
            }
        }
        if ((int) $attempt->institution_id !== (int) $institution->getKey()
            || (int) $attempt->academic_cycle_id !== (int) $cycle->getKey()
            || (int) $attempt->application_window_id !== (int) $window->getKey()
            || (int) $attempt->applicant_identity_id !== (int) $identity->getKey()
            || $attempt->reference_code !== $reference
            || ($attempt->payload['source'] ?? null) !== self::class
            || ($attempt->payload['workflow_proof'] ?? null) !== false
            || ($attempt->payload['consent_receipt'] ?? null) !== null
            || ($attempt->payload['consent'] ?? null) !== null
            || ($attempt->payload['terms_accepted'] ?? null) !== false
            || $attempt->privacy_policy_id !== null
            || $attempt->public_policy_id !== null
            || $attempt->preferred_program_code !== 'BSIT'
            || $attempt->program_preference_1_code !== 'BSIT'
            || $attempt->status !== AdmissionAttempt::STATUS_ACCEPTED
            || AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $attempt->getKey())->exists()) {
            throw new RuntimeException('Owned initializer attempt is not accepted/uninitialized or its provenance changed.');
        }

        return $attempt->fresh();
    }

    private function pinApplicantAccess(AdmissionAttempt $attempt): void
    {
        $identity = $attempt->identity;
        $hash = ApplicantAccessKey::hash(self::APPLICANT_ACCESS_KEY);
        $challenge = ApplicantAccessChallenge::query()
            ->where('request_key', 'admission-checklist-demo-access-v1')
            ->first();

        if ($challenge !== null) {
            if ((int) $challenge->admission_attempt_id !== (int) $attempt->getKey()
                || (int) $challenge->applicant_identity_id !== (int) $identity->getKey()
                || $challenge->token_hash !== $hash
                || $challenge->purpose !== 'access_key'
                || $challenge->consumed_at !== null
                || $challenge->expires_at === null
                || $challenge->expires_at->isPast()
                || $challenge->admissionAttempt?->status !== AdmissionAttempt::STATUS_ACCEPTED) {
                throw new RuntimeException('Owned checklist demo applicant access changed; no credential was reset.');
            }

            return;
        }

        $existingKey = ApplicantAccessChallenge::query()
            ->where('token_hash', $hash)
            ->whereNull('consumed_at')
            ->first();
        if ($existingKey !== null) {
            throw new RuntimeException('Checklist demo access key is already owned by another challenge; no credential was reset.');
        }

        app(IssueApplicantAccessChallenge::class)->handle(
            $identity,
            $attempt,
            $hash,
            'access_key',
            'admission-checklist-demo-access-v1',
        );
    }

    private function pinInitializerAccess(AdmissionAttempt $attempt): void
    {
        $identity = $attempt->identity;
        $hash = ApplicantAccessKey::hash(self::INITIALIZE_ACCESS_KEY);
        $challenge = ApplicantAccessChallenge::query()->where('request_key', 'admission-checklist-demo-init-access-v1')->first();
        if ($challenge === null) {
            if (ApplicantAccessChallenge::query()->where('token_hash', $hash)->exists()) {
                throw new RuntimeException('Checklist initializer access key collision; no credential was reset.');
            }
            app(IssueApplicantAccessChallenge::class)->handle($identity, $attempt, $hash, 'access_key', 'admission-checklist-demo-init-access-v1');

            return;
        }
        if ((int) $challenge->admission_attempt_id !== (int) $attempt->getKey()
            || (int) $challenge->applicant_identity_id !== (int) $identity->getKey()
            || $challenge->token_hash !== $hash
            || $challenge->purpose !== 'access_key'
            || $challenge->consumed_at !== null
            || $challenge->expires_at === null
            || $challenge->expires_at->isPast()
            || $challenge->admissionAttempt?->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            throw new RuntimeException('Owned checklist initializer access changed; no credential was reset.');
        }
    }

    private function ensureInspection(
        AdmissionAttempt $attempt,
        AdmissionAttemptRequirementSet $set,
        AdmissionAttemptRequirementItem $item,
        string $status,
        string $applicantInstruction,
        string $internalRemarks,
        User $registrar,
        string $idempotencyKey,
    ): void {
        $operationKey = sprintf(
            '%s:%s:%s:%s',
            $registrar->getKey(),
            'admission.documents.verify',
            'attempt:'.$attempt->getKey(),
            $idempotencyKey,
        );
        $operation = Operation::query()->where('idempotency_key', $operationKey)->first();
        $verificationId = $operation?->payload['receipt_verification_id'] ?? null;
        if ($operation !== null) {
            if ($verificationId === null) {
                throw new RuntimeException('Owned checklist demo operation has no inspection receipt; no repair or history rewrite was performed.');
            }
            $existing = AdmissionAttemptDocumentVerification::query()
                ->whereKey($verificationId)
                ->where('admission_attempt_id', $attempt->getKey())
                ->first();
            if ($existing === null) {
                throw new RuntimeException('Owned checklist demo inspection receipt could not be resolved on this attempt; no repair was performed.');
            }
        } else {
            $existing = null;
        }

        if ($existing !== null) {
            if ((int) $existing->requirement_item_id !== (int) $item->getKey()
                || $existing->status !== $status
                || $existing->applicant_instruction !== $applicantInstruction
                || $existing->internal_remarks !== $internalRemarks) {
                throw new RuntimeException('Owned checklist demo inspection changed; no history was rewritten.');
            }

            return;
        }

        $attempt->refresh();
        $latestId = AdmissionAttemptDocumentVerification::query()
            ->where('admission_attempt_id', $attempt->getKey())
            ->where('requirement_item_id', $item->getKey())
            ->latest('id')
            ->value('id');

        app(VerifyAdmissionAttemptDocument::class)->handle(
            attempt: $attempt,
            requirementSetId: (int) $set->getKey(),
            requirementItemId: (int) $item->getKey(),
            expectedLatestVerificationId: $latestId === null ? null : (int) $latestId,
            status: $status,
            actor: $registrar,
            expectedVersion: (int) $attempt->version,
            applicantInstruction: $applicantInstruction,
            internalRemarks: $internalRemarks,
            idempotencyKey: $idempotencyKey,
        );
    }

    private function checklistWriter(Institution $institution, AcademicCycle $cycle): User
    {
        $permissions = [
            CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW,
            CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY,
            CapabilityCatalog::REGISTRAR_SETUP_MANAGE,
        ];
        sort($permissions);
        $email = self::WRITER_EMAIL;
        $password = self::WRITER_PASSWORD;
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            $profile = $user->staffProfile;
            $auth = StaffAuthenticationState::query()->where('user_id', $user->getKey())->first();
            Permission::findOrCreate(CapabilityCatalog::REGISTRAR_SETUP_MANAGE, 'web');
            if (! $user->hasPermissionTo(CapabilityCatalog::REGISTRAR_SETUP_MANAGE)) {
                $user->givePermissionTo(CapabilityCatalog::REGISTRAR_SETUP_MANAGE);
            }
            $ownedPermissions = $user->getAllPermissions()->pluck('name')->sort()->values()->all();
            if ($user->name !== self::WRITER_NAME
                || ! Hash::check($password, (string) $user->password)
                || $profile === null
                || (int) $profile->institution_id !== (int) $institution->getKey()
                || $profile->office !== 'registrar'
                || $profile->active_cycle_code !== $cycle->code
                || $profile->program_codes !== []
                || ! $profile->isActive()
                || $profile->deactivated_at !== null
                || $user->applicantAccount()->exists()
                || $user->roles()->exists()
                || $ownedPermissions !== $permissions
                || $auth === null
                || $auth->activation_completed_at === null
                || $auth->password_set_at === null
                || $auth->must_change_password
                || (int) $auth->session_version !== 1
                || $auth->mfa_required) {
                throw new RuntimeException('Owned checklist writer principal changed; no password, grant, profile, or authentication repair was performed.');
            }

            return $user;
        }

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user = User::query()->create([
            'email' => $email,
            'name' => self::WRITER_NAME,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
        StaffProfile::query()->create([
            'user_id' => $user->getKey(),
            'institution_id' => $institution->getKey(),
            'office' => 'registrar',
            'active_cycle_code' => $cycle->code,
            'program_codes' => [],
            'status' => StaffProfile::STATUS_ACTIVE,
        ]);
        StaffAuthenticationState::query()->create([
            'user_id' => $user->getKey(),
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'must_change_password' => false,
            'session_version' => 1,
            'mfa_required' => false,
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function readOnlyRegistrar(Institution $institution, AcademicCycle $cycle): User
    {
        $email = self::STAFF_EMAIL;
        $password = self::STAFF_PASSWORD;
        $user = User::query()->where('email', $email)->first();
        if ($user !== null) {
            $profile = $user->staffProfile;
            if ($user->name !== 'Synthetic Admission Checklist Read-Only Registrar'
                || ! Hash::check($password, (string) $user->password)
                || $profile === null
                || (int) $profile->institution_id !== (int) $institution->getKey()
                || $profile->office !== 'registrar'
                || $profile->active_cycle_code !== $cycle->code
                || $profile->program_codes !== []
                || ! $profile->isActive()
                || $profile->deactivated_at !== null
                || $user->applicantAccount()->exists()
                || ! $user->hasPermissionTo(CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)
                || $user->hasPermissionTo(CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY)
                || ! StaffAuthenticationState::query()->where('user_id', $user->getKey())->exists()) {
                throw new RuntimeException('Owned checklist read-only staff account changed; no password/grant/profile reset was performed.');
            }

            return $user;
        }

        Permission::query()->where('name', CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)->where('guard_name', 'web')->firstOrFail();
        $user = User::query()->create([
            'email' => $email,
            'name' => 'Synthetic Admission Checklist Read-Only Registrar',
            'password' => $password,
            'email_verified_at' => now(),
        ]);
        StaffProfile::query()->create([
            'user_id' => $user->getKey(),
            'institution_id' => $institution->getKey(),
            'office' => 'registrar',
            'active_cycle_code' => $cycle->code,
            'program_codes' => [],
            'status' => StaffProfile::STATUS_ACTIVE,
        ]);
        StaffAuthenticationState::query()->create([
            'user_id' => $user->getKey(),
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'must_change_password' => false,
            'session_version' => 1,
            'mfa_required' => false,
        ]);
        $user->givePermissionTo(CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW);

        return $user;
    }
}
