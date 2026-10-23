<?php

namespace App\AdmissionEngine\Actions;

use App\Admission\Support\CommandIdempotency;
use App\AdmissionEngine\Support\BenchmarkRangeAuthoring;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class RestoreProgramBenchmarkDraftRevision
{
    public function __construct(
        private GovernanceAuthorizer $authorization,
        private CommandIdempotency $idempotency,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Program $program, AcademicCycle $cycle, AdmissionBenchmarkRevision $source, int $expectedVersion, string $reason, string $idempotencyKey): AdmissionBenchmarkRevision
    {
        $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $program, $cycle, true);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500 || $expectedVersion < 0 || $idempotencyKey === '' || mb_strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('Benchmark restore requires a reason, expected version, and idempotency key.');
        }

        return DB::transaction(function () use ($actor, $program, $cycle, $source, $expectedVersion, $reason, $idempotencyKey): AdmissionBenchmarkRevision {
            $lockedCycle = AcademicCycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $lockedProgram = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $lockedProgram, $lockedCycle, true);
            $draft = AdmissionBenchmarkDraft::query()->where('program_id', $lockedProgram->id)->where('academic_cycle_id', $lockedCycle->id)->lockForUpdate()->firstOrFail();
            $revision = AdmissionBenchmarkRevision::query()->whereKey($source->id)->where('draft_id', $draft->id)->firstOrFail();
            $payload = ['source_revision_id' => $revision->id, 'expected_version' => $expectedVersion, 'reason' => $reason];
            $operation = $this->idempotency->claim($actor, 'benchmark.draft.restore', (string) $draft->id, $payload, $idempotencyKey);
            if (isset($operation->payload['revision_id'])) {
                return AdmissionBenchmarkRevision::query()->whereKey($operation->payload['revision_id'])->firstOrFail();
            }
            if ((int) $draft->current_version !== $expectedVersion) {
                throw new RuntimeException('Benchmark draft version conflict.');
            }
            $baseId = AdmissionBenchmarkActivation::currentForCycle((int) $lockedCycle->id)?->benchmark_set_id;
            $base = $baseId === null ? null : AdmissionBenchmarkSet::query()->with('features')->findOrFail($baseId);
            if ($base === null || (int) $draft->base_benchmark_set_id !== (int) $base->id || ! hash_equals((string) $draft->feature_schema_digest, (string) $base->schema_sha256)) {
                throw new RuntimeException('Published benchmark base or schema changed; restore is no longer valid.');
            }
            BenchmarkRangeAuthoring::assertCanonical($revision->content, $base->features->pluck('logical_type', 'feature_key')->all());
            $current = $draft->currentRevision();
            $restored = AdmissionBenchmarkRevision::query()->create([
                'draft_id' => $draft->id,
                'base_benchmark_set_id' => $draft->base_benchmark_set_id,
                'feature_schema_digest' => $draft->feature_schema_digest,
                'version' => $expectedVersion + 1,
                'actor_id' => $actor->id,
                'old_content' => $current?->content,
                'content' => $revision->content,
                'reason' => $reason,
                'predecessor_revision_id' => $current?->id,
                'restored_from_revision_id' => $revision->id,
                'command_receipt' => (string) $operation->id,
            ]);
            $draft->forceFill(['current_version' => $expectedVersion + 1])->save();
            $operation->forceFill(['payload' => [...$operation->payload, 'revision_id' => $restored->id]])->save();
            $this->audit->handle('admission_benchmark.draft.restored', ['draft_id' => $draft->id, 'revision_id' => $restored->id, 'source_revision_id' => $revision->id, 'version' => $restored->version, 'reason' => $reason], (string) $actor->id);

            return $restored;
        });
    }
}
