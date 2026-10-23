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

final readonly class RebaseProgramBenchmarkDraft
{
    public function __construct(
        private GovernanceAuthorizer $authorization,
        private CommandIdempotency $idempotency,
        private RecordAuditEvent $audit,
        private SaveProgramBenchmarkDraft $save,
        private KnownHistoricalBenchmarkRecovery $recovery,
    ) {}

    public function handle(User $actor, Program $program, AcademicCycle $cycle, int $expectedVersion, int $baseSetId, string $expectedBaseDigest, string $reason, string $idempotencyKey): AdmissionBenchmarkRevision
    {
        $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $program, $cycle, true);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500 || $expectedVersion < 0 || $baseSetId < 1
            || ! preg_match('/^[a-f0-9]{64}$/', $expectedBaseDigest)
            || $idempotencyKey === '' || mb_strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('Benchmark rebase requires a reason, expected version, exact published base digest, and idempotency key.');
        }

        return DB::transaction(function () use ($actor, $program, $cycle, $expectedVersion, $baseSetId, $expectedBaseDigest, $reason, $idempotencyKey): AdmissionBenchmarkRevision {
            $lockedCycle = AcademicCycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $lockedProgram = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $lockedProgram, $lockedCycle, true);
            $draft = AdmissionBenchmarkDraft::query()
                ->where('institution_id', $lockedProgram->institution_id)
                ->where('program_id', $lockedProgram->id)
                ->where('academic_cycle_id', $lockedCycle->id)
                ->lockForUpdate()
                ->firstOrFail();
            $payload = [
                'expected_version' => $expectedVersion,
                'base_set_id' => $baseSetId,
                'base_content_sha256' => $expectedBaseDigest,
                'reason' => $reason,
            ];
            $operation = $this->idempotency->claim($actor, 'benchmark.draft.rebase', (string) $draft->id, $payload, $idempotencyKey);
            if (isset($operation->payload['revision_id'])) {
                return AdmissionBenchmarkRevision::query()
                    ->where('draft_id', $draft->id)
                    ->whereKey($operation->payload['revision_id'])
                    ->firstOrFail();
            }
            if ((int) $draft->current_version !== $expectedVersion) {
                throw new RuntimeException('Benchmark draft version conflict.');
            }
            $activation = AdmissionBenchmarkActivation::currentForCycle((int) $lockedCycle->id);
            if ($activation === null || (int) $activation->institution_id !== (int) $lockedProgram->institution_id
                || (int) $activation->benchmark_set_id !== $baseSetId) {
                throw new RuntimeException('Current benchmark activation changed; refresh before rebasing the draft.');
            }
            $base = AdmissionBenchmarkSet::query()
                ->with('features')
                ->where('institution_id', $lockedProgram->institution_id)
                ->where('status', 'published')
                ->whereKey($baseSetId)
                ->firstOrFail();
            if (! hash_equals((string) $base->content_sha256, $expectedBaseDigest)
                || ! hash_equals((string) $base->content_sha256, $base->computedContentDigest())
                || ! hash_equals((string) $base->schema_sha256, $base->computedSchemaDigest())) {
                throw new RuntimeException('Published benchmark base conflict: content or schema digest does not match its immutable stored content.');
            }
            $recovered = $draft->recovery_id === null
                ? null
                : $this->assertRecoverySuccessor($draft, $base, $lockedCycle, $lockedProgram);
            if ((int) $draft->base_benchmark_set_id === $baseSetId && $recovered === null) {
                throw new RuntimeException('The benchmark draft is already pinned to the requested published base.');
            }
            $current = $draft->currentRevision();
            if ($current === null) {
                throw new RuntimeException('A saved benchmark draft revision is required before rebasing.');
            }
            if (($current->feature_schema_digest !== null
                && ! hash_equals((string) $current->feature_schema_digest, (string) $draft->feature_schema_digest))
                || ($current->base_benchmark_set_id !== null
                    && (int) $current->base_benchmark_set_id !== (int) $draft->base_benchmark_set_id)) {
                throw new RuntimeException('The current draft revision has a pinned base or feature schema conflict.');
            }
            $oldSchema = BenchmarkInputSchema::schemaForDraft($draft, $current);
            $newSchema = BenchmarkInputSchema::schemaForSet($base);
            if (count($newSchema) < count($oldSchema)
                || ! hash_equals((string) $draft->feature_schema_digest, BenchmarkInputSchema::digest($oldSchema))
                || ! hash_equals((string) $base->schema_sha256, BenchmarkInputSchema::digest(array_slice($newSchema, 0, count($oldSchema))))) {
                throw new RuntimeException('The published successor schema must preserve the exact current draft schema before rebase.');
            }
            if ($recovered !== null && ! hash_equals((string) $draft->feature_schema_digest, (string) $base->schema_sha256)) {
                throw new RuntimeException('Historical recovery schema cannot be widened by the ordinary schema successor rebase.');
            }
            $rebasedContent = $current->content;
            $schemaKeys = array_column($newSchema, 'feature_key');
            foreach (range(1, 5) as $tier) {
                $values = $rebasedContent['tiers'][(string) $tier]['values'];
                if (array_diff(array_keys($values), $schemaKeys) !== []) {
                    throw new RuntimeException('The current draft contains cells outside the selected published successor schema.');
                }
                foreach ($schemaKeys as $featureKey) {
                    $values[$featureKey] ??= null;
                }
                $rebasedContent['tiers'][(string) $tier]['values'] = $values;
            }

            $oldBaseSetId = $draft->base_benchmark_set_id;
            $oldSchemaDigest = $draft->feature_schema_digest;
            $draft->forceFill([
                'base_benchmark_set_id' => $baseSetId,
                'feature_schema_digest' => $base->schema_sha256,
                'feature_schema_json' => $newSchema,
                'recovery_id' => null,
            ])->save();
            $revision = $this->save->handle(
                $actor,
                $lockedProgram,
                $lockedCycle,
                $rebasedContent,
                $expectedVersion,
                $reason,
                'rebase-save:'.hash('sha256', $idempotencyKey),
            );
            $operation->forceFill(['payload' => [...$operation->payload, 'revision_id' => $revision->id]])->save();
            $this->audit->handle('admission_benchmark.draft.rebased', [
                'draft_id' => $draft->id,
                'revision_id' => $revision->id,
                'old_base_benchmark_set_id' => $oldBaseSetId,
                'new_base_benchmark_set_id' => $baseSetId,
                'recovery_id' => $recovered?->id,
                'old_feature_schema_digest' => $oldSchemaDigest,
                'new_feature_schema_digest' => $base->schema_sha256,
                'base_content_sha256' => $expectedBaseDigest,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'old_version' => $expectedVersion,
                'version' => $revision->version,
            ], (string) $actor->id);

            return $revision;
        });
    }

    private function assertRecoverySuccessor(
        AdmissionBenchmarkDraft $draft,
        AdmissionBenchmarkSet $successor,
        AcademicCycle $cycle,
        Program $program,
    ): AdmissionBenchmarkRecovery {
        $recovery = AdmissionBenchmarkRecovery::query()
            ->whereKey($draft->recovery_id)
            ->where('institution_id', $program->institution_id)
            ->where('academic_cycle_id', $cycle->id)
            ->firstOrFail();
        $source = AdmissionBenchmarkSet::query()
            ->with(['features', 'exemplars.program', 'exemplars.values.feature'])
            ->whereKey($recovery->source_benchmark_set_id)
            ->first();
        $sourceSnapshot = $recovery->source_snapshot_json;
        $activationSnapshot = $recovery->source_activation_snapshot_json;
        $sourceActivation = AdmissionBenchmarkActivation::query()->whereKey($recovery->source_activation_id)->first();
        if ($source === null
            || $sourceActivation === null
            || ! is_array($sourceSnapshot)
            || ! is_array($activationSnapshot)
            || ! $this->recovery->isKnownHistoricalSet($source)
            || (int) $source->institution_id !== (int) $program->institution_id
            || ! hash_equals((string) $recovery->source_content_sha256, (string) $source->content_sha256)
            || ! hash_equals((string) $recovery->source_schema_sha256, (string) $source->schema_sha256)
            || ! hash_equals((string) $recovery->current_schema_sha256, $source->computedSchemaDigest())
            || ! hash_equals((string) $recovery->source_snapshot_sha256, $this->recovery->digest($sourceSnapshot))
            || ! hash_equals((string) $recovery->source_snapshot_sha256, $this->recovery->digest($this->recovery->sourceSnapshot($source)))
            || ! hash_equals((string) $recovery->source_activation_sha256, $this->recovery->digest($activationSnapshot))
            || (int) $sourceActivation->institution_id !== (int) $recovery->institution_id
            || (int) $sourceActivation->academic_cycle_id !== (int) $recovery->academic_cycle_id
            || (int) $sourceActivation->benchmark_set_id !== (int) $recovery->source_benchmark_set_id
            || (int) $sourceActivation->sequence !== (int) $recovery->source_activation_sequence) {
            throw new RuntimeException('Historical recovery provenance changed; the successor cannot be used to rebase this draft.');
        }
        $currentActivationSnapshot = $this->recovery->activationSnapshot($sourceActivation);
        foreach (['id', 'institution_id', 'academic_cycle_id', 'benchmark_set_id', 'sequence', 'activated_by_user_id', 'idempotency_key', 'idempotency_payload_sha256', 'expected_current_activation_id', 'activated_at', 'created_at'] as $key) {
            if (($activationSnapshot[$key] ?? null) != $currentActivationSnapshot[$key]) {
                throw new RuntimeException('Historical recovery activation provenance changed; the successor cannot be used to rebase this draft.');
            }
        }
        if (BenchmarkInputSchema::isCanonicalFeatureSchema(BenchmarkInputSchema::schemaForSet($successor))) {
            $manifest = $successor->publication_manifest_json;
            if ((int) $successor->predecessor_id !== (int) $source->id || ! is_array($manifest)) {
                throw new RuntimeException('The canonical successor does not identify the frozen predecessor.');
            }
            unset($manifest['retired_programs']);
            $programIds = array_map('intval', array_keys($manifest));
            sort($programIds);
            if ($programIds !== $this->recovery->retainedProgramIds($recovery, $source)) {
                throw new RuntimeException('The canonical successor does not cover the retained recovery scope.');
            }
            foreach ($manifest as $programId => $entry) {
                $revision = AdmissionBenchmarkRevision::query()
                    ->whereKey($entry['revision_id'] ?? 0)
                    ->whereHas('draft', fn ($query) => $query->where('program_id', $programId)->where('academic_cycle_id', $cycle->id))
                    ->where('base_benchmark_set_id', $source->id)->where('feature_schema_digest', $successor->schema_sha256)->first();
                if ($revision === null || $revision->version !== ($entry['revision_version'] ?? null)
                    || (int) ($entry['source_set_id'] ?? 0) !== (int) $source->id
                    || ! hash_equals((string) $source->content_sha256, (string) ($entry['source_content_sha256'] ?? ''))) {
                    throw new RuntimeException('The canonical successor lost its exact reviewed revision lineage.');
                }
            }

            return $recovery;
        }
        if ((int) $successor->predecessor_id !== (int) $source->id
            || ! is_array($successor->publication_manifest_json)
            || count($successor->publication_manifest_json) !== count($this->recovery->sourceProgramIds($source))) {
            throw new RuntimeException('The current published set is not the exact recovered successor.');
        }

        $sourceProgramIds = $this->recovery->sourceProgramIds($source);
        $drafts = AdmissionBenchmarkDraft::query()
            ->where('institution_id', $program->institution_id)
            ->where('academic_cycle_id', $cycle->id)
            ->whereIn('program_id', $sourceProgramIds)
            ->get()
            ->keyBy('program_id');
        if ($drafts->keys()->map(static fn ($id): int => (int) $id)->sort()->values()->all() !== $sourceProgramIds) {
            throw new RuntimeException('The recovered successor does not cover the exact historical program scope.');
        }
        foreach ($sourceProgramIds as $programId) {
            $entry = $successor->publication_manifest_json[(string) $programId] ?? null;
            $programDraft = $drafts->get($programId);
            $revision = is_array($entry) && $programDraft !== null
                ? AdmissionBenchmarkRevision::query()
                    ->whereKey($entry['revision_id'] ?? 0)
                    ->where('draft_id', $programDraft->id)
                    ->where('base_benchmark_set_id', $source->id)
                    ->where('feature_schema_digest', $recovery->current_schema_sha256)
                    ->first()
                : null;
            if (! is_array($entry)
                || $revision === null
                || (int) ($entry['revision_id'] ?? 0) !== (int) $revision->id
                || (int) ($entry['revision_version'] ?? 0) !== (int) $revision->version
                || (int) ($entry['source_set_id'] ?? 0) !== (int) $source->id
                || (int) ($entry['source_activation_id'] ?? 0) !== (int) $recovery->source_activation_id
                || (int) ($entry['recovery_id'] ?? 0) !== (int) $recovery->id
                || ! hash_equals((string) $source->content_sha256, (string) ($entry['source_content_sha256'] ?? ''))
                || ! hash_equals((string) $recovery->source_snapshot_sha256, (string) ($entry['source_snapshot_digest'] ?? ''))) {
                throw new RuntimeException('The recovered successor manifest does not prove the exact reviewed revision lineage.');
            }
        }

        return $recovery;
    }
}
