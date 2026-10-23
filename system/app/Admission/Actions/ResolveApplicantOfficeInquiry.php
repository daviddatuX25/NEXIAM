<?php

namespace App\Admission\Actions;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\CommandIdempotency;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class ResolveApplicantOfficeInquiry
{
    /**
     * @return array{inquiry_id: string, status: string, receipt_id: string}
     */
    public static function handle(User $actor, int $inquiryId, string $idempotencyKey): array
    {
        try {
            $access = app(ApplicantOfficeInquiryAccess::class);
            $freshActor = $access->actor($actor);
            if (! $access->isStaff($freshActor)) {
                throw new RuntimeException('office_inquiry_not_found');
            }
            $access->validateOperationKey($idempotencyKey);
            $idempotencyKey = strtolower($idempotencyKey);
            if ($inquiryId <= 0) {
                throw ValidationException::withMessages(['inquiry_id' => 'Choose a valid inquiry.']);
            }

            return DB::transaction(function () use ($access, $actor, $inquiryId, $idempotencyKey): array {
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
                if (! $access->isStaff($freshActor)) {
                    throw new RuntimeException('office_inquiry_not_found');
                }
                $access->assertRead($freshActor, $inquiry);
                try {
                    $operation = app(CommandIdempotency::class)->claim($freshActor, 'admission.office-inquiry.resolve', 'inquiry:'.$inquiry->getKey(), [
                        'inquiry_id' => (string) $inquiry->getKey(),
                    ], $idempotencyKey);
                } catch (RuntimeException $exception) {
                    if ($exception->getMessage() === 'Idempotency key was already used with a different request payload.') {
                        throw new RuntimeException('office_inquiry_conflict');
                    }
                    throw $exception;
                }

                $receiptInquiryId = $operation->payload['receipt_inquiry_id'] ?? null;
                if ($receiptInquiryId !== null) {
                    if ($receiptInquiryId !== (string) $inquiry->getKey() || $inquiry->status !== 'closed') {
                        throw new RuntimeException('office_inquiry_conflict');
                    }

                    return ['inquiry_id' => (string) $inquiry->getKey(), 'status' => 'closed', 'receipt_id' => (string) $operation->getKey()];
                }

                $access->assertResolve($freshActor, $inquiry);
                $inquiry->forceFill([
                    'status' => 'closed',
                    'closed_by_user_id' => $freshActor->getKey(),
                    'closed_at' => now(),
                ])->save();
                app(RecordAuditEvent::class)->handle('admission.office_inquiry.resolved', [
                    'inquiry_id' => (string) $inquiry->getKey(),
                    'attempt_id' => $attempt === null ? null : (string) $attempt->getKey(),
                    'office' => (string) $inquiry->office,
                ], (string) $freshActor->getKey());
                $operation->forceFill(['payload' => [
                    ...$operation->payload,
                    'receipt_inquiry_id' => (string) $inquiry->getKey(),
                ]])->save();

                return ['inquiry_id' => (string) $inquiry->getKey(), 'status' => 'closed', 'receipt_id' => (string) $operation->getKey()];
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
}
