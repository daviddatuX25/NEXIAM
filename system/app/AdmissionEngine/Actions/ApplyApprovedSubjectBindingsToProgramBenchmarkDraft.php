<?php

namespace App\AdmissionEngine\Actions;

use App\Admission\Support\CommandIdempotency;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\AdmissionBenchmarkSubjectBinding;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class ApplyApprovedSubjectBindingsToProgramBenchmarkDraft
{
    public function __construct(
        private GovernanceAuthorizer $authorization,
        private SaveProgramBenchmarkDraft $save,
        private RecordAuditEvent $audit,
        private CommandIdempotency $idempotency,
    ) {}

    /** @param list<int> $bindingIds */
    public function handle(
        User $actor,
        Program $program,
        AcademicCycle $cycle,
        array $bindingIds,
        int $expectedVersion,
        string $reason,
        string $idempotencyKey,
    ): AdmissionBenchmarkRevision {
        $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $program, $cycle, true);
        $reason = trim($reason);
        if ($bindingIds === [] || count($bindingIds) !== count(array_unique($bindingIds)) || $expectedVersion < 1
            || $reason === '' || mb_strlen($reason) > 500 || $idempotencyKey === '' || mb_strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('Applying a subject schema requires selected approved bindings, a saved draft version, reason, and idempotency key.');
        }

        sort($bindingIds);

        return DB::transaction(function () use ($actor, $program, $cycle, $bindingIds, $expectedVersion, $reason, $idempotencyKey): AdmissionBenchmarkRevision {
            $lockedCycle = AcademicCycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $lockedProgram = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();
            $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT, $lockedProgram, $lockedCycle, true);

            $draft = AdmissionBenchmarkDraft::query()
                ->where('institution_id', $lockedProgram->institution_id)
                ->where('program_id', $lockedProgram->id)
                ->where('academic_cycle_id', $lockedCycle->id)
                ->lockForUpdate()
                ->firstOrFail();
            $request = [
                'cycle_id' => (int) $lockedCycle->id,
                'program_id' => (int) $lockedProgram->id,
                'binding_ids' => $bindingIds,
                'expected_version' => $expectedVersion,
                'reason' => $reason,
            ];
            $operation = $this->idempotency->claim($actor, 'benchmark.draft.apply-subject-bindings', (string) $draft->id, $request, $idempotencyKey);
            if (isset($operation->payload['revision_id'])) {
                return AdmissionBenchmarkRevision::query()->where('draft_id', $draft->id)->whereKey($operation->payload['revision_id'])->firstOrFail();
            }
            $activation = AdmissionBenchmarkActivation::currentForCycle((int) $lockedCycle->id);
            if ($activation === null || (int) $activation->institution_id !== (int) $lockedProgram->institution_id) {
                throw new RuntimeException('An active published benchmark is required before applying approved subject bindings.');
            }
            $base = AdmissionBenchmarkSet::query()->with('features')->whereKey($activation->benchmark_set_id)->firstOrFail();
            if (! hash_equals((string) $base->schema_sha256, $base->computedSchemaDigest())
                || ! hash_equals((string) $base->content_sha256, $base->computedContentDigest())) {
                throw new RuntimeException('The current benchmark base failed integrity validation.');
            }
            if ((int) $draft->current_version !== $expectedVersion || (int) $draft->base_benchmark_set_id !== (int) $base->id) {
                throw new RuntimeException('The saved draft or active benchmark base changed; refresh before applying a subject schema.');
            }
            $current = $draft->currentRevision();
            if (! $current instanceof AdmissionBenchmarkRevision) {
                throw new RuntimeException('A saved benchmark revision is required before a schema successor can be applied.');
            }
            if (! hash_equals((string) $current->feature_schema_digest, (string) $draft->feature_schema_digest)) {
                throw new RuntimeException('The current draft revision schema does not match its draft root.');
            }

            $schema = BenchmarkInputSchema::schemaForDraft($draft, $current);
            $existingKeys = array_fill_keys(array_column($schema, 'feature_key'), true);
            $available = collect(BenchmarkInputSchema::availableBindings((int) $lockedProgram->institution_id))->keyBy('binding_id');
            $bindings = AdmissionBenchmarkSubjectBinding::query()
                ->with('sourceField')
                ->where('institution_id', $lockedProgram->institution_id)
                ->whereIn('id', $bindingIds)
                ->orderBy('feature_key')
                ->get();
            if ($bindings->count() !== count($bindingIds)) {
                throw new InvalidArgumentException('One or more selected subject bindings are unavailable in this institution.');
            }
            foreach ($bindings as $binding) {
                if (! $available->has((int) $binding->id)) {
                    throw new RuntimeException('A selected source field changed after its binding was approved. Re-approve the source definition before use.');
                }
                if (isset($existingKeys[$binding->feature_key])) {
                    throw new InvalidArgumentException('The selected feature key is already present in the draft schema.');
                }
                $schema[] = BenchmarkInputSchema::bindingDefinition($binding, count($schema) + 1);
                $existingKeys[$binding->feature_key] = true;
            }
            $digest = BenchmarkInputSchema::digest($schema);
            $content = $current->content;
            foreach (range(1, 5) as $tier) {
                foreach ($bindings as $binding) {
                    $content['tiers'][(string) $tier]['values'][$binding->feature_key] = null;
                }
            }
            $draft->forceFill([
                'feature_schema_json' => $schema,
                'feature_schema_digest' => $digest,
            ])->save();
            $revision = $this->save->handle(
                $actor,
                $lockedProgram,
                $lockedCycle,
                $content,
                $expectedVersion,
                $reason,
                'schema-successor:'.hash('sha256', $idempotencyKey),
            );
            $operation->forceFill(['payload' => [...$operation->payload, 'revision_id' => $revision->id]])->save();
            $this->audit->handle('admission_benchmark.draft.schema_applied', [
                'draft_id' => $draft->id,
                'revision_id' => $revision->id,
                'version' => $revision->version,
                'base_benchmark_set_id' => $base->id,
                'previous_schema_digest' => $current->feature_schema_digest,
                'schema_digest' => $digest,
                'binding_ids' => $bindings->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
                'reason' => $reason,
            ], (string) $actor->id);

            return $revision;
        });
    }
}
