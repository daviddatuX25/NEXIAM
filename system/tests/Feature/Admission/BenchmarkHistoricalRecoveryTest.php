<?php

use App\Admission\Queries\ProgramGovernanceDiscussionQuery;
use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\CutoverProgramBenchmarkDraftSchema;
use App\AdmissionEngine\Actions\PublishProgramBenchmarkSet;
use App\AdmissionEngine\Actions\RebaseProgramBenchmarkDraft;
use App\AdmissionEngine\Actions\RecoverHistoricalProgramBenchmarkDraft;
use App\AdmissionEngine\Actions\SaveProgramBenchmarkDraft;
use App\AdmissionEngine\Actions\StartProgramBenchmarkDraft;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\ProgramBenchmarkPreviewQuery;
use App\AdmissionEngine\Providers\TabFmPairwiseProvider;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\KnownHistoricalBenchmarkRecovery;
use App\Authorization\GovernanceAuthorizer;
use App\Consultation\Queries\ProgramAdvisingNoteQuery;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkExemplarValue;
use App\Models\Admission\AdmissionBenchmarkRecovery;
use App\Models\Admission\AdmissionBenchmarkRecoveryProgramReview;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Setup\Queries\AdmissionSupportSetupDashboardQuery;
use Database\Seeders\AdmissionBenchmarkSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'admission_engine.tabfm.institutional_use_approved' => true,
        'admission_engine.tabfm.hosted_use_approved' => true,
        'admission_engine.tabfm.package_version' => '9.1.0',
        'admission_engine.tabfm.checkpoint_id' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
        'admission_engine.tabfm.checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
    ]);
});

function reorderRecoverySnapshotKeys(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    foreach ($value as $key => $nested) {
        $value[$key] = reorderRecoverySnapshotKeys($nested);
    }
    if (! array_is_list($value)) {
        krsort($value, SORT_STRING);
    }

    return $value;
}

function historicalRecoveryFixture(): array
{
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $base = completeSyntheticBenchmarkTestBase();
    $base->load(['features', 'exemplars.program', 'exemplars.values.feature']);

    $aptitudeCodes = ['VERBAL', 'QUANT', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];
    $areas = AptitudeArea::query()->whereIn('code', $aptitudeCodes)->get()->keyBy('code');
    $definitions = [];
    foreach ($aptitudeCodes as $key) {
        $area = $areas->get($key);
        if ($area === null) {
            throw new RuntimeException("Historical fixture requires active aptitude area {$key}.");
        }
        $definitions[] = [$key, $area->name, 'numeric', (int) $area->id];
    }
    foreach ([
        ['gwa', 'General Weighted Average', 'numeric'],
        ['shs_strand', 'Senior High School Strand', 'categorical'],
        ['shs_math', 'Senior High School Mathematics', 'numeric'],
        ['shs_sci', 'Senior High School Science', 'numeric'],
        ['shs_eng', 'Senior High School English', 'numeric'],
        ['riasec_r', 'RIASEC Realistic', 'binary'],
        ['riasec_i', 'RIASEC Investigative', 'binary'],
        ['riasec_a', 'RIASEC Artistic', 'binary'],
        ['riasec_s', 'RIASEC Social', 'binary'],
        ['riasec_e', 'RIASEC Enterprising', 'binary'],
        ['riasec_c', 'RIASEC Conventional', 'binary'],
    ] as [$key, $label, $type]) {
        $definitions[] = [$key, $label, $type, null];
    }
    $schemaRows = array_map(static fn (array $definition): array => [$definition[0], $definition[2], $definition[3]], $definitions);
    $source = AdmissionBenchmarkSet::query()->create([
        'institution_id' => $institution->id,
        'version' => (int) AdmissionBenchmarkSet::query()->where('institution_id', $institution->id)->max('version') + 1,
        'status' => 'published',
        'change_reason' => 'Synthetic fixture with an unverified historical payload digest.',
        'schema_sha256' => hash('sha256', json_encode($schemaRows, JSON_THROW_ON_ERROR)),
        'content_sha256' => hash('sha256', 'historical import payload digest not reconstructible from stored rows'),
        'created_by_user_id' => $guidance->id,
        'published_by_user_id' => $guidance->id,
        'published_at' => now(),
        'idempotency_key' => 'test-historical-recovery-source',
        'import_payload_sha256' => hash('sha256', 'synthetic historical fixture payload'),
    ]);
    $features = [];
    foreach ($definitions as $position => [$key, $label, $type, $areaId]) {
        $features[$key] = $source->features()->create([
            'position' => $position + 1,
            'feature_key' => $key,
            'label' => $label,
            'logical_type' => $type,
            'aptitude_area_id' => $areaId,
            'is_required' => false,
        ]);
    }

    // Legacy schema digests cover only key, logical type, and aptitude-area ID.
    $legacySchema = $source->features()->orderBy('position')->get()->map(static fn ($feature): array => [
        $feature->feature_key,
        $feature->logical_type,
        $feature->aptitude_area_id === null ? null : (int) $feature->aptitude_area_id,
    ])->all();
    $source->forceFill(['schema_sha256' => hash('sha256', json_encode($legacySchema, JSON_THROW_ON_ERROR))])->save();

    $baseRows = $base->exemplars->groupBy('program_id');
    $oldLabels = [1 => 'Honors High', 2 => 'Honors Low', 3 => 'Regular', 4 => 'Cutoff', 5 => 'Below Cutoff'];
    foreach ($baseRows as $programRows) {
        $program = $programRows->first()->program;
        foreach (range(1, 5) as $tier) {
            $baseRow = $programRows->firstWhere('tier_level', $tier);
            $exemplar = $source->exemplars()->create([
                'program_id' => $program->id,
                'tier_level' => $tier,
                'tier_label' => $oldLabels[$tier],
                'target_class' => $tier === 5 ? 0 : 1,
            ]);
            $baseValues = $baseRow->values->keyBy(fn ($value): string => $value->feature->feature_key);
            foreach ($definitions as [$key, , $type]) {
                $baseValue = in_array($key, ['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa'], true)
                    ? $baseValues->get($key)
                    : null;
                $isMissing = ($baseValue === null && ! str_starts_with($key, 'riasec_') && $key !== 'shs_strand')
                    || ($baseValue !== null && $baseValue->is_missing)
                    || in_array($key, ['VERBAL', 'QUANT'], true);
                $value = match ($key) {
                    'shs_strand' => 'STEM',
                    default => str_starts_with($key, 'riasec_') ? 0 : ($baseValue?->numeric_value),
                };
                AdmissionBenchmarkExemplarValue::query()->create([
                    'exemplar_id' => $exemplar->id,
                    'benchmark_feature_id' => $features[$key]->id,
                    'is_missing' => $isMissing,
                    'numeric_value' => $isMissing || $type === 'categorical' ? null : $value,
                    'categorical_value' => $isMissing || $type !== 'categorical' ? null : $value,
                ]);
            }
        }
    }

    $previousActivation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    expect($previousActivation)->not->toBeNull();
    DB::table('admission_benchmark_activations')->where('id', $previousActivation->id)->update(['superseded_at' => now()]);
    $activation = AdmissionBenchmarkActivation::query()->create([
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'benchmark_set_id' => $source->id,
        'sequence' => (int) AdmissionBenchmarkActivation::query()->where('academic_cycle_id', $cycle->id)->max('sequence') + 1,
        'activated_by_user_id' => $guidance->id,
        'idempotency_key' => 'test-historical-recovery-activation',
        'activated_at' => now(),
    ]);

    return [$institution, $cycle, $source->fresh(), $activation, $guidance];
}

function completeHistoricalRecoveryContent(array $content): array
{
    foreach ($content['tiers'] as &$row) {
        foreach ($row['values'] as $key => $value) {
            if ($value === null) {
                $row['values'][$key] = 76.0;
            }
        }
        ksort($row['values'], SORT_STRING);
    }
    unset($row);

    return $content;
}

function historicalRecoveryPreviewInput(AcademicCycle $cycle, Institution $institution): AdmissionInputPayload
{
    return new AdmissionInputPayload(
        attemptId: 0,
        institutionId: (int) $institution->id,
        academicCycleId: (int) $cycle->id,
        gwa: 90.0,
        aptitudePercentiles: ['LOGIC' => 82.0, 'PROFILE_VERBAL' => 80.0, 'PROFILE_QUANT' => 81.0, 'SPATIAL' => 77.0, 'MEMORY' => 76.0, 'LANGUAGE' => 75.0],
    );
}

/** Synthetic HTTP response for consumer-contract coverage, not real-model evidence. */
function historicalRecoveryNeuralSuccessFixture(array $request): array
{
    $programs = collect($request['context_exemplars'])
        ->unique('program_code')
        ->sortBy('program_code')
        ->values();
    $featureDefinitions = $request['feature_definitions'];
    $applicant = $request['query_applicant'];
    $featureCoverage = [];
    $missingFeatures = [];
    $missingOptionalFeatures = [];
    foreach ($featureDefinitions as $definition) {
        $featureKey = $definition['feature_key'];
        $available = array_key_exists($featureKey, $applicant) && $applicant[$featureKey] !== null;
        $required = (bool) $definition['is_required'];
        $featureCoverage[$featureKey] = ['available' => $available, 'required' => $required];
        if (! $available) {
            $missingFeatures[] = $featureKey;
            if (! $required) {
                $missingOptionalFeatures[] = $featureKey;
            }
        }
    }
    $recommendations = [];
    foreach ($programs as $program) {
        $recommendations[] = [
            'rank' => count($recommendations) + 1,
            'program_code' => $program['program_code'],
            'program_name' => $program['program_name'],
            'compatibility_index' => 75,
            'band' => 'moderate_match',
            'drivers' => ['Synthetic neural success fixture'],
        ];
    }
    $packageVersion = '9.1.0';

    return [
        'status' => 'evaluated',
        'engine_version' => $packageVersion,
        'inference_backend' => 'tabpfn_pytorch',
        'fallback_reason' => null,
        'provenance' => [
            'package' => 'tabpfn',
            'package_version' => $packageVersion,
            'checkpoint_identity' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
            'checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
        ],
        'execution_time_ms' => 1.0,
        'modality_coverage' => [
            'available_features' => count($featureDefinitions) - count($missingFeatures),
            'total_features' => count($featureDefinitions),
            'missing_optional_features' => $missingOptionalFeatures,
            'missing_features' => $missingFeatures,
            'feature_coverage' => $featureCoverage,
        ],
        'feature_provenance' => array_map(static fn (array $definition): array => array_diff_key($definition, ['scale' => true]), $featureDefinitions),
        'recommendations' => $recommendations,
    ];
}

test('known historical active set is projected as unusable and explicit recovery maps exact old tiers append-only', function (): void {
    [$institution, $cycle, $source, $activation, $guidance] = historicalRecoveryFixture();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    config(['admission_engine.tabfm.driver' => 'http', 'admission_engine.tabfm.endpoint' => 'http://tabfm-inference:8101']);
    Http::preventStrayRequests();
    $input = historicalRecoveryPreviewInput($cycle, $institution);
    $activePreview = app(ProgramBenchmarkPreviewQuery::class)->handle($guidance, $program, $cycle, $input, 'active', requestId: 'historical-active-preview');
    expect(app(TabFmPairwiseProvider::class)->canEvaluate($input))->toBeFalse()
        ->and($activePreview->status)->toBe('standby')
        ->and($activePreview->recommendations)->toBe([])
        ->and($activePreview->evaluationContext?->benchmarkSetId)->toBe($source->id)
        ->and(fn () => app(TabFmPairwiseProvider::class)->evaluate($input))
        ->toThrow(InvalidArgumentException::class, 'historical benchmark');
    $verifier = app(KnownHistoricalBenchmarkRecovery::class);
    expect($verifier->isKnownHistoricalSet($source))->toBeTrue();
    $sourceSnapshotDigest = $verifier->digest($verifier->sourceSnapshot($source));
    $sourceRowCount = $source->exemplars()->count();
    $sourceHashes = [$source->schema_sha256, $source->content_sha256];
    $authorizer = app(GovernanceAuthorizer::class);
    $query = app(AdmissionSupportSetupDashboardQuery::class);
    $governance = $query->governance($guidance, $program, $cycle, $authorizer, app(ProgramAdvisingNoteQuery::class), app(ProgramGovernanceDiscussionQuery::class));

    expect($governance['active_set'])->toBeNull()
        ->and($governance['active_content'])->toBeNull()
        ->and($governance['recovery_content'])->toBeNull()
        ->and($governance['benchmark_snapshot']['usable'])->toBeFalse()
        ->and($governance['recovery']['status'])->toBe('available')
        ->and($governance['recovery']['source_content_sha256'])->toBe($source->content_sha256)
        ->and($governance['recovery']['source_snapshot_digest'])->toBe($sourceSnapshotDigest)
        ->and($governance['recovery']['source_activation_id'])->toBe($activation->id)
        ->and($governance['recovery']['historical_content_verified'])->toBeFalse()
        ->and($governance['schema_features'])->toHaveCount(19);

    $recover = app(RecoverHistoricalProgramBenchmarkDraft::class);
    $unauthorized = User::factory()->create();
    expect(fn () => $recover->handle($unauthorized, $program, $cycle, 0, $sourceSnapshotDigest, 'Unauthorized recovery', 'historical-recover-unauthorized'))
        ->toThrow(AuthorizationException::class);
    $response = actingAsAdmissionSupportStaff($this, $guidance)
        ->postJson(route('setup.admission-support.benchmarks.draft.recover', [$program, $cycle]), [
            'expected_version' => 0,
            'expected_source_digest' => $sourceSnapshotDigest,
            'reason' => 'Recover source cells for Guidance review',
            'idempotency_key' => 'historical-recover-bsit',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('recovery.source_snapshot_digest', $sourceSnapshotDigest);
    $revision = AdmissionBenchmarkRevision::query()->findOrFail($response->json('revision.id'));
    $replay = $recover->handle($guidance, $program, $cycle, 0, $sourceSnapshotDigest, 'Recover source cells for Guidance review', 'historical-recover-bsit');
    $oldRows = $source->exemplars()->where('program_id', $program->id)->with('values.feature')->get()->keyBy('tier_level');
    $oldRegular = $oldRows->get(3)->values->first(fn ($value): bool => $value->feature->feature_key === 'gwa')->numeric_value;
    $oldCutoff = $oldRows->get(4)->values->first(fn ($value): bool => $value->feature->feature_key === 'gwa')->numeric_value;

    expect($replay->id)->toBe($revision->id)
        ->and($revision->version)->toBe(1)
        ->and((float) $revision->content['tiers']['2']['values']['gwa'])->toBe((float) $oldRegular)
        ->and((float) $revision->content['tiers']['3']['values']['gwa'])->toBe((float) $oldCutoff)
        ->and($revision->content['tiers']['1']['values']['gwa'])->toBeNumeric()
        ->and(array_keys($revision->content['tiers']['1']['values']))->toBe(['LOGIC', 'LANGUAGE', 'MEMORY', 'PROFILE_QUANT', 'PROFILE_VERBAL', 'SPATIAL', 'gwa']);

    $schema = BenchmarkInputSchema::schemaForDraft($revision->draft, $revision);
    expect(array_column($schema, 'feature_key'))->toBe(['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa']);
    $edited = $revision->content;
    $edited['tiers']['4']['values']['gwa'] = 81.0;
    $saved = app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $edited, 1, 'Review canonical GWA', 'historical-save-bsit');
    expect($saved->version)->toBe(2)
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)->id)->toBe($activation->id)
        ->and($source->fresh()->schema_sha256)->toBe($sourceHashes[0])
        ->and($source->fresh()->content_sha256)->toBe($sourceHashes[1])
        ->and($source->exemplars()->count())->toBe($sourceRowCount)
        ->and($verifier->digest($verifier->sourceSnapshot($source->fresh())))->toBe($sourceSnapshotDigest);

    expect(fn () => $recover->handle($guidance, $program, $cycle, 0, $sourceSnapshotDigest, 'Changed reason', 'historical-recover-bsit'))
        ->toThrow(RuntimeException::class);
    $guidance->syncRoles([]);
    expect(fn () => $recover->handle($guidance, $program, $cycle, 0, $sourceSnapshotDigest, 'Recover source cells for Guidance review', 'historical-recover-bsit'))
        ->toThrow(AuthorizationException::class);
});

test('incomplete recovery cannot preview or publish, and source mutation fails closed', function (): void {
    [$institution, $cycle, $source, $activation, $guidance] = historicalRecoveryFixture();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    $verifier = app(KnownHistoricalBenchmarkRecovery::class);
    $digest = $verifier->digest($verifier->sourceSnapshot($source));
    $revision = app(RecoverHistoricalProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, 0, $digest, 'Recover incomplete synthetic rows', 'recover-incomplete-one');

    $schema = BenchmarkInputSchema::schemaForDraft($revision->draft, $revision);
    expect($schema)->toHaveCount(7)
        ->and(array_column($schema, 'feature_key'))->toBe(['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa'])
        ->and(collect($revision->content['tiers'])->flatMap(fn (array $tier): array => $tier['values'])->contains(null))->toBeTrue();

    $draftCount = AdmissionBenchmarkSet::query()->count();
    expect(fn () => app(PublishProgramBenchmarkSet::class)->handle(
        $guidance,
        $cycle,
        [$program->id => $revision->id],
        (string) $source->content_sha256,
        'Attempt incomplete recovery publication',
        'publish-incomplete-recovery',
        null,
        ['expected_predecessor_schema_digest' => (string) $source->schema_sha256],
    ))->toThrow(InvalidArgumentException::class);
    expect(AdmissionBenchmarkSet::query()->count())->toBe($draftCount)
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)->id)->toBe($activation->id);

    $value = $source->exemplars()->where('program_id', $program->id)->where('tier_level', 1)->with('values.feature')->firstOrFail()
        ->values->first(fn ($cell): bool => $cell->feature->feature_key === 'gwa');
    DB::table('admission_benchmark_exemplar_values')->where('id', $value->id)->update(['numeric_value' => 98]);
    expect(fn () => app(SaveProgramBenchmarkDraft::class)->handle(
        $guidance,
        $program,
        $cycle,
        $revision->content,
        1,
        'Save after source tampering',
        'recover-source-tampered-save',
    ))->toThrow(RuntimeException::class)
        ->and(fn () => app(RebaseProgramBenchmarkDraft::class)->handle(
            $guidance,
            $program,
            $cycle,
            1,
            (int) $source->id,
            (string) $source->content_sha256,
            'Attempt rebase after source tampering',
            'recover-source-tampered-rebase',
        ))->toThrow(RuntimeException::class, 'immutable stored content');

});

test('completed all-source recovery previews, publishes the exact canonical successor, then activates separately', function (): void {
    [$institution, $cycle, $source, $activation, $guidance] = historicalRecoveryFixture();
    $verifier = app(KnownHistoricalBenchmarkRecovery::class);
    $digest = $verifier->digest($verifier->sourceSnapshot($source));
    $sourceProgramIds = $verifier->sourceProgramIds($source);
    $manifest = [];
    $revisions = [];
    $reorderedSnapshots = false;
    foreach ($sourceProgramIds as $programId) {
        $program = Program::query()->findOrFail($programId);
        $revision = app(RecoverHistoricalProgramBenchmarkDraft::class)->handle(
            $guidance,
            $program,
            $cycle,
            0,
            $digest,
            'Recover explicitly for synthetic Guidance review',
            'recover-complete-'.$program->id,
        );
        if (! $reorderedSnapshots) {
            $recoveryId = AdmissionBenchmarkDraft::query()
                ->where('program_id', $program->id)
                ->where('academic_cycle_id', $cycle->id)
                ->value('recovery_id');
            $recovery = AdmissionBenchmarkRecovery::query()->findOrFail($recoveryId);
            DB::table('admission_benchmark_recoveries')->where('id', $recoveryId)->update([
                'source_snapshot_json' => json_encode(reorderRecoverySnapshotKeys($recovery->source_snapshot_json), JSON_THROW_ON_ERROR),
                'source_activation_snapshot_json' => json_encode(reorderRecoverySnapshotKeys($recovery->source_activation_snapshot_json), JSON_THROW_ON_ERROR),
            ]);
            $reorderedSnapshots = true;
        }
        $manifest[(string) $program->id] = $revision->id;
        $revisions[$program->id] = $revision;
    }

    $firstProgram = Program::query()->findOrFail($sourceProgramIds[0]);

    expect(fn () => app(PublishProgramBenchmarkSet::class)->handle(
        $guidance,
        $cycle,
        $manifest,
        (string) $source->content_sha256,
        'Reject legacy historical publication mode for new writes',
        'publish-complete-recovery',
        ['source_set_id' => (int) $source->id, 'source_snapshot_digest' => $digest, 'source_activation_id' => (int) $activation->id],
    ))->toThrow(InvalidArgumentException::class, 'canonical schema-cutover review');
    $published = app(PublishProgramBenchmarkSet::class)->handle(
        $guidance,
        $cycle,
        $manifest,
        (string) $source->content_sha256,
        'Publish exact full synthetic canonical cutover manifest',
        'publish-complete-recovery-cutover',
        null,
        ['expected_predecessor_schema_digest' => (string) $source->schema_sha256],
    );
    $sourceSnapshotDigest = $verifier->digest($verifier->sourceSnapshot($source->fresh()));
    expect($published->status)->toBe('published')
        ->and($published->predecessor_id)->toBe($source->id)
        ->and($published->computedContentDigest())->toBe($published->content_sha256)
        ->and($published->computedSchemaDigest())->toBe($published->schema_sha256)
        ->and($published->exemplars()->count())->toBe(count($sourceProgramIds) * 5)
        ->and($published->exemplars()->where('tier_label', 'Honors')->exists())->toBeTrue()
        ->and($published->exemplars()->where('tier_label', 'Regular')->exists())->toBeTrue()
        ->and($published->exemplars()->where('tier_label', 'Cutoff')->exists())->toBeTrue()
        ->and($published->exemplars()->where('tier_label', 'Cross-Strand')->exists())->toBeTrue()
        ->and($published->exemplars()->where('tier_label', 'Negative')->exists())->toBeTrue()
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)->id)->toBe($activation->id)
        ->and($verifier->digest($verifier->sourceSnapshot($source->fresh())))->toBe($sourceSnapshotDigest)
        ->and(array_column($published->features->sortBy('position')->all(), 'feature_key'))->toBe(['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa']);
    expect(fn () => app(PublishProgramBenchmarkSet::class)->handle(
        $guidance,
        $cycle,
        $manifest,
        (string) $source->content_sha256,
        'Reject stale predecessor schema review',
        'publish-stale-historical-cutover',
        null,
        ['expected_predecessor_schema_digest' => str_repeat('0', 64)],
    ))->toThrow(RuntimeException::class, 'exact broad predecessor changed')
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)->id)->toBe($activation->id);

    $activated = app(ActivateAdmissionBenchmarkSet::class)->handle(
        $cycle,
        $published,
        BenchmarkExecutionContext::human($guidance),
        $activation->id,
        (string) $published->content_sha256,
        'activate-complete-recovery-successor',
    );
    expect($activated->benchmark_set_id)->toBe($published->id)
        ->and($activation->fresh()->superseded_at)->not->toBeNull()
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)->id)->toBe($activated->id)
        ->and($source->fresh()->content_sha256)->not->toBe($published->content_sha256);
    $draft = AdmissionBenchmarkDraft::query()->where('program_id', $firstProgram->id)->where('academic_cycle_id', $cycle->id)->firstOrFail();
    $beforeRebase = $draft->currentRevision();
    $recoveryId = $draft->recovery_id;
    $rebase = app(RebaseProgramBenchmarkDraft::class)->handle(
        $guidance,
        $firstProgram,
        $cycle,
        (int) $draft->current_version,
        (int) $published->id,
        (string) $published->content_sha256,
        'Review and rebase exact recovered successor',
        'rebase-complete-recovery-successor',
    );
    $afterRebase = $rebase->content;
    $afterRebase['tiers']['4']['values']['gwa'] = 82.0;
    $savedAfterRebase = app(SaveProgramBenchmarkDraft::class)->handle(
        $guidance,
        $firstProgram,
        $cycle,
        $afterRebase,
        $rebase->version,
        'Save after explicit recovery rebase',
        'save-after-recovery-rebase',
    );
    expect($rebase->content)->toBe($beforeRebase->content)
        ->and((float) $savedAfterRebase->content['tiers']['4']['values']['gwa'])->toBe(82.0)
        ->and($savedAfterRebase->content['tiers']['1'])->toBe($beforeRebase->content['tiers']['1'])
        ->and($draft->fresh()->recovery_id)->toBeNull()
        ->and(AdmissionBenchmarkRecovery::query()->whereKey($recoveryId)->exists())->toBeTrue()
        ->and(AdmissionBenchmarkRecovery::query()->findOrFail($recoveryId)->source_activation_id)->toBe($activation->id);

    $secondProgram = Program::query()->findOrFail($sourceProgramIds[1]);
    $secondDraft = AdmissionBenchmarkDraft::query()->where('program_id', $secondProgram->id)->where('academic_cycle_id', $cycle->id)->firstOrFail();
    $secondContent = $secondDraft->currentRevision()->content;
    $secondRebase = app(RebaseProgramBenchmarkDraft::class)->handle(
        $guidance,
        $secondProgram,
        $cycle,
        (int) $secondDraft->current_version,
        (int) $published->id,
        (string) $published->content_sha256,
        'Rebase another source program after its sibling was rebased and edited',
        'rebase-second-recovery-program',
    );
    expect($secondRebase->content)->toBe($secondContent)
        ->and($secondDraft->fresh()->recovery_id)->toBeNull()
        ->and($secondDraft->fresh()->base_benchmark_set_id)->toBe($published->id)
        ->and(AdmissionBenchmarkRecovery::query()->findOrFail($recoveryId)->source_activation_id)->toBe($activation->id);
});

test('modern benchmark integrity failure cannot use the historical recovery exception', function (): void {
    [$institution, $cycle, $modern, $guidance] = (function (): array {
        seedE2Foundation();
        (new AdmissionBenchmarkSeeder)->run();

        return [
            Institution::query()->where('code', 'SYN-INST-001')->firstOrFail(),
            AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail(),
            AdmissionBenchmarkSet::query()->where('status', 'published')->latest('id')->firstOrFail(),
            User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail(),
        ];
    })();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    $content = ['tiers' => []];
    foreach (range(1, 5) as $tier) {
        $values = [];
        foreach ($modern->features as $feature) {
            $values[$feature->feature_key] = $feature->logical_type === 'categorical' ? 'STEM' : ($feature->logical_type === 'binary' ? 1 : 80.0);
        }
        $content['tiers'][(string) $tier] = ['values' => $values];
    }
    $revision = app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $content, 0, 'Modern integrity fixture', 'modern-integrity-draft');
    $featureValue = $modern->exemplars()->where('program_id', $program->id)->where('tier_level', 1)->with('values.feature')->firstOrFail()
        ->values->first(fn ($cell): bool => $cell->feature->feature_key === 'gwa');
    DB::table('admission_benchmark_exemplar_values')->where('id', $featureValue->id)->update(['numeric_value' => 97]);

    expect(app(KnownHistoricalBenchmarkRecovery::class)->isKnownHistoricalSet($modern->fresh()))->toBeFalse()
        ->and(fn () => app(ProgramBenchmarkPreviewQuery::class)->handle($guidance, $program, $cycle, historicalRecoveryPreviewInput($cycle, $institution), 'draft', $revision->id))
        ->toThrow(InvalidArgumentException::class, 'changed');
    config(['admission_engine.tabfm.driver' => 'http', 'admission_engine.tabfm.endpoint' => 'http://tabfm-inference:8101']);
    Http::preventStrayRequests();
    $input = historicalRecoveryPreviewInput($cycle, $institution);
    expect(app(TabFmPairwiseProvider::class)->canEvaluate($input))->toBeFalse()
        ->and(fn () => app(TabFmPairwiseProvider::class)->evaluate($input))
        ->toThrow(InvalidArgumentException::class, 'integrity check')
        ->and(fn () => app(ProgramBenchmarkPreviewQuery::class)->handle($guidance, $program, $cycle, $input, 'active'))
        ->toThrow(InvalidArgumentException::class, 'integrity check');
});
test('historical source schema explicitly registers and starts an offered program outside the old source set', function (): void {
    [$institution, $cycle, $source, $activation, $guidance] = historicalRecoveryFixture();
    $program = Program::query()->create([
        'institution_id' => $institution->id,
        'code' => 'SYN-HISTORICAL-NON-SOURCE',
        'name' => 'Synthetic offered program outside the historical source',
        'is_active' => true,
        'is_admission_offered' => true,
    ]);
    $sourceDigest = $source->content_sha256;
    $sourceExemplarCount = $source->exemplars()->count();

    $revision = app(StartProgramBenchmarkDraft::class)->handle(
        $guidance,
        $program,
        $cycle,
        0,
        'Review a new offered program against frozen historical schema',
        'historical-non-source-start',
    );

    $recovery = AdmissionBenchmarkRecovery::query()
        ->where('source_benchmark_set_id', $source->id)
        ->where('academic_cycle_id', $cycle->id)
        ->firstOrFail();
    $review = AdmissionBenchmarkRecoveryProgramReview::query()
        ->where('recovery_id', $recovery->id)
        ->where('program_id', $program->id)
        ->firstOrFail();
    $manifest = app(KnownHistoricalBenchmarkRecovery::class)->reviewManifest($recovery, $source);
    $draft = AdmissionBenchmarkDraft::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->firstOrFail();

    $schema = BenchmarkInputSchema::schemaForDraft($draft, $revision);
    $values = $revision->content['tiers'];
    expect($revision->base_benchmark_set_id)->toBe($source->id)
        ->and($revision->version)->toBe(1)
        ->and(array_column($schema, 'feature_key'))->toBe(['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa'])
        ->and(collect($values)->flatMap(fn (array $tier): array => $tier['values'])->every(fn ($value): bool => $value === null))->toBeTrue()
        ->and($review->registered_by_user_id)->toBe($guidance->id)
        ->and($review->reason)->toBe('Review a new offered program against frozen historical schema')
        ->and($manifest['retained_program_ids'])->toContain($program->id)
        ->and($source->fresh()->content_sha256)->toBe($sourceDigest)
        ->and($source->fresh()->exemplars()->count())->toBe($sourceExemplarCount);
});
test('historical recovery rejects stale snapshot digests and source tampering', function (): void {
    [$institution, $cycle, $source, , $guidance] = historicalRecoveryFixture();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    $recovery = app(RecoverHistoricalProgramBenchmarkDraft::class);
    $verifier = app(KnownHistoricalBenchmarkRecovery::class);
    $digest = $verifier->digest($verifier->sourceSnapshot($source));

    expect(fn () => $recovery->handle($guidance, $program, $cycle, 0, str_repeat('0', 64), 'Stale recovery review', 'stale-historical-digest'))
        ->toThrow(RuntimeException::class, 'changed');
    $revision = $recovery->handle($guidance, $program, $cycle, 0, $digest, 'Recover exact historical snapshot', 'exact-historical-digest');
    $cell = $source->exemplars()->where('program_id', $program->id)->where('tier_level', 1)->with('values.feature')->firstOrFail()
        ->values->first(fn ($value): bool => $value->feature->feature_key === 'gwa');
    DB::table('admission_benchmark_exemplar_values')->where('id', $cell->id)->update(['numeric_value' => 98]);

    expect(fn () => $recovery->handle($guidance, $program, $cycle, 0, $digest, 'Replay after source tampering', 'tampered-historical-replay'))
        ->toThrow(RuntimeException::class)
        ->and(fn () => app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $revision->content, 1, 'Save after source tampering', 'tampered-historical-save'))
        ->toThrow(RuntimeException::class)
        ->and(fn () => app(CutoverProgramBenchmarkDraftSchema::class)->handle($guidance, $program, $cycle, 1, 'Cut over after source tampering', 'tampered-historical-cutover', $digest))
        ->toThrow(RuntimeException::class);
});
