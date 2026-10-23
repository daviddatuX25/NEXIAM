<?php

namespace App\Admission\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\ProgramGovernanceThread;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuotaChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OpenProgramGovernanceDiscussion
{
    public function __construct(
        private readonly GovernanceAuthorizer $authorizer,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        User $actor,
        Program $program,
        AcademicCycle $cycle,
        string $topic,
        string $subject,
        string $idempotencyKey,
        ?int $admissionBenchmarkRevisionId = null,
        ?int $quotaChangeId = null,
    ): ProgramGovernanceThread {
        if (! in_array($topic, ProgramGovernanceThread::TOPICS, true)) {
            throw new InvalidArgumentException('Unsupported governance discussion topic.');
        }

        $subject = trim($subject);
        if ($subject === '' || mb_strlen($subject) > 255) {
            throw new InvalidArgumentException('Discussion subject must contain 1 to 255 characters.');
        }

        if (($admissionBenchmarkRevisionId !== null && $topic !== ProgramGovernanceThread::TOPIC_BENCHMARK)
            || ($quotaChangeId !== null && $topic !== ProgramGovernanceThread::TOPIC_QUOTA)
            || ($admissionBenchmarkRevisionId !== null && $quotaChangeId !== null)) {
            throw new InvalidArgumentException('Discussion references must match exactly one supported topic.');
        }

        return DB::transaction(function () use ($actor, $program, $cycle, $topic, $subject, $idempotencyKey, $admissionBenchmarkRevisionId, $quotaChangeId): ProgramGovernanceThread {
            $lockedProgram = Program::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();
            $lockedCycle = AcademicCycle::query()->whereKey($cycle->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $lockedProgram->institution_id !== (int) $lockedCycle->institution_id) {
                throw new RuntimeException('Program and academic cycle do not share an institution.');
            }

            $this->authorizer->authorize($actor, $this->readCapability($topic), $lockedProgram, $lockedCycle);
            $this->authorizer->authorize($actor, $this->sendCapability($topic), $lockedProgram, $lockedCycle, true);
            $this->validateReference($topic, $lockedProgram, $lockedCycle, $admissionBenchmarkRevisionId, $quotaChangeId);

            $payload = [
                'institution_id' => (int) $lockedProgram->institution_id,
                'program_id' => (int) $lockedProgram->id,
                'academic_cycle_id' => (int) $lockedCycle->id,
                'topic' => $topic,
                'subject' => $subject,
                'admission_benchmark_revision_id' => $admissionBenchmarkRevisionId,
                'quota_change_id' => $quotaChangeId,
            ];
            $operation = $this->idempotency->claim($actor, 'admission.governance-discussion.open', 'governance-discussion-open', $payload, $idempotencyKey);
            $operation->refresh();
            $receiptThreadId = $operation->payload['receipt_thread_id'] ?? null;
            if ($receiptThreadId !== null) {
                return ProgramGovernanceThread::query()
                    ->where('institution_id', $lockedProgram->institution_id)
                    ->where('program_id', $lockedProgram->id)
                    ->where('academic_cycle_id', $lockedCycle->id)
                    ->where('topic', $topic)
                    ->findOrFail($receiptThreadId);
            }

            $thread = ProgramGovernanceThread::query()->create([
                ...$payload,
                'status' => ProgramGovernanceThread::STATUS_OPEN,
                'created_by_user_id' => $actor->id,
            ]);

            $this->recordAuditEvent->handle('admission.governance_discussion_opened', [
                'thread_id' => $thread->id,
                'institution_id' => $thread->institution_id,
                'program_id' => $thread->program_id,
                'academic_cycle_id' => $thread->academic_cycle_id,
                'topic' => $thread->topic,
            ], (string) $actor->id);

            $operation->forceFill(['payload' => [...$operation->payload, 'receipt_thread_id' => $thread->id]])->save();

            return $thread;
        });
    }

    private function validateReference(string $topic, Program $program, AcademicCycle $cycle, ?int $benchmarkRevisionId, ?int $quotaChangeId): void
    {
        if ($benchmarkRevisionId !== null) {
            $revision = AdmissionBenchmarkRevision::query()->with('draft')->find($benchmarkRevisionId);
            $draft = $revision?->draft;
            if ($revision === null || $draft === null
                || (int) $draft->institution_id !== (int) $program->institution_id
                || (int) $draft->program_id !== (int) $program->id
                || (int) $draft->academic_cycle_id !== (int) $cycle->id
                || $topic !== ProgramGovernanceThread::TOPIC_BENCHMARK) {
                throw new RuntimeException('Benchmark revision is not available in this discussion context.');
            }
        }

        if ($quotaChangeId !== null) {
            $change = ProgramQuotaChange::query()->with('programQuota')->find($quotaChangeId);
            $quota = $change?->programQuota;
            if ($change === null || $quota === null
                || (int) $quota->program_id !== (int) $program->id
                || (int) $quota->academic_cycle_id !== (int) $cycle->id
                || $topic !== ProgramGovernanceThread::TOPIC_QUOTA) {
                throw new RuntimeException('Quota change is not available in this discussion context.');
            }
        }
    }

    private function readCapability(string $topic): string
    {
        return match ($topic) {
            ProgramGovernanceThread::TOPIC_BENCHMARK => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
            ProgramGovernanceThread::TOPIC_QUOTA => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
            ProgramGovernanceThread::TOPIC_ADVISING => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
        };
    }

    private function sendCapability(string $topic): string
    {
        return match ($topic) {
            ProgramGovernanceThread::TOPIC_BENCHMARK => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
            ProgramGovernanceThread::TOPIC_QUOTA => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
            ProgramGovernanceThread::TOPIC_ADVISING => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_SEND,
        };
    }
}
