<?php

namespace App\Admission\Queries;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ApplicantOfficeInquiryPreflight
{
    public function __construct(private readonly ApplicantOfficeInquiryAccess $access) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{office: string, subject: string, body: string}
     */
    public function open(User $actor, array $input): array
    {
        $validated = Validator::make(['input' => $input], [
            'input' => ['required', 'array:office,subject,body'],
            'input.office' => ['required', 'string', 'in:registrar,guidance'],
            'input.subject' => ['required', 'string'],
            'input.body' => ['required', 'string'],
        ])->validate()['input'];
        $subject = $this->access->normalizeSubject($validated['subject']);
        $body = $this->access->normalizeMessage($validated['body']);

        return DB::transaction(function () use ($actor, $validated, $subject, $body): array {
            $actor = $this->access->actor($actor);
            $attempt = $this->access->assertMayCreate($actor, $validated['office']);
            AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first(['id']);
            $this->access->assertMayCreate($actor, $validated['office']);

            return ['office' => $validated['office'], 'subject' => $subject, 'body' => $body];
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{inquiry_id: int, body: string}
     */
    public function reply(User $actor, array $input): array
    {
        $validated = Validator::make(['input' => $input], [
            'input' => ['required', 'array:inquiry_id,body'],
            'input.inquiry_id' => ['required'],
            'input.body' => ['required', 'string'],
        ])->validate()['input'];
        $id = $validated['inquiry_id'];
        if ((! is_int($id) && ! is_string($id)) || preg_match('/^[1-9][0-9]*$/D', (string) $id) !== 1
            || strlen((string) $id) > strlen((string) PHP_INT_MAX)
            || (strlen((string) $id) === strlen((string) PHP_INT_MAX) && strcmp((string) $id, (string) PHP_INT_MAX) > 0)) {
            throw ValidationException::withMessages(['inquiry_id' => 'The inquiry identifier is invalid.']);
        }
        $inquiryId = (int) $id;
        $body = $this->access->normalizeMessage($validated['body']);

        return DB::transaction(function () use ($actor, $inquiryId, $body): array {
            $actor = $this->access->actor($actor);
            $attemptId = ApplicantOfficeInquiry::query()->whereKey($inquiryId)->value('admission_attempt_id');
            if ($attemptId === null || AdmissionAttempt::query()->whereKey($attemptId)->lockForUpdate()->first(['id']) === null) {
                throw new RuntimeException('office_inquiry_not_found');
            }
            $inquiry = ApplicantOfficeInquiry::query()->whereKey($inquiryId)->where('admission_attempt_id', $attemptId)->lockForUpdate()->first();
            if ($inquiry === null) {
                throw new RuntimeException('office_inquiry_not_found');
            }
            $this->access->assertSend($actor, $inquiry);

            return ['inquiry_id' => $inquiryId, 'body' => $body];
        });
    }
}
