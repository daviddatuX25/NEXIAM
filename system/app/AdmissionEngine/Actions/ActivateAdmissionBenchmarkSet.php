<?php

namespace App\AdmissionEngine\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class ActivateAdmissionBenchmarkSet
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private GovernanceAuthorizer $authorization,
        private CommandIdempotency $idempotency,
        private TagudinUndergraduateCatalog $catalog,
    ) {}

    public function handle(
        AcademicCycle $cycle,
        AdmissionBenchmarkSet $benchmarkSet,
        BenchmarkExecutionContext $context,
        ?int $expectedCurrentActivationId,
        string $expectedContentSha256,
        string $idempotencyKey,
    ): AdmissionBenchmarkActivation {
        $set = AdmissionBenchmarkSet::query()->with('exemplars.program')->findOrFail($benchmarkSet->id);
        $institution = $set->institution_id;
        if ($context->trusted) {
            if ($context->institutionId !== (int) $institution) {
                throw new InvalidArgumentException('Trusted benchmark context scope does not match the set institution.');
            }
        } else {
            $this->authorizeGuidance($context->actor, $cycle, $set);
        }

        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 128 || ! preg_match('/^[a-f0-9]{64}$/', $expectedContentSha256)) {
            throw new InvalidArgumentException('Activation requires an exact content digest and idempotency key.');
        }
        $request = ['cycle_id' => $cycle->id, 'set_id' => $set->id, 'content_sha256' => $expectedContentSha256, 'expected_activation_id' => $expectedCurrentActivationId];
        $namespacedKey = ($context->actor?->id ?? 'trusted-'.$context->purpose).':benchmark.activate:'.$institution.':'.$idempotencyKey;
        $requestHash = hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($cycle, $benchmarkSet, $context, $expectedCurrentActivationId, $expectedContentSha256, $idempotencyKey, $namespacedKey, $requestHash, $request): AdmissionBenchmarkActivation {
            $lockedCycle = AcademicCycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $set = AdmissionBenchmarkSet::query()->whereKey($benchmarkSet->id)->lockForUpdate()->firstOrFail();
            if ((int) $set->institution_id !== (int) $lockedCycle->institution_id || $set->status !== 'published') {
                throw new InvalidArgumentException('Only a published benchmark set for the cycle institution can be activated.');
            }
            if ($context->trusted) {
                if ($context->institutionId !== (int) $set->institution_id) {
                    throw new InvalidArgumentException('Trusted benchmark context scope does not match the set institution.');
                }
            } else {
                $this->authorizeGuidance($context->actor, $lockedCycle, $set);
            }

            if (! hash_equals((string) $set->content_sha256, $expectedContentSha256)
                || ! hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())
                || ! $this->contentIsIntact($set)) {
                throw new RuntimeException('Published benchmark content or schema digest does not match its immutable stored content.');
            }

            $existing = AdmissionBenchmarkActivation::query()->where('idempotency_key', $namespacedKey)->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->idempotency_payload_sha256, $requestHash)) {
                    throw new RuntimeException('Activation idempotency key was reused with a different request payload.');
                }

                return $existing;
            }

            $active = AdmissionBenchmarkActivation::query()
                ->where('academic_cycle_id', $lockedCycle->id)
                ->whereNull('superseded_at')
                ->lockForUpdate()
                ->get();
            $currentId = $active->sortByDesc('sequence')->first()?->id;
            if ($currentId !== $expectedCurrentActivationId) {
                throw new RuntimeException('Current benchmark activation changed; refresh before activating.');
            }
            $currentBaseId = $active->sortByDesc('sequence')->first()?->benchmark_set_id;
            if ($currentBaseId !== null
                && (int) $currentBaseId !== (int) $set->id
                && $set->publication_manifest_json === null
                && ! $context->trusted
            ) {
                throw new RuntimeException('A benchmark successor must carry a reviewed publication manifest.');
            }
            if ($set->publication_manifest_json !== null && $currentBaseId === null) {
                throw new RuntimeException('A reviewed successor cannot activate without its current predecessor activation.');
            }
            if ($set->publication_manifest_json !== null
                && $currentBaseId !== null
                && (int) $currentBaseId !== (int) $set->id
                && (int) $set->predecessor_id !== (int) $currentBaseId) {
                throw new RuntimeException('Published benchmark was reviewed against a different active base; create a successor from the latest activation.');
            }
            if ($set->publication_manifest_json !== null && (int) $currentBaseId === (int) $set->id) {
                throw new RuntimeException('The reviewed benchmark is already active; there is no changed program delta to activate.');
            }
            if ($set->publication_manifest_json !== null) {
                $latestBase = AdmissionBenchmarkSet::query()->findOrFail($currentBaseId);
                $actualDeltaProgramIds = $this->deltaEligibleProgramIds($latestBase, $set);
                $this->authorizeProgramIds($context->actor, $lockedCycle, $actualDeltaProgramIds);
            }
            if ($currentBaseId === null && $set->publication_manifest_json === null && ! $context->trusted) {
                $this->authorizeProgramIds($context->actor, $lockedCycle, $this->programIds($set));
            }
            foreach ($active as $previous) {
                $previous->forceFill(['superseded_at' => now()])->save();
            }
            $sequence = (int) AdmissionBenchmarkActivation::query()->where('academic_cycle_id', $lockedCycle->id)->max('sequence') + 1;
            $activation = AdmissionBenchmarkActivation::query()->create([
                'institution_id' => $lockedCycle->institution_id,
                'academic_cycle_id' => $lockedCycle->id,
                'benchmark_set_id' => $set->id,
                'sequence' => $sequence,
                'activated_by_user_id' => $context->actor?->id,
                'idempotency_key' => $namespacedKey,
                'idempotency_payload_sha256' => $requestHash,
                'expected_current_activation_id' => $expectedCurrentActivationId,
                'activated_at' => now(),
            ]);
            if ($context->actor !== null) {
                $operation = $this->idempotency->claim($context->actor, 'benchmark.activate', (string) $lockedCycle->id, $request, $idempotencyKey);
                $operation->forceFill(['payload' => [...$operation->payload, 'activation_id' => $activation->id]])->save();
            }
            $this->recordAuditEvent->handle('admission_benchmark.activated', [
                'activation_id' => $activation->id,
                'benchmark_set_id' => $set->id,
                'academic_cycle_id' => $lockedCycle->id,
                'expected_current_activation_id' => $expectedCurrentActivationId,
                'sequence' => $sequence,
                'content_sha256' => $set->content_sha256,
                'idempotency_key' => $namespacedKey,
            ], (string) ($context->actor?->id ?? 'trusted:'.$context->purpose));

            return $activation;
        });
    }

    private function authorizeGuidance(?User $actor, AcademicCycle $cycle, AdmissionBenchmarkSet $set): void
    {
        if ($actor === null) {
            throw new InvalidArgumentException('Human benchmark activation requires an authenticated actor context.');
        }

        if (is_array($set->publication_manifest_json)) {
            if ($set->predecessor_id === null) {
                throw new InvalidArgumentException('Reviewed benchmark publication has no pinned predecessor.');
            }
            $base = AdmissionBenchmarkSet::query()
                ->whereKey($set->predecessor_id)
                ->where('institution_id', $set->institution_id)
                ->firstOrFail();
            $actualDelta = $this->actualDeltaProgramIds($base, $set);
            $this->assertManifestMatchesDelta($base, $set, $actualDelta);
            $this->authorizeProgramIds($actor, $cycle, $actualDelta);

            return;
        }

        $this->authorizeProgramIds($actor, $cycle, $this->programIds($set));
    }

    /**
     * Recomputes program-level content changes from stored exemplar values, independent of manifest claims.
     *
     * @return list<int>
     */
    public function actualDeltaProgramIds(AdmissionBenchmarkSet $base, AdmissionBenchmarkSet $candidate): array
    {
        $before = $this->programContentDigests($base);
        $after = $this->programContentDigests($candidate);
        $programIds = array_values(array_unique([...array_keys($before), ...array_keys($after)]));
        sort($programIds);

        return array_values(array_filter(
            $programIds,
            static fn (int $programId): bool => ! isset($before[$programId])
                || ! isset($after[$programId])
                || ! hash_equals($before[$programId], $after[$programId]),
        ));
    }

    /**
     * Returns the exact current delta's program IDs only when the immutable publication manifest matches it.
     *
     * @return list<int>
     */
    public function deltaEligibleProgramIds(AdmissionBenchmarkSet $currentBase, AdmissionBenchmarkSet $candidate): array
    {
        if ((int) $candidate->predecessor_id !== (int) $currentBase->id
            || ! is_array($candidate->publication_manifest_json)) {
            throw new RuntimeException('Published benchmark is not a reviewed successor of the supplied active base.');
        }

        $actualDelta = $this->actualDeltaProgramIds($currentBase, $candidate);
        $this->assertManifestMatchesDelta($currentBase, $candidate, $actualDelta);

        return $actualDelta;
    }

    /** @param list<int> $programIds */
    private function authorizeProgramIds(?User $actor, AcademicCycle $cycle, array $programIds): void
    {
        if ($actor === null) {
            throw new InvalidArgumentException('Human benchmark activation requires an authenticated actor context.');
        }
        if ($programIds === []) {
            throw new InvalidArgumentException('Benchmark activation has no changed program scope.');
        }
        foreach ($programIds as $programId) {
            $program = Program::query()->find($programId);
            if ($program === null) {
                throw new InvalidArgumentException('A changed benchmark program no longer exists for authorization.');
            }
            $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE, $program, $cycle, true);
        }
    }

    /**
     * @param  list<int>  $actualDelta
     */
    private function assertManifestMatchesDelta(AdmissionBenchmarkSet $base, AdmissionBenchmarkSet $candidate, array $actualDelta): void
    {
        $manifest = $candidate->publication_manifest_json;
        if (! is_array($manifest)) {
            throw new RuntimeException('Reviewed benchmark successor has no immutable publication manifest.');
        }
        $retiredPrograms = $manifest['retired_programs'] ?? [];
        unset($manifest['retired_programs']);
        if (! is_array($retiredPrograms) || ! array_is_list($retiredPrograms)) {
            throw new RuntimeException('Retired-program manifest must be an explicit provenance list.');
        }

        $actualCandidateIds = $this->programIds($candidate);
        $manifestProgramIds = array_map('intval', array_keys($manifest));
        sort($manifestProgramIds);
        if ($manifestProgramIds !== $actualCandidateIds) {
            throw new RuntimeException('Publication manifest coverage does not exactly match the published program set.');
        }

        $declaredChangedIds = [];
        foreach ($manifest as $programId => $entry) {
            $programId = (int) $programId;
            if (! is_array($entry)
                || (int) ($entry['source_set_id'] ?? 0) !== (int) $base->id
                || ! hash_equals((string) $base->content_sha256, (string) ($entry['source_content_sha256'] ?? ''))) {
                throw new RuntimeException('Publication manifest source identity does not match the latest active base.');
            }
            if (array_key_exists('revision_id', $entry)) {
                if (! isset($entry['revision_version']) || (int) $entry['revision_id'] < 1 || (int) $entry['revision_version'] < 1) {
                    throw new RuntimeException('Changed-program manifest entry has no exact revision identity.');
                }
                $declaredChangedIds[] = $programId;
            }
        }

        $baseIds = $this->programIds($base);
        $removedIds = array_values(array_diff($baseIds, $actualCandidateIds));
        $retirementIds = [];
        $retiredCodes = $this->catalog->retiredCodes();
        $provenance = $this->catalog->retirementProvenance();
        foreach ($retiredPrograms as $retirement) {
            if (! is_array($retirement)
                || array_diff(array_keys($retirement), ['program_id', 'code', 'source_set_id', 'source_content_sha256', 'provenance']) !== []
                || array_diff(['program_id', 'code', 'source_set_id', 'source_content_sha256', 'provenance'], array_keys($retirement)) !== []) {
                throw new RuntimeException('Retired-program manifest entry has an unsupported shape.');
            }
            $programId = (int) $retirement['program_id'];
            $program = Program::query()->whereKey($programId)->where('institution_id', $base->institution_id)->first();
            if (! in_array($programId, $removedIds, true)
                || $program === null
                || ! in_array($program->code, $retiredCodes, true)
                || $program->is_admission_offered
                || $retirement['code'] !== $program->code
                || (int) $retirement['source_set_id'] !== (int) $base->id
                || ! hash_equals((string) $base->content_sha256, (string) $retirement['source_content_sha256'])
                || $retirement['provenance'] !== $provenance) {
                throw new RuntimeException('Retirement provenance does not authorize this exact source-program removal.');
            }
            $retirementIds[] = $programId;
        }

        sort($removedIds);
        sort($retirementIds);
        if ($removedIds !== $retirementIds) {
            throw new RuntimeException('Publication manifest silently removes a program or retires an unremoved program.');
        }
        $declaredChangedIds = [...$declaredChangedIds, ...$retirementIds];
        sort($declaredChangedIds);
        $actualDelta = array_values(array_unique($actualDelta));
        sort($actualDelta);
        if ($declaredChangedIds !== $actualDelta) {
            throw new RuntimeException('Published program-content delta does not exactly match its reviewed manifest.');
        }

        foreach ($actualDelta as $programId) {
            if (in_array($programId, $removedIds, true)) {
                continue;
            }
            $program = Program::query()->whereKey($programId)->first();
            if ($program === null || ! $program->is_admission_offered) {
                throw new RuntimeException('A changed program is no longer eligible for the current offering.');
            }
        }
    }

    /** @return list<int> */
    private function programIds(AdmissionBenchmarkSet $set): array
    {
        return $set->exemplars()
            ->select('program_id')
            ->distinct()
            ->orderBy('program_id')
            ->pluck('program_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int, string> */
    private function programContentDigests(AdmissionBenchmarkSet $set): array
    {
        $set->loadMissing(['features', 'exemplars.program', 'exemplars.values.feature']);
        $schema = $set->features
            ->map(static fn ($feature): array => [
                $feature->feature_key,
                $feature->label,
                $feature->logical_type,
                $feature->aptitude_area_id,
                (bool) $feature->is_required,
            ])
            ->values()
            ->all();
        $authoring = $this->publishedAuthoring($set);
        $digests = [];
        foreach ($set->exemplars->sortBy([['program_id', 'asc'], ['tier_level', 'asc']])->groupBy('program_id') as $programId => $exemplars) {
            $tiers = [];
            foreach ($exemplars as $exemplar) {
                $values = [];
                foreach ($exemplar->values->sortBy('feature.feature_key') as $value) {
                    $values[$value->feature->feature_key] = $value->is_missing
                        ? null
                        : ($value->feature->logical_type === 'categorical' ? $value->categorical_value : (float) $value->numeric_value);
                }
                ksort($values, SORT_STRING);
                $tiers[] = [
                    'tier_level' => (int) $exemplar->tier_level,
                    'tier_label' => $exemplar->tier_label,
                    'target_class' => (int) $exemplar->target_class,
                    'values' => $values,
                ];
            }
            $digests[(int) $programId] = hash('sha256', json_encode([
                'program_code' => $exemplars->first()->program->code,
                'schema' => $schema,
                'tiers' => $tiers,
                'authoring' => $authoring[(int) $programId] ?? null,
            ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        }
        ksort($digests, SORT_NUMERIC);

        return $digests;
    }

    /** @return array<int, array<string, mixed>|null> */
    private function publishedAuthoring(AdmissionBenchmarkSet $set): array
    {
        $remaining = array_fill_keys($set->exemplars->pluck('program_id')->unique()->all(), true);
        $authoring = [];
        $visited = [];
        for ($depth = 0; $set !== null && $remaining !== [] && $depth < 100; $depth++) {
            if (isset($visited[$set->id])) {
                throw new RuntimeException('Published benchmark advisory lineage contains a cycle.');
            }
            $visited[$set->id] = true;
            $entries = [];
            foreach (array_keys($remaining) as $programId) {
                $entry = $set->publication_manifest_json[(string) $programId] ?? null;
                if (isset($entry['revision_id'])) {
                    $entries[$programId] = $entry;
                }
            }
            if ($entries !== []) {
                $revisions = AdmissionBenchmarkRevision::query()->with('draft:id,program_id,institution_id')
                    ->whereIn('id', array_column($entries, 'revision_id'))->get()->keyBy('id');
                foreach ($entries as $programId => $entry) {
                    $revision = $revisions->get($entry['revision_id']);
                    if ($revision === null || (int) $revision->draft->program_id !== (int) $programId
                        || (int) $revision->draft->institution_id !== (int) $set->institution_id) {
                        throw new RuntimeException('Published advisory metadata lost its exact program revision.');
                    }
                    $authoring[(int) $programId] = $this->normalizePublishedAuthoring($revision->content['authoring'] ?? null);
                    unset($remaining[$programId]);
                }
            }
            $set = $set->predecessor_id === null ? null : AdmissionBenchmarkSet::query()->findOrFail($set->predecessor_id);
        }
        if ($set !== null && $remaining !== []) {
            throw new RuntimeException('Published benchmark advisory lineage exceeds the supported depth.');
        }

        return $authoring;
    }

    /**
     * Advisory schema versions and empty defaults are not program-content changes.
     *
     * @return array<string, mixed>|null
     */
    private function normalizePublishedAuthoring(mixed $authoring): ?array
    {
        if (! is_array($authoring)) {
            return null;
        }

        unset($authoring['version']);
        $normalize = static function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return $value;
            }
            $normalized = [];
            foreach ($value as $key => $item) {
                $item = $normalize($item);
                if ($item === null || $item === false || $item === '' || $item === []) {
                    continue;
                }
                $normalized[$key] = $item;
            }
            if (! array_is_list($normalized)) {
                ksort($normalized, SORT_STRING);
            }

            return $normalized;
        };
        $authoring = $normalize($authoring);

        return $authoring === [] ? null : $authoring;
    }

    private function contentIsIntact(AdmissionBenchmarkSet $set): bool
    {
        return hash_equals((string) $set->content_sha256, $set->computedContentDigest());
    }
}
