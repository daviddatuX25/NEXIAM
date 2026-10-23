<?php

declare(strict_types=1);

namespace App\Admission\Actions;

use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\NormalizedIdentityKey;
use App\Admission\Support\ReferenceCodeGenerator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use App\Testing\PracticeWorkspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PunchInAdmissionAttempt
{
    public function __construct(
        private readonly ReferenceCodeGenerator $referenceCodeGenerator,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly IssueApplicantAccessChallenge $issueChallenge,
        private readonly NotificationIntentDispatcher $notificationDispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{attempt: AdmissionAttempt, access_key: string, credential_handoff: array<string, mixed>}
     */
    public function handle(
        Institution $institution,
        AcademicCycle $cycle,
        ?ApplicationWindow $window,
        array $data,
        User $actor,
    ): array {
        return DB::transaction(function () use ($institution, $cycle, $window, $data, $actor): array {
            $firstName = trim((string) ($data['first_name'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? ''));
            $middleName = filled($data['middle_name'] ?? null) ? trim((string) $data['middle_name']) : null;
            $suffix = filled($data['suffix'] ?? null) ? trim((string) $data['suffix']) : null;
            $birthdate = Carbon::parse($data['birthdate'])->format('Y-m-d');
            $sex = strtolower((string) ($data['sex'] ?? ''));

            $program1 = (string) ($data['program_preference_1_code'] ?? '');
            $program2 = filled($data['program_preference_2_code'] ?? null) ? (string) $data['program_preference_2_code'] : null;
            $program3 = filled($data['program_preference_3_code'] ?? null) ? (string) $data['program_preference_3_code'] : null;

            $programCodes = array_values(array_filter([$program1, $program2, $program3], static fn (?string $c): bool => filled($c)));
            $offeredProgramsCount = Program::query()
                ->where('institution_id', $institution->id)
                ->whereIn('code', $programCodes)
                ->where('is_active', true)
                ->count();

            if ($program1 === '' || $offeredProgramsCount !== count(array_unique($programCodes))) {
                throw ValidationException::withMessages([
                    'program_preference_1_code' => 'Every selected program must be an active program at this institution.',
                ]);
            }

            $workspace = app(PracticeWorkspace::class);
            $workspace->assertWritable();

            $normalizedKey = $workspace->normalizedKey(
                NormalizedIdentityKey::make($firstName, $lastName, $birthdate, $sex, $middleName)
            );

            $email = filled($data['contact_email'] ?? null) ? (string) $data['contact_email'] : ($data['email'] ?? null);
            $phone = filled($data['contact_phone'] ?? null) ? (string) $data['contact_phone'] : ($data['phone'] ?? null);

            $identity = ApplicantIdentity::query()->firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'normalized_key' => $normalizedKey,
                ],
                [
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'suffix' => $suffix,
                    'birthdate' => $birthdate,
                    'sex' => $sex,
                    'email' => $email,
                    'phone' => $phone,
                ]
            );

            // Prevent duplicate applications within the same academic cycle.
            $existingAttempt = AdmissionAttempt::query()
                ->where('applicant_identity_id', $identity->id)
                ->where('academic_cycle_id', $cycle->id)
                ->first();

            if ($existingAttempt !== null) {
                session()->flash('application_conflict', [
                    'id' => (int) $existingAttempt->id,
                    'reference_code' => $existingAttempt->reference_code,
                    'cycle_name' => $existingAttempt->academicCycle?->name ?? $cycle->name,
                    'status' => $existingAttempt->status,
                    'applicant_name' => $existingAttempt->identity?->fullName() ?? trim("{$firstName} {$lastName}"),
                    'created_at' => $existingAttempt->created_at?->format('F d, Y'),
                ]);

                throw ValidationException::withMessages([
                    'first_name' => "An application ({$existingAttempt->reference_code}) already exists for this person in the {$cycle->name} cycle.",
                ]);
            }

            $referenceCode = $this->referenceCodeGenerator->next($cycle);
            $status = ($data['status'] ?? 'pending') === 'accepted'
                ? AdmissionAttempt::STATUS_ACCEPTED
                : AdmissionAttempt::STATUS_PENDING;

            $attempt = AdmissionAttempt::query()->create([
                'applicant_identity_id' => $identity->id,
                'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id,
                'application_window_id' => $window?->id,
                'reference_code' => $referenceCode,
                'status' => $status,
                'preferred_program_code' => $program1,
                'program_preference_1_code' => $program1,
                'program_preference_2_code' => $program2,
                'program_preference_3_code' => $program3,
                'applicant_type' => (string) ($data['applicant_type'] ?? 'new'),
                'last_school_enrolled' => $data['last_school_enrolled'] ?? null,
                'strand' => $data['strand'] ?? null,
                'gwa' => null,
                'contact_email' => $email,
                'contact_phone' => $phone,
                'address_line' => $data['address_line'] ?? null,
                'city' => $data['city'] ?? null,
                'province' => $data['province'] ?? null,
                'zip_code' => $data['zip_code'] ?? null,
                'correction_notes' => $data['correction_notes'] ?? null,
                'payload' => $data,
                'version' => 1,
                'accepted_at' => $status === AdmissionAttempt::STATUS_ACCEPTED ? now() : null,
            ]);
            $set = app(EnsureAttemptRequirementChecklist::class)->handle($attempt, $actor);

            if ($status === AdmissionAttempt::STATUS_ACCEPTED && $set !== null) {
                $completeDocuments = (bool) ($data['complete_documents'] ?? false);
                $verifiedIds = ! $completeDocuments && ! empty($data['verified_requirement_ids']) && is_array($data['verified_requirement_ids'])
                    ? array_values(array_unique(array_map('intval', $data['verified_requirement_ids'])))
                    : [];

                $itemsToVerify = $completeDocuments
                    ? $set->items
                    : ($verifiedIds !== []
                        ? $set->items->filter(
                            static fn ($item): bool => in_array((int) $item->intake_requirement_id, $verifiedIds, true)
                        )->values()
                        : collect());

                if ($itemsToVerify->isNotEmpty()) {
                    $itemsPayload = $itemsToVerify->map(static fn ($item): array => [
                        'requirement_item_id' => (int) $item->id,
                        'expected_latest_verification_id' => null,
                    ])->values()->all();

                    app(BulkVerifyAdmissionAttemptDocuments::class)->handle(
                        attempt: $attempt,
                        requirementSetId: (int) $set->id,
                        items: $itemsPayload,
                        actor: $actor,
                        expectedVersion: (int) $attempt->version,
                        idempotencyKey: 'walk-in-verify-'.$attempt->id.'-'.$attempt->version,
                    );
                    $attempt->refresh();
                }
            }

            // Issue access key for applicant portal login (D-085).
            $accessKey = ApplicantAccessKey::mint();
            $tokenHash = ApplicantAccessKey::hash($accessKey);
            $this->issueChallenge->handle(
                $identity,
                $attempt,
                $tokenHash,
                'access_key',
                'walk-in-'.$attempt->id.'-'.now()->timestamp,
            );
            $handoff = $this->issueChallenge->issueHandoffBundle(
                $identity,
                $attempt,
                $accessKey,
                'created',
                'walk-in',
            );

            $this->recordAuditEvent->handle('admission.walk_in_punched', [
                'attempt_id' => $attempt->id,
                'reference_code' => $referenceCode,
                'identity_id' => $identity->id,
                'status' => $status,
                'punched_by' => $actor->id,
            ], (string) $actor->id);

            // Emit notification intent for the applicant
            $intent = NotificationIntent::v1(
                'admission.submitted.v1',
                'admission.submitted',
                [
                    ...AdmissionExaminationNoticeContext::admissionPayload($attempt, 'submitted'),
                    'action_label' => 'Open applicant portal',
                    'action_url' => AdmissionExaminationNoticeContext::url('applicant.portal'),
                    'reference_code' => $referenceCode,
                    'attempt_id' => $attempt->id,
                    'applicant_name' => $identity->first_name.' '.$identity->last_name,
                    'cycle_name' => $cycle->name,
                ],
                [NotificationDelivery::CHANNEL_IN_APP]
            );

            $this->notificationDispatcher->dispatch(
                $intent,
                'admission.punch_in:'.$attempt->id,
                [$identity],
                [NotificationDelivery::CHANNEL_IN_APP]
            );

            return [
                'attempt' => $attempt,
                'access_key' => $accessKey,
                'credential_handoff' => $handoff,
            ];
        });
    }
}
