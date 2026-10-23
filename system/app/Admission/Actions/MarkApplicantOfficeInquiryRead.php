<?php

namespace App\Admission\Actions;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\CaseConversationToken;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ApplicantOfficeInquiryMessage;
use App\Models\Admission\ApplicantOfficeInquiryRead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class MarkApplicantOfficeInquiryRead
{
    /**
     * @return array{own_read_sequence: string, unread_count: int, as_of: string}
     */
    public static function handle(User $actor, int $inquiryId, string $observedSequence, string $observationToken): array
    {
        try {
            $access = app(ApplicantOfficeInquiryAccess::class);
            $access->actor($actor);
            if ($inquiryId <= 0) {
                throw ValidationException::withMessages(['inquiry_id' => 'Choose a valid inquiry.']);
            }
            $maximum = (string) PHP_INT_MAX;
            if (preg_match('/^(0|[1-9][0-9]{0,18})$/D', $observedSequence) !== 1
                || strlen($observedSequence) > strlen($maximum)
                || (strlen($observedSequence) === strlen($maximum) && strcmp($observedSequence, $maximum) > 0)) {
                throw ValidationException::withMessages(['observed_sequence' => 'Use a canonical observed message sequence.']);
            }

            return DB::transaction(function () use ($access, $actor, $inquiryId, $observedSequence, $observationToken): array {
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
                $observed = (int) $observedSequence;
                $latest = (int) $inquiry->latest_message_sequence;
                if ($observed > $latest
                    || ($observed > 0 && ! ApplicantOfficeInquiryMessage::query()
                        ->where('applicant_office_inquiry_id', $inquiry->getKey())
                        ->where('sequence', $observedSequence)
                        ->exists())) {
                    throw ValidationException::withMessages(['observed_sequence' => 'That message sequence is not available.']);
                }
                if (! CaseConversationToken::verify($freshActor, 'office-inquiry:'.$inquiry->getKey(), $observedSequence, $observationToken)) {
                    throw ValidationException::withMessages(['observation_token' => 'Reload the conversation before marking messages read.']);
                }

                $read = ApplicantOfficeInquiryRead::query()
                    ->where('applicant_office_inquiry_id', $inquiry->getKey())
                    ->where('user_id', $freshActor->getKey())
                    ->lockForUpdate()
                    ->first();
                $currentRead = (int) ($read?->last_read_sequence ?? 0);
                if ($currentRead < 0 || $currentRead > $latest) {
                    throw new RuntimeException('office_inquiry_conflict');
                }

                if ($observed > $currentRead) {
                    if ($read === null) {
                        $read = new ApplicantOfficeInquiryRead([
                            'applicant_office_inquiry_id' => $inquiry->getKey(),
                            'user_id' => $freshActor->getKey(),
                        ]);
                    }
                    $read->last_read_sequence = $observed;
                    $read->save();
                    $currentRead = $observed;
                    app(RecordAuditEvent::class)->handle('admission.office_inquiry.read_marked', [
                        'inquiry_id' => (string) $inquiry->getKey(),
                        'attempt_id' => $attempt === null ? null : (string) $attempt->getKey(),
                        'last_read_sequence' => (string) $currentRead,
                    ], (string) $freshActor->getKey());
                }

                $unreadCount = ApplicantOfficeInquiryMessage::query()
                    ->where('applicant_office_inquiry_id', $inquiry->getKey())
                    ->where('sequence', '>', $currentRead)
                    ->where(fn ($query) => $query->whereNull('sender_user_id')->orWhere('sender_user_id', '!=', $freshActor->getKey()))
                    ->count();

                return [
                    'own_read_sequence' => (string) $currentRead,
                    'unread_count' => $unreadCount,
                    'as_of' => now()->toISOString(),
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
     * @return array{own_read_sequence: string, unread_count: int, as_of: string}
     */
    public static function handlePreApplication(
        string $deviceTokenHash,
        int $inquiryId,
        string $observedSequence,
        string $observationToken,
    ): array {
        try {
            $access = app(ApplicantOfficeInquiryAccess::class);
            if ($inquiryId <= 0) {
                throw ValidationException::withMessages(['inquiry_id' => 'Choose a valid inquiry.']);
            }
            $maximum = (string) PHP_INT_MAX;
            if (preg_match('/^(0|[1-9][0-9]{0,18})$/D', $observedSequence) !== 1
                || strlen($observedSequence) > strlen($maximum)
                || (strlen($observedSequence) === strlen($maximum) && strcmp($observedSequence, $maximum) > 0)) {
                throw ValidationException::withMessages(['observed_sequence' => 'Use a canonical observed message sequence.']);
            }

            return DB::transaction(function () use ($access, $deviceTokenHash, $inquiryId, $observedSequence, $observationToken): array {
                $inquiry = ApplicantOfficeInquiry::query()->whereKey($inquiryId)->lockForUpdate()->first();
                if ($inquiry === null) {
                    throw new RuntimeException('office_inquiry_not_found');
                }

                $access->assertReadPreApplication($deviceTokenHash, $inquiry);
                $observed = (int) $observedSequence;
                $latest = (int) $inquiry->latest_message_sequence;
                if ($observed > $latest
                    || ($observed > 0 && ! ApplicantOfficeInquiryMessage::query()
                        ->where('applicant_office_inquiry_id', $inquiry->getKey())
                        ->where('sequence', $observedSequence)
                        ->exists())) {
                    throw ValidationException::withMessages(['observed_sequence' => 'That message sequence is not available.']);
                }
                if (! CaseConversationToken::verifyForPrincipal('guest:'.$deviceTokenHash, 'office-inquiry:'.$inquiry->getKey(), $observedSequence, $observationToken)) {
                    throw ValidationException::withMessages(['observation_token' => 'Reload the conversation before marking messages read.']);
                }

                $currentRead = (int) $inquiry->guest_last_read_sequence;
                if ($currentRead < 0 || $currentRead > $latest) {
                    throw new RuntimeException('office_inquiry_conflict');
                }

                if ($observed > $currentRead) {
                    $inquiry->forceFill(['guest_last_read_sequence' => $observed])->save();
                    $currentRead = $observed;
                    app(RecordAuditEvent::class)->handle('admission.office_inquiry.read_marked', [
                        'inquiry_id' => (string) $inquiry->getKey(),
                        'public_reference_code' => (string) $inquiry->public_reference_code,
                        'last_read_sequence' => (string) $currentRead,
                        'pre_application' => true,
                    ], 'guest');
                }

                $unreadCount = ApplicantOfficeInquiryMessage::query()
                    ->where('applicant_office_inquiry_id', $inquiry->getKey())
                    ->where('sequence', '>', $currentRead)
                    ->where('sender_role', ApplicantOfficeInquiryMessage::ROLE_STAFF)
                    ->count();

                return [
                    'own_read_sequence' => (string) $currentRead,
                    'unread_count' => $unreadCount,
                    'as_of' => now()->toISOString(),
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
}
