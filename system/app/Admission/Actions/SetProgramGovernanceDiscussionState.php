<?php

namespace App\Admission\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\ProgramGovernanceThread;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SetProgramGovernanceDiscussionState
{
    public function __construct(
        private readonly GovernanceAuthorizer $authorizer,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(ProgramGovernanceThread $thread, User $actor, Program $routeProgram, AcademicCycle $routeCycle, string $status, string $idempotencyKey): ProgramGovernanceThread
    {
        if (! in_array($status, ProgramGovernanceThread::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported governance discussion state.');
        }

        return DB::transaction(function () use ($thread, $actor, $routeProgram, $routeCycle, $status, $idempotencyKey): ProgramGovernanceThread {
            $locked = ProgramGovernanceThread::query()->whereKey($thread->getKey())->lockForUpdate()->firstOrFail();
            $program = Program::query()->findOrFail($routeProgram->getKey());
            $cycle = AcademicCycle::query()->findOrFail($routeCycle->getKey());
            if ((int) $locked->program_id !== (int) $program->id
                || (int) $locked->academic_cycle_id !== (int) $cycle->id
                || (int) $locked->institution_id !== (int) $program->institution_id
                || (int) $locked->institution_id !== (int) $cycle->institution_id) {
                throw new RuntimeException('Discussion is not available in the selected context.');
            }

            $this->authorizer->authorize($actor, $this->manageStateCapability($locked->topic), $program, $cycle, true);

            $operation = $this->idempotency->claim(
                $actor,
                'admission.governance-discussion.state.set',
                'governance-discussion-state',
                ['thread_id' => $locked->id, 'status' => $status],
                $idempotencyKey,
            );
            $operation->refresh();
            $receiptThreadId = $operation->payload['receipt_thread_id'] ?? null;
            if ($receiptThreadId !== null) {
                if ((int) $receiptThreadId !== (int) $locked->id) {
                    throw new RuntimeException('State receipt is not available in this discussion context.');
                }

                return $locked->refresh();
            }

            $priorStatus = $locked->status;
            if ($priorStatus !== $status) {
                $locked->forceFill(['status' => $status])->save();
            }

            $this->recordAuditEvent->handle('admission.governance_discussion.state_changed', [
                'thread_id' => $locked->id,
                'program_id' => $locked->program_id,
                'academic_cycle_id' => $locked->academic_cycle_id,
                'topic' => $locked->topic,
                'from_status' => $priorStatus,
                'to_status' => $status,
            ], (string) $actor->id);

            $operation->forceFill(['payload' => [...$operation->payload, 'receipt_thread_id' => $locked->id]])->save();

            return $locked->refresh();
        });
    }

    private function manageStateCapability(string $topic): string
    {
        return match ($topic) {
            ProgramGovernanceThread::TOPIC_BENCHMARK => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_MANAGE_STATE,
            ProgramGovernanceThread::TOPIC_QUOTA => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_MANAGE_STATE,
            ProgramGovernanceThread::TOPIC_ADVISING => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_MANAGE_STATE,
            default => throw new RuntimeException('Discussion topic is invalid.'),
        };
    }
}
