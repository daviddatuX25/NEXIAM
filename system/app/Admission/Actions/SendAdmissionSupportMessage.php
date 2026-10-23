<?php

namespace App\Admission\Actions;

use App\Admission\Support\CaseMessageRecipients;
use App\Admission\Support\CommandIdempotency;
use App\Admission\Support\RecordThreadService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Testing\PracticeFixtureSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * D-092: append a message to a server-owned record thread. Participants are
 * derived from current scope; when the qualifying relationship ends, new
 * messages are denied.
 */
class SendAdmissionSupportMessage
{
    public function __construct(
        private readonly RecordThreadService $threads,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly CaseMessageRecipients $recipients,
    ) {}

    public function handle(
        RecordThread $thread,
        User $sender,
        string $body,
        string $idempotencyKey,
    ): RecordThreadMessage {
        $input = $this->validatedInput(body: $body, idempotencyKey: $idempotencyKey);

        return DB::transaction(function () use ($thread, $sender, $input): RecordThreadMessage {
            $attemptId = RecordThread::query()->whereKey($thread->id)->value('admission_attempt_id');
            $attempt = AdmissionAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
            $locked = RecordThread::query()->whereKey($thread->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->admission_attempt_id !== (int) $attempt->id || ! $this->threads->canRead($locked, $sender)) {
                throw new RuntimeException('You are not authorized to send messages to this thread.');
            }

            $operation = $this->idempotency->claim(
                $sender,
                'record.thread.message',
                'thread:'.$locked->id,
                ['thread_id' => $locked->id, 'body' => $input['body']],
                $input['idempotency_key'],
            );
            $operation->refresh();
            $receiptMessageId = $operation->payload['receipt_message_id'] ?? null;
            if ($receiptMessageId !== null) {
                return RecordThreadMessage::query()
                    ->where('record_thread_id', $locked->id)
                    ->where('sender_user_id', $sender->id)
                    ->where('body', $input['body'])
                    ->whereKey($receiptMessageId)
                    ->firstOrFail();
            }

            if (! $this->threads->canSend($locked, $sender)) {
                throw new RuntimeException('You are not authorized to send messages to this thread.');
            }

            $message = $this->appendLockedMessage($attempt, $locked, $sender, $input['body']);
            $operation->forceFill([
                'payload' => [...$operation->payload, 'receipt_message_id' => $message->id],
            ])->save();

            return $message;
        });
    }

    public function openInternalThread(
        AdmissionAttempt $attempt,
        ?int $programId,
        string $subject,
        string $body,
        User $creator,
        string $idempotencyKey,
    ): RecordThread {
        $input = $this->validatedInput(
            subject: $subject,
            body: $body,
            idempotencyKey: $idempotencyKey,
        );

        return DB::transaction(function () use ($attempt, $programId, $creator, $input): RecordThread {
            $lockedAttempt = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $this->assertCanOpenInternalThread($lockedAttempt, $programId, $creator);

            $operation = $this->idempotency->claim(
                $creator,
                'record.thread.open_internal',
                "attempt:{$lockedAttempt->id}:program:{$programId}",
                [
                    'attempt_id' => $lockedAttempt->id,
                    'program_id' => $programId,
                    'subject' => $input['subject'],
                    'body' => $input['body'],
                ],
                $input['idempotency_key'],
            );
            $operation->refresh();

            $receiptThreadId = $operation->payload['receipt_thread_id'] ?? null;
            $receiptMessageId = $operation->payload['receipt_message_id'] ?? null;
            if ($receiptThreadId !== null && $receiptMessageId !== null) {
                $thread = RecordThread::query()
                    ->whereKey($receiptThreadId)
                    ->where('admission_attempt_id', $lockedAttempt->id)
                    ->where('program_id', $programId)
                    ->where('thread_type', RecordThread::TYPE_INTERNAL_PH_GUIDANCE)
                    ->firstOrFail();
                if (! $this->threads->canRead($thread, $creator)) {
                    throw new RuntimeException('You are not authorized to read this thread.');
                }

                $receiptMessage = RecordThreadMessage::query()
                    ->where('record_thread_id', $thread->id)
                    ->where('sender_user_id', $creator->id)
                    ->where('body', $input['body'])
                    ->whereKey($receiptMessageId)
                    ->first();
                if ($receiptMessage === null) {
                    throw new RuntimeException('You are not authorized to read this thread.');
                }

                return $thread;
            }

            if ($lockedAttempt->practice_fixture_set_id !== null
                && ! PracticeFixtureSet::query()
                    ->whereKey($lockedAttempt->practice_fixture_set_id)
                    ->where('status', 'active')
                    ->exists()) {
                throw new RuntimeException('The practice case is retired.');
            }

            $thread = RecordThread::query()->create([
                'admission_attempt_id' => $lockedAttempt->id,
                'program_id' => $programId,
                'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
                'subject' => $input['subject'],
                'status' => RecordThread::STATUS_OPEN,
                'created_by_user_id' => $creator->id,
                'latest_message_sequence' => 0,
                'created_at' => now(),
            ]);
            $thread->setRelation('attempt', $lockedAttempt);

            $this->recordAuditEvent->handle('thread.opened', [
                'thread_id' => $thread->id,
                'attempt_id' => $lockedAttempt->id,
                'program_id' => $programId,
                'actor_id' => $creator->id,
                'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
            ], (string) $creator->id);

            $message = $this->appendLockedMessage($lockedAttempt, $thread, $creator, $input['body']);
            $operation->forceFill([
                'payload' => [
                    ...$operation->payload,
                    'receipt_thread_id' => $thread->id,
                    'receipt_message_id' => $message->id,
                ],
            ])->save();

            return $thread;
        });
    }

    /**
     * @return array{subject?:string,body:string,idempotency_key:string}
     */
    private function validatedInput(string $body, string $idempotencyKey, ?string $subject = null): array
    {
        $values = ['body' => trim($body), 'idempotency_key' => $idempotencyKey];
        $rules = [
            'body' => ['required', 'string', $this->textRule(4000)],
            'idempotency_key' => ['required', 'uuid'],
        ];
        if ($subject !== null) {
            $values['subject'] = trim($subject);
            $rules['subject'] = ['required', 'string', $this->textRule(120)];
        }

        return Validator::make($values, $rules)->validate();
    }

    private function textRule(int $maximum): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($maximum): void {
            if (! is_string($value)
                || ! mb_check_encoding($value, 'UTF-8')
                || trim($value) === ''
                || mb_strlen(trim($value), 'UTF-8') > $maximum
                || strlen(trim($value)) > 16384) {
                $fail('The text is blank or exceeds the supported limit.');
            }
        };
    }

    private function assertCanOpenInternalThread(AdmissionAttempt $attempt, ?int $programId, User $creator): void
    {
        if ($programId === null
            || ! $this->threads->hasCaseRelationship($attempt, $programId)
            || ! $this->threads->staffCanParticipate($creator, $attempt, $programId, RecordThread::TYPE_INTERNAL_PH_GUIDANCE)) {
            throw new RuntimeException('You are not authorized to open this case thread.');
        }
    }

    private function appendLockedMessage(
        AdmissionAttempt $attempt,
        RecordThread $thread,
        User $sender,
        string $body,
    ): RecordThreadMessage {
        $thread->increment('latest_message_sequence');
        $sequence = (string) $thread->fresh()->latest_message_sequence;

        $message = RecordThreadMessage::query()->create([
            'record_thread_id' => $thread->id,
            'sender_user_id' => $sender->id,
            'body' => $body,
            'sequence' => $sequence,
            'created_at' => now(),
        ]);

        $this->recordAuditEvent->handle('thread.message_sent', [
            'thread_id' => $thread->id,
            'message_id' => $message->id,
            'sequence' => $sequence,
            'attempt_id' => $thread->admission_attempt_id,
            'sender' => $sender->id,
        ], (string) $sender->id);

        $recipients = $this->recipients->recipientsFor($thread, $sender);
        if ($recipients->isNotEmpty()) {
            $intent = NotificationIntent::v1(
                'admission.case_message_sent.v1',
                'admission.case_message_sent',
                [
                    'institution_id' => $attempt->institution_id,
                    'attempt_id' => $attempt->id,
                    'thread_id' => $thread->id,
                    'message_id' => $message->id,
                    'subject' => 'New case message',
                    'body' => 'A new message was posted to your case conversation.',
                    'action_url' => route('applicant.admission-support', [
                        'group' => 'program_support',
                        'thread_id' => $thread->id,
                        'attempt_id' => $attempt->id,
                        'program_code' => $thread->program?->code,
                        'cycle_code' => $attempt->academicCycle()->value('code'),
                    ]),
                ],
                [NotificationDelivery::CHANNEL_IN_APP]
            );
            $eventId = hash('sha256', "case_message:{$message->id}:{$message->sequence}");
            $deliveries = $this->dispatcher->dispatch($intent, $eventId, $recipients, [NotificationDelivery::CHANNEL_IN_APP]);
            NotificationIntentDispatcher::enqueueAfterCommit($deliveries);
        }

        return $message;
    }
}
