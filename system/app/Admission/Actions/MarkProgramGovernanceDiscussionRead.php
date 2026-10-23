<?php

namespace App\Admission\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\ProgramGovernanceMessage;
use App\Models\Admission\ProgramGovernanceRead;
use App\Models\Admission\ProgramGovernanceThread;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarkProgramGovernanceDiscussionRead
{
    public function __construct(
        private readonly GovernanceAuthorizer $authorizer,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        ProgramGovernanceThread $thread,
        User $actor,
        Program $routeProgram,
        AcademicCycle $routeCycle,
        string $idempotencyKey,
        ?int $throughMessageId = null,
    ): ProgramGovernanceRead {
        return DB::transaction(function () use ($thread, $actor, $routeProgram, $routeCycle, $idempotencyKey, $throughMessageId): ProgramGovernanceRead {
            $locked = ProgramGovernanceThread::query()->whereKey($thread->getKey())->lockForUpdate()->firstOrFail();
            $program = Program::query()->findOrFail($routeProgram->getKey());
            $cycle = AcademicCycle::query()->findOrFail($routeCycle->getKey());
            if ((int) $locked->program_id !== (int) $program->id
                || (int) $locked->academic_cycle_id !== (int) $cycle->id
                || (int) $locked->institution_id !== (int) $program->institution_id
                || (int) $locked->institution_id !== (int) $cycle->institution_id) {
                throw new RuntimeException('Discussion is not available in the selected context.');
            }
            $this->authorizer->authorize($actor, $this->readCapability($locked->topic), $program, $cycle);

            $target = $throughMessageId === null
                ? null
                : ProgramGovernanceMessage::query()
                    ->where('program_governance_thread_id', $locked->id)
                    ->find($throughMessageId);
            if ($throughMessageId !== null && $target === null) {
                throw new RuntimeException('Read marker message is not part of this discussion.');
            }
            if ($throughMessageId === null
                && ProgramGovernanceMessage::query()->where('program_governance_thread_id', $locked->id)->exists()) {
                throw new RuntimeException('An empty-discussion acknowledgment cannot mark messages as read.');
            }
            $operation = $this->idempotency->claim(
                $actor,
                'admission.governance-discussion.read.mark',
                'governance-discussion-read-marker',
                ['thread_id' => $locked->id, 'requested_through_message_id' => $throughMessageId, 'empty_thread_acknowledgment' => $throughMessageId === null],
                $idempotencyKey,
            );
            $operation->refresh();
            $receiptReadId = $operation->payload['receipt_read_id'] ?? null;
            if ($receiptReadId !== null) {
                return ProgramGovernanceRead::query()
                    ->where('program_governance_thread_id', $locked->id)
                    ->where('user_id', $actor->id)
                    ->findOrFail($receiptReadId)
                    ->forceFill([
                        'last_read_message_id' => $operation->payload['receipt_last_read_message_id'] ?? null,
                        'last_read_at' => $operation->payload['receipt_last_read_at'] ?? null,
                    ]);
            }

            $marker = ProgramGovernanceRead::query()
                ->where('program_governance_thread_id', $locked->id)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();
            if ($marker === null) {
                $marker = new ProgramGovernanceRead([
                    'program_governance_thread_id' => $locked->id,
                    'user_id' => $actor->id,
                ]);
            }

            $currentRead = $marker->last_read_message_id === null
                ? null
                : ProgramGovernanceMessage::query()
                    ->where('program_governance_thread_id', $locked->id)
                    ->find($marker->last_read_message_id);
            if ($target !== null && ($currentRead === null
                || $target->created_at->gt($currentRead->created_at)
                || ($target->created_at->equalTo($currentRead->created_at) && $target->id > $currentRead->id))) {
                $marker->last_read_message_id = $target->id;
            }
            $marker->last_read_at = now();
            $marker->save();

            $this->recordAuditEvent->handle('admission.governance_discussion.read_marked', [
                'thread_id' => $locked->id,
                'program_id' => $locked->program_id,
                'academic_cycle_id' => $locked->academic_cycle_id,
                'topic' => $locked->topic,
                'last_read_message_id' => $marker->last_read_message_id,
            ], (string) $actor->id);

            $operation->forceFill(['payload' => [...$operation->payload, 'receipt_read_id' => $marker->id, 'receipt_last_read_message_id' => $marker->last_read_message_id, 'receipt_last_read_at' => $marker->last_read_at?->toISOString()]])->save();

            return $marker;
        });
    }

    private function readCapability(string $topic): string
    {
        return match ($topic) {
            ProgramGovernanceThread::TOPIC_BENCHMARK => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
            ProgramGovernanceThread::TOPIC_QUOTA => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
            ProgramGovernanceThread::TOPIC_ADVISING => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
            default => throw new RuntimeException('Discussion topic is invalid.'),
        };
    }
}
