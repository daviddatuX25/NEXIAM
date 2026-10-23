<?php

namespace App\AdmissionEngine\Actions;

use App\Admission\Support\CommandIdempotency;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\BenchmarkRangeAuthoring;
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

final readonly class SaveProgramBenchmarkDraft
{
    public function __construct(
        private GovernanceAuthorizer $authorization,
        private CommandIdempotency $idempotency,
        private RecordAuditEvent $audit,
        private KnownHistoricalBenchmarkRecovery $recovery,
    ) {}

    /** @param array<string, mixed> $content */
    public function handle(User $actor, Program $program, AcademicCycle $cycle, array $content, int $expectedVersion, string $reason, string $idempotencyKey): AdmissionBenchmarkRevision
    {
        $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $program, $cycle, true);
        $normalized = $this->normalizeContent($content);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500 || $expectedVersion < 0 || $idempotencyKey === '' || mb_strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('Benchmark draft requires a reason, expected version, and idempotency key.');
        }

        return DB::transaction(function () use ($actor, $program, $cycle, $normalized, $expectedVersion, $reason, $idempotencyKey): AdmissionBenchmarkRevision {
            $freshCycle = AcademicCycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $freshProgram = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $freshProgram, $freshCycle, true);

            $currentBaseSetId = AdmissionBenchmarkActivation::currentForCycle((int) $freshCycle->id)?->benchmark_set_id;
            $currentBase = $currentBaseSetId === null ? null : AdmissionBenchmarkSet::query()->with('features')->findOrFail($currentBaseSetId);
            $canonicalDefinitions = $currentBaseSetId === null ? BenchmarkInputSchema::canonicalFeatureDefinitions() : null;
            $draft = AdmissionBenchmarkDraft::query()->firstOrCreate(
                ['institution_id' => $freshProgram->institution_id, 'program_id' => $freshProgram->id, 'academic_cycle_id' => $freshCycle->id],
                [
                    'base_benchmark_set_id' => $currentBaseSetId,
                    'feature_schema_json' => $canonicalDefinitions,
                    'feature_schema_digest' => (string) ($currentBase?->schema_sha256 ?? ($canonicalDefinitions !== null ? BenchmarkInputSchema::digest($canonicalDefinitions) : hash('sha256', '[]'))),
                    'current_version' => 0,
                ],
            );
            $draft = AdmissionBenchmarkDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($draft->base_benchmark_set_id === null && $draft->feature_schema_json === null) {
                $draft->update([
                    'feature_schema_json' => BenchmarkInputSchema::canonicalFeatureDefinitions(),
                    'feature_schema_digest' => BenchmarkInputSchema::digest(BenchmarkInputSchema::canonicalFeatureDefinitions()),
                ]);
            }
            $baseSetId = $draft->base_benchmark_set_id;
            $schema = BenchmarkInputSchema::schemaForDraft($draft);
            $schemaDigest = $draft->feature_schema_json === null
                ? (string) $draft->feature_schema_digest
                : BenchmarkInputSchema::digest($schema);
            BenchmarkInputSchema::assertCanonicalFeatureSchema($schema);
            $recovery = $draft->recovery_id === null
                ? null
                : AdmissionBenchmarkRecovery::query()->whereKey($draft->recovery_id)->firstOrFail();
            $base = $baseSetId === null
                ? null
                : AdmissionBenchmarkSet::query()->with('features')->findOrFail($baseSetId);

            if ($recovery !== null) {
                if ((int) $recovery->source_benchmark_set_id !== (int) $baseSetId
                    || (int) $recovery->institution_id !== (int) $freshProgram->institution_id
                    || (int) $recovery->academic_cycle_id !== (int) $freshCycle->id) {
                    throw new RuntimeException('Recovery draft is outside its frozen source scope.');
                }
                if (! in_array((int) $freshProgram->id, $this->recovery->retainedProgramIds($recovery, $base), true)) {
                    throw new RuntimeException('Recovery draft program is outside its frozen approved review lineage.');
                }
                $this->recovery->assertUnchanged($recovery, $freshCycle);
                $schemaDigest = BenchmarkInputSchema::digest($schema);
            } elseif ($base !== null) {
                $base = AdmissionBenchmarkSet::query()->with('features')->findOrFail($baseSetId);
            }

            if ($base !== null) {
                $types = collect($schema)->pluck('logical_type', 'feature_key')->all();
                $normalized = BenchmarkRangeAuthoring::apply($normalized, $types);
                $normalized = $this->validateFeatureValues($normalized, $types);
                BenchmarkInputSchema::assertContentSourceBounds($schema, $normalized);
            } elseif ($normalized['tiers'] !== []) {
                throw new InvalidArgumentException('A published benchmark schema is required before saving program cells.');
            }

            $baseMatches = $recovery !== null
                ? $base !== null && $this->recovery->isKnownHistoricalSet($base)
                    && hash_equals((string) $recovery->source_snapshot_sha256, $this->recovery->digest($this->recovery->sourceSnapshot($base)))
                : ($base === null
                    ? $currentBase === null
                    : hash_equals((string) $base->schema_sha256, $base->computedSchemaDigest())
                        && hash_equals((string) $base->content_sha256, $base->computedContentDigest()));
            $expectedSchemaDigest = $draft->feature_schema_json === null
                ? (string) ($currentBase?->schema_sha256 ?? hash('sha256', '[]'))
                : BenchmarkInputSchema::digest($schema);
            if ((int) $draft->base_benchmark_set_id !== (int) $currentBaseSetId
                || ! hash_equals((string) $draft->feature_schema_digest, $expectedSchemaDigest)
                || ! $baseMatches) {
                throw new RuntimeException('Published benchmark base or feature schema changed; rebase the draft before saving.');
            }

            $payload = ['content' => $normalized, 'expected_version' => $expectedVersion, 'reason' => $reason, 'base_set_id' => $baseSetId, 'schema_digest' => $schemaDigest];
            $operation = $this->idempotency->claim($actor, 'benchmark.draft.save', (string) $draft->id, $payload, $idempotencyKey);
            $priorRevisionId = $operation->payload['revision_id'] ?? null;
            if ($priorRevisionId !== null) {
                return AdmissionBenchmarkRevision::query()->whereKey($priorRevisionId)->firstOrFail();
            }

            if ((int) $draft->current_version !== $expectedVersion) {
                throw new RuntimeException('Benchmark draft version conflict.');
            }
            $previous = $draft->currentRevision();
            BenchmarkRangeAuthoring::assertPreparationBeforeState($normalized, $previous?->content);
            $version = $expectedVersion + 1;
            $revision = AdmissionBenchmarkRevision::query()->create([
                'draft_id' => $draft->id,
                'feature_schema_json' => $schema,
                'base_benchmark_set_id' => $baseSetId,
                'feature_schema_digest' => $schemaDigest,
                'version' => $version,
                'actor_id' => $actor->id,
                'old_content' => $previous?->content,
                'content' => $normalized,
                'reason' => $reason,
                'predecessor_revision_id' => $previous?->id,
                'command_receipt' => (string) $operation->id,
            ]);
            $draft->forceFill(['current_version' => $version])->save();
            $operation->forceFill(['payload' => [...$operation->payload, 'revision_id' => $revision->id]])->save();
            $this->audit->handle('admission_benchmark.draft.saved', ['draft_id' => $draft->id, 'revision_id' => $revision->id, 'version' => $version, 'reason' => $reason, 'recovery_id' => $recovery?->id], (string) $actor->id);

            return $revision;
        });
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeContent(array $content): array
    {
        if (array_diff(array_keys($content), ['tiers', 'authoring']) !== []
            || ! is_array($content['tiers'] ?? null) || count($content['tiers']) !== 5) {
            throw new InvalidArgumentException('Benchmark content must contain exactly five tier rows and optional authoring metadata.');
        }
        $tiers = [];
        foreach (range(1, 5) as $tier) {
            $row = $content['tiers'][(string) $tier] ?? $content['tiers'][$tier] ?? null;
            if (! is_array($row) || array_diff(array_keys($row), ['values']) !== [] || ! is_array($row['values'] ?? null)) {
                throw new InvalidArgumentException('Each benchmark tier must contain only a values map.');
            }
            $values = $row['values'];
            ksort($values);
            foreach ($values as $value) {
                if ($value !== null && ! is_int($value) && ! is_float($value) && ! is_string($value) && ! is_bool($value)) {
                    throw new InvalidArgumentException('Benchmark feature cells must be scalar values or explicit nulls.');
                }
            }
            $tiers[(string) $tier] = ['values' => $values];
        }
        if (count(array_diff(array_map('strval', array_keys($content['tiers'])), ['1', '2', '3', '4', '5'])) !== 0) {
            throw new InvalidArgumentException('Benchmark content contains an unknown tier.');
        }
        $normalized = ['tiers' => $tiers];
        if (array_key_exists('authoring', $content)) {
            if (! is_array($content['authoring'])) {
                throw new InvalidArgumentException('Benchmark authoring metadata must be a versioned object.');
            }
            $normalized['authoring'] = $content['authoring'];
        }

        return $normalized;
    }

    /** @param array{tiers: array<string, array{values: array<string, mixed}>} $content */
    private function hasNegativeAnchor(array $content): bool
    {
        return collect($content['tiers']['5']['values'] ?? [])->contains(static fn ($value): bool => $value !== null
            && $value !== ''
            && $value !== false
            && ((is_string($value) && ! is_numeric($value)) || (is_numeric($value) && is_finite((float) $value) && (float) $value !== 0.0)));
    }

    /** @param array<string, array{values: array<string, mixed>}> $content @param array<string, string> $types @return array{tiers: array<string, array{values: array<string, mixed>}>} */
    private function validateFeatureValues(array $content, array $types): array
    {
        foreach ($content['tiers'] as $tierKey => $tier) {
            if (array_diff(array_keys($tier['values']), array_keys($types)) !== []) {
                throw new InvalidArgumentException('Benchmark content contains a feature outside the active schema.');
            }
            if (array_diff(array_keys($types), array_keys($tier['values'])) !== []) {
                throw new InvalidArgumentException('Every benchmark tier must provide an explicit value or null for every schema feature.');
            }
            foreach ($tier['values'] as $key => $value) {
                if ($value === null) {
                    continue;
                }
                $valid = match ($types[$key]) {
                    'numeric' => is_numeric($value) && is_finite((float) $value) && abs((float) $value) <= 9999.99 && $this->isPersistableNumeric($value),
                    'binary' => in_array($value, [0, 1, 0.0, 1.0, false, true, '0', '1'], true),
                    'categorical' => is_string($value) && $value !== '' && mb_strlen($value) <= 64,
                    default => false,
                };
                if (! $valid) {
                    throw new InvalidArgumentException("Benchmark value for {$key} does not match its feature type.");
                }
                if ($types[$key] === 'binary') {
                    $content['tiers'][$tierKey]['values'][$key] = (int) (bool) $value;
                }
            }
        }

        return $content;
    }

    private function isPersistableNumeric(mixed $value): bool
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            return false;
        }

        return (float) number_format((float) $value, 2, '.', '') === (float) $value;
    }
}
