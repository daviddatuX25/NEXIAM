<?php

namespace App\Testing\Actions;

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\AttemptProgramFacts;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicantOfficeInquiryDemoActor;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Testing\PracticeFixtureSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class RegisterPracticeInquiryActors
{
    public function __construct(
        private readonly ApplicantOfficeInquiryAccess $access,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    /** @return array{user_id:int,applicant_identity_id:int} */
    public function bootstrap(PracticeFixtureSet $selected, ApplicantIdentity $selectedIdentity, string $accessKey, string $birthdate): array
    {
        return DB::transaction(function () use ($selected, $selectedIdentity, $accessKey, $birthdate): array {
            $set = PracticeFixtureSet::query()->whereKey($selected->id)->lockForUpdate()->firstOrFail();
            $identity = ApplicantIdentity::query()->whereKey($selectedIdentity->id)->lockForUpdate()->firstOrFail();
            if (! app()->environment(['local', 'testing']) || $set->status !== 'active'
                || (string) $identity->practice_fixture_set_id !== (string) $set->id
                || (int) $identity->institution_id !== (int) $set->institution_id
                || $identity->applicantAccount()->exists()
                || ! is_string($identity->email) || ! filter_var($identity->email, FILTER_VALIDATE_EMAIL)
                || ! str_ends_with(strtolower($identity->email), '.test')
                || User::query()->where('email', $identity->email)->exists()) {
                throw ValidationException::withMessages(['inquiry' => 'Only an owned fresh identity with no existing portal account or user may bootstrap inquiry authority. Existing accounts are never repaired.']);
            }
            foreach (['registrar', 'guidance'] as $office) {
                $this->staff($set->actor_bindings, $office, (int) $set->institution_id, (int) $set->academic_cycle_id);
            }
            $challenge = ApplicantAccessChallenge::query()->where('applicant_identity_id', $identity->id)
                ->where('token_hash', ApplicantAccessKey::hash($accessKey))->whereNull('consumed_at')
                ->where('expires_at', '>', now())->whereHas('admissionAttempt', fn ($query) => $query
                ->where('practice_fixture_set_id', $set->id)->where('institution_id', $set->institution_id)
                ->where('academic_cycle_id', $set->academic_cycle_id)->where('applicant_identity_id', $identity->id)
                ->whereNull('archived_at')->whereIn('status', [AdmissionAttempt::STATUS_PENDING, AdmissionAttempt::STATUS_NEEDS_CORRECTION, AdmissionAttempt::STATUS_ACCEPTED]))
                ->lockForUpdate()->first();
            if ($challenge === null || AttemptProgramFacts::isEnrolled($challenge->admissionAttempt)) {
                throw ValidationException::withMessages(['inquiry' => 'The access challenge does not belong to the exact current eligible owned practice applicant.']);
            }
            $user = app(AuthenticateApplicantWithAccessKey::class)->handle($accessKey, $birthdate);
            if ($user->staffProfile()->exists()
                || (int) $user->applicantAccount()->value('applicant_identity_id') !== (int) $identity->id
                || ApplicantOfficeInquiryDemoActor::query()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['inquiry' => 'The native bootstrap did not create an unregistered owned applicant account.']);
            }
            ApplicantOfficeInquiryDemoActor::query()->create([
                'user_id' => $user->id, 'namespace' => ApplicantOfficeInquiryDemoActor::NAMESPACE,
                'institution_id' => $set->institution_id, 'actor_type' => ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT,
            ]);
            $user->givePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);

            return ['user_id' => (int) $user->id, 'applicant_identity_id' => (int) $identity->id];
        });
    }

    /** @param array<string,int> $bindings
     * @return list<array{key:string,label:string,status:string,reason:string}>
     */
    public function prerequisitesFor(array $bindings, int $institutionId, int $cycleId): array
    {
        $checks = [];
        foreach (['registrar', 'guidance'] as $office) {
            try {
                $this->staff($bindings, $office, $institutionId, $cycleId);
                $checks[] = ['key' => 'inquiry-'.$office, 'label' => ucfirst($office).' inquiry counterpart', 'status' => 'available',
                    'reason' => 'The exact assigned counterpart is registered and currently permitted by the native inquiry boundary.'];
            } catch (ValidationException $exception) {
                $checks[] = ['key' => 'inquiry-'.$office, 'label' => ucfirst($office).' inquiry counterpart', 'status' => 'missing',
                    'reason' => collect($exception->errors())->flatten()->first()];
            }
        }

        return $checks;
    }

    public function staffEligible(User $user, string $office, int $institutionId, int $cycleId): bool
    {
        try {
            $this->staff([$office => (int) $user->id], $office, $institutionId, $cycleId);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /** @param array<string,int> $bindings */
    private function staff(array $bindings, string $office, int $institutionId, int $cycleId): User
    {
        $user = User::query()->find($bindings[$office] ?? null);
        try {
            $fresh = $user === null ? null : $this->access->actor($user);
            if ($fresh === null || $fresh->staffProfile?->office !== $office
                || (int) $fresh->staffProfile->institution_id !== $institutionId
                || ! AcademicCycle::query()->whereKey($cycleId)
                    ->where('institution_id', $institutionId)->where('is_active', true)
                    ->where('code', $fresh->staffProfile->active_cycle_code)->exists()) {
                throw new RuntimeException('office_inquiry_destination_unavailable');
            }

            return $fresh;
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['inquiry' => 'Assigned '.$office.' must have current inquiry capability and institution/cycle scope.']);
        }
    }

    /** @return array<string,mixed> */
    public function register(PracticeFixtureSet $selected): array
    {
        return DB::transaction(function () use ($selected): array {
            $set = PracticeFixtureSet::query()->whereKey($selected->id)->lockForUpdate()->firstOrFail();
            if (! app()->environment(['local', 'testing']) || $set->status !== 'active') {
                throw ValidationException::withMessages(['inquiry' => 'Inquiry registration requires an active practice set in local/testing.']);
            }
            $staff = [];
            foreach (['registrar', 'guidance'] as $office) {
                $staff[$office] = (int) $this->staff($set->actor_bindings, $office, (int) $set->institution_id, (int) $set->academic_cycle_id)->id;
            }
            $attempts = AdmissionAttempt::query()->with('identity.applicantAccount.user')
                ->where('practice_fixture_set_id', $set->id)->where('institution_id', $set->institution_id)
                ->where('academic_cycle_id', $set->academic_cycle_id)->lockForUpdate()->orderBy('id')->get();
            if ($attempts->isEmpty()) {
                throw ValidationException::withMessages(['inquiry' => 'The set has no fresh owned applicant case to register.']);
            }
            $applicants = [];
            foreach ($attempts as $attempt) {
                $identity = $attempt->identity;
                if ($identity !== null && $identity->applicantAccount === null) {
                    $credential = $set->credentials[(string) $identity->id] ?? null;
                    if (! is_array($credential) || (int) ($credential['admission_attempt_id'] ?? 0) !== (int) $attempt->id) {
                        throw ValidationException::withMessages(['inquiry' => 'The owned applicant has no exact stored bootstrap credentials for this case.']);
                    }
                    $this->bootstrap($set, $identity, (string) ($credential['access_key'] ?? ''), (string) ($credential['birthdate'] ?? ''));
                    $identity->load('applicantAccount.user');
                }
                $user = $identity?->applicantAccount?->user;
                if ($identity === null || (string) $identity->practice_fixture_set_id !== (string) $set->id
                    || (int) $identity->institution_id !== (int) $set->institution_id
                    || $user === null || $user->staffProfile()->exists()
                    || $attempt->archived_at !== null || AttemptProgramFacts::isEnrolled($attempt)
                    || ! in_array($attempt->status, [AdmissionAttempt::STATUS_PENDING, AdmissionAttempt::STATUS_NEEDS_CORRECTION, AdmissionAttempt::STATUS_ACCEPTED], true)) {
                    throw ValidationException::withMessages(['inquiry' => 'Inquiry registration requires the actual owned applicant account and an eligible current admission case.']);
                }
                $decision = $this->authorizer->decideFresh($user, CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);
                if (! $decision->allowed) {
                    throw ValidationException::withMessages(['inquiry' => 'Existing applicant inquiry capability: '.$decision->reason.'. Preparation never restores missing or revoked authority.']);
                }
                $existing = ApplicantOfficeInquiryDemoActor::query()->where('user_id', $user->id)
                    ->where('namespace', ApplicantOfficeInquiryDemoActor::NAMESPACE)->lockForUpdate()->first();
                if ($existing !== null && ($existing->actor_type !== ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT
                    || (int) $existing->institution_id !== (int) $set->institution_id)) {
                    throw ValidationException::withMessages(['inquiry' => 'The applicant inquiry registry entry has a conflicting institution or actor type.']);
                }
                ApplicantOfficeInquiryDemoActor::query()->updateOrCreate([
                    'user_id' => $user->id, 'namespace' => ApplicantOfficeInquiryDemoActor::NAMESPACE,
                ], ['institution_id' => $set->institution_id, 'actor_type' => ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT]);
                foreach (array_keys($staff) as $office) {
                    $permitted = $this->access->assertMayCreate($user, $office);
                    if ((int) $permitted->id !== (int) $attempt->id) {
                        throw ValidationException::withMessages(['inquiry' => 'The native inquiry boundary selected another case. No registration was committed.']);
                    }
                }
                $applicants[] = ['user_id' => (int) $user->id, 'applicant_identity_id' => (int) $identity->id, 'admission_attempt_id' => (int) $attempt->id];
            }

            return ['status' => 'available', 'applicants' => $applicants, 'staff' => $staff,
                'registered_count' => count($applicants),
                'url' => route('applicant.inquiries.index', ['practice_set' => $set->id], absolute: false),
                'reason' => 'Owned applicant accounts are registered for ordinary inquiries to their existing eligible practice office counterparts. No inquiry, message, grant or delivery was fabricated.'];
        });
    }
}
