<?php

namespace App\AdmissionEngine\Actions;

use App\Admission\Support\CommandIdempotency;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\KnownHistoricalBenchmarkRecovery;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkRecovery;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class RecoverHistoricalProgramBenchmarkDraft
{
    public function __construct(
        private GovernanceAuthorizer $authorization,
        private CommandIdempotency $idempotency,
        private RecordAuditEvent $audit,
        private KnownHistoricalBenchmarkRecovery $recovery,
        private SaveProgramBenchmarkDraft $saveDraft,
    ) {}

    public function handle(
        User $actor,
        Program $program,
        AcademicCycle $cycle,
        int $expectedVersion,
        string $expectedSourceDigest,
        string $reason,
        string $idempotencyKey,
    ): AdmissionBenchmarkRevision {
        $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $program, $cycle, true);
        $reason = trim($reason);
        if ($expectedVersion !== 0 || ! preg_match('/^[a-f0-9]{64}$/', $expectedSourceDigest)
            || $reason === '' || mb_strlen($reason) > 500 || $idempotencyKey === '' || mb_strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('Historical recovery requires the initial draft version, current source digest, reason, and idempotency key.');
        }

        return DB::transaction(function () use ($actor, $program, $cycle, $expectedVersion, $expectedSourceDigest, $reason, $idempotencyKey): AdmissionBenchmarkRevision {
            $lockedCycle = AcademicCycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $lockedProgram = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $lockedProgram, $lockedCycle, true);
            if ((int) $lockedProgram->institution_id !== (int) $lockedCycle->institution_id) {
                throw new RuntimeException('Historical recovery program and cycle are outside one institution.');
            }

            $activation = AdmissionBenchmarkActivation::currentForCycle((int) $lockedCycle->id);
            if ($activation === null) {
                throw new RuntimeException('There is no current historical benchmark activation to recover.');
            }
            $source = AdmissionBenchmarkSet::query()
                ->with(['features', 'exemplars.program', 'exemplars.values.feature'])
                ->where('institution_id', $lockedProgram->institution_id)
                ->whereKey($activation->benchmark_set_id)
                ->lockForUpdate()
                ->firstOrFail();
            if (! $this->recovery->isKnownHistoricalSet($source)) {
                throw new RuntimeException('The active benchmark set is not the recognized historical schema; recovery is unavailable.');
            }
            if (! in_array((int) $lockedProgram->id, $this->recovery->sourceProgramIds($source), true)) {
                throw new RuntimeException('The selected program is not part of the historical source set.');
            }
            $snapshot = $this->recovery->sourceSnapshot($source);
            $snapshotDigest = $this->recovery->digest($snapshot);
            $activationSnapshot = $this->recovery->activationSnapshot($activation);
            $activationDigest = $this->recovery->digest($activationSnapshot);
            if (! hash_equals($expectedSourceDigest, $snapshotDigest)) {
                throw new RuntimeException('Historical benchmark source changed; refresh the context before recovery.');
            }

            $recovery = AdmissionBenchmarkRecovery::query()
                ->where('source_benchmark_set_id', $source->id)
                ->where('academic_cycle_id', $lockedCycle->id)
                ->lockForUpdate()
                ->first();
            if ($recovery === null) {
                $recovery = AdmissionBenchmarkRecovery::query()->create([
                    'institution_id' => $lockedProgram->institution_id,
                    'academic_cycle_id' => $lockedCycle->id,
                    'source_benchmark_set_id' => $source->id,
                    'source_activation_id' => $activation->id,
                    'source_version' => $source->version,
                    'source_content_sha256' => $source->content_sha256,
                    'source_schema_sha256' => $source->schema_sha256,
                    'current_schema_sha256' => $source->computedSchemaDigest(),
                    'source_activation_sequence' => $activation->sequence,
                    'source_snapshot_json' => $snapshot,
                    'source_snapshot_sha256' => $snapshotDigest,
                    'source_activation_snapshot_json' => $activationSnapshot,
                    'source_activation_sha256' => $activationDigest,
                    'created_by_user_id' => $actor->id,
                    'reason' => $reason,
                ]);
                $this->audit->handle('admission_benchmark.recovery.snapshotted', [
                    'recovery_id' => $recovery->id,
                    'source_set_id' => $source->id,
                    'source_version' => $source->version,
                    'source_content_sha256' => $source->content_sha256,
                    'source_schema_sha256' => $source->schema_sha256,
                    'source_snapshot_sha256' => $snapshotDigest,
                    'source_activation_id' => $activation->id,
                    'source_activation_sha256' => $activationDigest,
                    'institution_id' => $lockedProgram->institution_id,
                    'academic_cycle_id' => $lockedCycle->id,
                    'reason' => $reason,
                    'historical_content_verified' => false,
                ], (string) $actor->id);
            } else {
                $source = $this->recovery->assertUnchanged($recovery, $lockedCycle);
                if (! hash_equals((string) $recovery->source_snapshot_sha256, $expectedSourceDigest)) {
                    throw new RuntimeException('Historical recovery snapshot digest does not match the current source.');
                }
            }

            $payload = [
                'program_id' => $lockedProgram->id,
                'cycle_id' => $lockedCycle->id,
                'recovery_id' => $recovery->id,
                'expected_version' => $expectedVersion,
                'expected_source_digest' => $expectedSourceDigest,
                'reason' => $reason,
            ];
            $operation = $this->idempotency->claim($actor, 'benchmark.draft.recover', (string) $lockedProgram->id, $payload, $idempotencyKey);
            if (isset($operation->payload['revision_id'])) {
                return AdmissionBenchmarkRevision::query()->whereKey($operation->payload['revision_id'])->firstOrFail();
            }

            $draft = AdmissionBenchmarkDraft::query()
                ->where('institution_id', $lockedProgram->institution_id)
                ->where('program_id', $lockedProgram->id)
                ->where('academic_cycle_id', $lockedCycle->id)
                ->lockForUpdate()
                ->first();
            if ($draft !== null && ((int) $draft->current_version !== 0 || $draft->recovery_id !== null)) {
                throw new RuntimeException('This program already has a benchmark draft; recovery cannot overwrite it.');
            }
            if ($draft !== null) {
                $draft->forceFill([
                    'base_benchmark_set_id' => $source->id,
                    'feature_schema_digest' => $source->computedSchemaDigest(),
                    'recovery_id' => $recovery->id,
                ])->save();
            } else {
                $draft = AdmissionBenchmarkDraft::query()->create([
                    'institution_id' => $lockedProgram->institution_id,
                    'program_id' => $lockedProgram->id,
                    'academic_cycle_id' => $lockedCycle->id,
                    'base_benchmark_set_id' => $source->id,
                    'feature_schema_digest' => $source->computedSchemaDigest(),
                    'recovery_id' => $recovery->id,
                    'current_version' => 0,
                ]);
            }
            $schema = BenchmarkInputSchema::canonicalSchema();
            $draft->forceFill([
                'feature_schema_json' => $schema,
                'feature_schema_digest' => BenchmarkInputSchema::digest($schema),
            ])->save();

            $saveKey = 'recovery-'.hash('sha256', (string) $operation->id.':'.$idempotencyKey);
            $revision = $this->saveDraft->handle(
                $actor,
                $lockedProgram,
                $lockedCycle,
                BenchmarkInputSchema::canonicalDraftContent($this->recovery->mappedContent($source, (int) $lockedProgram->id)),
                $expectedVersion,
                $reason,
                $saveKey,
            );
            $operation->forceFill(['payload' => [...$operation->payload, 'revision_id' => $revision->id]])->save();
            $this->audit->handle('admission_benchmark.recovery.draft_created', [
                'recovery_id' => $recovery->id,
                'draft_id' => $draft->id,
                'revision_id' => $revision->id,
                'program_id' => $lockedProgram->id,
                'source_set_id' => $source->id,
                'source_snapshot_sha256' => $recovery->source_snapshot_sha256,
                'reason' => $reason,
                'historical_content_verified' => false,
            ], (string) $actor->id);

            return $revision;
        });
    }
}
