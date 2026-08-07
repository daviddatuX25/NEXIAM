<?php

namespace App\Admission\Actions;

use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Exceptions\DuplicateAdmissionAttemptException;
use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\NormalizedIdentityKey;
use App\Admission\Support\ReferenceCodeGenerator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Release\DispatchNotificationOutboxEntry;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeGradeField;
use App\Models\Foundation\PrivacyPolicy;
use App\Models\Admission\AdmissionAttemptGradeValue;
use App\Models\Release\NotificationOutbox;
use Illuminate\Support\Facades\DB;

class SubmitAdmissionAttempt
{
    public function __construct(
        private readonly ReferenceCodeGenerator $referenceCodeGenerator,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly IssueApplicantAccessChallenge $issueChallenge,
    ) {
    }

    /**
     * @return array{attempt: AdmissionAttempt, access_token: string}
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

        $outboxId = null;

        $result = DB::transaction(function () use (
            $institution,
            $cycle,
            $window,
            $intake,
            $normalizedKey,
            $actor,
            &$outboxId,
        ): array {
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
                ->first();

            if ($existing !== null) {
                throw new DuplicateAdmissionAttemptException('An admission attempt already exists for this cycle.');
            }

            $reference = $this->referenceCodeGenerator->next($cycle);
            $prefs = $intake->programPreferenceCodes;
            $privacyPolicyId = $intake->privacyPolicyId ?? PrivacyPolicy::active()?->id;

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
                'gwa' => $intake->gwa(),
                'privacy_policy_id' => $privacyPolicyId,
                'contact_email' => $intake->contactEmail,
                'contact_phone' => $intake->contactPhone,
                'address_line' => $intake->addressLine,
                'city' => $intake->city,
                'province' => $intake->province,
                'zip_code' => $intake->zipCode,
                'payload' => [
                    'terms_accepted' => true,
                    'intake_version' => 'e2-apply-v3',
                    'privacy_policy_id' => $privacyPolicyId,
                ],
            ]);

            $this->persistGradeValues($attempt, $intake->grades);

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

            $outbox = NotificationOutbox::query()->create([
                'source_type' => AdmissionAttempt::class,
                'source_id' => $attempt->id,
                'recipient_type' => 'email',
                'recipient_id' => 0,
                'channel' => 'mail',
                'payload' => [
                    'template' => 'admission.submitted',
                    'email' => $intake->contactEmail,
                    'reference_code' => $attempt->reference_code,
                    'access_key' => $token,
                ],
                'status' => NotificationOutbox::STATUS_PENDING,
                'idempotency_key' => 'notify-submit-'.$attempt->id,
            ]);

            $outboxId = $outbox->id;

            return [
                'attempt' => $attempt->fresh(),
                'access_token' => $token,
            ];
        });

        if ($outboxId !== null) {
            DispatchNotificationOutboxEntry::dispatchSync($outboxId);
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $grades
     */
    private function persistGradeValues(AdmissionAttempt $attempt, array $grades): void
    {
        if ($grades === []) {
            return;
        }

        $fields = IntakeGradeField::activeForInstitution((int) $attempt->institution_id)
            ->keyBy('code');

        foreach ($grades as $code => $value) {
            $field = $fields->get($code);
            if ($field === null) {
                continue;
            }

            AdmissionAttemptGradeValue::query()->create([
                'admission_attempt_id' => $attempt->id,
                'intake_grade_field_id' => $field->id,
                'field_code' => $field->code,
                'field_label' => $field->label,
                'value' => $value,
            ]);
        }
    }
}
