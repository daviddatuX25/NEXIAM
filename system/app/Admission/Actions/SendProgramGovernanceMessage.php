<?php

namespace App\Admission\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\ProgramGovernanceMessage;
use App\Models\Admission\ProgramGovernanceThread;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SendProgramGovernanceMessage
{
    public function __construct(
        private readonly GovernanceAuthorizer $authorizer,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(ProgramGovernanceThread $thread, User $actor, Program $routeProgram, AcademicCycle $routeCycle, string $body, string $idempotencyKey): ProgramGovernanceMessage
    {
        if (trim($body) === '') {
            throw new InvalidArgumentException('Message cannot be empty.');
        }

        return DB::transaction(function () use ($thread, $actor, $routeProgram, $routeCycle, $body, $idempotencyKey): ProgramGovernanceMessage {
            $locked = ProgramGovernanceThread::query()->whereKey($thread->getKey())->lockForUpdate()->firstOrFail();
            [$program, $cycle] = $this->resolveContext($locked, $routeProgram, $routeCycle);
            $this->authorizer->authorize($actor, $this->sendCapability($locked->topic), $program, $cycle, true);

            if ($locked->status !== ProgramGovernanceThread::STATUS_OPEN) {
                throw new RuntimeException('Messages cannot be sent to a read-only or closed discussion.');
            }

            $operation = $this->idempotency->claim(
                $actor,
                'admission.governance-discussion.message.send',
                'governance-discussion-message',
                ['thread_id' => $locked->id, 'body' => $body],
                $idempotencyKey,
            );
            $operation->refresh();
            $receiptMessageId = $operation->payload['receipt_message_id'] ?? null;
            if ($receiptMessageId !== null) {
                return ProgramGovernanceMessage::query()
                    ->where('program_governance_thread_id', $locked->id)
                    ->findOrFail($receiptMessageId);
            }

            $message = ProgramGovernanceMessage::query()->create([
                'program_governance_thread_id' => $locked->id,
                'sender_user_id' => $actor->id,
                'body' => $body,
                'created_at' => now(),
            ]);

            $this->recordAuditEvent->handle('admission.governance_discussion.message_sent', [
                'thread_id' => $locked->id,
                'message_id' => $message->id,
                'topic' => $locked->topic,
                'program_id' => $locked->program_id,
                'academic_cycle_id' => $locked->academic_cycle_id,
            ], (string) $actor->id);

            $operation->forceFill(['payload' => [...$operation->payload, 'receipt_message_id' => $message->id]])->save();

            return $message;
        });
    }

    /** @return array{Program, AcademicCycle} */
    private function resolveContext(ProgramGovernanceThread $thread, Program $routeProgram, AcademicCycle $routeCycle): array
    {
        $program = Program::query()->findOrFail($routeProgram->getKey());
        $cycle = AcademicCycle::query()->findOrFail($routeCycle->getKey());
        if ((int) $thread->program_id !== (int) $program->id
            || (int) $thread->academic_cycle_id !== (int) $cycle->id
            || (int) $thread->institution_id !== (int) $program->institution_id
            || (int) $thread->institution_id !== (int) $cycle->institution_id) {
            throw new RuntimeException('Discussion is not available in the selected context.');
        }

        return [$program, $cycle];
    }

    private function sendCapability(string $topic): string
    {
        return match ($topic) {
            ProgramGovernanceThread::TOPIC_BENCHMARK => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
            ProgramGovernanceThread::TOPIC_QUOTA => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
            ProgramGovernanceThread::TOPIC_ADVISING => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_SEND,
            default => throw new RuntimeException('Discussion topic is invalid.'),
        };
    }
}
