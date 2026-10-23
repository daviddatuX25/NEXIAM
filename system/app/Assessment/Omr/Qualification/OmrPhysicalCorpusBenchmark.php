<?php

namespace App\Assessment\Omr\Qualification;

use InvalidArgumentException;

final class OmrPhysicalCorpusBenchmark
{
    /** @param array<string, mixed> $baseline @param array<string, mixed> $candidate @return array<string, mixed> */
    public function compare(array $baseline, array $candidate): array
    {
        $this->assertReport($baseline, requirePassedProductionGate: true);
        $this->assertReport($candidate, requirePassedProductionGate: false);
        if (($baseline['corpus_id'] ?? null) !== ($candidate['corpus_id'] ?? null)
            || ($baseline['target_hashes'] ?? null) !== ($candidate['target_hashes'] ?? null)) {
            throw new InvalidArgumentException('qualification_baseline_incompatible');
        }
        $baselineCases = $this->indexCases((array) $baseline['cases']);
        $candidateCases = $this->indexCases((array) $candidate['cases']);
        if (array_keys($baselineCases) !== array_keys($candidateCases)) {
            throw new InvalidArgumentException('qualification_baseline_case_set_mismatch');
        }

        $regressions = [];
        foreach ($baselineCases as $id => $before) {
            $after = $candidateCases[$id];
            if ($before['input_hash'] !== $after['input_hash']
                || $before['expected_outcome'] !== $after['expected_outcome']) {
                throw new InvalidArgumentException("qualification_baseline_case_binding_mismatch:{$id}");
            }
            if ($before['passed'] === true && $after['passed'] !== true) {
                $regressions[] = [
                    'id' => $id,
                    'baseline_outcome' => $before['actual_outcome'],
                    'candidate_outcome' => $after['actual_outcome'],
                    'error_code' => $after['error_code'] ?? null,
                ];
            }
        }

        return [
            'baseline_report_hash' => $baseline['report_hash'],
            'baseline_strategy' => $baseline['strategy'],
            'candidate_strategy' => $candidate['strategy'],
            'zero_regressions' => $regressions === [],
            'regressions' => $regressions,
        ];
    }

    /** @param array<string, mixed> $baseline @param array<string, mixed> $candidate @return array<string, mixed> */
    public function applyComparisonGate(array $baseline, array $candidate): array
    {
        $candidate['comparison'] = $this->compare($baseline, $candidate);
        if ($candidate['comparison']['zero_regressions'] !== true) {
            $candidate['gate']['passed'] = false;
            $candidate['gate']['status'] = 'blocked';
            $candidate['gate']['reason_codes'][] = 'strategy_regression_detected';
            $candidate['gate']['reason_codes'] = array_values(array_unique($candidate['gate']['reason_codes']));
        }
        $candidate['report_hash'] = self::reportHash($candidate);

        return $candidate;
    }

    /** @param array<string, mixed> $report */
    public static function reportHash(array $report): string
    {
        unset($report['report_hash']);

        return hash('sha256', json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed> $report */
    private function assertReport(array $report, bool $requirePassedProductionGate): void
    {
        $strategy = $report['strategy'] ?? null;
        $strategyKeys = is_array($strategy) ? array_keys($strategy) : [];
        sort($strategyKeys);
        $targetHashes = $report['target_hashes'] ?? null;
        $targetHashKeys = is_array($targetHashes) ? array_keys($targetHashes) : [];
        sort($targetHashKeys);
        $gate = $report['gate'] ?? null;
        if (($report['contract'] ?? null) !== 'nexiam.omr.qualification-result.v1'
            || ! is_string($report['corpus_id'] ?? null)
            || trim($report['corpus_id']) === ''
            || ! is_array($targetHashes)
            || $targetHashKeys !== ['artifact', 'config', 'manifest', 'mapping', 'native_bundle']
            || array_filter(
                $targetHashes,
                static fn (mixed $hash): bool => ! is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1,
            ) !== []
            || ! is_array($strategy)
            || $strategyKeys !== ['config_hash', 'id', 'version']
            || ! is_string($strategy['id'] ?? null)
            || trim($strategy['id']) === ''
            || ! is_string($strategy['version'] ?? null)
            || trim($strategy['version']) === ''
            || preg_match('/^[a-f0-9]{64}$/', (string) ($strategy['config_hash'] ?? '')) !== 1
            || ! is_array($gate)
            || ! is_bool($gate['passed'] ?? null)
            || ! is_array($report['cases'] ?? null)
            || ! array_is_list($report['cases'])
            || preg_match('/^[a-f0-9]{64}$/', (string) ($report['report_hash'] ?? '')) !== 1
            || ! hash_equals((string) $report['report_hash'], self::reportHash($report))) {
            throw new InvalidArgumentException('qualification_report_invalid');
        }
        if ($requirePassedProductionGate
            && (($report['mode'] ?? null) !== 'production' || $gate['passed'] !== true)) {
            throw new InvalidArgumentException('qualification_baseline_not_passed');
        }
        foreach ($report['cases'] as $case) {
            if (! is_array($case)
                || ! is_bool($case['passed'] ?? null)
                || ! is_string($case['input_hash'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/', $case['input_hash']) !== 1
                || ! in_array($case['expected_outcome'] ?? null, ['exact', 'review', 'reject'], true)
                || ! in_array($case['actual_outcome'] ?? null, ['exact', 'review', 'reject'], true)) {
                throw new InvalidArgumentException('qualification_report_cases_invalid');
            }
        }
    }

    /** @param list<array<string, mixed>> $cases @return array<string, array<string, mixed>> */
    private function indexCases(array $cases): array
    {
        $indexed = [];
        foreach ($cases as $case) {
            if (! is_array($case) || ! is_string($case['id'] ?? null) || isset($indexed[$case['id']])) {
                throw new InvalidArgumentException('qualification_baseline_cases_invalid');
            }
            $indexed[$case['id']] = $case;
        }
        ksort($indexed);

        return $indexed;
    }
}
