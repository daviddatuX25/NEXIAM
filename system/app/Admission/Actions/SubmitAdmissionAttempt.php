<?php

namespace App\Admission\Actions;

use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Exceptions\DuplicateAdmissionAttemptException;
use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\GwaScaleNormalizer;
use App\Admission\Support\NormalizedIdentityKey;
use App\Admission\Support\ReferenceCodeGenerator;
use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use App\Testing\PracticeWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitAdmissionAttempt
{
    public function __construct(
        private readonly ReferenceCodeGenerator $referenceCodeGenerator,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly IssueApplicantAccessChallenge $issueChallenge,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly NotificationIntentDispatcher $notificationDispatcher,
    ) {}

    /**
     * @return array{attempt: AdmissionAttempt, access_token: string, credential_handoff: array<string, mixed>}
     */
    public function handle(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        AdmissionIntakeData $intake,
        string $actor = 'public',
    ): array {
        if (! $intake->termsAccepted) {
            throw new \InvalidArgumentException('Terms must be accepted before submission.');
        }

        $normalizedKey = NormalizedIdentityKey::make(
            $intake->firstName,
            $intake->lastName,
            $intake->birthdate,
            $intake->sex,
            $intake->middleName,
        );
        $workspace = app(PracticeWorkspace::class);
        $workspace->assertWritable();
        $normalizedKey = $workspace->normalizedKey($normalizedKey);

        $createdDeliveries = collect();
        $result = DB::transaction(function () use (
            $institution,
            $cycle,
            $window,
            $intake,
            $normalizedKey,
            $actor,
            &$createdDeliveries,
        ): array {
            $currentInstitution = Institution::query()
                ->where('code', 'SYN-INST-001')
                ->lockForUpdate()
                ->firstOrFail();

            if ($institution->id !== $currentInstitution->id) {
                throw ValidationException::withMessages(['academic_cycle_id' => 'This admission period is not available.']);
            }

            $cycle = AcademicCycle::query()->lockForUpdate()->find($cycle->id);
            $today = now()->toDateString();
            if ($cycle === null || $cycle->institution_id !== $currentInstitution->id
                || ! $cycle->is_active || $cycle->opens_on?->toDateString() === null
                || $cycle->closes_on?->toDateString() === null
                || $cycle->opens_on->toDateString() > $today
                || $cycle->closes_on->toDateString() < $today) {
                throw ValidationException::withMessages(['academic_cycle_id' => 'This admission period is no longer open.']);
            }

            $window = ApplicationWindow::query()->lockForUpdate()->find($window->id);
            $now = now();
            if ($window === null || $window->academic_cycle_id !== $cycle->id
                || $window->status !== 'published'
                || $window->opens_at === null || $window->closes_at === null
                || $window->opens_at->gt($now) || $window->closes_at->lt($now)) {
                throw ValidationException::withMessages(['application_window_id' => 'This application window is no longer open.']);
            }

            $offeredProgramCount = Program::query()
                ->where('institution_id', $currentInstitution->id)
                ->where('is_active', true)
                ->where('is_admission_offered', true)
                ->whereIn('code', $intake->programPreferenceCodes)
                ->count();
            if ($offeredProgramCount !== count(array_unique($intake->programPreferenceCodes))) {
                throw ValidationException::withMessages([
                    'program_preference_1' => 'Choose programs currently offered for admission.',
                ]);
            }

            $policy = PublicPolicy::designatedIntake((int) $currentInstitution->getKey(), lockForUpdate: true);
            if ($policy === null || (int) $policy->getKey() !== $intake->publicPolicyId) {
                throw ValidationException::withMessages(['public_policy_id' => 'The designated privacy policy changed. Reload the form and review it again.']);
            }

            $publishedRevision = PublicPolicyRevision::query()
                ->whereKey($policy->published_revision_id)
                ->where('public_policy_id', $policy->getKey())
                ->where('institution_id', $currentInstitution->getKey())
                ->where('state', 'published')
                ->lockForUpdate()
                ->first();
            if ($publishedRevision === null || (int) $publishedRevision->getKey() !== $intake->publicPolicyRevisionId) {
                throw ValidationException::withMessages(['public_policy_revision_id' => 'The published privacy policy revision changed. Reload the form and review it again.']);
            }

            $policyDigest = $publishedRevision->contentDigest();
            if ($intake->publicPolicyRevisionDigest === null
                || ! hash_equals($policyDigest, $intake->publicPolicyRevisionDigest)) {
                throw ValidationException::withMessages(['public_policy_revision_digest' => 'The published privacy policy text changed. Reload the form and review it again.']);
            }

            $policyId = (int) $policy->getKey();
            $policyRevisionId = (int) $publishedRevision->getKey();
            $policyRevisionNumber = (int) $publishedRevision->revision;

            $identity = ApplicantIdentity::query()->firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'normalized_key' => $normalizedKey,
                ],
                [
                    'first_name' => $intake->firstName,
                    'middle_name' => $intake->middleName,
                    'last_name' => $intake->lastName,
                    'suffix' => $intake->suffix,
                    'birthdate' => $intake->birthdate,
                    'sex' => $intake->sex,
                    'email' => $intake->contactEmail,
                    'phone' => $intake->contactPhone,
                ],
            );

            $identity->fill([
                'suffix' => $intake->suffix,
                'email' => $intake->contactEmail,
                'phone' => $intake->contactPhone,
            ])->save();

            $existing = AdmissionAttempt::query()
                ->where('applicant_identity_id', $identity->id)
                ->where('academic_cycle_id', $cycle->id)
                ->with(['academicCycle', 'identity'])
                ->first();

            if ($existing !== null) {
                throw new DuplicateAdmissionAttemptException('An admission attempt already exists for this cycle.', $existing);
            }

            $reference = $this->referenceCodeGenerator->next($cycle);
            $prefs = $intake->programPreferenceCodes;
            $gwaMeta = $intake->gwa !== null
                ? GwaScaleNormalizer::normalize($intake->gwa, $intake->gwaScale)
                : null;

            $attempt = AdmissionAttempt::query()->create([
                'applicant_identity_id' => $identity->id,
                'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id,
                'application_window_id' => $window->id,
                'reference_code' => $reference,
                'status' => AdmissionAttempt::STATUS_PENDING,
                'preferred_program_code' => $intake->primaryProgramCode(),
                'program_preference_1_code' => $prefs[0] ?? null,
                'program_preference_2_code' => $prefs[1] ?? null,
                'program_preference_3_code' => $prefs[2] ?? null,
                'applicant_type' => $intake->applicantType,
                'last_school_enrolled' => $intake->lastSchoolEnrolled,
                'strand' => $intake->strand,
                'gwa' => $gwaMeta['normalized'] ?? null,
                'privacy_policy_id' => null,
                'public_policy_id' => $policyId,
                'contact_email' => $intake->contactEmail,
                'contact_phone' => $intake->contactPhone,
                'address_line' => $intake->addressLine,
                'city' => $intake->city,
                'province' => $intake->province,
                'zip_code' => $intake->zipCode,
                'payload' => array_filter([
                    'terms_accepted' => true,
                    'intake_version' => 'e2-apply-v3',
                    'gwa_meta' => $gwaMeta,
                    'consent' => [
                        'source' => 'public_policy',
                        'policy_id' => $policyId,
                        'revision_id' => $policyRevisionId,
                        'revision' => $policyRevisionNumber,
                        'digest' => $policyDigest,
                    ],
                ], static fn ($v) => $v !== null),
            ]);
            app(EnsureAttemptRequirementChecklist::class)->handle($attempt);
            app(ClaimPreApplicationInquiries::class)->claimForAttempt($attempt);

            $this->recordAuditEvent->handle('admission.attempt_submitted', [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'identity_id' => $identity->id,
                'program_preference_1' => $attempt->program_preference_1_code,
            ], $actor);

            // D-085: paper-friendly access key (hash only here; plaintext returned once + emailed).
            $token = ApplicantAccessKey::mint();
            $this->issueChallenge->handle(
                $identity,
                $attempt,
                ApplicantAccessKey::hash($token),
                'access_key',
                'submit-'.$attempt->id,
            );
            $handoff = $this->issueChallenge->issueHandoffBundle(
                $identity,
                $attempt,
                $token,
                'submitted',
                'submit',
            );

            $admissionPolicy = AdmissionNotificationPolicy::fromStorage($cycle->admission_notification_policy);

            $applicantIntent = NotificationIntent::v1(
                eventType: 'admission.submitted.v1',
                templateKey: 'admission.submitted',
                payload: [
                    ...AdmissionExaminationNoticeContext::admissionPayload($attempt, 'submitted'),
                    'action_label' => 'Open applicant portal',
                    'action_url' => AdmissionExaminationNoticeContext::url('applicant.portal'),
                    'attempt_id' => $attempt->id,
                    'reference_code' => $attempt->reference_code,
                ],
                channels: [NotificationDelivery::CHANNEL_IN_APP],
            );
            $createdDeliveries = $createdDeliveries->concat($this->notificationDispatcher->dispatch(
                $applicantIntent,
                hash('sha256', "admission.submitted:{$attempt->id}:v{$attempt->version}"),
                [$identity],
                $applicantIntent->channels,
            ));

            if ($admissionPolicy->registrarNewSubmissionAlertEnabled) {
                $registrarRecipients = NotificationRecipients::registrarApplicationViewersFor($identity, $this->authorizer);

                if ($registrarRecipients->isNotEmpty()) {
                    $staffIntent = NotificationIntent::v1(
                        eventType: 'admission.new_submission.v1',
                        templateKey: 'admission.new_submission',
                        payload: [
                            ...AdmissionExaminationNoticeContext::admissionPayload($attempt, 'new-submission'),
                            'action_label' => 'Open admissions queue',
                            'action_url' => AdmissionExaminationNoticeContext::url('admissions.index'),
                            'attempt_id' => $attempt->id,
                            'reference_code' => $attempt->reference_code,
                        ],
                        channels: [NotificationDelivery::CHANNEL_IN_APP],
                    );
                    $createdDeliveries = $createdDeliveries->concat($this->notificationDispatcher->dispatch(
                        $staffIntent,
                        hash('sha256', "admission.new_submission:{$attempt->id}:v1"),
                        $registrarRecipients,
                        $staffIntent->channels,
                    ));
                }
            }

            return [
                'attempt' => $attempt->fresh(),
                'access_token' => $token,
                'credential_handoff' => $handoff,
            ];
        });
        NotificationIntentDispatcher::enqueueAfterCommit($createdDeliveries);

        return $result;
    }
}
