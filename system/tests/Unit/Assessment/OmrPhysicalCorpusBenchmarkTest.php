<?php

use App\Assessment\Omr\Qualification\OmrPhysicalCorpusBenchmark;

function physicalBenchmarkReport(string $outcome = 'exact', bool $passed = true): array
{
    $report = [
        'contract' => 'nexiam.omr.qualification-result.v1',
        'corpus_id' => 'corpus-1',
        'mode' => 'production',
        'target_hashes' => [
            'artifact' => str_repeat('c', 64),
            'native_bundle' => str_repeat('d', 64),
            'manifest' => str_repeat('e', 64),
            'config' => str_repeat('f', 64),
            'mapping' => str_repeat('1', 64),
        ],
        'strategy' => ['id' => 'strategy', 'version' => '1', 'config_hash' => str_repeat('a', 64)],
        'gate' => ['passed' => $passed, 'status' => $passed ? 'passed' : 'blocked', 'reason_codes' => []],
        'cases' => [[
            'id' => 'capture-1',
            'input_hash' => str_repeat('b', 64),
            'expected_outcome' => 'exact',
            'actual_outcome' => $outcome,
            'passed' => $passed,
            'error_code' => $passed ? null : 'vector_mismatch',
        ]],
    ];
    $report['report_hash'] = OmrPhysicalCorpusBenchmark::reportHash($report);

    return $report;
}

test('comparison reports no regression when the candidate preserves a passing case', function () {
    $comparison = app(OmrPhysicalCorpusBenchmark::class)->compare(
        physicalBenchmarkReport(),
        physicalBenchmarkReport(),
    );

    expect($comparison['zero_regressions'])->toBeTrue()
        ->and($comparison['regressions'])->toBeEmpty();
});

test('comparison identifies a previously passing case that regressed', function () {
    $comparison = app(OmrPhysicalCorpusBenchmark::class)->compare(
        physicalBenchmarkReport(),
        physicalBenchmarkReport('review', false),
    );

    expect($comparison['zero_regressions'])->toBeFalse()
        ->and($comparison['regressions'])->toBe([[
            'id' => 'capture-1',
            'baseline_outcome' => 'exact',
            'candidate_outcome' => 'review',
            'error_code' => 'vector_mismatch',
        ]]);
});

test('comparison gate blocks a regressing candidate and rebinds its report hash', function () {
    $candidate = app(OmrPhysicalCorpusBenchmark::class)->applyComparisonGate(
        physicalBenchmarkReport(),
        physicalBenchmarkReport('review', false),
    );

    expect($candidate['gate']['passed'])->toBeFalse()
        ->and($candidate['gate']['status'])->toBe('blocked')
        ->and($candidate['gate']['reason_codes'])->toContain('strategy_regression_detected')
        ->and($candidate['comparison']['zero_regressions'])->toBeFalse()
        ->and($candidate['report_hash'])->toBe(OmrPhysicalCorpusBenchmark::reportHash($candidate));
});

test('comparison rejects a baseline from a different corpus', function () {
    $baseline = physicalBenchmarkReport();
    $baseline['corpus_id'] = 'other-corpus';
    $baseline['report_hash'] = OmrPhysicalCorpusBenchmark::reportHash($baseline);

    expect(fn () => app(OmrPhysicalCorpusBenchmark::class)->compare(
        $baseline,
        physicalBenchmarkReport(),
    ))->toThrow(InvalidArgumentException::class, 'qualification_baseline_incompatible');
});

test('comparison rejects a tampered baseline report', function () {
    $baseline = physicalBenchmarkReport();
    $baseline['cases'][0]['passed'] = false;

    expect(fn () => app(OmrPhysicalCorpusBenchmark::class)->compare(
        $baseline,
        physicalBenchmarkReport(),
    ))->toThrow(InvalidArgumentException::class, 'qualification_report_invalid');
});

test('comparison rejects a baseline for a different target', function () {
    $baseline = physicalBenchmarkReport();
    $baseline['target_hashes']['artifact'] = str_repeat('d', 64);
    $baseline['report_hash'] = OmrPhysicalCorpusBenchmark::reportHash($baseline);

    expect(fn () => app(OmrPhysicalCorpusBenchmark::class)->compare(
        $baseline,
        physicalBenchmarkReport(),
    ))->toThrow(InvalidArgumentException::class, 'qualification_baseline_incompatible');
});

test('comparison rejects an incomplete target identity', function () {
    $baseline = physicalBenchmarkReport();
    unset($baseline['target_hashes']['mapping']);
    $baseline['report_hash'] = OmrPhysicalCorpusBenchmark::reportHash($baseline);

    expect(fn () => app(OmrPhysicalCorpusBenchmark::class)->compare(
        $baseline,
        physicalBenchmarkReport(),
    ))->toThrow(InvalidArgumentException::class, 'qualification_report_invalid');
});

test('comparison rejects a baseline whose production gate did not pass', function () {
    $baseline = physicalBenchmarkReport('review', false);

    expect(fn () => app(OmrPhysicalCorpusBenchmark::class)->compare(
        $baseline,
        physicalBenchmarkReport(),
    ))->toThrow(InvalidArgumentException::class, 'qualification_baseline_not_passed');
});
