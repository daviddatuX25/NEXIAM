<?php

declare(strict_types=1);

use App\Assessment\Support\AssessmentRawEvaluator;
use App\Assessment\Support\TestKitAcceptancePlan;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

require_once __DIR__.'/../../Support/TestKitHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->institution = Institution::current();
    $this->fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $this->truthSource = [
        'reference' => 'tests/Fixtures/TestKit/two-item-count-native-v1.json',
        'sha256' => hash_file('sha256', base_path('tests/Fixtures/TestKit/two-item-count-native-v1.json')),
        'attributed_to' => 'repository artifact; expected arithmetic independently hand-worked by the AI software reviewer identified in its exact review evidence',
    ];
});

function planForAcceptanceTest(array $fixture, array $truthSource): array
{
    return [...$fixture['plan'], 'truth_source' => $truthSource];
}

test('rejects malformed envelopes and incomplete independent truth attribution before case processing', function (): void {
    $fixture = $this->fixture;
    $service = new TestKitAcceptancePlan;
    $plan = planForAcceptanceTest($fixture, $this->truthSource);

    $missingCases = $plan;
    unset($missingCases['cases']);
    $extraField = $plan;
    $extraField['trusted'] = true;
    $invalidEvidence = $plan;
    $invalidEvidence['source_evidence'] = null;
    $malformedTruth = $plan;
    unset($malformedTruth['truth_source']['attributed_to']);

    foreach ([$missingCases, $extraField, $invalidEvidence, $malformedTruth] as $malformed) {
        expect(fn () => $service->freeze($fixture['version'], $malformed))
            ->toThrow(RuntimeException::class);
    }
});

test('freezes independently authored correct incorrect and blank vectors with a stable complete digest', function (): void {
    $fixture = $this->fixture;
    $plan = planForAcceptanceTest($fixture, $this->truthSource);
    $service = new TestKitAcceptancePlan;

    $frozen = $service->freeze($fixture['version'], $plan);
    $reordered = $plan;
    $reordered['cases'] = array_reverse($reordered['cases']);
    foreach ($reordered['cases'] as &$case) {
        $case['pages'] = array_reverse($case['pages']);
        foreach ($case['pages'] as &$page) {
            $page['responses'] = array_reverse($page['responses']);
        }
        unset($page);
        $case['expected_metrics'] = array_reverse($case['expected_metrics']);
    }
    unset($case);
    $second = $service->freeze($fixture['version'], $reordered);

    expect($frozen['acceptance_plan_sha256'])->toBe($second['acceptance_plan_sha256'])
        ->and($frozen['acceptance_plan_json']['cases'])->toHaveCount(4)
        ->and(array_column($frozen['acceptance_plan_json']['cases'], 'case_id'))->toBe(['blank', 'correct', 'incorrect', 'mixed'])
        ->and(array_column($frozen['acceptance_plan_json']['cases'], 'coverage'))->toContain(['incorrect'])
        ->and($frozen['acceptance_plan_json']['item_bindings'][0])->not->toHaveKey('correct_token')
        ->and($frozen['acceptance_plan_json']['cases'][0]['pages'][0]['responses'][0]['token'])->toBeNull()
        ->and(hash('sha256', json_encode($frozen['acceptance_plan_json'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))
        ->toBe($frozen['acceptance_plan_sha256']);
});

test('freezes the exact reviewed registry, canonical protocol, worker, and scoring identities', function (): void {
    $fixture = $this->fixture;
    $service = new TestKitAcceptancePlan;
    $source = $service->loadReviewedSource('two-item-count-native-v1');
    $frozen = $service->freeze($fixture['version'], [
        ...$source['authored_plan'],
        'truth_source' => $source['truth_source'],
        'source_evidence' => $source['source_evidence'],
    ]);

    expect($frozen['registry_sha256'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($frozen['acceptance_plan_json']['source_evidence']['registry_sha256'])->toBe($frozen['registry_sha256'])
        ->and($frozen['acceptance_plan_json']['source_evidence']['provenance_kind'])->toBe('synthetic_software_fixture')
        ->and($frozen['acceptance_plan_json']['source_evidence']['reviewer_reference'])->toBe($source['reviewer_reference'])
        ->and($frozen['acceptance_plan_json']['source_evidence']['reviewer_evidence_sha256'])->toBe($source['reviewer_evidence_sha256'])
        ->and($frozen['acceptance_plan_json']['plan_schema_id'])->toBe(TestKitAcceptancePlan::PLAN_SCHEMA_ID)
        ->and($frozen['acceptance_plan_json']['protocol_identity']['sha256'])->toBe($service->protocolIdentity()['sha256'])
        ->and($frozen['acceptance_plan_json']['expected_worker_identity']['engine_commit'])->toBe($fixture['version']->templateVersion->engine_commit)
        ->and($frozen['acceptance_plan_json']['scoring_kernel_identity'])->toBe(AssessmentRawEvaluator::KERNEL_VERSION)
        ->and($frozen['acceptance_plan_json']['item_bindings'][0])->not->toHaveKey('correct_token');
});

test('rejects unreviewed source keys and source bytes that mismatch the code-owned registry', function (): void {
    $service = new TestKitAcceptancePlan;

    expect(fn () => $service->loadReviewedSource('unreviewed-plan'))
        ->toThrow(RuntimeException::class);
    $registry = config('test-kit.acceptance_sources');
    $mismatchedSource = $registry;
    $mismatchedSource['two-item-count-native-v1']['source_sha256'] = str_repeat('0', 64);
    config(['test-kit.acceptance_sources' => $mismatchedSource]);
    expect(fn () => $service->loadReviewedSource('two-item-count-native-v1'))
        ->toThrow(RuntimeException::class);

    $mismatchedReview = $registry;
    $mismatchedReview['two-item-count-native-v1']['reviewer_evidence_sha256'] = str_repeat('0', 64);
    config(['test-kit.acceptance_sources' => $mismatchedReview]);
    expect(fn () => $service->loadReviewedSource('two-item-count-native-v1'))
        ->toThrow(RuntimeException::class);
});

test('rejects duplicate ids omitted pages labels invalid tokens and mismatched map identities', function (): void {
    $fixture = $this->fixture;
    $service = new TestKitAcceptancePlan;
    $plan = planForAcceptanceTest($fixture, $this->truthSource);

    $duplicate = $plan;
    $duplicate['cases'][1]['case_id'] = $duplicate['cases'][0]['case_id'];
    $missingPage = $plan;
    $missingPage['cases'][0]['pages'] = [];
    $missingLabel = $plan;
    $missingLabel['cases'][0]['pages'][0]['responses'] = [];
    $invalidToken = $plan;
    $invalidToken['cases'][0]['pages'][0]['responses'][0]['token'] = 'UNKNOWN';
    $caseChangedToken = $plan;
    $caseChangedToken['cases'][0]['pages'][0]['responses'][0]['token'] = 'a';
    $mismatchedLabel = $plan;
    $mismatchedLabel['cases'][0]['pages'][0]['responses'][0]['native_label'] = 'NOT-IN-MAP';
    $mismatchedPage = $plan;
    $mismatchedPage['cases'][0]['pages'][0]['page_no'] = 2;

    foreach ([$duplicate, $missingPage, $missingLabel, $invalidToken, $caseChangedToken, $mismatchedLabel, $mismatchedPage] as $malformed) {
        expect(fn () => $service->freeze($fixture['version'], $malformed))->toThrow(RuntimeException::class);
    }
});
test('requires every exact item token to appear in the complete response vectors', function (): void {
    $fixture = $this->fixture;
    $plan = planForAcceptanceTest($fixture, $this->truthSource);
    foreach ($plan['cases'] as &$case) {
        if ($case['case_id'] === 'correct') {
            $case['pages'][0]['responses'][1]['token'] = null;
            $case['expected_metrics'][0]['value'] = '1';
            $case['coverage'] = ['correct', 'blank'];
        }
    }
    unset($case);

    expect(fn () => (new TestKitAcceptancePlan)->freeze($fixture['version'], $plan))
        ->toThrow(RuntimeException::class);
});

test('rejects absent required metric outputs and non-independent metric identities', function (): void {
    $fixture = $this->fixture;
    $service = new TestKitAcceptancePlan;
    $plan = planForAcceptanceTest($fixture, $this->truthSource);

    $missing = $plan;
    $missing['cases'][0]['expected_metrics'] = [];
    $unknownMetric = $plan;
    $unknownMetric['cases'][0]['expected_metrics'][0]['metric_code'] = 'UNRELATED_METRIC';
    $invalidDecimal = $plan;
    $invalidDecimal['cases'][0]['expected_metrics'][0]['value'] = '2.5';
    $unsupportedPlan = $plan;
    $unsupportedPlan['schema_version'] = 2;

    foreach ([$missing, $unknownMetric, $invalidDecimal, $unsupportedPlan] as $malformed) {
        expect(fn () => $service->freeze($fixture['version'], $malformed))->toThrow(RuntimeException::class);
    }
});

test('binds required recorded measures to exact procedure identity without deriving truth', function (): void {
    $fixture = $this->fixture;
    $profile = $fixture['profile'];
    $baseMetric = $fixture['metric'];
    $recordedMetric = (new TestInstrumentMetric)->forceFill([
        'id' => 88002,
        'test_instrument_version_id' => (int) $profile->getKey(),
        'code' => 'PROCEDURE_SCORE',
        'name' => 'Procedure score',
        'rule_kind' => TestInstrumentMetric::RULE_RECORDED_MEASURE,
        'test_instrument_subtest_id' => (int) $baseMetric->test_instrument_subtest_id,
        'unit' => 'points',
        'min_raw_value' => '0',
        'max_raw_value' => '10',
        'precision_scale' => 2,
        'is_required' => true,
        'procedure_reference' => 'fixture-procedure:independent-measure-v1',
    ]);
    $recordedMetric->setRelation('inputs', new Collection);
    $profile->setRelation('metrics', new Collection([$baseMetric, $recordedMetric]));
    $fixture['version']->setRelation('testInstrumentVersion', $profile);
    $plan = planForAcceptanceTest($fixture, $this->truthSource);
    foreach ($plan['cases'] as &$case) {
        $case['expected_metrics'][] = ['metric_code' => 'PROCEDURE_SCORE', 'value' => '3.5'];
        $case['recorded_measures'][] = [
            'metric_code' => 'PROCEDURE_SCORE',
            'value' => '3.50',
            'procedure_reference' => 'fixture-procedure:independent-measure-v1',
        ];
    }
    unset($case);

    $frozen = (new TestKitAcceptancePlan)->freeze($fixture['version'], $plan);
    expect(collect($frozen['acceptance_plan_json']['cases'][0]['expected_metrics'])->firstWhere('metric_code', 'PROCEDURE_SCORE'))
        ->toMatchArray([
            'metric_id' => 88002,
            'metric_code' => 'PROCEDURE_SCORE',
            'value' => '3.50',
        ]);

    $wrongProcedure = $plan;
    $wrongProcedure['cases'][0]['recorded_measures'][0]['procedure_reference'] = 'other-procedure';
    expect(fn () => (new TestKitAcceptancePlan)->freeze($fixture['version'], $wrongProcedure))
        ->toThrow(RuntimeException::class);
});

test('keeps protocol identity separate from the immutable material manifest', function (): void {
    $fixture = $this->fixture;
    $version = $fixture['version'];
    $manifest = $version->manifest_sha256;
    $service = new TestKitAcceptancePlan;
    $first = $service->freeze($version, planForAcceptanceTest($fixture, $this->truthSource));
    $second = $service->freeze($version, planForAcceptanceTest($fixture, $this->truthSource));
    $protocol = $service->protocolIdentity();

    expect($version->manifest_sha256)->toBe($manifest)
        ->and($first['acceptance_protocol_schema_version'])->toBe($protocol['schema_version'])
        ->and($first['acceptance_protocol_sha256'])->toBe($protocol['sha256'])
        ->and($protocol['sha256'])->not->toBe(hash('sha256', $protocol['schema_version']))
        ->and($first['acceptance_plan_json']['protocol_identity']['sha256'])->toBe($protocol['sha256'])
        ->and($second['acceptance_plan_sha256'])->toBe($first['acceptance_plan_sha256'])
        ->and($version->manifest_json)->not->toHaveKey('protocol')
        ->and($version->manifest_json)->not->toHaveKey('acceptance_plan');
});
