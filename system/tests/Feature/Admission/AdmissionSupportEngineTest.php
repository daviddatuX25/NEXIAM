<?php

use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\CutoverProgramBenchmarkDraftSchema;
use App\AdmissionEngine\Actions\ImportAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\PublishProgramBenchmarkSet;
use App\AdmissionEngine\Actions\SaveProgramBenchmarkDraft;
use App\AdmissionEngine\Actions\StartProgramBenchmarkDraft;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\DTOs\EngineEvaluationResult;
use App\AdmissionEngine\Providers\TabFmPairwiseProvider;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkExemplarValue;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Database\Seeders\AdmissionBenchmarkSeeder;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/../../Support/AdmissionModelFixtures.php';

beforeEach(function (): void {
    config([
        'admission_engine.tabfm.institutional_use_approved' => true,
        'admission_engine.tabfm.hosted_use_approved' => true,
        'admission_engine.tabfm.package_version' => '9.1.0',
        'admission_engine.tabfm.checkpoint_id' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
        'admission_engine.tabfm.checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
    ]);
    Http::preventStrayRequests();
});

/** Synthetic HTTP response for consumer-contract coverage, not real-model evidence. */
function admissionSupportEngineNeuralResponse(
    ClientRequest $request,
    array $scores = [],
    array $passedCutoffFlags = [],
): array {
    $programs = [];
    foreach ($request['context_exemplars'] as $exemplar) {
        $programs[$exemplar['program_code']] = $exemplar['program_name'];
    }
    ksort($programs);

    $recommendations = [];
    foreach ($programs as $programCode => $programName) {
        $score = $scores[$programCode] ?? 80;
        $normalizedScore = (int) round($score);
        $recommendation = [
            'rank' => 0,
            'program_code' => $programCode,
            'program_name' => $programName,
            'compatibility_index' => $score,
            'band' => $normalizedScore >= 80 ? 'high_match' : ($normalizedScore >= 68 ? 'moderate_match' : 'below_threshold'),
            'drivers' => ['Synthetic adapter attribution'],
        ];
        if (array_key_exists($programCode, $passedCutoffFlags)) {
            $recommendation['passed_cutoff'] = $passedCutoffFlags[$programCode];
        }
        $recommendations[] = $recommendation;
    }
    usort($recommendations, static fn (array $left, array $right): int => ($right['compatibility_index'] <=> $left['compatibility_index']) ?: ($left['program_code'] <=> $right['program_code']));
    foreach ($recommendations as $position => &$recommendation) {
        $recommendation['rank'] = $position + 1;
    }
    unset($recommendation);

    $featureDefinitions = $request['feature_definitions'];
    $queryApplicant = $request['query_applicant'];
    $featureCoverage = [];
    $missingFeatures = [];
    $missingOptionalFeatures = [];
    foreach ($featureDefinitions as $definition) {
        $featureKey = $definition['feature_key'];
        $available = array_key_exists($featureKey, $queryApplicant) && $queryApplicant[$featureKey] !== null;
        $required = (bool) $definition['is_required'];
        $featureCoverage[$featureKey] = ['available' => $available, 'required' => $required];
        if (! $available) {
            $missingFeatures[] = $featureKey;
            if (! $required) {
                $missingOptionalFeatures[] = $featureKey;
            }
        }
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
        'execution_time_ms' => 1.25,
        'modality_coverage' => [
            'available_features' => count($featureDefinitions) - count($missingFeatures),
            'total_features' => count($featureDefinitions),
            'missing_optional_features' => $missingOptionalFeatures,
            'missing_features' => $missingFeatures,
            'feature_coverage' => $featureCoverage,
        ],
        'feature_provenance' => $featureDefinitions,
        'recommendations' => $recommendations,
    ];
}

/** @return array{activation:AdmissionBenchmarkActivation} */
function admissionSupportEngineDynamicFeatureFixture(): array
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $active = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    $base = $active->benchmarkSet()->with(['features', 'exemplars.values.feature'])->firstOrFail();
    $set = $base->replicate();
    $set->setRelations([]);
    $set->forceFill([
        'status' => 'draft', 'version' => (int) AdmissionBenchmarkSet::query()->max('version') + 1,
        'name' => 'Synthetic frozen broad-source fixture', 'idempotency_key' => 'synthetic-broad-source',
    ])->save();
    $featureIds = [];
    foreach ($base->features as $feature) {
        $copy = $feature->replicate();
        $copy->setRelations([]);
        $copy->benchmark_set_id = $set->id;
        $copy->save();
        $featureIds[$feature->id] = $copy->id;
    }
    $strand = $set->features()->create(['feature_key' => 'shs_strand', 'label' => 'Historical strand', 'logical_type' => 'categorical', 'position' => 8, 'is_required' => false]);
    $subject = $set->features()->create(['feature_key' => 'biology_grade', 'label' => 'Historical Biology', 'logical_type' => 'numeric', 'position' => 9, 'is_required' => false]);
    foreach ($base->exemplars as $exemplar) {
        $copy = $exemplar->replicate();
        $copy->setRelations([]);
        $copy->benchmark_set_id = $set->id;
        $copy->save();
        foreach ($exemplar->values as $value) {
            $cell = $value->replicate();
            $cell->setRelations([]);
            $cell->exemplar_id = $copy->id;
            $cell->benchmark_feature_id = $featureIds[$value->benchmark_feature_id];
            $cell->save();
        }
        $copy->values()->create(['benchmark_feature_id' => $strand->id, 'is_missing' => false, 'categorical_value' => 'STEM']);
        $copy->values()->create(['benchmark_feature_id' => $subject->id, 'is_missing' => false, 'numeric_value' => 84]);
    }
    $set->forceFill(['schema_sha256' => $set->computedSchemaDigest(), 'content_sha256' => $set->computedContentDigest(), 'status' => 'published'])->save();
    DB::table('admission_benchmark_activations')->where('id', $active->id)->update(['superseded_at' => now()]);
    $activation = AdmissionBenchmarkActivation::query()->create([
        'institution_id' => $cycle->institution_id, 'academic_cycle_id' => $cycle->id,
        'benchmark_set_id' => $set->id, 'sequence' => $active->sequence + 1,
        'activated_by_user_id' => $active->activated_by_user_id,
        'idempotency_key' => 'synthetic-broad-activation', 'activated_at' => now(),
    ]);

    return ['activation' => $activation];
}

test('ImportAdmissionBenchmarkSet snapshots dynamic AptitudeArea features and leak-free pairwise exemplars with idempotency', function (): void {
    seedE2Foundation();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $importer = app(ImportAdmissionBenchmarkSet::class);

    $payload = [
        'name' => 'ISPSC Tagudin Studio Calibration v1',
        'change_reason' => 'Initial psychometrician calibration',
        'programs' => [
            ['code' => 'BSIT', 'name' => 'BS Information Technology'],
            ['code' => 'BSBA', 'name' => 'Bachelor of Science in Business Administration'],
        ],
        'raw_ranges' => [
            'BSIT' => [
                'honors' => ['gwa' => [90, 96], 'gen' => [82, 95], 'verb' => [75, 90], 'num' => [85, 98], 'spat' => [80, 95], 'perc' => [78, 92], 'man' => [75, 90]],
                'regular' => ['gwa' => [85, 89.9], 'gen' => [74, 81], 'verb' => [70, 74], 'num' => [76, 84], 'spat' => [72, 79], 'perc' => [70, 77], 'man' => [70, 74]],
                'cutoff' => ['gwa' => [82, 84.9], 'gen' => [68, 73], 'verb' => [65, 69], 'num' => [70, 75], 'spat' => [68, 71], 'perc' => [65, 69], 'man' => [65, 69]],
            ],
            'BSBA' => [
                'honors' => ['gwa' => [91, 97], 'gen' => [84, 96], 'verb' => [82, 94], 'num' => [82, 94], 'spat' => [78, 90], 'perc' => [84, 96], 'man' => [82, 95]],
                'regular' => ['gwa' => [86, 90.9], 'gen' => [76, 83], 'verb' => [75, 81], 'num' => [75, 81], 'spat' => [72, 77], 'perc' => [76, 83], 'man' => [75, 81]],
                'cutoff' => ['gwa' => [84, 85.9], 'gen' => [72, 75], 'verb' => [70, 74], 'num' => [70, 74], 'spat' => [68, 71], 'perc' => [72, 75], 'man' => [70, 74]],
            ],
        ],
    ];

    $context = BenchmarkExecutionContext::human($guidance);
    $set = $importer->handle($institution, $cycle, $context, $payload, 'bench-import-key-1');
    $replay = $importer->handle($institution, $cycle, $context, $payload, 'bench-import-key-1');
    $changedPayload = $payload;
    $changedPayload['change_reason'] = 'Changed calibration payload';
    expect(fn () => $importer->handle($institution, $cycle, $context, $changedPayload, 'bench-import-key-1'))
        ->toThrow(RuntimeException::class);

    expect($set->id)->toBe($replay->id)
        ->and($set->status)->toBe('published')
        ->and($set->content_sha256)->toMatch('/^[a-f0-9]{64}$/');

    $featureKeys = $set->features()->orderBy('position')->pluck('feature_key')->all();
    expect($featureKeys)->toEqual(['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa']);

    // 2 programs * 5 synthesized tiers = 10 exemplars (8 positive target_class=1, 2 negative target_class=0)
    expect($featureKeys)->toEqual(['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa']);
    expect($set->exemplars()->count())->toBe(10)
        ->and($set->exemplars()->where('target_class', 1)->count())->toBe(8)
        ->and($set->exemplars()->where('target_class', 0)->count())->toBe(2);
    expect(AuditEvent::query()->where('event_type', 'admission_benchmark.imported')->exists())->toBeTrue();
});

test('ActivateAdmissionBenchmarkSet atomically supersedes prior cycle activations under lock', function (): void {
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $importer = app(ImportAdmissionBenchmarkSet::class);
    $activator = app(ActivateAdmissionBenchmarkSet::class);

    $baseRanges = [
        'BSBA' => [
            'honors' => ['gwa' => [90, 95], 'gen' => [80, 90], 'verb' => [80, 90], 'num' => [80, 90], 'spat' => [75, 85], 'perc' => [80, 90], 'man' => [80, 90]],
            'regular' => ['gwa' => [85, 89], 'gen' => [74, 79], 'verb' => [74, 79], 'num' => [74, 79], 'spat' => [70, 74], 'perc' => [74, 79], 'man' => [74, 79]],
            'cutoff' => ['gwa' => [82, 84], 'gen' => [70, 73], 'verb' => [70, 73], 'num' => [70, 73], 'spat' => [66, 69], 'perc' => [70, 73], 'man' => [70, 73]],
        ],
    ];

    $context = BenchmarkExecutionContext::human($guidance);
    $set1 = $importer->handle($institution, $cycle, $context, [
        'name' => 'Set v1',
        'change_reason' => 'Initial synthetic cycle calibration',
        'raw_ranges' => $baseRanges,
    ], 'set-v1');
    $act1 = $activator->handle($cycle, $set1, $context, null, (string) $set1->content_sha256, 'act-key-1');
    $act1Replay = $activator->handle($cycle, $set1, $context, null, (string) $set1->content_sha256, 'act-key-1');
    $set2 = publishSyntheticCompleteBenchmarkSuccessor($guidance, $cycle, false, 'engine-activation');

    expect($act1->id)->toBe($act1Replay->id)
        ->and($act1->sequence)->toBe(1)
        ->and($act1->superseded_at)->toBeNull();
    expect(fn () => $activator->handle($cycle, $set1, $context, $act1->id, (string) $set1->content_sha256, 'act-key-1'))
        ->toThrow(RuntimeException::class);

    $act2 = $activator->handle($cycle, $set2, $context, $act1->id, (string) $set2->content_sha256, 'act-key-2');
    $act1->refresh();

    expect($act1->superseded_at)->not->toBeNull()
        ->and($act2->sequence)->toBe(2)
        ->and($act2->superseded_at)->toBeNull()
        ->and(AdmissionBenchmarkActivation::currentForCycle($cycle->id)?->id)->toBe($act2->id);

    expect(AuditEvent::query()->where('event_type', 'admission_benchmark.activated')->count())->toBe(2);
});

test('AdmissionBenchmarkSeeder preserves an existing active benchmark set', function (): void {
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $baseSet = app(ImportAdmissionBenchmarkSet::class)->handle(
        $institution,
        $cycle,
        BenchmarkExecutionContext::human($guidance),
        [
            'name' => 'Pre-existing active benchmark',
            'change_reason' => 'Regression fixture for benchmark seeder activation safety.',
            'raw_ranges' => [
                'BSBA' => [
                    'honors' => ['gwa' => [90, 95], 'gen' => [80, 90], 'verb' => [80, 90], 'num' => [80, 90], 'spat' => [75, 85], 'perc' => [80, 90], 'man' => [80, 90]],
                    'regular' => ['gwa' => [85, 89], 'gen' => [74, 79], 'verb' => [74, 79], 'num' => [74, 79], 'spat' => [70, 74], 'perc' => [70, 74], 'man' => [70, 74]],
                    'cutoff' => ['gwa' => [82, 84], 'gen' => [70, 73], 'verb' => [70, 73], 'num' => [70, 73], 'spat' => [66, 69], 'perc' => [66, 69], 'man' => [66, 69]],
                ],
            ],
        ],
        'admission-benchmark-seeder-existing-active-base',
    );
    $baseContentDigest = (string) $baseSet->content_sha256;
    $activation = app(ActivateAdmissionBenchmarkSet::class)->handle(
        $cycle,
        $baseSet,
        BenchmarkExecutionContext::trustedSeed($institution, 'seed'),
        null,
        (string) $baseSet->content_sha256,
        'admission-benchmark-seeder-existing-active-bootstrap',
    );
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSBA')->firstOrFail();
    $started = app(StartProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, 0, 'Start demo draft', 'benchmark-seeder-start-draft');
    $editedContent = $started->content;
    $editedContent['tiers']['1']['values']['gwa'] = 94;
    $edited = app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $editedContent, 1, 'Edit demo draft', 'benchmark-seeder-edit-draft');
    $draft = AdmissionBenchmarkDraft::query()->where('program_id', $program->id)->firstOrFail();
    $draftVersion = $draft->current_version;
    $setCount = AdmissionBenchmarkSet::query()->count();
    $activationCount = AdmissionBenchmarkActivation::query()->count();
    $activationAuditCount = AuditEvent::query()->where('event_type', 'admission_benchmark.activated')->count();

    (new AdmissionBenchmarkSeeder)->run();

    expect(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id)->toBe($activation->id)
        ->and($activation->fresh()->superseded_at)->toBeNull()
        ->and($baseSet->fresh()->content_sha256)->toBe($baseContentDigest)
        ->and($draft->fresh()->current_version)->toBe($draftVersion)
        ->and($edited->fresh()->content)->toBe($editedContent)
        ->and(AdmissionBenchmarkSet::query()->count())->toBe($setCount)
        ->and(AdmissionBenchmarkActivation::query()->count())->toBe($activationCount)
        ->and(AuditEvent::query()->where('event_type', 'admission_benchmark.activated')->count())->toBe($activationAuditCount);
});
test('AdmissionBenchmarkSeeder rejects synthetic fixture execution in production without writing state', function (): void {
    seedE2Foundation();
    $tables = [
        'admission_benchmark_sets',
        'admission_benchmark_activations',
        'admission_benchmark_drafts',
        'applicant_identities',
        'admission_attempts',
    ];
    $before = array_map(static fn (string $table): int => DB::table($table)->count(), $tables);
    $originalEnvironment = app()->environment();
    app()['env'] = 'production';

    try {
        expect(fn () => (new AdmissionBenchmarkSeeder)->run())
            ->toThrow(RuntimeException::class, 'Synthetic admission benchmark fixtures are unavailable in production.');
    } finally {
        app()['env'] = $originalEnvironment;
    }

    expect(array_map(static fn (string $table): int => DB::table($table)->count(), $tables))->toBe($before);
});

test('ConsultationAdmissionSupportQuery uses strict TabPFN output without mutating domain state', function (): void {
    [$attempt] = buildCanonicalAdmissionSupportAttempt($this, 'tabfm-stem@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);
    Http::fake([
        'http://inference:8101/inference/tabpfn' => fn (ClientRequest $request) => Http::response(
            admissionSupportEngineNeuralResponse($request, ['BSIT' => 100, 'BPA' => 0]),
        ),
    ]);

    $attempt->forceFill([
        'strand' => 'STEM',
        'gwa' => 92.5,
        'payload' => array_merge($attempt->payload ?? [], [
            'historical_subject_grades' => ['shs_math' => 94, 'shs_sci' => 90, 'shs_eng' => 88],
            'historical_riasec_flags' => ['riasec_r' => 1, 'riasec_i' => 1, 'riasec_a' => 0, 'riasec_s' => 0, 'riasec_e' => 0, 'riasec_c' => 0],
        ]),
    ])->save();

    $poolsBefore = ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count();
    $selectionsBefore = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count();

    $support = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh());
    $tabfmCard = $support['models'][0];
    $recommendations = collect($tabfmCard['recommendations']);
    $recommendationsByCode = $recommendations->keyBy('program_code');

    expect($support['analysis']['status'])->toBe('evaluated')
        ->and($support['analysis']['engine'])->toBe('tabfm_pairwise')
        ->and($tabfmCard['slot'])->toBe('tabfm_pairwise')
        ->and($recommendationsByCode['BSIT']['compatibility_index'])->toBe(100)
        ->and($recommendationsByCode['BPA']['compatibility_index'])->toBe(0)
        ->and($recommendationsByCode['BSIT']['passed_cutoff'])->toBeTrue()
        ->and($tabfmCard['modality_coverage'])->toHaveKeys(['features', 'feature_coverage'])
        ->and($tabfmCard['modality_coverage']['inference_provenance']['checkpoint_identity'])->toBe('Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt')
        ->and($tabfmCard['modality_coverage']['inference_provenance']['checkpoint_sha256'])->toBe('1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88')
        ->and($recommendationsByCode['BSIT']['radar_comparison'])->toBeArray()
        ->and(array_column($recommendationsByCode['BSIT']['radar_comparison']['axes'], 'key'))->toBe(['logic', 'verbal', 'numerical', 'spatial', 'memory', 'language'])
        ->and($recommendationsByCode['BSIT']['radar_comparison']['student']['values']['logic'])->toBeFloat()
        ->and($recommendationsByCode['BSIT']['radar_comparison']['profiles']['cutoff']['values']['logic'])->toBeFloat()
        ->and($recommendationsByCode['BPA']['radar_comparison'])->toBeArray()
        ->and($tabfmCard['evaluation_context']['source'])->toBe('active')
        ->and(collect($tabfmCard['applicant_evidence']['aptitude_scores'])->pluck('area_code'))->toContain('LOGIC')
        ->and(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($poolsBefore)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($selectionsBefore);

    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://inference:8101/inference/tabpfn'
        && isset($request['context_exemplars'], $request['query_applicant'], $request['feature_definitions'])
        && count(array_unique(array_column($request['context_exemplars'], 'program_code'))) === 11);
});

test('TabFmPairwiseProvider maps exact neural adapter programs and preserves 0 and 100 scores', function (): void {
    [$attempt] = buildCanonicalAdmissionSupportAttempt($this, 'tabfm-http@example.test');
    (new AdmissionBenchmarkSeeder)->run();

    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
        'admission_engine.tabfm.version' => '9.1.0',
    ]);
    Http::fake([
        'http://inference:8101/inference/tabpfn' => fn (ClientRequest $request) => Http::response(
            admissionSupportEngineNeuralResponse($request, ['BSIT' => 0, 'BPA' => 100]),
        ),
    ]);

    $support = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh());
    $tabfmCard = $support['models'][0];
    $recommendations = collect($tabfmCard['recommendations'])->keyBy('program_code');

    expect($support['analysis']['status'])->toBe('evaluated')
        ->and($tabfmCard['status'])->toBe('evaluated')
        ->and($tabfmCard['engine_version'])->toBe('9.1.0')
        ->and($tabfmCard['inference_backend'])->toBe('tabpfn_pytorch')
        ->and($tabfmCard['modality_coverage']['inference_provenance'])->toBe([
            'package' => 'tabpfn',
            'package_version' => '9.1.0',
            'checkpoint_identity' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
            'checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
        ])
        ->and($recommendations)->toHaveCount(11)
        ->and($recommendations['BSIT']['compatibility_index'])->toBe(0)
        ->and($recommendations['BPA']['compatibility_index'])->toBe(100);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://inference:8101/inference/tabpfn'
        && isset($request['context_exemplars'], $request['query_applicant'], $request['feature_definitions'])
        && count(array_unique(array_column($request['context_exemplars'], 'program_code'))) === 11);
});

test('TabFM rejects a broad active schema before sending model inputs', function (): void {
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $fixture = admissionSupportEngineDynamicFeatureFixture();
    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);
    Http::preventStrayRequests();

    $result = app(TabFmPairwiseProvider::class)->evaluateAtActivation(
        new AdmissionInputPayload(
            attemptId: 1,
            institutionId: (int) $cycle->institution_id,
            academicCycleId: (int) $cycle->id,
            gwa: 90.0,
            aptitudePercentiles: [],
        ),
        $fixture['activation'],
    );

    expect($result->status)->toBe('unavailable')
        ->and($result->fallbackReason)->toBe('noncanonical_feature_schema');
    Http::assertNothingSent();
});

test('TabFM never dispatches subject grades or advisory RIASEC from a broad source', function (): void {
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $fixture = admissionSupportEngineDynamicFeatureFixture();
    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);
    Http::preventStrayRequests();

    $result = app(TabFmPairwiseProvider::class)->evaluateAtActivation(
        new AdmissionInputPayload(
            attemptId: 1,
            institutionId: (int) $cycle->institution_id,
            academicCycleId: (int) $cycle->id,
            gwa: 90.0,
            aptitudePercentiles: ['LOGIC' => 76.0],
        ),
        $fixture['activation'],
    );

    expect($result->status)->toBe('unavailable')
        ->and($result->fallbackReason)->toBe('noncanonical_feature_schema');
    Http::assertNothingSent();
});

test('TabFM treats legacy and unknown drivers as unavailable without making HTTP requests', function (): void {
    [$attempt] = buildCanonicalAdmissionSupportAttempt($this, 'tabfm-unsupported-driver@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    config(['admission_engine.tabfm.endpoint' => 'http://inference:8101']);

    foreach (['in_process', 'legacy-driver'] as $driver) {
        config(['admission_engine.tabfm.driver' => $driver]);
        $support = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh());
        $tabfmCard = $support['models'][0];

        expect($support['analysis']['status'])->toBe('unavailable')
            ->and($tabfmCard['status'])->toBe('unavailable')
            ->and($tabfmCard['evaluated'])->toBeFalse()
            ->and($tabfmCard['engine_version'])->toBe('unavailable')
            ->and($tabfmCard['inference_backend'])->toBe('unavailable')
            ->and($tabfmCard['fallback_reason'])->toBe('unsupported_driver')
            ->and($tabfmCard['recommendations'])->toBeEmpty()
            ->and($tabfmCard['modality_coverage'])->toHaveKey('features')
            ->and($tabfmCard['modality_coverage'])->not->toHaveKey('inference_provenance')
            ->and($tabfmCard['evaluation_context']['source'])->toBe('active');
    }

    Http::assertNothingSent();
});

test('TabFM marks a non-success HTTP response unavailable without completed backend provenance', function (): void {
    [$attempt] = buildCanonicalAdmissionSupportAttempt($this, 'tabfm-http-503@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);
    Http::fake([
        'http://inference:8101/inference/tabpfn' => Http::response(['detail' => 'unavailable'], 503),
    ]);

    $support = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh());
    $tabfmCard = $support['models'][0];

    expect($support['analysis']['status'])->toBe('unavailable')
        ->and($tabfmCard['status'])->toBe('unavailable')
        ->and($tabfmCard['evaluated'])->toBeFalse()
        ->and($tabfmCard['engine_version'])->toBe('unavailable')
        ->and($tabfmCard['inference_backend'])->toBe('unavailable')
        ->and($tabfmCard['fallback_reason'])->toBe('sidecar_unavailable')
        ->and($tabfmCard['recommendations'])->toBeEmpty()
        ->and($tabfmCard['modality_coverage'])->toHaveKey('features')
        ->and($tabfmCard['modality_coverage'])->not->toHaveKey('inference_provenance')
        ->and($tabfmCard['evaluation_context']['source'])->toBe('active');
});

test('TabFM requires explicit approvals and exact configured neural provenance before returning scores', function (): void {
    [$attempt] = buildCanonicalAdmissionSupportAttempt($this, 'tabfm-approved-provenance@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);
    $installFake = static function (callable $response): HttpFactory {
        $factory = new HttpFactory(app('events'));
        Http::swap($factory);
        $factory->preventStrayRequests()->fake([
            'http://inference:8101/inference/tabpfn' => $response,
        ]);

        return $factory;
    };

    foreach (['institutional_use_approved', 'hosted_use_approved'] as $approval) {
        config(['admission_engine.tabfm.'.$approval => false]);
        $installFake(fn (ClientRequest $request) => Http::response(admissionSupportEngineNeuralResponse($request)));
        $unapproved = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh())['models'][0];
        expect($unapproved['status'])->toBe('unavailable')
            ->and($unapproved['message'])->toContain('No model evaluation was run')
            ->and($unapproved['recommendations'])->toBeEmpty();
        Http::assertNothingSent();
        config(['admission_engine.tabfm.'.$approval => true]);
    }

    foreach (['checkpoint_id', 'checkpoint_sha256'] as $pin) {
        config(['admission_engine.tabfm.'.$pin => '']);
        $installFake(fn (ClientRequest $request) => Http::response(admissionSupportEngineNeuralResponse($request)));
        $unconfigured = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh())['models'][0];
        expect($unconfigured['status'])->toBe('unavailable')
            ->and($unconfigured['recommendations'])->toBeEmpty();
        Http::assertNothingSent();
        config([
            'admission_engine.tabfm.'.$pin => $pin === 'checkpoint_id'
                ? 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt'
                : '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
        ]);
    }

    config(['admission_engine.tabfm.package_version' => '9.1.1']);
    $installFake(fn (ClientRequest $request) => Http::response(admissionSupportEngineNeuralResponse($request)));
    $wrongConfiguredPackage = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh())['models'][0];
    expect($wrongConfiguredPackage['status'])->toBe('unavailable')
        ->and($wrongConfiguredPackage['recommendations'])->toBeEmpty();
    Http::assertNothingSent();
    config(['admission_engine.tabfm.package_version' => '9.1.0']);

    $invalidProvenance = [
        'model identity' => static function (array $response): array {
            $response['provenance']['checkpoint_identity'] = 'other-checkpoint.ckpt';

            return $response;
        },
        'checkpoint digest' => static function (array $response): array {
            $response['provenance']['checkpoint_sha256'] = str_repeat('0', 64);

            return $response;
        },
        'package pin' => static function (array $response): array {
            $response['provenance']['package_version'] = '9.1.1';

            return $response;
        },
        'engine version pin' => static function (array $response): array {
            $response['engine_version'] = '9.1.1';

            return $response;
        },
    ];
    foreach ($invalidProvenance as $mutation) {
        $httpFactory = $installFake(function (ClientRequest $request) use ($mutation) {
            return Http::response($mutation(admissionSupportEngineNeuralResponse($request)));
        });

        $unavailable = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh())['models'][0];
        expect($unavailable['status'])->toBe('unavailable')
            ->and($unavailable['evaluated'])->toBeFalse()
            ->and($unavailable['recommendations'])->toBeEmpty()
            ->and($httpFactory->recorded())->toHaveCount(1);
    }
});

test('TabFM rejects malformed, non-exact, fallback, and invalid-score adapter responses', function (): void {
    [$attempt] = buildCanonicalAdmissionSupportAttempt($this, 'tabfm-http-invalid@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);

    $installFake = static function (callable $response): HttpFactory {
        $factory = new HttpFactory(app('events'));
        Http::swap($factory);
        $factory->preventStrayRequests()->fake([
            'http://inference:8101/inference/tabpfn' => $response,
        ]);

        return $factory;
    };

    $invalidResponses = [
        'malformed body' => static fn (array $response): string => 'not-json',
        'foreign program' => static function (array $response): array {
            $response['recommendations'][0]['program_code'] = 'UNKNOWN';
            $response['recommendations'][0]['program_name'] = 'Unknown Program';

            return $response;
        },
        'duplicate program' => static function (array $response): array {
            $response['recommendations'][1] = $response['recommendations'][0];
            $response['recommendations'][1]['rank'] = 2;

            return $response;
        },
        'missing program' => static function (array $response): array {
            array_pop($response['recommendations']);

            return $response;
        },
        'missing engine version' => static function (array $response): array {
            unset($response['engine_version']);

            return $response;
        },
        'fallback backend' => static function (array $response): array {
            $response['engine_version'] = 'bayesian-prior-kernel-v1';
            $response['inference_backend'] = 'bayesian_prior_kernel_v1';
            $response['fallback_reason'] = 'tabpfn_unavailable';

            return $response;
        },
        'missing checkpoint identity' => static function (array $response): array {
            unset($response['provenance']['checkpoint_identity']);

            return $response;
        },
        'invalid checkpoint digest' => static function (array $response): array {
            $response['provenance']['checkpoint_sha256'] = 'not-a-checksum';

            return $response;
        },
        'configured version fallback' => static function (array $response): array {
            $response['engine_version'] = 'tabfm-pairwise-v1';

            return $response;
        },
        'out-of-range score' => static function (array $response): array {
            $response['recommendations'][0]['compatibility_index'] = 101;

            return $response;
        },
        'non-finite score' => static function (array $response): string {
            $json = json_encode($response, JSON_THROW_ON_ERROR);

            return str_replace('"compatibility_index":80', '"compatibility_index":1e999', $json);
        },
    ];

    foreach ($invalidResponses as $mutation) {
        $httpFactory = $installFake(function (ClientRequest $request) use ($mutation) {
            return Http::response(
                $mutation(admissionSupportEngineNeuralResponse($request)),
                200,
                ['Content-Type' => 'application/json'],
            );
        });

        $support = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh());
        $tabfmCard = $support['models'][0];

        expect($support['analysis']['status'])->toBe('unavailable')
            ->and($tabfmCard['status'])->toBe('unavailable')
            ->and($tabfmCard['evaluated'])->toBeFalse()
            ->and($tabfmCard['engine_version'])->toBe('unavailable')
            ->and($tabfmCard['inference_backend'])->toBe('unavailable')
            ->and($tabfmCard['fallback_reason'])->not->toBeNull()
            ->and($tabfmCard['recommendations'])->toBeEmpty()
            ->and($tabfmCard['modality_coverage'])->not->toHaveKey('inference_provenance')
            ->and($tabfmCard['evaluation_context']['source'])->toBe('active')
            ->and($httpFactory->recorded())->toHaveCount(1);
    }
});

test('TabFM computes the Tier 3 cutoff at equality and below independently of remote CMI and flag', function (): void {
    [$attempt] = buildCanonicalAdmissionSupportAttempt($this, 'tabfm-tier3-cutoff@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    $attempt->forceFill(['strand' => 'GAS', 'gwa' => 83.0])->save();

    $grading = GradingSession::query()->latest('id')->firstOrFail();
    $scoreMap = [
        'LOGIC' => 73,
        'PROFILE_QUANT' => 76,
        'PROFILE_VERBAL' => 70,
        'SPATIAL' => 72,
        'MEMORY' => 70,
        'LANGUAGE' => 68,
    ];
    foreach ($scoreMap as $code => $score) {
        $area = AptitudeArea::query()->where('code', $code)->firstOrFail();
        ScoreRecord::query()->updateOrCreate(
            [
                'grading_session_id' => $grading->id,
                'admission_attempt_id' => $attempt->id,
                'aptitude_area_id' => $area->id,
            ],
            [
                'score' => $score,
                'converted_score' => $score,
                'source' => 'manual',
                'idempotency_key' => 'support-score-'.$attempt->id.'-'.$area->id,
            ],
        );
    }

    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);
    $remoteScore = 0;
    $remotePassedCutoff = false;
    Http::fake([
        'http://inference:8101/inference/tabpfn' => function (ClientRequest $request) use (&$remoteScore, &$remotePassedCutoff) {
            $response = admissionSupportEngineNeuralResponse(
                $request,
                ['BSIT' => $remoteScore],
                ['BSIT' => $remotePassedCutoff],
            );

            return Http::response($response);
        },
    ]);

    $atCutoff = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh());
    $atCutoffRecommendation = collect($atCutoff['models'][0]['recommendations'])->firstWhere('program_code', 'BSIT');
    $remoteScore = 100;
    $remotePassedCutoff = true;
    $attempt->forceFill(['gwa' => 82.99])->save();
    $belowCutoff = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh());
    $belowCutoffRecommendation = collect($belowCutoff['models'][0]['recommendations'])->firstWhere('program_code', 'BSIT');

    expect($atCutoffRecommendation['compatibility_index'])->toBe(0)
        ->and($atCutoffRecommendation['passed_cutoff'])->toBeTrue()
        ->and($belowCutoffRecommendation['compatibility_index'])->toBe(100)
        ->and($belowCutoffRecommendation['passed_cutoff'])->toBeFalse();
});

test('TabFM refuses a program without a Tier 3 exemplar before dispatching inference', function (): void {
    [$attempt] = buildCanonicalAdmissionSupportAttempt($this, 'tabfm-tier3-missing@example.test');
    (new AdmissionBenchmarkSeeder)->run();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $activation = AdmissionBenchmarkActivation::currentForCycle($cycle->id);
    $activation->load(['benchmarkSet.features', 'benchmarkSet.exemplars.program', 'benchmarkSet.exemplars.values.feature']);
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $baseSet = $activation->benchmarkSet;
    $baseSetDigest = (string) $baseSet->content_sha256;
    $baseExemplarCount = $baseSet->exemplars->count();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $fixtureSet = AdmissionBenchmarkSet::query()->create([
        'institution_id' => $baseSet->institution_id,
        'version' => (int) AdmissionBenchmarkSet::query()->where('institution_id', $baseSet->institution_id)->max('version') + 1,
        'status' => 'draft',
        'name' => 'Synthetic draft fixture missing one Tier 3 exemplar',
        'change_reason' => 'Test-only canonical fixture for the missing Tier 3 guard.',
        'schema_sha256' => $baseSet->computedSchemaDigest(),
        'content_sha256' => hash('sha256', 'pending canonical test fixture digest'),
        'created_by_user_id' => $guidance->id,
        'idempotency_key' => 'tabfm-tier3-missing-draft-fixture',
        'import_payload_sha256' => hash('sha256', 'tabfm-tier3-missing-draft-fixture'),
    ]);
    $featuresByKey = [];
    foreach ($baseSet->features as $baseFeature) {
        $featuresByKey[$baseFeature->feature_key] = $fixtureSet->features()->create([
            'position' => $baseFeature->position,
            'feature_key' => $baseFeature->feature_key,
            'label' => $baseFeature->label,
            'logical_type' => $baseFeature->logical_type,
            'aptitude_area_id' => $baseFeature->aptitude_area_id,
            'is_required' => $baseFeature->is_required,
            'source_definition_json' => $baseFeature->source_definition_json,
        ]);
    }
    foreach ($baseSet->exemplars as $baseExemplar) {
        if ((int) $baseExemplar->program_id === (int) $program->id && (int) $baseExemplar->tier_level === 3) {
            continue;
        }

        $exemplar = $fixtureSet->exemplars()->create([
            'program_id' => $baseExemplar->program_id,
            'tier_level' => $baseExemplar->tier_level,
            'tier_label' => $baseExemplar->tier_label,
            'target_class' => $baseExemplar->target_class,
        ]);
        foreach ($baseExemplar->values as $baseValue) {
            AdmissionBenchmarkExemplarValue::query()->create([
                'exemplar_id' => $exemplar->id,
                'benchmark_feature_id' => $featuresByKey[$baseValue->feature->feature_key]->id,
                'is_missing' => $baseValue->is_missing,
                'numeric_value' => $baseValue->numeric_value,
                'categorical_value' => $baseValue->categorical_value,
            ]);
        }
    }
    $fixtureSet->load(['features', 'exemplars.program', 'exemplars.values.feature']);
    $fixtureSet->forceFill(['content_sha256' => $fixtureSet->computedContentDigest()])->save();
    expect($fixtureSet->status)->toBe('draft')
        ->and($fixtureSet->content_sha256)->toBe($fixtureSet->computedContentDigest());
    $activation->setRelation('benchmarkSet', $fixtureSet);
    $input = new AdmissionInputPayload(
        attemptId: (int) $attempt->id,
        institutionId: (int) $attempt->institution_id,
        academicCycleId: (int) $attempt->academic_cycle_id,
        gwa: 91.5,
        aptitudePercentiles: [
            'LOGIC' => 90.0,
            'PROFILE_QUANT' => 90.0,
            'PROFILE_VERBAL' => 90.0,
            'SPATIAL' => 90.0,
            'MEMORY' => 90.0,
            'LANGUAGE' => 90.0,
        ],
    );
    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);

    $result = app(TabFmPairwiseProvider::class)->evaluateAtActivation($input, $activation);

    expect($result->status)->toBe('unavailable')
        ->and($result->fallbackReason)->toBe('tier3_missing')
        ->and($result->inferenceBackend)->toBe('unavailable')
        ->and($result->recommendations)->toBeEmpty()
        ->and($result->modalityCoverage)->toHaveKey('features')
        ->and($result->evaluationContext?->source)->toBe('active');

    $baseSet->refresh()->load(['features', 'exemplars.program', 'exemplars.values.feature']);
    expect($baseSet->content_sha256)->toBe($baseSetDigest)
        ->and($baseSet->exemplars)->toHaveCount($baseExemplarCount)
        ->and($baseSet->computedContentDigest())->toBe($baseSetDigest);
    Http::assertNothingSent();
});
test('TabFM radar comparison requires verified student aptitude scale provenance', function (): void {
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    expect($activation)->not->toBeNull();
    $activation->load([
        'benchmarkSet.features.aptitudeArea',
        'benchmarkSet.exemplars.program',
        'benchmarkSet.exemplars.values.feature',
    ]);
    $set = $activation->benchmarkSet;
    $radarSources = ['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE'];

    foreach ($set->features as $feature) {
        if (! in_array($feature->feature_key, $radarSources, true)) {
            continue;
        }

        $feature->source_definition_json = [
            'source_code' => $feature->feature_key,
            'scale' => 'percentile',
            'norm_version' => 'fixture-v1',
        ];
    }
    $set->forceFill([
        'schema_sha256' => $set->computedSchemaDigest(),
        'content_sha256' => $set->computedContentDigest(),
    ]);

    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);
    Http::fake([
        'http://inference:8101/inference/tabpfn' => fn (ClientRequest $request) => Http::response(
            admissionSupportEngineNeuralResponse($request),
        ),
    ]);

    $aptitudePercentiles = [
        'LOGIC' => 90.0,
        'PROFILE_VERBAL' => 88.0,
        'PROFILE_QUANT' => 92.0,
        'SPATIAL' => 86.0,
        'MEMORY' => 84.0,
        'LANGUAGE' => 82.0,
    ];
    $evaluateWithScales = static function (array $aptitudeScales) use ($activation, $cycle, $aptitudePercentiles): EngineEvaluationResult {
        return app(TabFmPairwiseProvider::class)->evaluateAtActivation(
            new AdmissionInputPayload(
                attemptId: 1,
                institutionId: (int) $cycle->institution_id,
                academicCycleId: (int) $cycle->id,
                gwa: 90.0,
                aptitudePercentiles: $aptitudePercentiles,
                aptitudeScales: $aptitudeScales,
            ),
            $activation,
        );
    };

    foreach ([[], ['LOGIC' => 'legacy', 'PROFILE_QUANT' => null]] as $untypedScales) {
        $untypedResult = $evaluateWithScales($untypedScales);
        $untypedRecommendations = collect($untypedResult->recommendations);

        expect($untypedResult->status)->toBe('evaluated')
            ->and($untypedRecommendations)->not->toBeEmpty()
            ->and($untypedRecommendations->every(
                static fn ($recommendation): bool => $recommendation->compatibilityIndex === 80
                    && $recommendation->radarComparison === null,
            ))->toBeTrue();
    }

    $verifiedResult = $evaluateWithScales(array_fill_keys($radarSources, 'percentile'));
    $verifiedRecommendations = collect($verifiedResult->recommendations);

    expect($verifiedResult->status)->toBe('evaluated')
        ->and($verifiedRecommendations)->not->toBeEmpty();

    foreach ($verifiedRecommendations as $recommendation) {
        expect($recommendation->radarComparison)->not->toBeNull()
            ->and($recommendation->radarComparison['student']['values'])->toBe([
                'logic' => 90.0,
                'verbal' => 88.0,
                'numerical' => 92.0,
                'spatial' => 86.0,
                'memory' => 84.0,
                'language' => 82.0,
            ])
            ->and($recommendation->radarComparison['profiles'])->toHaveKeys(['cutoff', 'regular', 'honors'])
            ->and($recommendation->radarComparison['profiles']['cutoff']['values'])->not->toBeEmpty();
    }

    Http::assertSentCount(3);
});
test('TabFM sends only approved aptitude outputs and GWA to the worker', function (): void {
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    expect($activation)->not->toBeNull();
    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);
    Http::fake([
        'http://inference:8101/inference/tabpfn' => fn (ClientRequest $request) => Http::response(
            admissionSupportEngineNeuralResponse($request),
        ),
    ]);
    $input = new AdmissionInputPayload(
        attemptId: 1,
        institutionId: (int) $cycle->institution_id,
        academicCycleId: (int) $cycle->id,
        gwa: 90.0,
        aptitudePercentiles: [
            'LOGIC' => 75.0,
            'PROFILE_VERBAL' => 76.0,
            'PROFILE_QUANT' => 77.0,
            'SPATIAL' => 78.0,
            'MEMORY' => 79.0,
            'LANGUAGE' => 80.0,
        ],
    );

    $result = app(TabFmPairwiseProvider::class)->evaluateAtActivation($input, $activation);

    expect($result->status)->toBe('evaluated');
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://inference:8101/inference/tabpfn'
        && array_keys($request['query_applicant']) === ['gwa', 'LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE']
        && collect($request['feature_definitions'])->pluck('feature_key')->all() === ['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa']
        && collect($request['feature_definitions'])->every(static fn (array $definition): bool => $definition['logical_type'] === 'numeric'));
    Http::assertSentCount(1);
});

test('EngineEvaluationResult does not infer a backend when provenance is absent', function (): void {
    $result = new EngineEvaluationResult(
        providerId: 'tabfm_pairwise',
        slot: 'tabfm_pairwise',
        status: 'standby',
        engineVersion: 'unavailable',
        benchmarkVersion: null,
        benchmarkName: null,
        evaluatedAt: now()->toIso8601String(),
        modalityCoverage: [],
        recommendations: [],
        message: 'Inference has not run.',
    );

    expect($result->inferenceBackend)->toBe('not_reported')
        ->and($result->toArray()['inference_backend'])->toBe('not_reported');
});

test('reviewed aptitude and GWA schema cutover preserves its predecessor and requires full publication before separate activation', function (): void {
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $activation = admissionSupportEngineDynamicFeatureFixture()['activation'];
    $source = $activation->benchmarkSet()->with(['features', 'exemplars.program'])->firstOrFail();
    $sourceDigest = $source->computedContentDigest();
    $schemaDigest = $source->computedSchemaDigest();
    $sourceFeatureIds = $source->features->pluck('id')->all();
    $programs = Program::query()->whereIn('id', $source->exemplars->pluck('program_id')->unique())->orderBy('id')->get();
    $cutover = app(CutoverProgramBenchmarkDraftSchema::class);
    $manifest = [];
    foreach ($programs as $program) {
        $key = 'exact-cutover-'.$program->id;
        $revision = $cutover->handle($guidance, $program, $cycle, 0, 'Review exact input exclusion.', $key, $sourceDigest);
        $manifest[$program->id] = $revision->id;
        expect($cutover->handle($guidance, $program, $cycle, 0, 'Review exact input exclusion.', $key, $sourceDigest)->id)->toBe($revision->id);
        expect(fn () => $cutover->handle($guidance, $program, $cycle, 0, 'Changed review.', $key, $sourceDigest))->toThrow(RuntimeException::class);
        expect(fn () => $cutover->handle($guidance, $program, $cycle, 0, 'Stale review.', $key.'-stale', $sourceDigest))->toThrow(RuntimeException::class);
        expect(array_keys($revision->content['tiers']['1']['values']))->toEqualCanonicalizing(['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa']);
    }
    expect(AdmissionBenchmarkActivation::currentForCycle($cycle->id)->id)->toBe($activation->id);
    $publisher = app(PublishProgramBenchmarkSet::class);
    $marker = ['expected_predecessor_schema_digest' => $schemaDigest];
    $subset = array_slice($manifest, 0, 1, true);
    expect(fn () => $publisher->handle($guidance, $cycle, $subset, $sourceDigest, 'Incomplete manifest.', 'cutover-subset', null, $marker))->toThrow(RuntimeException::class);
    $published = $publisher->handle($guidance, $cycle, $manifest, $sourceDigest, 'All retained programs reviewed.', 'cutover-full-publish', null, $marker);
    expect(AdmissionBenchmarkActivation::currentForCycle($cycle->id)->id)->toBe($activation->id)
        ->and($published->predecessor_id)->toBe($source->id)
        ->and($published->features()->pluck('feature_key')->all())->toEqualCanonicalizing(['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa']);
    app(ActivateAdmissionBenchmarkSet::class)->handle($cycle, $published, BenchmarkExecutionContext::human($guidance), $activation->id, $published->content_sha256, 'cutover-full-activate');
    expect(AdmissionBenchmarkActivation::currentForCycle($cycle->id)->benchmark_set_id)->toBe($published->id)
        ->and($source->fresh()->computedContentDigest())->toBe($sourceDigest)
        ->and($source->fresh()->computedSchemaDigest())->toBe($schemaDigest)
        ->and($source->fresh()->features()->pluck('id')->all())->toBe($sourceFeatureIds);
});

test('advisory-only benchmark changes can be published and activated without changing model cells', function (): void {
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $active = AdmissionBenchmarkActivation::currentForCycle($cycle->id);
    $base = $active->benchmarkSet()->with('features')->firstOrFail();
    $originalNumericRows = $base->exemplars()->where('program_id', $program->id)->orderBy('tier_level')->with('values.feature')->get()
        ->map(fn ($row) => $row->values->mapWithKeys(fn ($cell) => [$cell->feature->feature_key => (float) $cell->numeric_value])->all())
        ->all();
    $tiers = [];
    foreach ($base->exemplars()->where('program_id', $program->id)->orderBy('tier_level')->with('values.feature')->get() as $row) {
        $tiers[(string) $row->tier_level] = ['values' => $row->values->mapWithKeys(fn ($cell) => [$cell->feature->feature_key => (float) $cell->numeric_value])->all()];
    }
    $content = BenchmarkInputSchema::canonicalDraftContent(['tiers' => $tiers]);
    $content['authoring']['preferred_strands'] = ['STEM'];
    $saved = app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $content, 0, 'Advisory preference review only.', 'advisory-only-draft');
    $published = app(PublishProgramBenchmarkSet::class)->handle($guidance, $cycle, [$program->id => $saved->id], $base->content_sha256, 'Publish advisory-only revision.', 'advisory-only-publish');
    $publishedNumericRows = $published->exemplars()->where('program_id', $program->id)->orderBy('tier_level')->with('values.feature')->get()
        ->map(fn ($row) => $row->values->mapWithKeys(fn ($cell) => [$cell->feature->feature_key => (float) $cell->numeric_value])->all())
        ->all();
    expect($publishedNumericRows)->toBe($originalNumericRows)
        ->and($base->fresh()->exemplars()->where('program_id', $program->id)->orderBy('tier_level')->with('values.feature')->get()
            ->map(fn ($row) => $row->values->mapWithKeys(fn ($cell) => [$cell->feature->feature_key => (float) $cell->numeric_value])->all())
            ->all())->toBe($originalNumericRows);
    $action = app(ActivateAdmissionBenchmarkSet::class);
    expect($action->deltaEligibleProgramIds($base, $published))->toBe([$program->id]);
    $activation = $action->handle($cycle, $published, BenchmarkExecutionContext::human($guidance), $active->id, $published->content_sha256, 'advisory-only-activate');
    expect($activation->benchmark_set_id)->toBe($published->id)
        ->and(AdmissionBenchmarkActivation::currentForCycle($cycle->id)->id)->toBe($activation->id)
        ->and($published->fresh()->exemplars()->where('program_id', $program->id)->orderBy('tier_level')->with('values.feature')->get()
            ->map(fn ($row) => $row->values->mapWithKeys(fn ($cell) => [$cell->feature->feature_key => (float) $cell->numeric_value])->all())
            ->all())->toBe($originalNumericRows)
        ->and($saved->fresh()->content['authoring']['preferred_strands'])->toBe(['STEM']);
});
