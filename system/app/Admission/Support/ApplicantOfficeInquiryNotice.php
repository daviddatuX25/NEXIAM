<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ApplicantOfficeInquiryMessage;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ApplicantOfficeInquiryNotice
{
    private const EVENT = 'admission.office_inquiry_message.v1';

    private const TEMPLATE = 'admission.office_inquiry_message';

    public function __construct(
        private readonly ApplicantOfficeInquiryAccess $access,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    public function dispatchMessage(ApplicantOfficeInquiry $inquiry, ApplicantOfficeInquiryMessage $message, User $sender): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('office_inquiry_transaction_required');
        }
        if ((string) $message->applicant_office_inquiry_id !== (string) $inquiry->id
            || (string) $message->sender_user_id !== (string) $sender->id
            || (int) $message->sequence < 1 || (int) $message->sequence > (int) $inquiry->latest_message_sequence) {
            throw new RuntimeException('office_inquiry_not_found');
        }
        $sender = $this->access->actor($sender);
        $this->access->assertRead($sender, $inquiry);
        if ($inquiry->admission_attempt_id === null) {
            return;
        }
        $attempt = AdmissionAttempt::query()->whereKey($inquiry->admission_attempt_id)
            ->where('applicant_identity_id', $inquiry->applicant_identity_id)
            ->where('institution_id', $inquiry->institution_id)->where('academic_cycle_id', $inquiry->academic_cycle_id)->first();
        if ($attempt === null) {
            throw new RuntimeException('office_inquiry_not_found');
        }
        if ($this->access->isApplicant($sender)) {
            $recipients = $this->access->officeStaffFor($attempt, $inquiry->office)
                ->filter(fn (User $recipient): bool => (string) $recipient->id !== (string) $sender->id
                    && $this->access->canRead($recipient, $inquiry));
        } else {
            $identity = $this->applicantRecipient($inquiry, $sender->id);
            $recipients = $identity === null ? collect() : collect([$identity]);
        }
        foreach ($recipients as $recipient) {
            $intent = NotificationIntent::v1(self::EVENT, self::TEMPLATE, [
                'inquiry_id' => (string) $inquiry->id,
                'message_id' => (string) $message->id,
                'attempt_id' => (string) $inquiry->admission_attempt_id,
                'identity_id' => (string) $inquiry->applicant_identity_id,
                'institution_id' => (string) $inquiry->institution_id,
                'academic_cycle_id' => (string) $inquiry->academic_cycle_id,
                'office' => (string) $inquiry->office,
                'sequence' => (string) $message->sequence,
                'subject' => 'New office inquiry message',
                'body' => 'A new message is available in your office inquiry.',
                'action_label' => 'View inquiry',
                'action_url' => route($recipient instanceof ApplicantIdentity ? 'applicant.inquiries.show' : 'staff.inquiries.show', ['inquiry' => $inquiry->id], false),
            ], [NotificationDelivery::CHANNEL_IN_APP]);
            $deliveries = $this->dispatcher->dispatch($intent, $this->eventId($inquiry, $message), [$recipient], [NotificationDelivery::CHANNEL_IN_APP]);
            NotificationIntentDispatcher::enqueueAfterCommit($deliveries);
        }
    }

    public function dispatchPreApplicationMessage(ApplicantOfficeInquiry $inquiry, ApplicantOfficeInquiryMessage $message): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('office_inquiry_transaction_required');
        }
        if ((string) $message->applicant_office_inquiry_id !== (string) $inquiry->id
            || (int) $message->sequence < 1 || (int) $message->sequence > (int) $inquiry->latest_message_sequence) {
            throw new RuntimeException('office_inquiry_not_found');
        }
        $cycle = AcademicCycle::query()
            ->whereKey($inquiry->academic_cycle_id)
            ->where('institution_id', $inquiry->institution_id)
            ->where('is_active', true)
            ->first();
        if ($cycle === null) {
            return;
        }
        $recipients = $this->access->officeStaffForCycle((int) $inquiry->institution_id, $cycle, (string) $inquiry->office)
            ->filter(fn (User $recipient): bool => $this->access->canRead($recipient, $inquiry));
        foreach ($recipients as $recipient) {
            $intent = NotificationIntent::v1(self::EVENT, self::TEMPLATE, [
                'inquiry_id' => (string) $inquiry->id,
                'message_id' => (string) $message->id,
                'attempt_id' => (string) ($inquiry->admission_attempt_id ?? ''),
                'identity_id' => (string) ($inquiry->applicant_identity_id ?? ''),
                'institution_id' => (string) $inquiry->institution_id,
                'academic_cycle_id' => (string) $inquiry->academic_cycle_id,
                'office' => (string) $inquiry->office,
                'sequence' => (string) $message->sequence,
                'subject' => 'New pre-application inquiry message',
                'body' => 'A prospective applicant sent a message to your office queue.',
                'action_label' => 'View inquiry',
                'action_url' => route('staff.inquiries.show', ['inquiry' => $inquiry->id], false),
            ], [NotificationDelivery::CHANNEL_IN_APP]);
            $deliveries = $this->dispatcher->dispatch($intent, $this->eventId($inquiry, $message), [$recipient], [NotificationDelivery::CHANNEL_IN_APP]);
            NotificationIntentDispatcher::enqueueAfterCommit($deliveries);
        }
    }

    public function isCurrent(NotificationDelivery $delivery): bool
    {
        if (! app()->environment(['local', 'testing']) || $delivery->event_type !== self::EVENT
            || $delivery->template_key !== self::TEMPLATE || (int) $delivery->event_version !== 1
            || $delivery->channel !== NotificationDelivery::CHANNEL_IN_APP) {
            return false;
        }
        $payload = $delivery->payload;
        if (! is_array($payload)) {
            return false;
        }
        foreach (['inquiry_id', 'message_id', 'institution_id', 'academic_cycle_id', 'sequence'] as $key) {
            $value = $payload[$key] ?? null;
            if ((! is_string($value) && ! is_int($value)) || ! $this->validId((string) $value)) {
                return false;
            }
        }
        if (! in_array($payload['office'] ?? null, ['registrar', 'guidance'], true)
            || ! $this->validId((string) $delivery->recipient_id)) {
            return false;
        }
        $attemptId = (string) ($payload['attempt_id'] ?? '');
        $identityId = (string) ($payload['identity_id'] ?? '');
        $inquiryQuery = ApplicantOfficeInquiry::query()->whereKey($payload['inquiry_id'])
            ->where('institution_id', $payload['institution_id'])->where('academic_cycle_id', $payload['academic_cycle_id'])
            ->where('office', $payload['office'] ?? '');
        if ($attemptId !== '' || $identityId !== '') {
            if (! $this->validId($attemptId) || ! $this->validId($identityId)) {
                return false;
            }
            $inquiryQuery->where('admission_attempt_id', $attemptId)->where('applicant_identity_id', $identityId);
        }
        $inquiry = $inquiryQuery->first();
        if ($inquiry === null) {
            return false;
        }
        $message = ApplicantOfficeInquiryMessage::query()->select(['id', 'applicant_office_inquiry_id', 'sender_user_id', 'sequence'])
            ->whereKey($payload['message_id'])->where('applicant_office_inquiry_id', $inquiry->id)
            ->where('sequence', $payload['sequence'])->where('sequence', '<=', $inquiry->latest_message_sequence)->first();
        if ($message === null || ! hash_equals($this->eventId($inquiry, $message), (string) $delivery->event_id)) {
            return false;
        }
        if ($delivery->recipient_type === ApplicantIdentity::class) {
            if ((string) $delivery->recipient_id !== (string) $inquiry->applicant_identity_id) {
                return false;
            }

            return $this->applicantRecipient($inquiry, $message->sender_user_id) !== null;
        }
        if ($delivery->recipient_type !== User::class || ($message->sender_user_id !== null && (string) $delivery->recipient_id === (string) $message->sender_user_id)) {
            return false;
        }
        $recipient = User::query()->find($delivery->recipient_id);

        return $recipient !== null && $this->currentActor($recipient, $inquiry, 'staff') !== null;
    }

    private function applicantRecipient(ApplicantOfficeInquiry $inquiry, int|string $senderId): ?ApplicantIdentity
    {
        $identity = ApplicantIdentity::query()->select(['id', 'institution_id'])
            ->whereKey($inquiry->applicant_identity_id)->where('institution_id', $inquiry->institution_id)->first();
        $userId = $identity?->applicantAccount()->value('user_id');
        if ($userId === null || (string) $userId === (string) $senderId) {
            return null;
        }
        $applicant = User::query()->find($userId);
        $applicant = $applicant === null ? null : $this->currentActor($applicant, $inquiry, 'applicant');
        if ($applicant === null || (string) $applicant->applicantAccount?->applicant_identity_id !== (string) $identity->id) {
            return null;
        }

        return $identity;
    }

    private function currentActor(User $actor, ApplicantOfficeInquiry $inquiry, string $actorType): ?User
    {
        try {
            $actor = $this->access->actor($actor);
            if (($actorType === 'staff'
                    ? $actor->getRelation('officeInquiryDemoActor')->actor_type !== 'staff'
                    : $actor->getRelation('officeInquiryDemoActor')->actor_type !== 'applicant')
                || ! $this->access->canRead($actor, $inquiry)) {
                return null;
            }

            return $actor;
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'office_inquiry_not_found') {
                throw $exception;
            }

            return null;
        }
    }

    private function eventId(ApplicantOfficeInquiry $inquiry, ApplicantOfficeInquiryMessage $message): string
    {
        return hash('sha256', self::EVENT.':'.$inquiry->id.':'.$message->id.':'.$message->sequence);
    }

    private function validId(string $id): bool
    {
        return preg_match('/^[1-9][0-9]*$/D', $id) === 1 && strlen($id) <= strlen((string) PHP_INT_MAX)
            && (strlen($id) < strlen((string) PHP_INT_MAX) || strcmp($id, (string) PHP_INT_MAX) <= 0);
    }
}
