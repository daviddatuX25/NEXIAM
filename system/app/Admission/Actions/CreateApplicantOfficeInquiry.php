<?php

namespace App\Admission\Actions;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\ApplicantOfficeInquiryNotice;
use App\Admission\Support\CommandIdempotency;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ApplicantOfficeInquiryMessage;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class CreateApplicantOfficeInquiry
{
    /**
     * @return array{inquiry_id: string, message_id: string, sequence: string, office: string, status: string, receipt_id: string, replayed: bool}
     */
    public static function handle(User $actor, string $office, string $subject, string $body, string $idempotencyKey): array
    {
        try {
            $access = app(ApplicantOfficeInquiryAccess::class);
            $attempt = $access->applicantAttempt($actor);
            $subject = $access->normalizeSubject($subject);
            $body = $access->normalizeMessage($body);
            $access->validateOperationKey($idempotencyKey);
            $idempotencyKey = strtolower($idempotencyKey);
            if (! in_array($office, ['registrar', 'guidance'], true)) {
                throw ValidationException::withMessages(['office' => 'Choose Registrar or Guidance.']);
            }

            return DB::transaction(function () use ($access, $attempt, $actor, $office, $subject, $body, $idempotencyKey): array {
                $lockedAttempt = AdmissionAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->first();
                $freshActor = $access->actor($actor);
                $ownAttempt = $access->applicantAttempt($freshActor);
                if ($lockedAttempt === null || (string) $ownAttempt->getKey() !== (string) $lockedAttempt->getKey()) {
                    throw new RuntimeException('office_inquiry_not_found');
                }

                $command = 'admission.office-inquiry.create';
                $aggregate = 'attempt:'.$lockedAttempt->getKey();
                $prior = Operation::query()
                    ->where('idempotency_key', $freshActor->getKey().':'.$command.':'.$aggregate.':'.$idempotencyKey)
                    ->first();
                $priorInquiryId = $prior?->payload['receipt_inquiry_id'] ?? null;
                $inquiry = null;
                if ($priorInquiryId !== null) {
                    $inquiry = ApplicantOfficeInquiry::query()->whereKey($priorInquiryId)->lockForUpdate()->first();
                    if ($inquiry === null
                        || (string) $inquiry->admission_attempt_id !== (string) $lockedAttempt->getKey()
                        || (string) $inquiry->created_by_user_id !== (string) $freshActor->getKey()) {
                        throw new RuntimeException('office_inquiry_not_found');
                    }
                    $access->assertRead($freshActor, $inquiry);
                } else {
                    $availableAttempt = $access->assertMayCreate($freshActor, $office);
                    if ((string) $availableAttempt->getKey() !== (string) $lockedAttempt->getKey()) {
                        throw new RuntimeException('office_inquiry_not_found');
                    }
                }

                try {
                    $operation = app(CommandIdempotency::class)->claim($freshActor, $command, $aggregate, [
                        'attempt_id' => (string) $lockedAttempt->getKey(),
                        'office_digest' => hash('sha256', $office),
                        'subject_digest' => hash('sha256', $subject),
                        'body_digest' => hash('sha256', $body),
                    ], $idempotencyKey);
                } catch (RuntimeException $exception) {
                    if ($exception->getMessage() === 'Idempotency key was already used with a different request payload.') {
                        throw new RuntimeException('office_inquiry_conflict');
                    }
                    throw $exception;
                }

                if ($inquiry !== null) {
                    $message = ApplicantOfficeInquiryMessage::query()
                        ->where('applicant_office_inquiry_id', $inquiry->getKey())
                        ->where('sender_user_id', $freshActor->getKey())
                        ->find($operation->payload['receipt_message_id'] ?? null);
                    if ($message === null || (string) $message->sequence !== '1') {
                        throw new RuntimeException('office_inquiry_conflict');
                    }

                    return self::receipt($inquiry, $message, $operation);
                }

                $inquiry = ApplicantOfficeInquiry::query()->create([
                    'institution_id' => $lockedAttempt->institution_id,
                    'academic_cycle_id' => $lockedAttempt->academic_cycle_id,
                    'admission_attempt_id' => $lockedAttempt->getKey(),
                    'applicant_identity_id' => $lockedAttempt->applicant_identity_id,
                    'office' => $office,
                    'subject' => $subject,
                    'status' => 'open',
                    'latest_message_sequence' => 1,
                    'created_by_user_id' => $freshActor->getKey(),
                ]);
                $message = ApplicantOfficeInquiryMessage::query()->create([
                    'applicant_office_inquiry_id' => $inquiry->getKey(),
                    'sender_user_id' => $freshActor->getKey(),
                    'sender_role' => ApplicantOfficeInquiryMessage::ROLE_APPLICANT,
                    'sequence' => 1,
                    'body' => $body,
                    'created_at' => now(),
                ]);

                $audit = app(RecordAuditEvent::class);
                $audit->handle('admission.office_inquiry.opened', [
                    'inquiry_id' => (string) $inquiry->getKey(),
                    'attempt_id' => (string) $lockedAttempt->getKey(),
                    'office' => $office,
                ], (string) $freshActor->getKey());
                $audit->handle('admission.office_inquiry.message_sent', [
                    'inquiry_id' => (string) $inquiry->getKey(),
                    'message_id' => (string) $message->getKey(),
                    'sequence' => '1',
                    'attempt_id' => (string) $lockedAttempt->getKey(),
                ], (string) $freshActor->getKey());
                app(ApplicantOfficeInquiryNotice::class)->dispatchMessage($inquiry, $message, $freshActor);

                $operation->forceFill(['payload' => [
                    ...$operation->payload,
                    'receipt_inquiry_id' => (string) $inquiry->getKey(),
                    'receipt_message_id' => (string) $message->getKey(),
                ]])->save();

                return self::receipt($inquiry, $message, $operation);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException && str_starts_with($exception->getMessage(), 'office_inquiry_')) {
                throw $exception;
            }

            throw new RuntimeException('office_inquiry_unavailable', 0, $exception);
        }
    }

    /**
     * @return array{inquiry_id: string, public_reference_code: string, message_id: string, sequence: string, office: string, status: string, receipt_id: string, replayed: bool}
     */
    public static function handlePreApplication(
        string $deviceTokenHash,
        string $office,
        string $subject,
        string $body,
        string $idempotencyKey,
    ): array {
        try {
            $access = app(ApplicantOfficeInquiryAccess::class);
            $subject = $access->normalizeSubject($subject);
            $body = $access->normalizeMessage($body);
            $access->validateOperationKey($idempotencyKey);
            $idempotencyKey = strtolower($idempotencyKey);
            if (! in_array($office, ['registrar', 'guidance'], true)) {
                throw ValidationException::withMessages(['office' => 'Choose Registrar or Guidance.']);
            }

            $context = $access->preApplicationContext();
            if ($context === null) {
                throw new RuntimeException('office_inquiry_destination_unavailable');
            }
            $institution = $context['institution'];
            $cycle = $context['cycle'];
            if ($access->officeStaffForCycle((int) $institution->id, $cycle, $office)->isEmpty()) {
                throw new RuntimeException('office_inquiry_destination_unavailable');
            }

            return DB::transaction(function () use ($access, $institution, $cycle, $deviceTokenHash, $office, $subject, $body, $idempotencyKey): array {
                $principalNamespace = 'guest:'.$deviceTokenHash;
                $command = 'admission.office-inquiry.create-pre-application';
                $aggregate = 'cycle:'.$cycle->getKey();
                $prior = Operation::query()
                    ->where('idempotency_key', $principalNamespace.':'.$command.':'.$aggregate.':'.$idempotencyKey)
                    ->first();
                $priorInquiryId = $prior?->payload['receipt_inquiry_id'] ?? null;
                $inquiry = null;
                if ($priorInquiryId !== null) {
                    $inquiry = ApplicantOfficeInquiry::query()->whereKey($priorInquiryId)->lockForUpdate()->first();
                    if ($inquiry === null || ! $access->canReadPreApplication($deviceTokenHash, $inquiry)) {
                        throw new RuntimeException('office_inquiry_not_found');
                    }
                } else {
                    $openCount = ApplicantOfficeInquiry::query()
                        ->where('institution_id', $institution->getKey())
                        ->where('academic_cycle_id', $cycle->getKey())
                        ->where('guest_token_hash', $deviceTokenHash)
                        ->where('status', 'open')
                        ->lockForUpdate()
                        ->count();
                    if ($openCount >= ApplicantOfficeInquiryAccess::MAX_OPEN_PRE_APPLICATION_INQUIRIES) {
                        throw ValidationException::withMessages([
                            'subject' => 'You already have 2 open pre-application inquiries on this device. Reply to an existing inquiry or wait for the office to resolve it.',
                        ]);
                    }
                }

                try {
                    $operation = app(CommandIdempotency::class)->claimForPrincipal($principalNamespace, $command, $aggregate, [
                        'cycle_id' => (string) $cycle->getKey(),
                        'office_digest' => hash('sha256', $office),
                        'subject_digest' => hash('sha256', $subject),
                        'body_digest' => hash('sha256', $body),
                    ], $idempotencyKey);
                } catch (RuntimeException $exception) {
                    if ($exception->getMessage() === 'Idempotency key was already used with a different request payload.') {
                        throw new RuntimeException('office_inquiry_conflict');
                    }
                    throw $exception;
                }

                if ($inquiry !== null) {
                    $message = ApplicantOfficeInquiryMessage::query()
                        ->where('applicant_office_inquiry_id', $inquiry->getKey())
                        ->find($operation->payload['receipt_message_id'] ?? null);
                    if ($message === null || (string) $message->sequence !== '1') {
                        throw new RuntimeException('office_inquiry_conflict');
                    }

                    return [
                        ...self::receipt($inquiry, $message, $operation),
                        'public_reference_code' => (string) $inquiry->public_reference_code,
                    ];
                }

                $publicReferenceCode = $access->nextPublicReferenceCode($cycle);
                $inquiry = ApplicantOfficeInquiry::query()->create([
                    'institution_id' => $institution->getKey(),
                    'academic_cycle_id' => $cycle->getKey(),
                    'admission_attempt_id' => null,
                    'applicant_identity_id' => null,
                    'office' => $office,
                    'subject' => $subject,
                    'status' => 'open',
                    'latest_message_sequence' => 1,
                    'public_reference_code' => $publicReferenceCode,
                    'guest_token_hash' => $deviceTokenHash,
                    'guest_last_read_sequence' => 1,
                    'created_by_user_id' => null,
                ]);
                $message = ApplicantOfficeInquiryMessage::query()->create([
                    'applicant_office_inquiry_id' => $inquiry->getKey(),
                    'sender_user_id' => null,
                    'sender_role' => ApplicantOfficeInquiryMessage::ROLE_APPLICANT,
                    'sequence' => 1,
                    'body' => $body,
                    'created_at' => now(),
                ]);

                $audit = app(RecordAuditEvent::class);
                $audit->handle('admission.office_inquiry.opened', [
                    'inquiry_id' => (string) $inquiry->getKey(),
                    'public_reference_code' => $publicReferenceCode,
                    'office' => $office,
                    'pre_application' => true,
                ], 'guest');
                $audit->handle('admission.office_inquiry.message_sent', [
                    'inquiry_id' => (string) $inquiry->getKey(),
                    'message_id' => (string) $message->getKey(),
                    'sequence' => '1',
                    'public_reference_code' => $publicReferenceCode,
                    'pre_application' => true,
                ], 'guest');
                app(ApplicantOfficeInquiryNotice::class)->dispatchPreApplicationMessage($inquiry, $message);

                $operation->forceFill(['payload' => [
                    ...$operation->payload,
                    'receipt_inquiry_id' => (string) $inquiry->getKey(),
                    'receipt_message_id' => (string) $message->getKey(),
                ]])->save();

                return [
                    ...self::receipt($inquiry, $message, $operation),
                    'public_reference_code' => $publicReferenceCode,
                ];
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException && str_starts_with($exception->getMessage(), 'office_inquiry_')) {
                throw $exception;
            }

            throw new RuntimeException('office_inquiry_unavailable', 0, $exception);
        }
    }

    /**
     * @return array{inquiry_id: string, message_id: string, sequence: string, office: string, status: string, receipt_id: string, replayed: bool}
     */
    private static function receipt(ApplicantOfficeInquiry $inquiry, ApplicantOfficeInquiryMessage $message, Operation $operation): array
    {
        return [
            'inquiry_id' => (string) $inquiry->getKey(),
            'message_id' => (string) $message->getKey(),
            'sequence' => (string) $message->sequence,
            'office' => (string) $inquiry->office,
            'status' => 'open',
            'receipt_id' => (string) $operation->getKey(),
            'replayed' => ! $operation->wasRecentlyCreated,
        ];
    }
}
