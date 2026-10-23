<?php

namespace App\Assessment\Omr\Qualification;

use App\Assessment\Omr\OmrCaptureAnalysisPort;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\Verification\OmrVerificationTargetLoader;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class OmrPhysicalCorpusRunner
{
    public const STRATEGY_ID = 'nexiam_background_normalization';

    public const STRATEGY_VERSION = '2';

    public function __construct(
        private OmrCaptureAnalysisPort $pipeline,
        private OmrVerificationTargetLoader $targets,
    ) {}

    /** @return array{id:string,version:string,config_hash:string} */
    public static function strategy(): array
    {
        $identity = ['id' => self::STRATEGY_ID, 'version' => self::STRATEGY_VERSION];
        $configuration = [
            'capture_policy' => config('omr.capture-policy.v1'),
            'normalization' => [
                'jpeg_quality' => 92,
                'max_candidate_bytes' => (int) config('omr.omrchecker.max_candidate_bytes', 10 * 1024 * 1024),
                'max_pixels' => (int) config('omr.canonical.limits.max_pixels_per_page', 20_000_000),
            ],
            'worker' => [
                'contract_version' => (string) config('omr.omrchecker.contract_version'),
                'engine_name' => (string) config('omr.omrchecker.engine_name'),
                'engine_commit' => (string) config('omr.omrchecker.engine_commit'),
                'patch_identity' => (string) config('omr.omrchecker.patch_identity'),
                'deadline_seconds' => (int) config('omr.provider.deadline_seconds'),
            ],
            'implementation' => [
                'capture_pipeline' => self::sourceHash('app/Assessment/Omr/OmrCaptureAnalysisPipeline.php'),
                'quarter_turn_normalizer' => self::sourceHash('app/Assessment/Omr/OmrQuarterTurnNormalizer.php'),
                'qr_orientation' => self::sourceHash('app/Assessment/Omr/Verification/OmrPhysicalQrOrientation.php'),
                'qr_reader' => self::sourceHash('app/Assessment/Omr/Canonical/OmrQrReader.php'),
                'corpus_runner' => self::sourceHash('app/Assessment/Omr/Qualification/OmrPhysicalCorpusRunner.php'),
                'diagnostic_request_builder' => self::sourceHash('app/Assessment/Omr/OmrDiagnosticRequestBuilder.php'),
                'diagnostic_runner' => self::sourceHash('app/Assessment/Omr/Diagnostics/OmrTemplateDiagnosticRunner.php'),
                'result_validator' => self::sourceHash('app/Assessment/Omr/OmrDetectionResultValidator.php'),
                'worker_detection' => self::sourceHash('docker/inference/detect.py'),
                'worker_layout' => self::sourceHash('docker/inference/verify.py'),
                'worker_marker_states' => self::sourceHash('docker/inference/marker_states.py'),
                'worker_rectification' => self::sourceHash('docker/inference/rectification_recovery.py'),
                'worker_rectifier' => self::sourceHash('docker/inference/document_rectifier.py'),
                'worker_envelope' => self::sourceHash('docker/inference/server.py'),
            ],
        ];

        return [
            ...$identity,
            'config_hash' => hash('sha256', json_encode(
                ['identity' => $identity, 'configuration' => $configuration],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            )),
        ];
    }

    private static function sourceHash(string $relativePath): string
    {
        $hash = hash_file('sha256', base_path($relativePath));
        if (! is_string($hash)) {
            throw new RuntimeException("qualification_strategy_source_missing:{$relativePath}");
        }

        return $hash;
    }

    /** @return array<string, mixed> */
    public function run(OmrTemplateVerificationRun $run, OmrPhysicalCorpusManifest $manifest): array
    {
        if ($manifest->strategy !== self::strategy()) {
            throw new RuntimeException('qualification_strategy_mismatch');
        }
        $loadedByMethod = [];
        $challengeCodes = [];
        $cases = [];
        foreach ($manifest->captures as $capture) {
            $method = (string) $capture['method'];
            if (! isset($loadedByMethod[$method])) {
                $loadedByMethod[$method] = $this->loadTargetWithoutMutation($run, $method);
                $challenge = OmrPhysicalChallenge::query()
                    ->where('run_id', $run->id)
                    ->where('method', $method)
                    ->latest('id')
                    ->first();
                if ($challenge === null) {
                    throw new RuntimeException("qualification_challenge_missing:{$method}");
                }
                $challengeCodes[$method] = (string) $challenge->challenge_code;
            }
            $loaded = $loadedByMethod[$method];
            $pageNo = (int) $capture['page_no'];
            $expectedVector = $loaded['plan']->expectedVectorForPage($pageNo);
            if (isset($capture['expected_vector'])
                && self::canonicalMap((array) $capture['expected_vector']) !== self::canonicalMap($expectedVector)) {
                $cases[] = $this->errorCase($capture, 'corpus_truth_plan_mismatch');

                continue;
            }

            $bytes = (string) file_get_contents((string) $capture['absolute_path']);
            try {
                $prepared = $this->pipeline->analyze(new OmrCaptureAnalysisRequest(
                    capture: new OmrCaptureInput(
                        bytes: $bytes,
                        mime: (string) $capture['mime'],
                        pageNo: $pageNo,
                        originalHash: (string) $capture['sha256'],
                    ),
                    target: $loaded['target'],
                    plan: $loaded['plan'],
                    pageNo: $pageNo,
                    expectedVectorHash: $loaded['plan']->expectedVectorHash,
                    challengeCode: $challengeCodes[$method],
                ));
                $actualOutcome = $prepared->succeeded()
                    ? (($prepared->diagnosticEvidence?->summary['vector_match'] ?? false) ? 'exact' : 'review')
                    : 'reject';
                $identityMatch = self::identityMatches($prepared->identity, (array) ($capture['expected_identity'] ?? []));
                if ($actualOutcome === 'exact' && $identityMatch === false) {
                    $actualOutcome = 'review';
                }
                $casePassed = $actualOutcome === $capture['expected_outcome']
                    && ! ($capture['classification'] === 'adversarial' && $actualOutcome === 'exact');
                $cases[] = [
                    'id' => $capture['id'],
                    'classification' => $capture['classification'],
                    'expected_outcome' => $capture['expected_outcome'],
                    'actual_outcome' => $actualOutcome,
                    'passed' => $casePassed,
                    'input_hash' => $prepared->inputHash,
                    'result_hashes' => $prepared->hashes,
                    'accepted_orientation' => $prepared->acceptedOrientation,
                    'orientation_diagnostics' => $prepared->orientationDiagnostics->toArray(),
                    'marker_matches' => $prepared->markerMatches,
                    'gates' => $prepared->gates,
                    'rectification' => $prepared->layout?->rectification,
                    'vector_match' => $prepared->diagnosticEvidence?->summary['vector_match'],
                    'identity_match' => $identityMatch,
                    'issues' => $prepared->issues,
                    'error_code' => $prepared->code,
                    'timings' => $prepared->timings,
                ];
            } catch (Throwable $exception) {
                $cases[] = $this->errorCase($capture, self::safeCode($exception));
            }
        }

        $failedCases = array_values(array_filter($cases, static fn (array $case): bool => $case['passed'] !== true));
        $gateReasons = $manifest->coverageIssues;
        if ($run->mode !== OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION) {
            $gateReasons[] = 'qualification_run_mode_invalid';
        }
        if ($run->draft_id !== null || $run->template_version_id === null) {
            $gateReasons[] = 'qualification_target_must_be_compiled';
        }
        if ($failedCases !== []) {
            $gateReasons[] = 'corpus_cases_failed';
        }
        $corpusPassed = $gateReasons === [];
        $report = [
            'contract' => 'nexiam.omr.qualification-result.v1',
            'corpus_id' => $manifest->corpusId,
            'mode' => $manifest->mode,
            'run_id' => (int) $run->id,
            'target_hashes' => $run->pinnedTargetHashes(),
            'strategy' => self::strategy(),
            'counts' => [
                'declared' => count($cases),
                'positive' => count(array_filter($cases, static fn (array $case): bool => $case['classification'] === 'positive')),
                'adversarial' => count(array_filter($cases, static fn (array $case): bool => $case['classification'] === 'adversarial')),
                'regression_only' => count(array_filter($cases, static fn (array $case): bool => $case['classification'] === 'regression_only')),
                'passed' => count($cases) - count($failedCases),
                'failed' => count($failedCases),
            ],
            'gate' => [
                'passed' => $corpusPassed,
                'status' => $corpusPassed ? 'passed' : 'blocked',
                'reason_codes' => array_values(array_unique($gateReasons)),
            ],
            'cases' => $cases,
        ];
        $report['report_hash'] = OmrPhysicalCorpusBenchmark::reportHash($report);

        return $report;
    }

    /** @return array<string, mixed> */
    private function loadTargetWithoutMutation(OmrTemplateVerificationRun $run, string $method): array
    {
        DB::beginTransaction();
        try {
            return $this->targets->loadPhysical($run, $method);
        } finally {
            DB::rollBack();
        }
    }

    /** @param array<string, mixed> $capture @return array<string, mixed> */
    private function errorCase(array $capture, string $code): array
    {
        $actualOutcome = 'reject';
        $typedReject = in_array($code, [
            'qr_purpose_mismatch',
            'qr_signature_invalid',
            'qr_key_unknown',
            'wrong_challenge',
            'wrong_challenge_page',
        ], true);

        return [
            'id' => $capture['id'],
            'classification' => $capture['classification'],
            'expected_outcome' => $capture['expected_outcome'],
            'actual_outcome' => $actualOutcome,
            'passed' => $capture['expected_outcome'] === $actualOutcome && $typedReject,
            'input_hash' => $capture['sha256'],
            'accepted_orientation' => null,
            'orientation_diagnostics' => null,
            'marker_matches' => null,
            'result_hashes' => [],
            'gates' => null,
            'rectification' => null,
            'vector_match' => false,
            'identity_match' => false,
            'issues' => [],
            'error_code' => $code,
            'timings' => [],
        ];
    }

    /** @param array<string, mixed> $actual @param array<string, mixed> $expected */
    private static function identityMatches(array $actual, array $expected): ?bool
    {
        if ($expected === []) {
            return null;
        }
        $detected = [];
        foreach ($actual as $entry) {
            if (is_array($entry) && is_string($entry['code'] ?? null)) {
                $detected[$entry['code']] = $entry['detected_value'] ?? $entry['value'] ?? null;
            }
        }

        return self::canonicalMap($detected) === self::canonicalMap($expected);
    }

    /** @param array<string, mixed> $values @return array<string, string> */
    private static function canonicalMap(array $values): array
    {
        $normalized = array_map('strval', $values);
        ksort($normalized);

        return $normalized;
    }

    private static function safeCode(Throwable $exception): string
    {
        $code = explode(':', $exception->getMessage(), 2)[0];

        return preg_match('/^[a-z0-9_]+$/', $code) === 1 ? $code : 'qualification_case_failed';
    }
}
