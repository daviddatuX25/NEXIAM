<?php

declare(strict_types=1);

namespace App\Admission\Actions;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ApplicantOfficeInquiryMessage;
use App\Models\Admission\ApplicantOfficeInquiryRead;
use App\Models\User;
use App\Testing\PracticeWorkspace;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ClaimPreApplicationInquiries
{
    public function __construct(
        private readonly ApplicantOfficeInquiryAccess $access,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function resolveDeviceTokenHashFromCurrentRequest(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();
        $candidates = [
            $request->cookie(ApplicantOfficeInquiryAccess::DEVICE_COOKIE),
            $request->header(ApplicantOfficeInquiryAccess::DEVICE_HEADER),
            $request->hasSession() ? $request->session()->get(ApplicantOfficeInquiryAccess::DEVICE_COOKIE) : null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }
            try {
                return $this->access->hashDeviceToken($candidate);
            } catch (RuntimeException) {
                continue;
            }
        }

        return null;
    }

    public function claimForAttempt(AdmissionAttempt $attempt, ?string $deviceTokenHash = null): int
    {
        $deviceTokenHash ??= $this->resolveDeviceTokenHashFromCurrentRequest();
        if ($deviceTokenHash === null
            || preg_match('/^[0-9a-f]{64}$/D', $deviceTokenHash) !== 1
            || app(PracticeWorkspace::class)->currentId() !== null
            || $attempt->practice_fixture_set_id !== null) {
            return 0;
        }

        return DB::transaction(function () use ($attempt, $deviceTokenHash): int {
            $inquiries = ApplicantOfficeInquiry::query()
                ->where('institution_id', $attempt->institution_id)
                ->where('academic_cycle_id', $attempt->academic_cycle_id)
                ->where('guest_token_hash', $deviceTokenHash)
                ->whereNull('admission_attempt_id')
                ->whereNull('created_by_user_id')
                ->lockForUpdate()
                ->get();

            if ($inquiries->isEmpty()) {
                return 0;
            }

            foreach ($inquiries as $inquiry) {
                $inquiry->forceFill([
                    'admission_attempt_id' => $attempt->getKey(),
                    'applicant_identity_id' => $attempt->applicant_identity_id,
                ])->save();

                $this->audit->handle('admission.office_inquiry.married_to_attempt', [
                    'inquiry_id' => (string) $inquiry->getKey(),
                    'public_reference_code' => (string) $inquiry->public_reference_code,
                    'attempt_id' => (string) $attempt->getKey(),
                    'identity_id' => (string) $attempt->applicant_identity_id,
                ], 'public');
            }

            return $inquiries->count();
        });
    }

    public function claimForUser(User $user, ?string $deviceTokenHash = null): int
    {
        $deviceTokenHash ??= $this->resolveDeviceTokenHashFromCurrentRequest();
        if (app(PracticeWorkspace::class)->currentId() !== null) {
            return 0;
        }

        $account = $user->applicantAccount()->first();
        if ($account === null || $user->staffProfile()->exists()) {
            return 0;
        }

        $identity = ApplicantIdentity::query()->find($account->applicant_identity_id);
        if ($identity === null || $identity->practice_fixture_set_id !== null) {
            return 0;
        }

        $attempt = AdmissionAttempt::query()
            ->where('applicant_identity_id', $identity->getKey())
            ->where('institution_id', $identity->institution_id)
            ->whereNull('practice_fixture_set_id')
            ->whereHas('academicCycle', function ($query) use ($identity): void {
                $query->where('institution_id', $identity->institution_id)->where('is_active', true);
            })
            ->orderByDesc('id')
            ->first();

        if ($attempt === null) {
            return 0;
        }

        return DB::transaction(function () use ($user, $attempt, $deviceTokenHash): int {
            $validHash = is_string($deviceTokenHash) && preg_match('/^[0-9a-f]{64}$/D', $deviceTokenHash) === 1
                ? $deviceTokenHash
                : null;

            $inquiries = ApplicantOfficeInquiry::query()
                ->where('institution_id', $attempt->institution_id)
                ->where('academic_cycle_id', $attempt->academic_cycle_id)
                ->whereNull('created_by_user_id')
                ->where(function ($query) use ($attempt, $validHash): void {
                    $query->where('admission_attempt_id', $attempt->getKey());
                    if ($validHash !== null) {
                        $query->orWhere(function ($sub) use ($validHash): void {
                            $sub->whereNull('admission_attempt_id')
                                ->where('guest_token_hash', $validHash);
                        });
                    }
                })
                ->lockForUpdate()
                ->get();

            if ($inquiries->isEmpty()) {
                return 0;
            }

            foreach ($inquiries as $inquiry) {
                $guestRead = max(0, (int) $inquiry->guest_last_read_sequence);
                $inquiry->forceFill([
                    'admission_attempt_id' => $attempt->getKey(),
                    'applicant_identity_id' => $attempt->applicant_identity_id,
                    'created_by_user_id' => $user->getKey(),
                    'guest_token_hash' => null,
                ])->save();

                ApplicantOfficeInquiryMessage::query()
                    ->where('applicant_office_inquiry_id', $inquiry->getKey())
                    ->where('sender_role', ApplicantOfficeInquiryMessage::ROLE_APPLICANT)
                    ->whereNull('sender_user_id')
                    ->update(['sender_user_id' => $user->getKey()]);

                if ($guestRead > 0) {
                    $read = ApplicantOfficeInquiryRead::query()
                        ->where('applicant_office_inquiry_id', $inquiry->getKey())
                        ->where('user_id', $user->getKey())
                        ->lockForUpdate()
                        ->first();
                    if ($read === null) {
                        ApplicantOfficeInquiryRead::query()->create([
                            'applicant_office_inquiry_id' => $inquiry->getKey(),
                            'user_id' => $user->getKey(),
                            'last_read_sequence' => min($guestRead, (int) $inquiry->latest_message_sequence),
                        ]);
                    } elseif ((int) $read->last_read_sequence < $guestRead) {
                        $read->forceFill([
                            'last_read_sequence' => min($guestRead, (int) $inquiry->latest_message_sequence),
                        ])->save();
                    }
                }

                $this->audit->handle('admission.office_inquiry.married_to_account', [
                    'inquiry_id' => (string) $inquiry->getKey(),
                    'public_reference_code' => (string) $inquiry->public_reference_code,
                    'attempt_id' => (string) $attempt->getKey(),
                    'identity_id' => (string) $attempt->applicant_identity_id,
                    'user_id' => (string) $user->getKey(),
                ], (string) $user->getKey());
            }

            return $inquiries->count();
        });
    }
}
