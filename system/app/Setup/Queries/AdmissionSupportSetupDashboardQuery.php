<?php

namespace App\Setup\Queries;

use App\Admission\Queries\ProgramGovernanceDiscussionQuery;
use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\AdmissionEngineRegistry;
use App\AdmissionEngine\Queries\SchemaCutoverReviewQuery;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\KnownHistoricalBenchmarkRecovery;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Consultation\Queries\ProgramAdvisingNoteQuery;
use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkFeature;
use App\Models\Admission\AdmissionBenchmarkRecovery;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Admission Support Setup Dashboard Query (Domain: Setup & Analytical Decision Governance)
 *
 * Architectural Purpose:
 * Aggregates analytical model registry states, active benchmark activations,
 * versioned benchmark exemplar sets, and degree program lists for the Setup hub:
 * - Resolves the current AcademicCycle and its non-superseded AdmissionBenchmarkActivation.
 * - Hydrates model capabilities from the AdmissionEngineRegistry (TabFM vs YuKee).
 * - Returns cryptographic content hashes (content_sha256) for auditability.
 */
final class AdmissionSupportSetupDashboardQuery
{
    public function __construct(
        private readonly KnownHistoricalBenchmarkRecovery $recovery,
        private readonly SchemaCutoverReviewQuery $cutoverReviews,
    ) {}

    /** @return array<string, mixed> */
    public function for(Institution $institution, ?AcademicCycle $cycle = null): array
    {
        return $this->handle($institution, $cycle);
    }

    /** @return array<string, mixed> */
    public function handle(Institution $institution, ?AcademicCycle $cycle = null): array
    {
        $cycle ??= AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->first()
            ?? AcademicCycle::query()
                ->where('institution_id', $institution->id)
                ->latest('id')
                ->first();

        $activeActivation = $cycle === null
            ? null
            : AdmissionBenchmarkActivation::query()
                ->where('institution_id', $institution->id)
                ->where('academic_cycle_id', $cycle->id)
                ->whereNull('superseded_at')
                ->with([
                    'benchmarkSet.features',
                    'benchmarkSet.exemplars.program',
                    'benchmarkSet.exemplars.values',
                    'benchmarkSet.activations',
                ])
                ->orderByDesc('sequence')
                ->first();

        $activeSetId = $activeActivation?->benchmark_set_id;
        $overviewSet = $activeActivation?->benchmarkSet;
        $historicalOverview = $overviewSet !== null && $this->recovery->isKnownHistoricalSet($overviewSet);
        $overviewUsable = $overviewSet !== null && ! $historicalOverview
            && hash_equals((string) $overviewSet->content_sha256, $overviewSet->computedContentDigest())
            && hash_equals((string) $overviewSet->schema_sha256, $overviewSet->computedSchemaDigest());
        $benchmarkSnapshot = $overviewSet === null
            ? null
            : (! $overviewUsable
                ? [
                    'set_id' => $overviewSet->id,
                    'version' => $overviewSet->version,
                    'name' => $overviewSet->name,
                    'usable' => false,
                    'requires_recovery' => $historicalOverview,
                    'integrity_failed' => ! $historicalOverview,
                    'content_sha256' => $overviewSet->content_sha256,
                ]
                : [...$this->formatBenchmarkSnapshot($overviewSet), 'usable' => true, 'requires_recovery' => false, 'content_sha256' => $overviewSet->content_sha256]);

        $history = AdmissionBenchmarkSet::query()
            ->where('institution_id', $institution->id)
            ->select(['id', 'version', 'name', 'status', 'content_sha256', 'published_at', 'created_at'])
            ->withCount('exemplars')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (AdmissionBenchmarkSet $set): array => [
                'id' => $set->id,
                'version' => $set->version,
                'name' => $set->name,
                'status' => $set->status,
                'content_sha256' => $set->content_sha256,
                'published_at' => $set->published_at,
                'created_at' => $set->created_at,
                'exemplar_count' => $set->exemplars_count,
                'is_active_for_cycle' => $activeSetId !== null && (int) $activeSetId === (int) $set->id,
            ])
            ->all();
        $providers = array_map(
            fn ($provider): array => $provider->manifest($cycle?->id)->toArray(),
            app(AdmissionEngineRegistry::class)->providers(),
        );

        return [
            'page_state' => 'ready',
            'cycle' => $cycle === null ? null : [
                'id' => $cycle->id,
                'code' => $cycle->code,
                'name' => $cycle->name,
                'is_active' => $cycle->is_active,
            ],
            'providers' => $providers,
            'benchmark_snapshot' => $benchmarkSnapshot,
            'history' => $history,
            'programs' => Program::query()
                ->where('institution_id', $institution->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->toArray(),
            'aptitude_areas' => AptitudeArea::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'code', 'name'])
                ->map(static fn (AptitudeArea $area): array => [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                    'display_order' => $area->id,
                ])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function governance(
        User $actor,
        ?Program $selectedProgram,
        ?AcademicCycle $selectedCycle,
        GovernanceAuthorizer $authorizer,
        ProgramAdvisingNoteQuery $notes,
        ProgramGovernanceDiscussionQuery $discussions,
    ): array {
        $institution = Institution::current();
        $actor = User::query()->findOrFail($actor->id);
        $scope = $actor->actorScope();
        $canImport = $scope->office === 'guidance'
            && (int) $scope->institutionId === (int) $institution->id
            && app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)->allowed;
        $aptitudeCodes = BenchmarkInputSchema::canonicalAptitudeCodes();
        $areaCodes = array_values(array_unique(array_merge(...array_map(
            static fn (string $code): array => BenchmarkInputSchema::constructAliases($code),
            $aptitudeCodes,
        ))));
        $aptitudeAreas = AptitudeArea::query()->active()->whereIn('code', $areaCodes)->orderBy('id')->get(['id', 'code', 'name'])
            ->sortByDesc(static fn (AptitudeArea $area): int => (string) $area->code === (BenchmarkInputSchema::canonicalConstructCode((string) $area->code) ?? '') ? 1 : 0)
            ->map(static function (AptitudeArea $area): array {
                $code = BenchmarkInputSchema::canonicalConstructCode((string) $area->code);
                $unit = 'percentile_rank';

                return [...$area->only(['id', 'name']), 'code' => $code, 'declared_unit' => $unit, 'declared_scale' => 'percentile', 'unit_unverified' => false];
            })->unique('code')->values()->all();
        $cycles = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->orderByDesc('is_active')
            ->orderByDesc('opens_on')
            ->get(['id', 'institution_id', 'code', 'name', 'is_active', 'opens_on']);
        $candidateCycles = $cycles;
        $programs = Program::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->where('is_admission_offered', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'institution_id', 'department_id', 'is_active'])
            ->filter(function (Program $program) use ($actor, $authorizer, $candidateCycles): bool {
                foreach ($candidateCycles as $cycle) {
                    foreach ($this->studioCapabilities() as $capability) {
                        if ($authorizer->allows($actor, $capability, $program, $cycle)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->values();
        $cycles = $candidateCycles
            ->filter(function (AcademicCycle $cycle) use ($actor, $authorizer, $programs): bool {
                foreach ($programs as $program) {
                    foreach ($this->studioCapabilities() as $capability) {
                        if ($authorizer->allows($actor, $capability, $program, $cycle)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->values();
        if ($selectedProgram === null || $selectedCycle === null
            || ! $programs->contains('id', (int) $selectedProgram->id)
            || ! $cycles->contains('id', (int) $selectedCycle->id)) {
            return [
                'programs' => $programs->map(static fn (Program $program): array => $program->only(['id', 'code', 'name']))->all(),
                'cycles' => $cycles->map(static fn (AcademicCycle $cycle): array => [...$cycle->only(['id', 'code', 'name', 'is_active']), 'staff_label' => $cycle->staffLabel()])->all(),
                'selected_program' => null,
                'selected_cycle' => null,
                'benchmark_snapshot' => null,
                'sets' => [],
                'schema_features' => [],
                'aptitude_areas' => $programs->isEmpty() ? [] : $aptitudeAreas,
                'can_import' => $canImport,
                'active_set' => null,
                'active_content' => null,
                'draft' => null,
                'recovery' => null,
                'recovery_content' => null,
                'benchmark_revision_history' => [],
                'capabilities' => [],
                'input_schema' => ['subjects' => [], 'activities' => []],
                'active_input_schema' => ['subjects' => [], 'activities' => []],
                'available_subjects' => [],
                'subject_sources' => [],
                'can_manage_schema' => false,
                'return_to' => null,
                'comparison' => null,
                'version_comparison' => null,
                'notes' => null,
                'tab_badges' => [],
                'discussion' => [],
            ];
        }
        $selectedProgram = $programs->firstWhere('id', (int) $selectedProgram->id);
        $selectedCycle = $cycles->firstWhere('id', (int) $selectedCycle->id);
        $capabilities = [];
        foreach ($this->studioCapabilities() as $capability) {
            $write = in_array($capability, [
                CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT,
                CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH,
                CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE,
                CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
                CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH,
                CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
                CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_MANAGE_STATE,
                CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_SEND,
                CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_MANAGE_STATE,
            ], true);
            $capabilities[$capability] = $authorizer->allows($actor, $capability, $selectedProgram, $selectedCycle, $write);
        }
        foreach ([
            CapabilityCatalog::PROGRAM_QUOTA_READ,
            CapabilityCatalog::PROGRAM_QUOTA_MANAGE,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_MANAGE_STATE,
        ] as $capability) {
            $capabilities[$capability] = $authorizer->allows($actor, $capability, $selectedProgram, $selectedCycle);
        }
        $hasBenchmarkAccess = ($capabilities[CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW] ?? false)
            || ($capabilities[CapabilityCatalog::ADMISSION_BENCHMARK_PREVIEW] ?? false)
            || ($capabilities[CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT] ?? false);
        $activation = $hasBenchmarkAccess ? AdmissionBenchmarkActivation::currentForCycle((int) $selectedCycle->id) : null;
        $sourceSet = $activation?->benchmarkSet()->with('features.aptitudeArea')->first();
        $isHistoricalSource = $sourceSet !== null && $this->recovery->isKnownHistoricalSet($sourceSet);
        $set = $isHistoricalSource ? null : $sourceSet;
        $sourceProblem = null;
        if ($sourceSet !== null && ! $isHistoricalSource) {
            if (! hash_equals((string) $sourceSet->schema_sha256, $sourceSet->computedSchemaDigest())) {
                $sourceProblem = 'schema_conflict';
            } elseif (! hash_equals((string) $sourceSet->content_sha256, $sourceSet->computedContentDigest())) {
                $sourceProblem = 'integrity_failure';
            }
            if ($sourceProblem !== null) {
                $set = null;
            }
        }
        $historicalProgramIds = $isHistoricalSource ? $this->recovery->sourceProgramIds($sourceSet) : [];
        if ($activation !== null && (
            (int) $activation->institution_id !== (int) $institution->id
            || (int) $activation->academic_cycle_id !== (int) $selectedCycle->id
        )) {
            throw new \RuntimeException('The current benchmark activation is outside the selected institution and cycle.');
        }
        if ($sourceSet !== null && (int) $sourceSet->institution_id !== (int) $institution->id) {
            throw new \RuntimeException('The current benchmark source is outside the selected institution.');
        }
        $schema = $sourceProblem === null ? $sourceSet?->features->map(function (AdmissionBenchmarkFeature $feature): array {
            $source = $feature->source_definition_json;
            $measurement = self::measurementProjection($source, (string) $feature->feature_key);

            return [
                'id' => $feature->id,
                'key' => $feature->feature_key,
                'label' => $feature->label,
                'logical_type' => $feature->logical_type,
                ...$measurement,
                'is_required' => (bool) $feature->is_required,
                'source_definition' => $source,
                'aptitude_area' => $feature->aptitudeArea?->only(['id', 'code', 'name']),
            ];
        })->all() ?? [] : [];
        $activeSchema = $schema;
        $recoveryRecord = null;
        $recoveryContent = null;
        $recoverySnapshotDigest = null;
        if ($isHistoricalSource) {
            $recoveryRecord = AdmissionBenchmarkRecovery::query()
                ->where('source_benchmark_set_id', $sourceSet->id)
                ->where('academic_cycle_id', $selectedCycle->id)
                ->first();
            if ($recoveryRecord !== null) {
                $sourceSet = $this->recovery->assertUnchanged($recoveryRecord, $selectedCycle);
                $recoverySnapshotDigest = (string) $recoveryRecord->source_snapshot_sha256;
            } else {
                $recoverySnapshotDigest = $this->recovery->digest($this->recovery->sourceSnapshot($sourceSet));
            }
            $canReadRecoveryContent = ($capabilities[CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW] ?? false)
                || ($capabilities[CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT] ?? false);
            if ($canReadRecoveryContent && in_array((int) $selectedProgram->id, $historicalProgramIds, true)) {
                $recoveryContent = $this->recovery->mappedContent($sourceSet, (int) $selectedProgram->id);
            }
        }
        $canSeeDraft = ($capabilities[CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW] ?? false)
            || ($capabilities[CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT] ?? false);
        $draft = $canSeeDraft ? AdmissionBenchmarkDraft::query()
            ->where('institution_id', $institution->id)
            ->where('program_id', $selectedProgram->id)
            ->where('academic_cycle_id', $selectedCycle->id)
            ->first() : null;
        $currentRevision = $draft?->currentRevision();
        $aptitudeAreasById = collect($aptitudeAreas)->keyBy('id');
        $activeInputSchema = $set === null ? ['subjects' => [], 'activities' => []] : BenchmarkInputSchema::projection($set);
        $editorDefinitions = $draft !== null && $currentRevision !== null
            ? BenchmarkInputSchema::schemaForDraft($draft, $currentRevision)
            : ($set !== null
                ? BenchmarkInputSchema::schemaForSet($set)
                : ($isHistoricalSource ? BenchmarkInputSchema::schemaForSet($sourceSet) : []));
        $inputSchema = $draft !== null && $currentRevision !== null
            ? BenchmarkInputSchema::projectionForDraft($draft, $currentRevision)
            : $activeInputSchema;
        $schema = array_map(static function (array $feature, int $position) use ($aptitudeAreasById): array {
            $source = $feature['source_definition'] ?? null;

            return [
                'id' => $feature['binding_id'] ?? $feature['feature_key'],
                'key' => $feature['feature_key'],
                'label' => $feature['label'],
                'logical_type' => $feature['logical_type'],
                ...self::measurementProjection($source, (string) $feature['feature_key']),
                'is_required' => (bool) $feature['is_required'],
                'source_definition' => $source,
                'position' => $feature['position'] ?? $position + 1,
                'aptitude_area' => isset($feature['aptitude_area_id']) ? $aptitudeAreasById->get((int) $feature['aptitude_area_id']) : null,
            ];
        }, $editorDefinitions, array_keys($editorDefinitions));
        if ($sourceProblem === null && $draft !== null && $sourceSet !== null) {
            $draftSchema = BenchmarkInputSchema::schemaForDraft($draft, $currentRevision);
            $draftSchemaDigest = BenchmarkInputSchema::digest($draftSchema);
            $schemaValid = hash_equals((string) $draft->feature_schema_digest, $draftSchemaDigest)
                && ($currentRevision === null || hash_equals((string) $currentRevision->feature_schema_digest, $draftSchemaDigest));
            $baseSchema = $isHistoricalSource ? [] : BenchmarkInputSchema::schemaForSet($sourceSet);
            $baseMatches = (int) $draft->base_benchmark_set_id === (int) $sourceSet->id;
            $compatible = $isHistoricalSource || BenchmarkInputSchema::isCompatibleSuccessor(
                $baseSchema,
                $draftSchema,
                (int) $institution->id,
            );
            if (! $schemaValid || ! $baseMatches || ! $compatible) {
                $sourceProblem = 'schema_conflict';
            }
        }
        $recoveryMetadata = null;
        if ($isHistoricalSource && $hasBenchmarkAccess) {
            $sourceProgramIds = $recoveryRecord === null
                ? Program::query()->whereIn('id', $historicalProgramIds)->whereNotIn('code', app(TagudinUndergraduateCatalog::class)->retiredCodes())->pluck('id')->map(static fn ($id): int => (int) $id)->all()
                : $this->recovery->retainedProgramIds($recoveryRecord, $sourceSet);
            $sourcePrograms = Program::query()
                ->where('institution_id', $institution->id)
                ->whereIn('id', $sourceProgramIds)
                ->get(['id', 'code', 'name', 'institution_id', 'is_active'])
                ->keyBy('id');
            $recoveryDrafts = $recoveryRecord === null
                ? collect()
                : $recoveryRecord->drafts()->with('revisions')->get()->keyBy('program_id');
            $incompletePrograms = [];
            $revisionManifest = [];
            $fullSourcePublisher = $sourcePrograms->count() === count($sourceProgramIds);
            foreach ($sourceProgramIds as $sourceProgramId) {
                $sourceProgram = $sourcePrograms->get($sourceProgramId);
                if ($sourceProgram === null || ! $authorizer->allows(
                    $actor, CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH, $sourceProgram, $selectedCycle, true,
                )) {
                    $fullSourcePublisher = false;

                    continue;
                }
                $programDraft = $recoveryDrafts->get($sourceProgramId);
                $revision = $programDraft?->revisions->firstWhere('version', $programDraft->current_version);
                $content = $revision?->content ?? ['tiers' => []];
                $completeness = $this->recovery->incompleteContent($content, $sourceSet->features);
                if ($revision === null || $completeness['missing_cells'] !== [] || $completeness['negative_anchor_missing']) {
                    $incompletePrograms[] = [
                        'program_id' => $sourceProgramId,
                        'program_code' => $sourceProgram?->code,
                        'missing_cells' => $completeness['missing_cells'],
                        'negative_anchor_missing' => $completeness['negative_anchor_missing'],
                    ];
                } else {
                    $revisionManifest[(string) $sourceProgramId] = (int) $revision->id;
                }
            }
            ksort($revisionManifest, SORT_NUMERIC);
            $recoveryMetadata = [
                'source_set_id' => (int) $sourceSet->id,
                'source_version' => (int) $sourceSet->version,
                'source_content_sha256' => (string) $sourceSet->content_sha256,
                'source_snapshot_digest' => (string) $recoverySnapshotDigest,
                'source_activation_id' => (int) $activation->id,
                'expected_content_sha256' => (string) $sourceSet->content_sha256,
                'expected_source_snapshot_digest' => (string) $recoverySnapshotDigest,
                'expected_source_activation_id' => (int) $activation->id,
                'program_in_source' => in_array((int) $selectedProgram->id, $historicalProgramIds, true),
                'status' => $recoveryRecord !== null && (int) $draft?->recovery_id === (int) $recoveryRecord->id && $currentRevision !== null ? 'draft' : 'available',
                'historical_content_verified' => false,
                'missing_tier' => 4,
                'source_program_count' => $fullSourcePublisher ? count($sourceProgramIds) : null,
                'remaining_program_count' => $fullSourcePublisher ? count($incompletePrograms) : null,
                'incomplete_programs' => $incompletePrograms,
                'publication_requires_institution_review' => ! $fullSourcePublisher,
                'full_source_ready' => $fullSourcePublisher && $incompletePrograms === [],
                'revision_ids_by_program' => $fullSourcePublisher && $incompletePrograms === [] ? $revisionManifest : null,
            ];
        }
        $activeContent = null;
        if ($set !== null && ($capabilities[CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW] ?? false)) {
            if (! hash_equals((string) $set->content_sha256, $set->computedContentDigest())
                || ! hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())) {
                throw new \RuntimeException('The active benchmark set failed content or schema integrity validation.');
            }
            $featuresById = $set->features->keyBy('id');
            $tiers = [];
            foreach ($set->exemplars()->where('program_id', $selectedProgram->id)->with('values')->get() as $exemplar) {
                $values = [];
                foreach ($exemplar->values as $value) {
                    $feature = $featuresById->get($value->benchmark_feature_id);
                    if ($feature !== null) {
                        $values[$feature->feature_key] = $value->is_missing ? null : ($value->numeric_value ?? $value->categorical_value);
                    }
                }
                $tiers[(string) $exemplar->tier_level] = [
                    'tier_level' => (int) $exemplar->tier_level,
                    'tier_label' => $exemplar->tier_label,
                    'target_class' => (int) $exemplar->target_class,
                    'values' => $values,
                ];
            }
            if ($tiers !== []) {
                ksort($tiers);
                $activeContent = ['program_id' => (int) $selectedProgram->id, 'tiers' => $tiers];
            }
        }
        $noteBoard = ($capabilities[CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW] ?? false)
            ? $notes->boardFor($actor, $selectedProgram, $selectedCycle)
            : null;
        $discussion = [];
        $unreadThreads = 0;
        $readableThreadCount = 0;
        foreach (['benchmark', 'quota', 'advising'] as $topic) {
            $key = match ($topic) {
                'benchmark' => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
                'quota' => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
                default => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
            };
            if ($authorizer->allows($actor, $key, $selectedProgram, $selectedCycle)) {
                $discussion[$topic] = $discussions->list($actor, $selectedProgram, $selectedCycle, $topic);
                $summary = $discussions->summary($actor, $selectedProgram, $selectedCycle, $topic);
                $unreadThreads += $summary['unread_threads'];
                $readableThreadCount += $summary['thread_count'];
            }
        }

        $revisionHistory = $draft?->revisions()->with('actor:id,name')->latest('id')->limit(20)->get()
            ->map(static fn ($revision): array => [
                'id' => $revision->id,
                'version' => $revision->version,
                'content' => $revision->content,
                'actor' => $revision->actor?->name,
                'reason' => $revision->reason,
                'created_at' => $revision->created_at?->toIso8601String(),
            ])->all() ?? [];

        return [
            'programs' => $programs->map(static fn (Program $program): array => $program->only(['id', 'code', 'name']))->all(),
            'cycles' => $cycles->map(static fn (AcademicCycle $item): array => [...$item->only(['id', 'code', 'name', 'is_active']), 'staff_label' => $item->staffLabel()])->all(),
            'selected_program' => $selectedProgram->only(['id', 'code', 'name']),
            'selected_cycle' => [...$selectedCycle->only(['id', 'code', 'name', 'is_active']), 'staff_label' => $selectedCycle->staffLabel()],
            'can_import' => $canImport,
            'benchmark_snapshot' => $isHistoricalSource
                ? ['set_id' => $sourceSet->id, 'version' => $sourceSet->version, 'usable' => false, 'requires_recovery' => true]
                : ($set === null ? null : ['set_id' => $set->id, 'version' => $set->version, 'usable' => true, 'requires_recovery' => false]),
            'sets' => ($capabilities[CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE] ?? false) && $sourceProblem === null && $sourceSet !== null
                ? $this->eligiblePublishedSets($actor, $selectedCycle, $sourceSet, $authorizer)
                : [],
            'schema_features' => $schema,
            'input_schema' => $inputSchema,
            'active_input_schema' => $activeInputSchema,
            'requires_input_cutover' => $canSeeDraft && $editorDefinitions !== [] && ! BenchmarkInputSchema::isCanonicalFeatureSchema($editorDefinitions),
            'input_cutover_source_digest' => $sourceSet === null ? null : ($isHistoricalSource ? $recoverySnapshotDigest : (string) $sourceSet->content_sha256),
            'schema_cutover' => $sourceSet !== null
                && ($capabilities[CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH] ?? false)
                && ! BenchmarkInputSchema::isCanonicalFeatureSchema(BenchmarkInputSchema::schemaForSet($sourceSet))
                    ? $this->cutoverReviews->handle($actor, $selectedProgram, $selectedCycle) : null,
            'aptitude_areas' => $hasBenchmarkAccess ? $aptitudeAreas : [],
            'active_set' => $set === null ? null : ['id' => $set->id, 'version' => $set->version, 'content_sha256' => $set->content_sha256, 'schema_sha256' => $set->schema_sha256, 'activation_id' => $activation->id],
            'recovery' => $recoveryMetadata,
            'recovery_content' => $recoveryContent,
            'active_content' => $activeContent,
            'comparison' => $this->deanComparison($actor, $selectedProgram, $selectedCycle, $set, $activeSchema, $authorizer),
            'draft' => $draft === null ? null : ['id' => $draft->id, 'current_version' => $draft->current_version, 'current_revision_id' => $currentRevision?->id, 'base_benchmark_set_id' => $draft->base_benchmark_set_id, 'feature_schema_digest' => $draft->feature_schema_digest, 'content' => $currentRevision?->content],
            'benchmark_revision_history' => $revisionHistory,
            'capabilities' => $capabilities,
            'notes' => $noteBoard,
            'tab_badges' => [
                'benchmarks' => $canSeeDraft ? $this->benchmarkBadge(
                    $currentRevision?->content ?? $activeContent ?? $recoveryContent,
                    $sourceSet?->features ?? collect(),
                    $isHistoricalSource,
                    $draft === null,
                    $sourceProblem,
                ) : null,
                'notes' => $noteBoard['counts'] ?? null,
                'discussion' => $discussion === [] ? null : ['unread_threads' => $unreadThreads, 'thread_count' => $readableThreadCount],
            ],
            'discussion' => $discussion,
        ];
    }

    /** @param array<string, mixed>|null $content @param \Illuminate\Support\Collection<int, AdmissionBenchmarkFeature> $features @return array<string, mixed> */
    private function benchmarkBadge(?array $content, Collection $features, bool $historical, bool $noDraft, ?string $problem = null): array
    {
        if ($problem !== null) {
            return ['state' => $problem, 'label' => $problem === 'schema_conflict' ? 'Needs review' : 'Unavailable', 'missing_required_count' => 0, 'partial_range_count' => 0];
        }
        if ($features->isEmpty()) {
            return ['state' => 'schema_unavailable', 'label' => 'Needs setup', 'missing_required_count' => 0, 'partial_range_count' => 0];
        }
        if ($content === null) {
            return ['state' => 'no_content', 'label' => 'Needs values', 'missing_required_count' => 0, 'partial_range_count' => 0];
        }
        $incomplete = $this->recovery->incompleteContent($content, $features);
        $partial = 0;
        foreach ($content['authoring']['ranges'] ?? [] as $ranges) {
            foreach ($ranges as $pair) {
                if (is_array($pair) && count($pair) === 2 && (($pair[0] === null) !== ($pair[1] === null))) {
                    $partial++;
                }
            }
        }
        $missing = count($incomplete['missing_cells']);
        $state = $missing > 0 || $partial > 0 || $incomplete['negative_anchor_missing']
            ? 'incomplete'
            : ($historical && $noDraft ? 'recovery' : 'ready');

        return [
            'state' => $state,
            'label' => $state === 'ready' ? 'Ready' : ($state === 'recovery' ? 'Needs review' : 'Needs values'),
            'missing_required_count' => $missing,
            'partial_range_count' => $partial,
            'negative_anchor_missing' => $incomplete['negative_anchor_missing'],
        ];
    }

    /** @param list<array<string, mixed>> $schema @return array<string, mixed>|null */
    private function deanComparison(
        User $actor,
        Program $selectedProgram,
        AcademicCycle $cycle,
        ?AdmissionBenchmarkSet $set,
        array $schema,
        GovernanceAuthorizer $authorizer,
    ): ?array {
        $principal = User::query()->find($actor->id);
        $scope = $principal?->actorScope();
        if ($principal === null || $scope === null || $set === null
            || (int) $set->institution_id !== (int) $cycle->institution_id
            || (int) $scope->institutionId !== (int) $cycle->institution_id) {
            return null;
        }
        $college = Department::query()
            ->whereKey($selectedProgram->department_id)
            ->where('institution_id', $scope->institutionId)
            ->where('dean_user_id', $principal->id)
            ->where('is_active', true)
            ->first();
        if ($college === null) {
            return null;
        }
        if (! hash_equals((string) $set->content_sha256, $set->computedContentDigest())
            || ! hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())) {
            throw new \RuntimeException('The comparison benchmark set failed content or schema integrity validation.');
        }
        $programs = Program::query()
            ->where('institution_id', $cycle->institution_id)
            ->where('department_id', $college->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'institution_id', 'department_id', 'is_active'])
            ->filter(fn (Program $program): bool => $authorizer->allows($principal, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $cycle));
        $truncated = $programs->count() > 50;
        $programs = $programs->take(50)->keyBy('id');
        $features = $set->features->keyBy('id');
        $rows = $set->exemplars()
            ->whereIn('program_id', $programs->keys())
            ->where('tier_level', 3)
            ->with('values')
            ->orderBy('program_id')
            ->get()
            ->map(function ($exemplar) use ($programs, $features, $cycle): array {
                $values = [];
                foreach ($exemplar->values as $value) {
                    $feature = $features->get($value->benchmark_feature_id);
                    if ($feature !== null) {
                        $values[$feature->feature_key] = $value->is_missing ? null : ($value->numeric_value ?? $value->categorical_value);
                    }
                }

                return [
                    'program' => $programs->get($exemplar->program_id)->only(['id', 'code', 'name']),
                    'tier_level' => (int) $exemplar->tier_level,
                    'tier_label' => $exemplar->tier_label,
                    'values' => $values,
                    'studio_url' => route('setup.decision-support.index', ['program_id' => $exemplar->program_id, 'cycle_id' => $cycle->id, 'tool' => 'benchmarks']),
                ];
            })->all();

        return [
            'college' => $college->only(['id', 'code', 'name']),
            'set_id' => (int) $set->id,
            'version' => (int) $set->version,
            'schema_features' => $schema,
            'rows' => $rows,
            'truncated' => $truncated,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function eligiblePublishedSets(User $actor, AcademicCycle $cycle, AdmissionBenchmarkSet $base, GovernanceAuthorizer $authorizer): array
    {
        $eligible = [];
        $activation = app(ActivateAdmissionBenchmarkSet::class);
        $candidates = AdmissionBenchmarkSet::query()
            ->where('institution_id', $cycle->institution_id)
            ->where('status', 'published')
            ->with(['features', 'exemplars.values'])
            ->latest('id')->limit(20)->get();
        foreach ($candidates as $candidate) {
            if ((int) $candidate->id === (int) $base->id || $this->recovery->isKnownHistoricalSet($candidate)
                || ! hash_equals((string) $candidate->content_sha256, $candidate->computedContentDigest())
                || ! hash_equals((string) $candidate->schema_sha256, $candidate->computedSchemaDigest())) {
                continue;
            }
            try {
                $programIds = $activation->deltaEligibleProgramIds($base, $candidate);
            } catch (\InvalidArgumentException|\RuntimeException) {
                continue;
            }
            $programs = Program::query()->whereIn('id', $programIds)->get();
            if ($programs->count() !== count($programIds) || $programs->isEmpty()
                || $programs->contains(fn (Program $program): bool => ! $authorizer->allows($actor, CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE, $program, $cycle, true))) {
                continue;
            }
            $eligible[] = [...$candidate->only(['id', 'version', 'name', 'status', 'content_sha256', 'schema_sha256', 'published_at']), 'is_active_for_cycle' => false];
        }

        return $eligible;
    }

    /** @return list<string> */
    private function studioCapabilities(): array
    {
        return [
            CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW,
            CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT,
            CapabilityCatalog::ADMISSION_BENCHMARK_PREVIEW,
            CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH,
            CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE,
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_MANAGE_STATE,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_SEND,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_MANAGE_STATE,
        ];
    }

    /** @return array<string, mixed>|null */
    private function formatBenchmarkSnapshot(?AdmissionBenchmarkSet $set): ?array
    {
        if ($set === null) {
            return null;
        }

        $features = $set->features->keyBy('id');
        $programs = [];
        foreach ($set->exemplars->groupBy('program_id') as $exemplars) {
            $first = $exemplars->first();
            $program = $first?->program;
            if ($program === null) {
                continue;
            }

            $tiers = [];
            foreach ($exemplars as $exemplar) {
                $values = [];
                foreach ($exemplar->values as $value) {
                    $feature = $features->get($value->benchmark_feature_id);
                    if ($feature !== null) {
                        $values[$feature->feature_key] = $value->numeric_value ?? $value->categorical_value;
                    }
                }
                $tiers[(int) $exemplar->tier_level] = [
                    'tier_level' => (int) $exemplar->tier_level,
                    'tier_label' => $exemplar->tier_label,
                    'target_class' => (int) $exemplar->target_class,
                    'values' => $values,
                ];
            }
            $programs[$program->code] = [
                'code' => $program->code,
                'name' => $program->name,
                'tiers' => $tiers,
            ];
        }

        return [
            'set_id' => $set->id,
            'version' => $set->version,
            'name' => $set->name,
            'programs' => $programs,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $source
     * @return array{declared_unit:?string,declared_scale:?string,unit_unverified:bool}
     */
    private static function measurementProjection(?array $source, ?string $featureKey = null): array
    {
        if ($featureKey === 'gwa' && $source === null) {
            return [
                'declared_unit' => 'gwa_scale',
                'declared_scale' => 'gwa_scale',
                'unit_unverified' => false,
            ];
        }
        $supported = ['percentile_rank', 'percentile', 'gwa_scale', 'count'];
        $unit = $source['unit'] ?? null;
        $scale = $source['scale'] ?? null;
        $unit = is_string($unit) ? trim($unit) : '';
        $scale = is_string($scale) ? trim($scale) : '';
        $declaredUnit = in_array($unit, $supported, true)
            ? $unit
            : (in_array($scale, $supported, true) ? $scale : null);
        $declaredScale = in_array($scale, $supported, true) ? $scale : null;

        return [
            'declared_unit' => $declaredUnit,
            'declared_scale' => $declaredScale,
            'unit_unverified' => $declaredUnit === null,
        ];
    }
}
