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
use App\Models\Foundation\Operation;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class CutoverProgramBenchmarkDraftSchema
{
    public function __construct(
        private GovernanceAuthorizer $authorization,
        private CommandIdempotency $idempotency,
        private SaveProgramBenchmarkDraft $save,
        private RecordAuditEvent $audit,
        private KnownHistoricalBenchmarkRecovery $recovery,
        private RecoverHistoricalProgramBenchmarkDraft $recover,
        private StartProgramBenchmarkDraft $start,
    ) {}

    public function handle(
        User $actor,
        Program $program,
        AcademicCycle $cycle,
        int $expectedVersion,
        string $reason,
        string $idempotencyKey,
        string $expectedSourceDigest,
    ): AdmissionBenchmarkRevision {
        $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $program, $cycle, true);
        $reason = trim($reason);
        if ($expectedVersion < 0 || $reason === '' || mb_strlen($reason) > 500 || $idempotencyKey === '' || mb_strlen($idempotencyKey) > 128
            || ! preg_match('/^[a-f0-9]{64}$/', $expectedSourceDigest)) {
            throw new InvalidArgumentException('Schema cutover requires an exact source digest, expected version, reason, and idempotency key.');
        }

        return DB::transaction(function () use ($actor, $program, $cycle, $expectedVersion, $reason, $idempotencyKey, $expectedSourceDigest): AdmissionBenchmarkRevision {
            $lockedCycle = AcademicCycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $lockedProgram = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $lockedProgram, $lockedCycle, true);
            if ((int) $lockedCycle->institution_id !== (int) $lockedProgram->institution_id) {
                throw new InvalidArgumentException('Program and academic cycle must belong to the same institution.');
            }
            $operation = $this->idempotency->claim($actor, 'benchmark.draft.schema-cutover', 'program:'.$lockedProgram->id.':cycle:'.$lockedCycle->id, [
                'program_id' => (int) $lockedProgram->id,
                'cycle_id' => (int) $lockedCycle->id,
                'expected_version' => $expectedVersion,
                'expected_source_digest' => $expectedSourceDigest,
                'reason' => $reason,
            ], $idempotencyKey);
            if (isset($operation->payload['revision_id'])) {
                return AdmissionBenchmarkRevision::query()->whereKey($operation->payload['revision_id'])->firstOrFail();
            }
            $activation = AdmissionBenchmarkActivation::currentForCycle((int) $lockedCycle->id);
            if ($activation === null || (int) $activation->institution_id !== (int) $lockedProgram->institution_id) {
                throw new RuntimeException('A current published benchmark is required for schema cutover.');
            }
            $base = AdmissionBenchmarkSet::query()->with(['features', 'exemplars.program', 'exemplars.values.feature'])->whereKey($activation->benchmark_set_id)->firstOrFail();
            $this->recovery->assertCutoverSourceIntact($base, $lockedCycle, true);
            $historical = $this->recovery->isKnownHistoricalSet($base);
            $sourceDigest = $historical ? $this->recovery->digest($this->recovery->sourceSnapshot($base)) : (string) $base->content_sha256;
            if (! hash_equals($sourceDigest, $expectedSourceDigest)) {
                throw new RuntimeException('The reviewed benchmark source changed before schema cutover.');
            }
            if (BenchmarkInputSchema::isCanonicalFeatureSchema(BenchmarkInputSchema::schemaForSet($base))) {
                throw new InvalidArgumentException('The current benchmark already uses aptitude and GWA inputs.');
            }
            $draft = AdmissionBenchmarkDraft::query()
                ->where('institution_id', $lockedProgram->institution_id)->where('program_id', $lockedProgram->id)
                ->where('academic_cycle_id', $lockedCycle->id)->lockForUpdate()->first();
            $dropped = array_values(array_diff($base->features->pluck('feature_key')->all(), [...BenchmarkInputSchema::canonicalAptitudeCodes(), 'gwa']));
            if ($historical && ($draft === null || (int) $draft->current_version === 0)) {
                if ($expectedVersion !== 0) {
                    throw new RuntimeException('The historical draft version changed before cutover.');
                }
                $key = 'canonical-recovery:'.hash('sha256', $idempotencyKey);
                $revision = in_array((int) $lockedProgram->id, $this->recovery->sourceProgramIds($base), true)
                    ? $this->recover->handle($actor, $lockedProgram, $lockedCycle, 0, $expectedSourceDigest, $reason, $key)
                    : $this->start->handle($actor, $lockedProgram, $lockedCycle, 0, $reason, $key);

                return $this->recordCutover($actor, $operation, $base, $revision, $dropped, $reason);
            }
            $draft ??= AdmissionBenchmarkDraft::query()->create([
                'institution_id' => $lockedProgram->institution_id, 'program_id' => $lockedProgram->id,
                'academic_cycle_id' => $lockedCycle->id, 'base_benchmark_set_id' => $base->id,
                'feature_schema_digest' => (string) $base->schema_sha256, 'current_version' => 0,
            ]);
            if ((int) $draft->current_version !== $expectedVersion || (int) $draft->base_benchmark_set_id !== (int) $base->id) {
                throw new RuntimeException('The draft version or published source changed before cutover.');
            }
            if ($historical) {
                $record = $draft->recovery_id === null ? null : AdmissionBenchmarkRecovery::query()->find($draft->recovery_id);
                if ($record === null) {
                    throw new RuntimeException('The historical draft requires its frozen recovery lineage.');
                }
                $this->recovery->assertUnchanged($record, $lockedCycle);
            }
            $current = $draft->currentRevision();
            if ($current !== null && ! hash_equals((string) $current->feature_schema_digest, (string) $draft->feature_schema_digest)) {
                throw new RuntimeException('The current draft schema no longer matches its saved revision.');
            }
            if ($current !== null && BenchmarkInputSchema::isCanonicalFeatureSchema(BenchmarkInputSchema::schemaForDraft($draft, $current))) {
                throw new InvalidArgumentException('This draft already uses aptitude and GWA inputs.');
            }
            $schema = BenchmarkInputSchema::canonicalSchema();
            $content = $this->canonicalContent($current?->content, $base, (int) $lockedProgram->id);
            $draft->forceFill(['feature_schema_json' => $schema, 'feature_schema_digest' => BenchmarkInputSchema::digest($schema)])->save();
            $revision = $this->save->handle($actor, $lockedProgram, $lockedCycle, $content, $expectedVersion, $reason, 'schema-cutover:'.hash('sha256', $idempotencyKey));

            return $this->recordCutover($actor, $operation, $base, $revision, $dropped, $reason);
        });
    }

    /** @param list<string> $dropped */
    private function recordCutover(User $actor, Operation $operation, AdmissionBenchmarkSet $source, AdmissionBenchmarkRevision $revision, array $dropped, string $reason): AdmissionBenchmarkRevision
    {
        $operation->forceFill(['payload' => [...$operation->payload, 'revision_id' => (int) $revision->id]])->save();
        $this->audit->handle('admission_benchmark.draft.schema_cutover', [
            'draft_id' => (int) $revision->draft_id, 'revision_id' => (int) $revision->id,
            'source_set_id' => (int) $source->id, 'source_schema_digest' => (string) $source->schema_sha256,
            'target_schema_digest' => (string) $revision->feature_schema_digest,
            'excluded_feature_keys' => $dropped, 'reason' => $reason,
        ], (string) $actor->id);

        return $revision;
    }

    /** @param array<string, mixed>|null $previous @return array<string, mixed> */
    private function canonicalContent(?array $previous, AdmissionBenchmarkSet $base, int $programId): array
    {
        if ($previous === null) {
            $previous = ['tiers' => []];
            foreach ($base->exemplars->where('program_id', $programId) as $exemplar) {
                $values = [];
                foreach ($exemplar->values as $value) {
                    $values[$value->feature->feature_key] = $value->is_missing ? null : $value->numeric_value;
                }
                $previous['tiers'][(string) $exemplar->tier_level] = ['values' => $values];
            }
        }

        return BenchmarkInputSchema::canonicalDraftContent($previous);
    }
}
