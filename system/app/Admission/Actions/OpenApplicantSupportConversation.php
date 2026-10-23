<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptProgramFacts;
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
 * Gated for assigned Program Head: atomically open an applicant-support
 * thread with subject and first message under payload-bound idempotency.
 */
class OpenApplicantSupportConversation
{
    public function __construct(
        private readonly RecordThreadService $threads,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly CaseMessageRecipients $recipients,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        ?int $programId,
        string $subject,
        string $body,
        User $creator,
        string $idempotencyKey,
    ): RecordThread {
        $input = Validator::make([
            'subject' => trim($subject),
            'body' => trim($body),
            'idempotency_key' => $idempotencyKey,
        ], [
            'subject' => ['required', 'string', $this->textRule(120)],
            'body' => ['required', 'string', $this->textRule(4000)],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();

        $attempt = AdmissionAttempt::query()->findOrFail($attempt->id);
        $this->assertAllowed($attempt, $programId, $creator);

        return DB::transaction(function () use ($attempt, $programId, $input, $creator): RecordThread {
            $currentAttempt = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $this->assertAllowed($currentAttempt, $programId, $creator);

            $operation = $this->idempotency->claim(
                $creator,
                'record.thread.open_support',
                "attempt:{$attempt->id}:program:{$programId}",
                ['subject' => $input['subject'], 'body' => $input['body']],
                $input['idempotency_key'],
            );
            $operation->refresh();
            $receiptThreadId = $operation->payload['receipt_thread_id'] ?? $operation->payload['thread_id'] ?? null;
            $receiptMessageId = $operation->payload['receipt_message_id'] ?? null;
            if ($receiptThreadId !== null && $receiptMessageId !== null) {
                $thread = RecordThread::query()
                    ->whereKey($receiptThreadId)
                    ->where('admission_attempt_id', $currentAttempt->id)
                    ->where('program_id', $programId)
                    ->where('thread_type', RecordThread::TYPE_APPLICANT_SUPPORT)
                    ->firstOrFail();
                if (! $this->threads->canRead($thread, $creator)
                    || ! RecordThreadMessage::query()
                        ->where('record_thread_id', $thread->id)
                        ->where('sender_user_id', $creator->id)
                        ->where('body', $input['body'])
                        ->whereKey($receiptMessageId)
                        ->exists()) {
                    throw new RuntimeException('You are not authorized to read this thread.');
                }

                return $thread;
            }

            if ($currentAttempt->practice_fixture_set_id !== null
                && ! PracticeFixtureSet::query()
                    ->whereKey($currentAttempt->practice_fixture_set_id)
                    ->where('status', 'active')
                    ->exists()) {
                throw new RuntimeException('The practice case is retired.');
            }

            $thread = RecordThread::query()->create([
                'admission_attempt_id' => $attempt->id,
                'program_id' => $programId,
                'thread_type' => RecordThread::TYPE_APPLICANT_SUPPORT,
                'subject' => $input['subject'],
                'status' => RecordThread::STATUS_OPEN,
                'created_by_user_id' => $creator->id,
                'latest_message_sequence' => 1,
                'created_at' => now(),
            ]);

            $message = RecordThreadMessage::query()->create([
                'record_thread_id' => $thread->id,
                'sender_user_id' => $creator->id,
                'body' => $input['body'],
                'sequence' => 1,
                'created_at' => now(),
            ]);

            $this->recordAuditEvent->handle('thread.opened', [
                'thread_id' => $thread->id,
                'attempt_id' => $attempt->id,
                'program_id' => $programId,
                'actor_id' => $creator->id,
                'thread_type' => RecordThread::TYPE_APPLICANT_SUPPORT,
            ], (string) $creator->id);

            $this->recordAuditEvent->handle('thread.message_sent', [
                'thread_id' => $thread->id,
                'message_id' => $message->id,
                'sequence' => 1,
                'attempt_id' => $attempt->id,
                'sender' => $creator->id,
            ], (string) $creator->id);

            $recipientsList = $this->recipients->recipientsFor($thread, $creator);

            if ($recipientsList->isNotEmpty()) {
                $intent = NotificationIntent::v1(
                    'admission.case_message_sent.v1',
                    'admission.case_message_sent',
                    [
                        'institution_id' => $currentAttempt->institution_id,
                        'attempt_id' => $currentAttempt->id,
                        'thread_id' => $thread->id,
                        'message_id' => $message->id,
                        'subject' => 'New case message',
                        'body' => 'A new message was posted to your case conversation.',
                        'action_url' => route('applicant.admission-support', [
                            'group' => 'program_support',
                            'thread_id' => $thread->id,
                            'attempt_id' => $currentAttempt->id,
                            'program_code' => $thread->program?->code,
                            'cycle_code' => $currentAttempt->academicCycle()->value('code'),
                        ]),
                    ],
                    [NotificationDelivery::CHANNEL_IN_APP]
                );
                $eventId = hash('sha256', "case_message:{$message->id}:{$message->sequence}");
                $deliveries = $this->dispatcher->dispatch($intent, $eventId, $recipientsList, [NotificationDelivery::CHANNEL_IN_APP]);
                NotificationIntentDispatcher::enqueueAfterCommit($deliveries);
            }

            $operation->forceFill([
                'payload' => [
                    ...$operation->payload,
                    'receipt_thread_id' => $thread->id,
                    'receipt_message_id' => $message->id,
                    'thread_id' => $thread->id,
                ],
            ])->save();

            return $thread;
        });
    }

    private function assertAllowed(AdmissionAttempt $attempt, ?int $programId, User $creator): void
    {
        if (AttemptProgramFacts::isEnrolled($attempt)) {
            throw new RuntimeException('Applicant support messaging ends once the applicant is enrolled.');
        }

        if (! $this->threads->hasCaseRelationship($attempt, $programId)) {
            throw new RuntimeException('Applicant messaging requires a current selection or targeted follow-up for that program.');
        }

        $freshCreator = User::query()->find($creator->id) ?? $creator;
        if ($freshCreator->actorScope()->office === 'guidance') {
            throw new RuntimeException('You are not authorized to open this applicant-support thread.');
        }

        if (! $this->threads->staffCanParticipate($creator, $attempt, $programId, RecordThread::TYPE_APPLICANT_SUPPORT)) {
            throw new RuntimeException('You are not authorized to open this applicant-support thread.');
        }
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
}
