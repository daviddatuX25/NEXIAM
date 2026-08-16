<?php

namespace App\Admission\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Admission\Support\RecordThreadService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
    ) {}

    public function handle(
        RecordThread $thread,
        User $sender,
        string $body,
        string $idempotencyKey,
    ): RecordThreadMessage {
        $this->idempotency->claim($sender, 'record.thread.message', 'thread:'.$thread->id, [
            'body' => $body,
        ], $idempotencyKey);

        $replayed = RecordThreadMessage::query()
            ->where('record_thread_id', $thread->id)
            ->where('sender_user_id', $sender->id)
            ->where('body', $body)
            ->orderByDesc('id')
            ->first();

        return DB::transaction(function () use ($thread, $sender, $body): RecordThreadMessage {
            $locked = RecordThread::query()->whereKey($thread->id)->lockForUpdate()->firstOrFail();

            if (! $this->threads->canParticipate($locked, $sender)) {
                throw new RuntimeException('You are not an active participant of this thread.');
            }

            $message = RecordThreadMessage::query()->create([
                'record_thread_id' => $locked->id,
                'sender_user_id' => $sender->id,
                'body' => $body,
                'created_at' => now(),
            ]);

            $this->recordAuditEvent->handle('thread.message_sent', [
                'thread_id' => $locked->id,
                'message_id' => $message->id,
                'attempt_id' => $locked->admission_attempt_id,
                'sender' => $sender->id,
            ], (string) $sender->id);

            return $message;
        });
    }
}
