<?php

namespace App\AdmissionEngine\Actions;

use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\KnownHistoricalBenchmarkRecovery;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkRecovery;
use App\Models\Admission\AdmissionBenchmarkRecoveryProgramReview;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class StartProgramBenchmarkDraft
{
    public function __construct(
        private GovernanceAuthorizer $authorization,
        private SaveProgramBenchmarkDraft $save,
        private KnownHistoricalBenchmarkRecovery $recovery,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        Program $program,
        AcademicCycle $cycle,
        int $expectedVersion,
        string $reason,
        string $idempotencyKey,
    ): AdmissionBenchmarkRevision {
        $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $program, $cycle, true);
        $program = Program::query()->findOrFail($program->getKey());
        $reason = trim($reason);
        if ($expectedVersion !== 0 || $reason === '' || mb_strlen($reason) > 500 || $idempotencyKey === '' || mb_strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('Starting a benchmark draft requires version zero, a reason, and an idempotency key.');
        }
        if (! $program->is_admission_offered) {
            throw new RuntimeException('A program excluded from the current offering cannot start a new benchmark draft.');
        }

        $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
        if ($activation === null) {
            throw new RuntimeException('An approved benchmark feature schema is required before a program draft can start.');
        }

        $base = AdmissionBenchmarkSet::query()
            ->with('features')
            ->where('institution_id', $program->institution_id)
            ->whereKey($activation->benchmark_set_id)
            ->firstOrFail();
        if ($this->recovery->isKnownHistoricalSet($base)) {
            return $this->startHistorical($actor, $program, $cycle, (int) $base->id, $reason, $idempotencyKey);
        }
        if ($base->features->isEmpty()) {
            throw new RuntimeException('The current benchmark set has no approved feature schema; Bootstrap must provide that prerequisite.');
        }
        if (! hash_equals((string) $base->schema_sha256, $base->computedSchemaDigest())
            || ! hash_equals((string) $base->content_sha256, $base->computedContentDigest())) {
            throw new RuntimeException('The active benchmark base failed integrity validation.');
        }
        BenchmarkInputSchema::assertCanonicalFeatureSchema(BenchmarkInputSchema::schemaForSet($base));

        $content = ['tiers' => []];
        $featureKeys = $base->features->pluck('feature_key')->all();
        foreach (range(1, 5) as $tier) {
            $content['tiers'][(string) $tier] = ['values' => array_fill_keys($featureKeys, null)];
        }

        return $this->save->handle(
            $actor,
            $program,
            $cycle,
            $content,
            $expectedVersion,
            $reason,
            'start-'.hash('sha256', $idempotencyKey),
        );
    }

    private function startHistorical(
        User $actor,
        Program $program,
        AcademicCycle $cycle,
        int $sourceSetId,
        string $reason,
        string $idempotencyKey,
    ): AdmissionBenchmarkRevision {
        return DB::transaction(function () use ($actor, $program, $cycle, $sourceSetId, $reason, $idempotencyKey): AdmissionBenchmarkRevision {
            $lockedCycle = AcademicCycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $lockedProgram = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $lockedProgram, $lockedCycle, true);
            if (! $lockedProgram->is_admission_offered || (int) $lockedProgram->institution_id !== (int) $lockedCycle->institution_id) {
                throw new RuntimeException('Only a currently offered program in the current institution can start a historical-schema review.');
            }

            $activation = AdmissionBenchmarkActivation::currentForCycle((int) $lockedCycle->id);
            if ($activation === null || (int) $activation->benchmark_set_id !== $sourceSetId) {
                throw new RuntimeException('The historical source activation changed before the program review started.');
            }
            $source = AdmissionBenchmarkSet::query()
                ->with(['features', 'exemplars.program', 'exemplars.values.feature'])
                ->whereKey($sourceSetId)
                ->where('institution_id', $lockedProgram->institution_id)
                ->lockForUpdate()
                ->firstOrFail();
            if (! $this->recovery->isKnownHistoricalSet($source)) {
                throw new RuntimeException('The historical source no longer matches the approved source lineage.');
            }
            if ($source->features->isEmpty()) {
                throw new RuntimeException('The frozen historical source has no approved feature schema for a program draft.');
            }

            $recovery = AdmissionBenchmarkRecovery::query()
                ->where('source_benchmark_set_id', $source->id)
                ->where('academic_cycle_id', $lockedCycle->id)
                ->lockForUpdate()
                ->first();
            if ($recovery === null) {
                $sourceSnapshot = $this->recovery->sourceSnapshot($source);
                $activationSnapshot = $this->recovery->activationSnapshot($activation);
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
                    'source_snapshot_json' => $sourceSnapshot,
                    'source_snapshot_sha256' => $this->recovery->digest($sourceSnapshot),
                    'source_activation_snapshot_json' => $activationSnapshot,
                    'source_activation_sha256' => $this->recovery->digest($activationSnapshot),
                    'created_by_user_id' => $actor->id,
                    'reason' => $reason,
                ]);
                $this->audit->handle('admission_benchmark.recovery.snapshotted', [
                    'recovery_id' => $recovery->id,
                    'source_set_id' => $source->id,
                    'source_activation_id' => $activation->id,
                    'source_snapshot_sha256' => $recovery->source_snapshot_sha256,
                    'review_program_id' => $lockedProgram->id,
                    'historical_content_verified' => false,
                    'reason' => $reason,
                ], (string) $actor->id);
            } else {
                $source = $this->recovery->assertUnchanged($recovery, $lockedCycle);
            }

            if (! in_array((int) $lockedProgram->id, $this->recovery->sourceProgramIds($source), true)) {
                $review = AdmissionBenchmarkRecoveryProgramReview::query()
                    ->where('recovery_id', $recovery->id)
                    ->where('program_id', $lockedProgram->id)
                    ->lockForUpdate()
                    ->first();
                if ($review !== null) {
                    if ((int) $review->registered_by_user_id !== (int) $actor->id
                        || $review->reason !== $reason
                        || $review->idempotency_key !== $idempotencyKey) {
                        throw new RuntimeException('Historical program review idempotency key was already used with a different registration.');
                    }
                } else {
                    if (AdmissionBenchmarkRecoveryProgramReview::query()
                        ->where('recovery_id', $recovery->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->exists()) {
                        throw new RuntimeException('Historical program review idempotency key is already bound to another program.');
                    }
                    $review = AdmissionBenchmarkRecoveryProgramReview::query()->create([
                        'recovery_id' => $recovery->id,
                        'program_id' => $lockedProgram->id,
                        'registered_by_user_id' => $actor->id,
                        'reason' => $reason,
                        'idempotency_key' => $idempotencyKey,
                    ]);
                    $this->audit->handle('admission_benchmark.recovery.program_registered', [
                        'recovery_id' => $recovery->id,
                        'review_id' => $review->id,
                        'program_id' => $lockedProgram->id,
                        'source_set_id' => $source->id,
                        'source_snapshot_sha256' => $recovery->source_snapshot_sha256,
                        'reason' => $reason,
                    ], (string) $actor->id);
                }
            }

            $draft = AdmissionBenchmarkDraft::query()
                ->where('institution_id', $lockedProgram->institution_id)
                ->where('program_id', $lockedProgram->id)
                ->where('academic_cycle_id', $lockedCycle->id)
                ->lockForUpdate()
                ->first();
            if ($draft === null) {
                $draft = AdmissionBenchmarkDraft::query()->create([
                    'institution_id' => $lockedProgram->institution_id,
                    'program_id' => $lockedProgram->id,
                    'academic_cycle_id' => $lockedCycle->id,
                    'base_benchmark_set_id' => $source->id,
                    'feature_schema_digest' => $source->computedSchemaDigest(),
                    'recovery_id' => $recovery->id,
                    'current_version' => 0,
                ]);
            } elseif ((int) $draft->base_benchmark_set_id !== (int) $source->id
                || (int) $draft->recovery_id !== (int) $recovery->id) {
                throw new RuntimeException('Existing draft is pinned to a different base or recovery lineage.');
            }

            if ((int) $draft->current_version === 0) {
                $schema = BenchmarkInputSchema::canonicalSchema();
                $draft->forceFill([
                    'feature_schema_json' => $schema,
                    'feature_schema_digest' => BenchmarkInputSchema::digest($schema),
                ])->save();
            }
            $content = ['tiers' => []];
            $featureKeys = array_column(BenchmarkInputSchema::canonicalSchema(), 'feature_key');
            foreach (range(1, 5) as $tier) {
                $content['tiers'][(string) $tier] = ['values' => array_fill_keys($featureKeys, null)];
            }

            return $this->save->handle(
                $actor,
                $lockedProgram,
                $lockedCycle,
                $content,
                0,
                $reason,
                'start-'.hash('sha256', $idempotencyKey),
            );
        });
    }
}
