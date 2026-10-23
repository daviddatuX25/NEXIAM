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

final class SendApplicantOfficeInquiryMessage
{
    /**
     * @return array{inquiry_id: string, message_id: string, sequence: string, office: string, status: string, receipt_id: string, replayed: bool}
     */
    public static function handle(User $actor, int $inquiryId, string $body, string $idempotencyKey): array
    {
        try {
            $access = app(ApplicantOfficeInquiryAccess::class);
            $access->actor($actor);
            $body = $access->normalizeMessage($body);
            $access->validateOperationKey($idempotencyKey);
            $idempotencyKey = strtolower($idempotencyKey);
            if ($inquiryId <= 0) {
                throw ValidationException::withMessages(['inquiry_id' => 'Choose a valid inquiry.']);
            }

            return DB::transaction(function () use ($access, $actor, $inquiryId, $body, $idempotencyKey): array {
                $attemptId = ApplicantOfficeInquiry::query()->whereKey($inquiryId)->value('admission_attempt_id');
                $attempt = $attemptId === null
                    ? null
                    : AdmissionAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();
                $inquiry = ApplicantOfficeInquiry::query()->whereKey($inquiryId)->lockForUpdate()->first();
                if ($inquiry === null
                    || ($attemptId !== null && ($attempt === null || (string) $inquiry->admission_attempt_id !== (string) $attempt->getKey()))) {
                    throw new RuntimeException('office_inquiry_not_found');
                }
                $freshActor = $access->actor($actor);
                $access->assertRead($freshActor, $inquiry);
                try {
                    $operation = app(CommandIdempotency::class)->claim($freshActor, 'admission.office-inquiry.send', 'inquiry:'.$inquiry->getKey(), [
                        'inquiry_id' => (string) $inquiry->getKey(),
                        'body_digest' => hash('sha256', $body),
                    ], $idempotencyKey);
                } catch (RuntimeException $exception) {
                    if ($exception->getMessage() === 'Idempotency key was already used with a different request payload.') {
                        throw new RuntimeException('office_inquiry_conflict');
                    }
                    throw $exception;
                }

                $receiptMessageId = $operation->payload['receipt_message_id'] ?? null;
                if ($receiptMessageId !== null) {
                    $message = ApplicantOfficeInquiryMessage::query()
                        ->where('applicant_office_inquiry_id', $inquiry->getKey())
                        ->where('sender_user_id', $freshActor->getKey())
                        ->find($receiptMessageId);
                    if ($message === null
                        || ($operation->payload['receipt_inquiry_id'] ?? null) !== (string) $inquiry->getKey()) {
                        throw new RuntimeException('office_inquiry_conflict');
                    }

                    return self::receipt($inquiry, $message, $operation);
                }

                $access->assertSend($freshActor, $inquiry);
                $latestSequence = (int) $inquiry->latest_message_sequence;
                if ($latestSequence < 1 || $latestSequence === PHP_INT_MAX) {
                    throw new RuntimeException('office_inquiry_conflict');
                }
                $sequence = $latestSequence + 1;
                $inquiry->forceFill(['latest_message_sequence' => $sequence])->save();
                $message = ApplicantOfficeInquiryMessage::query()->create([
                    'applicant_office_inquiry_id' => $inquiry->getKey(),
                    'sender_user_id' => $freshActor->getKey(),
                    'sender_role' => $access->isStaff($freshActor)
                        ? ApplicantOfficeInquiryMessage::ROLE_STAFF
                        : ApplicantOfficeInquiryMessage::ROLE_APPLICANT,
                    'sequence' => $sequence,
                    'body' => $body,
                    'created_at' => now(),
                ]);

                app(RecordAuditEvent::class)->handle('admission.office_inquiry.message_sent', [
                    'inquiry_id' => (string) $inquiry->getKey(),
                    'message_id' => (string) $message->getKey(),
                    'sequence' => (string) $sequence,
                    'attempt_id' => $attempt === null ? null : (string) $attempt->getKey(),
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
     * @return array{inquiry_id: string, message_id: string, sequence: string, office: string, status: string, receipt_id: string, replayed: bool}
     */
    public static function handlePreApplication(
        string $deviceTokenHash,
        int $inquiryId,
        string $body,
        string $idempotencyKey,
    ): array {
        try {
            $access = app(ApplicantOfficeInquiryAccess::class);
            $body = $access->normalizeMessage($body);
            $access->validateOperationKey($idempotencyKey);
            $idempotencyKey = strtolower($idempotencyKey);
            if ($inquiryId <= 0) {
                throw ValidationException::withMessages(['inquiry_id' => 'Choose a valid inquiry.']);
            }

            return DB::transaction(function () use ($access, $deviceTokenHash, $inquiryId, $body, $idempotencyKey): array {
                $inquiry = ApplicantOfficeInquiry::query()->whereKey($inquiryId)->lockForUpdate()->first();
                if ($inquiry === null) {
                    throw new RuntimeException('office_inquiry_not_found');
                }

                $access->assertReadPreApplication($deviceTokenHash, $inquiry);
                $principalNamespace = 'guest:'.$deviceTokenHash;
                try {
                    $operation = app(CommandIdempotency::class)->claimForPrincipal(
                        $principalNamespace,
                        'admission.office-inquiry.send-pre-application',
                        'inquiry:'.$inquiry->getKey(),
                        [
                            'inquiry_id' => (string) $inquiry->getKey(),
                            'body_digest' => hash('sha256', $body),
                        ],
                        $idempotencyKey,
                    );
                } catch (RuntimeException $exception) {
                    if ($exception->getMessage() === 'Idempotency key was already used with a different request payload.') {
                        throw new RuntimeException('office_inquiry_conflict');
                    }
                    throw $exception;
                }

                $receiptMessageId = $operation->payload['receipt_message_id'] ?? null;
                if ($receiptMessageId !== null) {
                    $message = ApplicantOfficeInquiryMessage::query()
                        ->where('applicant_office_inquiry_id', $inquiry->getKey())
                        ->find($receiptMessageId);
                    if ($message === null
                        || ($operation->payload['receipt_inquiry_id'] ?? null) !== (string) $inquiry->getKey()) {
                        throw new RuntimeException('office_inquiry_conflict');
                    }

                    return self::receipt($inquiry, $message, $operation);
                }

                $access->assertSendPreApplication($deviceTokenHash, $inquiry);
                $latestSequence = (int) $inquiry->latest_message_sequence;
                if ($latestSequence < 1 || $latestSequence === PHP_INT_MAX) {
                    throw new RuntimeException('office_inquiry_conflict');
                }
                $sequence = $latestSequence + 1;
                $inquiry->forceFill([
                    'latest_message_sequence' => $sequence,
                    'guest_last_read_sequence' => max((int) $inquiry->guest_last_read_sequence, $sequence),
                ])->save();
                $message = ApplicantOfficeInquiryMessage::query()->create([
                    'applicant_office_inquiry_id' => $inquiry->getKey(),
                    'sender_user_id' => null,
                    'sender_role' => ApplicantOfficeInquiryMessage::ROLE_APPLICANT,
                    'sequence' => $sequence,
                    'body' => $body,
                    'created_at' => now(),
                ]);

                app(RecordAuditEvent::class)->handle('admission.office_inquiry.message_sent', [
                    'inquiry_id' => (string) $inquiry->getKey(),
                    'message_id' => (string) $message->getKey(),
                    'sequence' => (string) $sequence,
                    'public_reference_code' => (string) $inquiry->public_reference_code,
                    'pre_application' => true,
                ], 'guest');
                app(ApplicantOfficeInquiryNotice::class)->dispatchPreApplicationMessage($inquiry, $message);
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
