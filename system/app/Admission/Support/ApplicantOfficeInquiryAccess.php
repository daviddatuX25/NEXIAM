<?php

namespace App\Admission\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccount;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ApplicantOfficeInquiryDemoActor;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ApplicantOfficeInquiryAccess
{
    public const DEMO_NAMESPACE = 'applicant-office-inquiry-demo';

    public const DEVICE_COOKIE = 'nexiam_inquiry_device_token';

    public const DEVICE_HEADER = 'X-Inquiry-Device-Token';

    public const MAX_OPEN_PRE_APPLICATION_INQUIRIES = 2;

    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /**
     * @return array{available: bool, reason: string, message: string}
     */
    public function availability(?User $actor): array
    {
        if ($actor !== null) {
            try {
                $fresh = $this->actor($actor);
                if ($fresh->getRelation('officeInquiryDemoActor')->actor_type === 'applicant') {
                    $this->applicantAttempt($fresh);
                }

                return ['available' => true, 'reason' => 'available', 'message' => 'Office inquiries are available for this account.'];
            } catch (RuntimeException) {
            }
        }

        return ['available' => false, 'reason' => 'unavailable', 'message' => 'Office inquiries are unavailable for this account.'];
    }

    public function isAvailable(?User $actor): bool
    {
        return $this->availability($actor)['available'];
    }

    public function actor(User $actor): User
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('office_inquiry_not_found');
        }

        $fresh = User::query()->with(['applicantAccount', 'staffProfile'])->find($actor->getKey());
        if ($fresh === null || ($fresh->applicantAccount !== null && $fresh->staffProfile !== null)
            || ($fresh->applicantAccount === null && $fresh->staffProfile === null)) {
            throw new RuntimeException('office_inquiry_not_found');
        }

        if ($fresh->applicantAccount !== null) {
            $identity = ApplicantIdentity::query()->find($fresh->applicantAccount->applicant_identity_id);
            if ($identity === null || (int) $identity->institution_id <= 0
                || ! $this->authorizer->decideFresh($fresh, CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE)->allowed) {
                throw new RuntimeException('office_inquiry_not_found');
            }

            $fresh->setRelation('officeInquiryDemoActor', new ApplicantOfficeInquiryDemoActor([
                'user_id' => $fresh->id,
                'institution_id' => (int) $identity->institution_id,
                'actor_type' => ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT,
                'namespace' => self::DEMO_NAMESPACE,
            ]));

            return $fresh;
        }

        $profile = $fresh->staffProfile;
        if (! $profile->isActive() || $profile->deactivated_at !== null
            || ! in_array($profile->office, ['registrar', 'guidance'], true)
            || (int) $profile->institution_id <= 0) {
            throw new RuntimeException('office_inquiry_not_found');
        }

        $cycle = AcademicCycle::query()->where('institution_id', $profile->institution_id)
            ->where('code', $profile->active_cycle_code)->where('is_active', true)->first();
        if ($cycle === null || ! $this->authorizer->decideFresh($fresh, CapabilityCatalog::OFFICE_INQUIRY_HANDLE, [
            'institution_id' => $profile->institution_id,
            'office' => $profile->office,
            'cycle' => $cycle->code,
        ])->allowed) {
            throw new RuntimeException('office_inquiry_not_found');
        }

        $fresh->setRelation('officeInquiryDemoActor', new ApplicantOfficeInquiryDemoActor([
            'user_id' => $fresh->id,
            'institution_id' => (int) $profile->institution_id,
            'actor_type' => ApplicantOfficeInquiryDemoActor::TYPE_STAFF,
            'namespace' => self::DEMO_NAMESPACE,
        ]));

        return $fresh;
    }

    public function actorType(User $actor): string
    {
        return (string) $this->actor($actor)->getRelation('officeInquiryDemoActor')->actor_type;
    }

    public function isApplicant(User $actor): bool
    {
        return $this->actorType($actor) === ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT;
    }

    public function isStaff(User $actor): bool
    {
        return $this->actorType($actor) === ApplicantOfficeInquiryDemoActor::TYPE_STAFF;
    }

    public function applicantAttempt(User $actor): AdmissionAttempt
    {
        $fresh = $this->actor($actor);
        $marker = $fresh->getRelation('officeInquiryDemoActor');
        if ($marker->actor_type !== 'applicant' || $fresh->applicantAccount === null) {
            throw new RuntimeException('office_inquiry_not_found');
        }

        $attempt = AdmissionAttempt::query()
            ->where('applicant_identity_id', $fresh->applicantAccount->applicant_identity_id)
            ->where('institution_id', $marker->institution_id)
            ->whereHas('academicCycle', function ($query) use ($marker): void {
                $query->where('institution_id', $marker->institution_id)->where('is_active', true);
            })
            ->orderByDesc('id')
            ->first();

        if ($attempt === null || ! $this->attemptIsEligible($attempt)) {
            throw new RuntimeException('office_inquiry_not_found');
        }

        return $attempt;
    }

    public function assertMayCreate(User $actor, string $office): AdmissionAttempt
    {
        if (! in_array($office, ['registrar', 'guidance'], true)) {
            throw ValidationException::withMessages(['office' => 'Choose Registrar or Guidance.']);
        }

        $attempt = $this->applicantAttempt($actor);
        if ($this->officeStaffFor($attempt, $office)->isEmpty()) {
            throw new RuntimeException('office_inquiry_destination_unavailable');
        }

        return $attempt;
    }

    public function canRead(User $actor, ApplicantOfficeInquiry $inquiry): bool
    {
        try {
            $fresh = $this->actor($actor);
            $current = ApplicantOfficeInquiry::query()->find($inquiry->getKey());
            if ($current === null
                || ! in_array($current->office, ['registrar', 'guidance'], true)
                || ! in_array($current->status, ['open', 'closed'], true)) {
                return false;
            }

            $marker = $fresh->getRelation('officeInquiryDemoActor');
            if ((string) $marker->institution_id !== (string) $current->institution_id) {
                return false;
            }

            if ($current->admission_attempt_id === null) {
                if ($marker->actor_type !== 'staff' || app(PracticeWorkspace::class)->currentId() !== null) {
                    return false;
                }
                $cycle = AcademicCycle::query()->find($current->academic_cycle_id);

                return $cycle !== null
                    && $cycle->is_active
                    && (string) $cycle->institution_id === (string) $current->institution_id
                    && $fresh->staffProfile->office === $current->office
                    && $fresh->staffProfile->active_cycle_code === $cycle->code
                    && $this->authorizer->decideFresh($fresh, CapabilityCatalog::OFFICE_INQUIRY_HANDLE, [
                        'institution_id' => $current->institution_id,
                        'office' => $current->office,
                        'cycle' => $cycle->code,
                    ])->allowed;
            }

            $attempt = AdmissionAttempt::query()->find($current->admission_attempt_id);
            $requireApplicantCapability = $current->created_by_user_id !== null || $marker->actor_type === 'applicant';
            if ($attempt === null
                || ! $this->attemptIsEligible($attempt, $requireApplicantCapability)
                || (string) $current->institution_id !== (string) $attempt->institution_id
                || (string) $current->academic_cycle_id !== (string) $attempt->academic_cycle_id
                || (string) $current->applicant_identity_id !== (string) $attempt->applicant_identity_id) {
                return false;
            }

            if ($marker->actor_type === 'applicant') {
                $ownAttempt = $this->applicantAttempt($fresh);

                return (string) $ownAttempt->getKey() === (string) $attempt->getKey()
                    && (string) $fresh->applicantAccount->applicant_identity_id === (string) $current->applicant_identity_id;
            }

            if ($attempt->practice_fixture_set_id !== null) {
                $set = PracticeFixtureSet::query()->find($attempt->practice_fixture_set_id);
                if ($set === null || (int) ($set->actor_bindings[$current->office] ?? 0) !== (int) $fresh->id) {
                    return false;
                }
            }
            $cycle = AcademicCycle::query()->find($attempt->academic_cycle_id);

            return $fresh->staffProfile->office === $current->office
                && $cycle !== null
                && $fresh->staffProfile->active_cycle_code === $cycle->code
                && $this->authorizer->decideFresh($fresh, CapabilityCatalog::OFFICE_INQUIRY_HANDLE, [
                    'institution_id' => $current->institution_id,
                    'office' => $current->office,
                    'cycle' => $cycle->code,
                ])->allowed;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function assertRead(User $actor, ApplicantOfficeInquiry $inquiry): void
    {
        if (! $this->canRead($actor, $inquiry)) {
            throw new RuntimeException('office_inquiry_not_found');
        }
    }

    public function assertSend(User $actor, ApplicantOfficeInquiry $inquiry): void
    {
        $this->assertRead($actor, $inquiry);
        if (ApplicantOfficeInquiry::query()->whereKey($inquiry->getKey())->value('status') !== 'open') {
            throw new RuntimeException('office_inquiry_closed');
        }
    }

    public function assertResolve(User $actor, ApplicantOfficeInquiry $inquiry): void
    {
        $fresh = $this->actor($actor);
        if ($fresh->getRelation('officeInquiryDemoActor')->actor_type !== 'staff') {
            throw new RuntimeException('office_inquiry_not_found');
        }

        $this->assertSend($fresh, $inquiry);
    }

    public function normalizeSubject(string $subject): string
    {
        return $this->normalizeText($subject, 'subject', 120, 480);
    }

    public function normalizeMessage(string $body): string
    {
        return $this->normalizeText($body, 'body', 4000, 16384);
    }

    public function validateOperationKey(string $key): void
    {
        Validator::make(['idempotency_key' => $key], ['idempotency_key' => ['required', 'string', 'uuid']])->validate();
    }

    /**
     * @return Collection<int, User>
     */
    public function officeStaffFor(AdmissionAttempt $attempt, string $office): Collection
    {
        if (! app()->environment(['local', 'testing']) || ! in_array($office, ['registrar', 'guidance'], true)) {
            return collect();
        }

        $currentAttempt = AdmissionAttempt::query()->find($attempt->getKey());
        if ($currentAttempt === null || ! $this->attemptIsEligible($currentAttempt)) {
            return collect();
        }

        $cycle = AcademicCycle::query()->find($currentAttempt->academic_cycle_id);
        $candidates = User::query()
            ->whereDoesntHave('applicantAccount')
            ->whereHas('staffProfile', function ($query) use ($office, $currentAttempt, $cycle): void {
                $query->where('office', $office)
                    ->where('institution_id', $currentAttempt->institution_id)
                    ->where('status', 'active')
                    ->whereNull('deactivated_at')
                    ->where('active_cycle_code', $cycle->code);
            })
            ->orderBy('id')
            ->get();
        if ($currentAttempt->practice_fixture_set_id !== null) {
            $set = PracticeFixtureSet::query()->find($currentAttempt->practice_fixture_set_id);
            $candidates = $candidates->where('id', $set?->actor_bindings[$office] ?? 0);
        }

        return $candidates->filter(function (User $candidate) use ($office, $currentAttempt, $cycle): bool {
            try {
                $fresh = $this->actor($candidate);

                return $fresh->getRelation('officeInquiryDemoActor')->actor_type === ApplicantOfficeInquiryDemoActor::TYPE_STAFF
                    && $fresh->staffProfile->office === $office
                    && (string) $fresh->staffProfile->institution_id === (string) $currentAttempt->institution_id
                    && $fresh->staffProfile->active_cycle_code === $cycle->code;
            } catch (RuntimeException) {
                return false;
            }
        })->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function officeStaffForCycle(int $institutionId, AcademicCycle $cycle, string $office): Collection
    {
        if (! app()->environment(['local', 'testing'])
            || ! in_array($office, ['registrar', 'guidance'], true)
            || ! $cycle->is_active
            || (int) $cycle->institution_id !== $institutionId) {
            return collect();
        }

        $candidates = User::query()
            ->whereDoesntHave('applicantAccount')
            ->whereHas('staffProfile', function ($query) use ($office, $institutionId, $cycle): void {
                $query->where('office', $office)
                    ->where('institution_id', $institutionId)
                    ->where('status', 'active')
                    ->whereNull('deactivated_at')
                    ->where('active_cycle_code', $cycle->code);
            })
            ->orderBy('id')
            ->get();

        return $candidates->filter(function (User $candidate) use ($office, $institutionId, $cycle): bool {
            try {
                $fresh = $this->actor($candidate);

                return $fresh->getRelation('officeInquiryDemoActor')->actor_type === ApplicantOfficeInquiryDemoActor::TYPE_STAFF
                    && $fresh->staffProfile->office === $office
                    && (int) $fresh->staffProfile->institution_id === $institutionId
                    && $fresh->staffProfile->active_cycle_code === $cycle->code;
            } catch (RuntimeException) {
                return false;
            }
        })->values();
    }

    /**
     * @return array{institution: Institution, cycle: AcademicCycle, destinations: list<array{code: string, label: string}>}|null
     */
    public function preApplicationContext(): ?array
    {
        if (! app()->environment(['local', 'testing']) || app(PracticeWorkspace::class)->currentId() !== null) {
            return null;
        }

        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        if ($institution === null) {
            return null;
        }

        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
        if ($cycle === null) {
            return null;
        }

        $destinations = [];
        foreach (['registrar' => 'Registrar', 'guidance' => 'Guidance'] as $office => $label) {
            if ($this->officeStaffForCycle((int) $institution->id, $cycle, $office)->isNotEmpty()) {
                $destinations[] = ['code' => $office, 'label' => $label];
            }
        }

        return [
            'institution' => $institution,
            'cycle' => $cycle,
            'destinations' => $destinations,
        ];
    }

    public function hashDeviceToken(string $rawToken): string
    {
        $trimmed = trim($rawToken);
        if (strlen($trimmed) < 24 || strlen($trimmed) > 256 || preg_match('/^[A-Za-z0-9_\-]+$/D', $trimmed) !== 1) {
            throw new RuntimeException('office_inquiry_not_found');
        }

        return hash('sha256', $trimmed);
    }

    public function canReadPreApplication(string $deviceTokenHash, ApplicantOfficeInquiry $inquiry): bool
    {
        if (! app()->environment(['local', 'testing'])
            || app(PracticeWorkspace::class)->currentId() !== null
            || preg_match('/^[0-9a-f]{64}$/D', $deviceTokenHash) !== 1) {
            return false;
        }

        $current = ApplicantOfficeInquiry::query()->find($inquiry->getKey());
        if ($current === null
            || $current->created_by_user_id !== null
            || ! is_string($current->guest_token_hash)
            || ! hash_equals($current->guest_token_hash, $deviceTokenHash)
            || ! in_array($current->office, ['registrar', 'guidance'], true)
            || ! in_array($current->status, ['open', 'closed'], true)) {
            return false;
        }

        $cycle = AcademicCycle::query()->find($current->academic_cycle_id);

        return $cycle !== null
            && $cycle->is_active
            && (string) $cycle->institution_id === (string) $current->institution_id;
    }

    public function assertReadPreApplication(string $deviceTokenHash, ApplicantOfficeInquiry $inquiry): void
    {
        if (! $this->canReadPreApplication($deviceTokenHash, $inquiry)) {
            throw new RuntimeException('office_inquiry_not_found');
        }
    }

    public function assertSendPreApplication(string $deviceTokenHash, ApplicantOfficeInquiry $inquiry): void
    {
        $this->assertReadPreApplication($deviceTokenHash, $inquiry);
        if (ApplicantOfficeInquiry::query()->whereKey($inquiry->getKey())->value('status') !== 'open') {
            throw new RuntimeException('office_inquiry_closed');
        }
    }

    public function nextPublicReferenceCode(AcademicCycle $cycle): string
    {
        $yearDigits = '26';
        if (is_string($cycle->academic_year) && preg_match('/20(\d{2})/', $cycle->academic_year, $matches) === 1) {
            $yearDigits = $matches[1];
        } elseif (is_string($cycle->code) && preg_match('/20(\d{2})/', $cycle->code, $matches) === 1) {
            $yearDigits = $matches[1];
        }

        $prefix = "INQ-{$yearDigits}-";
        $latest = ApplicantOfficeInquiry::query()
            ->where('public_reference_code', 'like', $prefix.'%')
            ->orderByDesc('public_reference_code')
            ->value('public_reference_code');

        $sequence = 1;
        if (is_string($latest) && preg_match('/^'.preg_quote($prefix, '/').'(\d{5})$/D', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = sprintf('%s%05d', $prefix, $sequence + $attempt);
            if (! ApplicantOfficeInquiry::query()->where('public_reference_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to allocate a unique public inquiry reference code.');
    }

    private function attemptIsEligible(AdmissionAttempt $attempt, bool $requireApplicantCapability = true): bool
    {
        if (! in_array($attempt->status, [
            AdmissionAttempt::STATUS_PENDING,
            AdmissionAttempt::STATUS_NEEDS_CORRECTION,
            AdmissionAttempt::STATUS_ACCEPTED,
        ], true) || $attempt->archived_at !== null || AttemptProgramFacts::isEnrolled($attempt)) {
            return false;
        }

        $cycle = AcademicCycle::query()->find($attempt->academic_cycle_id);
        $identity = ApplicantIdentity::query()->find($attempt->applicant_identity_id);
        if ($cycle === null || ! $cycle->is_active || $identity === null
            || (string) $cycle->institution_id !== (string) $attempt->institution_id
            || (string) $identity->institution_id !== (string) $attempt->institution_id) {
            return false;
        }
        if ($attempt->practice_fixture_set_id !== null) {
            $set = PracticeFixtureSet::query()->find($attempt->practice_fixture_set_id);
            if ($set === null || $set->status !== 'active'
                || (int) $set->institution_id !== (int) $attempt->institution_id
                || (int) $set->academic_cycle_id !== (int) $attempt->academic_cycle_id
                || (string) $identity->practice_fixture_set_id !== (string) $set->id) {
                return false;
            }
        } elseif ($identity->practice_fixture_set_id !== null) {
            return false;
        }

        if (! $requireApplicantCapability) {
            return true;
        }

        return ApplicantAccount::query()
            ->where('applicant_identity_id', $identity->getKey())
            ->whereHas('user', fn ($query) => $query->whereDoesntHave('staffProfile'))
            ->with('user')
            ->get()
            ->contains(fn (ApplicantAccount $account): bool => $account->user !== null
                && $this->authorizer->decideFresh($account->user, CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE)->allowed);
    }

    private function normalizeText(string $text, string $field, int $maxCharacters, int $maxBytes): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            throw ValidationException::withMessages([$field => 'Use valid UTF-8 plain text.']);
        }

        $normalized = trim($text);
        if ($normalized === ''
            || mb_strlen($normalized, 'UTF-8') > $maxCharacters
            || strlen($normalized) > $maxBytes
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $normalized) === 1) {
            throw ValidationException::withMessages([$field => "Use nonempty plain text of at most {$maxCharacters} characters."]);
        }

        return $normalized;
    }
}
