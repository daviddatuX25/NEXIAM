<?php

declare(strict_types=1);

namespace App\Assessment\Support;

use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\TestKitAcceptancePlanRecord;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;

/** One current-software-proof decision shared by acceptance consumers. */
final class TestKitCurrentAcceptanceStatus
{
    /** @var list<string> */
    private const REQUIRED_CHECKS = [
        'manifest_hash_current',
        'run_revision_current',
        'protocol_schema_current',
        'plan_schema_current',
        'diagnostic_evidence_current',
        'worker_identity_present',
        'exact_item_map_supported',
        'all_plan_cases_present',
        'independent_response_truth_match',
        'independent_raw_score_match',
        'sealed_evidence_current',
    ];

    public function __construct(
        private readonly TestKitCurrentEvidence $currentEvidence,
        private readonly TestKitAcceptancePlan $plans,
    ) {}

    /** @return array{current:bool,blockers:list<string>} */
    public function forReceipt(TestKitVersion $version, ?TestKitAcceptanceReceipt $receipt, bool $lock = false): array
    {
        $blockers = [];
        if ($receipt === null || $receipt->result !== TestKitAcceptanceReceipt::RESULT_PASSED) {
            $blockers[] = 'historical_pass_not_current';

            return ['current' => false, 'blockers' => $blockers];
        }

        $run = $receipt->diagnosticRun()->first();
        if (! $run instanceof OmrDiagnosticRun
            || $run->execution_kind !== OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC
            || $run->status !== OmrDiagnosticRun::STATUS_COMPLETED
            || (int) $run->revision !== (int) $receipt->run_revision
            || (int) $run->test_kit_version_id !== (int) $version->getKey()
            || (int) $receipt->test_kit_version_id !== (int) $version->getKey()
            || (int) $receipt->acceptance_plan_id !== (int) $run->acceptance_plan_id
            || ! hash_equals((string) $version->manifest_sha256, (string) $receipt->manifest_sha256)
            || ! hash_equals((string) $run->manifest_sha256, (string) $receipt->manifest_sha256)) {
            $blockers[] = 'acceptance_run_or_manifest_mismatch';
        }

        if ($run instanceof OmrDiagnosticRun) {
            $evidence = $this->currentEvidence->forRun($run, $lock);
            $run = $evidence['run'];
            if ($run->execution_kind !== OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC
                || $run->status !== OmrDiagnosticRun::STATUS_COMPLETED
                || (int) $run->revision !== (int) $receipt->run_revision
                || ! ($evidence['complete'] ?? false)
                || ($evidence['errors'] ?? []) !== []
                || $run->sealed_at === null
                || (int) $run->sealed_evidence_revision !== (int) $run->revision
                || ! hash_equals((string) $run->sealed_evidence_vector_sha256, (string) $evidence['evidence_vector_sha256'])
                || $receipt->evidence_revision_vector_json !== $evidence['evidence_vector']
                || ! hash_equals((string) $receipt->evidence_revision_vector_sha256, (string) $evidence['evidence_vector_sha256'])) {
                $blockers[] = 'acceptance_evidence_not_current';
            }

            $plan = is_array($run->acceptance_plan_json) ? $run->acceptance_plan_json : [];
            $report = is_array($receipt->check_report_json) ? $receipt->check_report_json : [];
            if (! $this->completeCheckReport($report, $plan)) {
                $blockers[] = 'acceptance_check_report_incomplete';
            }
            if (! $this->workerIdentityMatches($run, $receipt, $plan)) {
                $blockers[] = 'acceptance_worker_identity_mismatch';
            }
            $planRecord = TestKitAcceptancePlanRecord::query()
                ->whereKey((int) $receipt->acceptance_plan_id)
                ->where('test_kit_version_id', (int) $version->getKey())
                ->first();
            $protocol = $this->plans->protocolIdentity();
            $planProtocol = $plan['protocol_identity'] ?? null;
            if ($planRecord === null
                || $run->acceptance_plan_json !== $planRecord->plan_payload
                || (int) ($plan['test_kit_version_id'] ?? 0) !== (int) $version->getKey()
                || ! hash_equals((string) ($plan['manifest_sha256'] ?? ''), (string) $version->manifest_sha256)
                || ! is_array($planProtocol)
                || ($planProtocol['schema_version'] ?? null) !== (string) $protocol['schema_version']
                || ! hash_equals((string) ($planProtocol['sha256'] ?? ''), (string) $protocol['sha256'])
                || $run->acceptance_plan_schema_version !== $planRecord->plan_schema_version
                || ! hash_equals((string) $run->acceptance_plan_sha256, (string) $planRecord->plan_sha256)
                || $receipt->acceptance_plan_schema_version !== $planRecord->plan_schema_version
                || ! hash_equals((string) $receipt->acceptance_plan_sha256, (string) $planRecord->plan_sha256)
                || $receipt->acceptance_protocol_schema_version !== (string) $protocol['schema_version']
                || ! hash_equals((string) $receipt->acceptance_protocol_sha256, (string) $protocol['sha256'])
                || $run->acceptance_protocol_schema_version !== (string) $protocol['schema_version']
                || ! hash_equals((string) $run->acceptance_protocol_sha256, (string) $protocol['sha256'])
                || $receipt->scoring_kernel_identity !== AssessmentRawEvaluator::KERNEL_VERSION
                || $run->scoring_kernel_identity !== AssessmentRawEvaluator::KERNEL_VERSION) {
                $blockers[] = 'acceptance_plan_protocol_or_kernel_stale';
            }
        } else {
            $blockers[] = 'acceptance_run_missing';
        }

        return ['current' => $blockers === [], 'blockers' => array_values(array_unique($blockers))];
    }

    /** @param array<string, mixed> $report @param array<string, mixed> $plan */
    public function completeCheckReport(array $report, array $plan): bool
    {
        $checks = $report['checks'] ?? null;
        if (! is_array($checks)) {
            return false;
        }
        $actual = [];
        foreach ($checks as $check) {
            if (! is_array($check) || ! is_string($check['code'] ?? null) || ! is_bool($check['passed'] ?? null)) {
                return false;
            }
            $actual[] = $check['code'];
            if (! $check['passed']) {
                return false;
            }
        }
        sort($actual);
        $expected = self::REQUIRED_CHECKS;
        sort($expected);
        if ($actual !== $expected) {
            return false;
        }

        $cases = $plan['cases'] ?? null;
        $caseResults = $report['case_results'] ?? null;
        if (! is_array($cases) || $cases === [] || ! is_array($caseResults)) {
            return false;
        }
        $expectedIds = [];
        foreach ($cases as $case) {
            if (! is_array($case) || ! is_string($case['case_id'] ?? null) || $case['case_id'] === '') {
                return false;
            }
            $expectedIds[] = $case['case_id'];
        }
        $actualIds = array_keys($caseResults);
        sort($expectedIds);
        sort($actualIds);
        if ($expectedIds !== $actualIds) {
            return false;
        }
        foreach ($caseResults as $case) {
            if (! is_array($case)
                || ($case['passed'] ?? false) !== true
                || ($case['response_truth_match'] ?? false) !== true
                || ($case['raw_score_match'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $plan */
    public function workerIdentityMatchesRun(OmrDiagnosticRun $run, array $plan): bool
    {
        $persisted = $run->expected_worker_identity_json;
        $planned = $plan['expected_worker_identity'] ?? null;
        $template = $run->templateVersion;
        if (! is_array($persisted) || $persisted === [] || ! is_array($planned) || $template === null) {
            return false;
        }

        try {
            $current = $this->plans->expectedWorkerIdentity();
        } catch (\RuntimeException) {
            return false;
        }

        ksort($persisted, SORT_STRING);
        ksort($planned, SORT_STRING);
        ksort($current, SORT_STRING);

        $workerCommit = (string) ($current['engine_commit'] ?? '');

        return $persisted === $planned
            && $persisted === $current
            && $workerCommit !== ''
            && hash_equals($workerCommit, (string) $run->worker_commit);
    }

    /** @param array<string, mixed> $plan */
    private function workerIdentityMatches(OmrDiagnosticRun $run, TestKitAcceptanceReceipt $receipt, array $plan): bool
    {
        return $this->workerIdentityMatchesRun($run, $plan)
            && hash_equals(
                (string) ($run->expected_worker_identity_json['engine_commit'] ?? ''),
                (string) $receipt->worker_commit,
            );
    }
}
