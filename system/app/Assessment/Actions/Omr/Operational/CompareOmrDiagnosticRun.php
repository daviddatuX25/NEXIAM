<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Operational;

use App\Assessment\Omr\Operational\OmrDiagnosticEffectiveAnswerProjector;
use App\Assessment\Support\AssessmentRawEvaluator;
use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitCurrentEvidence;
use App\Assessment\Support\TestKitMockSession;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

use function array_values;
use function count;
use function hash;
use function hash_equals;
use function is_array;
use function json_decode;
use function json_encode;
use function preg_match;
use function strtoupper;
use function trim;

/**
 * Compares diagnostic effective answers with the server-owned finalized
 * Booklet Set. Expected answers never enter capture analysis or the effective
 * answer projector; they are resolved only inside this terminal comparison.
 */
final class CompareOmrDiagnosticRun
{
    public const COMMAND_NAME = 'omr.diagnostic.compare';

    public const AUDIT_EVENT = 'omr.diagnostic.compared';

    private const RECEIPT_LEASE_MINUTES = 5;

    private const AGGREGATE_TYPE = 'omr_diagnostic_run';

    private const COMPARISON_SCHEMA = 'nexiam.omr.diagnostic-comparison.v1';

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly OmrDiagnosticEffectiveAnswerProjector $projector,
        private readonly ?TestKitCurrentEvidence $currentEvidence = null,
        private readonly ?AssessmentRawEvaluator $rawEvaluator = null,
    ) {}

    /**
     * Resolve and compare a diagnostic run's effective answers.
     *
     * @return array<string, mixed>
     */
    public function handle(
        OmrDiagnosticRun $run,
        User $actor,
        int $expectedRevision,
        string $idempotencyKey,
    ): array {
        $idempotencyKey = trim($idempotencyKey);
        if ($expectedRevision < 1) {
            throw new RuntimeException('omr_diagnostic_run_revision_invalid');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 255) {
            throw new RuntimeException('omr_diagnostic_compare_idempotency_key_invalid');
        }

        $this->assertDiagnosticKind($run);
        $this->assertAuthorized($actor, $run);
        $payloadFingerprint = hash('sha256', json_encode([
            'run_id' => (int) $run->getKey(),
            'expected_revision' => $expectedRevision,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use (
            $run,
            $actor,
            $expectedRevision,
            $idempotencyKey,
            $payloadFingerprint,
        ): array {
            $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail((int) $run->getKey());
            $this->assertDiagnosticKind($lockedRun);
            $this->assertAuthorized($actor, $lockedRun);
            if ($lockedRun->status === OmrDiagnosticRun::STATUS_CANCELLED) {
                throw new RuntimeException('omr_diagnostic_run_not_comparable');
            }

            $receipt = $this->lockReceipt($actor, (int) $lockedRun->getKey(), $idempotencyKey);
            if ($receipt !== null) {
                $this->assertReceiptFingerprint($receipt, $payloadFingerprint);
                if ((string) $receipt->status === 'succeeded') {
                    return $this->resultFromReceipt($receipt, $lockedRun);
                }
                if ((string) $receipt->status === 'started'
                    && $receipt->lease_expires_at !== null
                    && strtotime((string) $receipt->lease_expires_at) > time()) {
                    throw new RuntimeException('omr_diagnostic_compare_idempotency_in_progress');
                }

                $receiptId = (int) $receipt->id;
                $correlationId = Str::isUuid((string) $receipt->correlation_id)
                    ? (string) $receipt->correlation_id
                    : (string) Str::uuid();
                DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                    'status' => 'started',
                    'execution_token' => (string) Str::uuid(),
                    'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                    'correlation_id' => $correlationId,
                    'updated_at' => now(),
                ]);
            } else {
                $correlationId = (string) Str::uuid();
                $receiptId = (int) DB::table('omr_command_receipts')->insertGetId([
                    'actor_id' => (int) $actor->getKey(),
                    'command_name' => self::COMMAND_NAME,
                    'aggregate_type' => self::AGGREGATE_TYPE,
                    'aggregate_id' => (string) $lockedRun->getKey(),
                    'idempotency_key' => $idempotencyKey,
                    'payload_fingerprint' => $payloadFingerprint,
                    'status' => 'started',
                    'execution_token' => (string) Str::uuid(),
                    'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                    'safe_result_json' => null,
                    'correlation_id' => $correlationId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($lockedRun->isTerminal()) {
                throw new RuntimeException('omr_diagnostic_run_not_comparable');
            }
            if ((int) $lockedRun->revision !== $expectedRevision) {
                throw new RuntimeException('omr_diagnostic_run_revision_stale');
            }

            if ((string) $lockedRun->diagnostic_purpose === 'mock_rehearsal') {
                $mockCaptures = OmrDiagnosticCapture::query()
                    ->where('diagnostic_run_id', (int) $lockedRun->getKey())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                foreach ($mockCaptures as $mockCapture) {
                    if (in_array($mockCapture->status, [
                        OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                        OmrDiagnosticCapture::STATUS_REVIEWED,
                        OmrDiagnosticCapture::STATUS_COMPLETED,
                    ], true)) {
                        $projected = $this->projector->project($mockCapture);
                        $mockCapture->forceFill([
                            'status' => ($projected['has_unresolved'] ?? false) || ($projected['has_stale'] ?? false)
                                ? OmrDiagnosticCapture::STATUS_NEEDS_REVIEW
                                : OmrDiagnosticCapture::STATUS_REVIEWED,
                            'version' => (int) $mockCapture->version + 1,
                            'effective_answers_json' => $projected,
                        ])->saveOrFail();
                    }
                }
                $summary = app(TestKitMockSession::class)->summarize($lockedRun);
                $nextRevision = (int) $lockedRun->revision + 1;
                $lockedRun->forceFill([
                    'status' => OmrDiagnosticRun::STATUS_NEEDS_REVIEW,
                    'phase' => OmrDiagnosticRun::PHASE_REVIEW,
                    'revision' => $nextRevision,
                ])->saveOrFail();
                $result = [
                    'outcome' => 'succeeded',
                    'run_id' => (int) $lockedRun->getKey(),
                    'run_revision' => $nextRevision,
                    'diagnostic_purpose' => 'mock_rehearsal',
                    'official' => false,
                    'safe_outcome' => $summary,
                    'comparison' => $summary,
                    'projected_score' => $summary,
                    'receipt' => [
                        'command_name' => self::COMMAND_NAME,
                        'aggregate_type' => self::AGGREGATE_TYPE,
                        'aggregate_id' => (string) $lockedRun->getKey(),
                        'run_revision' => $nextRevision,
                        'correlation_id' => $correlationId,
                    ],
                ];
                $this->recordAuditEvent->handle(
                    self::AUDIT_EVENT,
                    [
                        'run_id' => (int) $lockedRun->getKey(),
                        'diagnostic_purpose' => 'mock_rehearsal',
                        'official' => false,
                        'prior_run_revision' => $expectedRevision,
                        'run_revision' => $nextRevision,
                        'correlation_id' => $correlationId,
                    ],
                    (string) $actor->getKey(),
                );
                DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                    'status' => 'succeeded',
                    'lease_expires_at' => null,
                    'safe_result_json' => json_encode($result, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

                return $result;
            }

            if ($lockedRun->test_kit_version_id !== null) {
                return $this->compareTestKitDiagnosticRun(
                    $lockedRun,
                    $actor,
                    $receiptId,
                    $correlationId,
                    $expectedRevision,
                );
            }
            $captures = OmrDiagnosticCapture::query()
                ->where('diagnostic_run_id', (int) $lockedRun->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('input_asset_id')
                ->map->last()
                ->sortBy('id')
                ->values();
            if (count($captures) === 0) {
                throw new RuntimeException('omr_diagnostic_capture_missing');
            }
            if ($captures->contains(static fn (OmrDiagnosticCapture $capture): bool => in_array(
                $capture->status,
                [
                    OmrDiagnosticCapture::STATUS_RECEIVED,
                    OmrDiagnosticCapture::STATUS_QUEUED,
                    OmrDiagnosticCapture::STATUS_PROCESSING,
                ],
                true,
            ))) {
                throw new RuntimeException('omr_diagnostic_capture_active');
            }
            if ($captures->contains(static fn (OmrDiagnosticCapture $capture): bool => ! in_array(
                $capture->status,
                [
                    OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                    OmrDiagnosticCapture::STATUS_REVIEWED,
                    OmrDiagnosticCapture::STATUS_COMPLETED,
                ],
                true,
            ))) {
                throw new RuntimeException('omr_diagnostic_capture_not_comparable');
            }

            // This is intentionally before material resolution. The expected
            // Set is not visible to recognition or effective-answer derivation.
            $effectiveByCapture = [];
            foreach ($captures as $capture) {
                $effectiveByCapture[(int) $capture->getKey()] = $this->projector->project($capture);
            }

            $set = $this->resolveFinalizedSet($lockedRun);
            $comparison = $this->compareEffectiveAnswers($effectiveByCapture, $set);
            $nextRevision = (int) $lockedRun->revision + 1;

            foreach ($captures as $capture) {
                $captureProjection = [
                    'schema_version' => self::COMPARISON_SCHEMA,
                    'run_id' => (int) $lockedRun->getKey(),
                    'capture_id' => (int) $capture->getKey(),
                    'run_revision' => $nextRevision,
                    'effective' => $effectiveByCapture[(int) $capture->getKey()],
                    'comparison' => $comparison,
                ];
                $capture->forceFill([
                    'status' => $comparison['passed'] === true
                        ? OmrDiagnosticCapture::STATUS_REVIEWED
                        : OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                    'version' => (int) $capture->version + 1,
                    'projected_score_json' => $captureProjection,
                ])->saveOrFail();
            }

            $lockedRun->forceFill([
                'status' => OmrDiagnosticRun::STATUS_NEEDS_REVIEW,
                'phase' => OmrDiagnosticRun::PHASE_REVIEW,
                'revision' => $nextRevision,
            ])->saveOrFail();

            $result = [
                'outcome' => 'succeeded',
                'run_id' => (int) $lockedRun->getKey(),
                'run_revision' => $nextRevision,
                'capture_id' => count($effectiveByCapture) === 1 ? (int) array_key_first($effectiveByCapture) : null,
                'effective' => count($effectiveByCapture) === 1
                    ? array_values($effectiveByCapture)[0]
                    : ['captures' => $effectiveByCapture],
                'comparison' => $comparison,
                'projected_score' => $comparison,
                'receipt' => [
                    'command_name' => self::COMMAND_NAME,
                    'aggregate_type' => self::AGGREGATE_TYPE,
                    'aggregate_id' => (string) $lockedRun->getKey(),
                    'run_revision' => $nextRevision,
                    'correlation_id' => $correlationId,
                ],
            ];

            $this->recordAuditEvent->handle(
                self::AUDIT_EVENT,
                [
                    'run_id' => (int) $lockedRun->getKey(),
                    'booklet_version_id' => (int) $lockedRun->booklet_version_id,
                    'declared_set_code' => (string) $lockedRun->declared_set_code,
                    'booklet_set_id' => (int) $comparison['booklet_set_id'],
                    'capture_count' => count($effectiveByCapture),
                    'total_items' => (int) $comparison['total_items'],
                    'exact_matches' => (int) $comparison['exact_matches'],
                    'mismatches' => (int) $comparison['mismatches'],
                    'unresolved_items' => (int) $comparison['unresolved_items'],
                    'missing_items' => (int) $comparison['missing_items'],
                    'passed' => (bool) $comparison['passed'],
                    'prior_run_revision' => $expectedRevision,
                    'run_revision' => $nextRevision,
                    'correlation_id' => $correlationId,
                ],
                (string) $actor->getKey(),
            );

            DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                'status' => 'succeeded',
                'lease_expires_at' => null,
                'safe_result_json' => json_encode($result, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return $result;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function compareTestKitDiagnosticRun(
        OmrDiagnosticRun $lockedRun,
        User $actor,
        int $receiptId,
        string $correlationId,
        int $expectedRevision,
    ): array {
        $selectedEvidence = ($this->currentEvidence ?? app(TestKitCurrentEvidence::class))->forRun($lockedRun, true);
        $captures = $selectedEvidence['captures'];
        if ($captures->isEmpty()) {
            throw new RuntimeException('omr_diagnostic_capture_missing');
        }

        $evidenceComplete = ($selectedEvidence['complete'] ?? false) === true && ($selectedEvidence['errors'] ?? []) === [];

        $effectiveByCapture = [];
        foreach ($captures as $capture) {
            $effectiveByCapture[(int) $capture->getKey()] = $this->projector->project($capture);
        }

        $projectedItemsByKeyByCapture = [];
        foreach ($effectiveByCapture as $captureId => $projection) {
            foreach ((array) ($projection['items'] ?? []) as $pItem) {
                if (is_array($pItem) && isset($pItem['item_key'])) {
                    $projectedItemsByKeyByCapture[$captureId][(string) $pItem['item_key']] = $pItem;
                }
            }
        }

        $observationsByCase = [];
        $caseObservationsByDest = [];
        $entries = (array) ($selectedEvidence['evidence_vector']['entries'] ?? []);
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $caseId = (string) ($entry['case_id'] ?? '');
            $pageNo = (int) ($entry['page_no'] ?? 0);
            $captureId = (int) ($entry['capture_id'] ?? 0);
            foreach ((array) ($entry['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $itemKey = (string) ($item['item_key'] ?? '');
                $identity = is_array($item['item_identity'] ?? null) ? $item['item_identity'] : [];
                $nativeLabel = (string) ($identity['native_label'] ?? $itemKey);
                $pItem = $projectedItemsByKeyByCapture[$captureId][$itemKey] ?? null;

                $effectiveState = is_array($pItem) ? (string) ($pItem['effective_state'] ?? 'unresolved') : 'unresolved';
                $observedToken = null;
                if ($effectiveState === 'blank') {
                    $observedToken = null;
                } elseif ($effectiveState === 'selected' && is_array($pItem) && isset($pItem['effective_option'])) {
                    $observedToken = is_string($pItem['effective_option']) ? $pItem['effective_option'] : null;
                } else {
                    $observedToken = '__UNRESOLVED__';
                }

                $observationsByCase[$caseId][] = [
                    'page_no' => $pageNo,
                    'native_label' => $nativeLabel,
                    'token' => $observedToken === '__UNRESOLVED__' ? null : $observedToken,
                    'raw_token' => $observedToken,
                    'effective_state' => $effectiveState,
                ];
                $caseObservationsByDest[$caseId][$pageNo][$nativeLabel] = $observedToken;
            }
        }

        $plan = is_array($lockedRun->acceptance_plan_json) ? $lockedRun->acceptance_plan_json : [];
        $cases = is_array($plan['cases'] ?? null) ? $plan['cases'] : [];

        $allResponsesMatched = true;
        $totalItems = 0;
        $exactMatches = 0;
        $mismatches = 0;
        $blankItems = 0;
        $unresolvedItems = 0;
        $missingItems = 0;
        $caseResults = [];

        foreach ($cases as $case) {
            if (! is_array($case)) {
                continue;
            }
            $caseId = (string) ($case['case_id'] ?? '');
            $caseMatched = true;
            $caseExpectedCount = 0;
            $caseExact = 0;

            foreach ((array) ($case['pages'] ?? []) as $page) {
                if (! is_array($page)) {
                    continue;
                }
                $pageNo = (int) ($page['page_no'] ?? 0);
                foreach ((array) ($page['responses'] ?? []) as $resp) {
                    if (! is_array($resp)) {
                        continue;
                    }
                    $totalItems++;
                    $caseExpectedCount++;
                    $label = (string) ($resp['native_label'] ?? '');
                    $expectedToken = array_key_exists('token', $resp) ? $resp['token'] : null;

                    if (! array_key_exists($label, $caseObservationsByDest[$caseId][$pageNo] ?? [])) {
                        $missingItems++;
                        $caseMatched = false;
                        $allResponsesMatched = false;

                        continue;
                    }

                    $observed = $caseObservationsByDest[$caseId][$pageNo][$label];
                    if ($observed === '__UNRESOLVED__') {
                        $unresolvedItems++;
                        $caseMatched = false;
                        $allResponsesMatched = false;
                    } elseif ($observed === null) {
                        $blankItems++;
                        if ($expectedToken === null) {
                            $exactMatches++;
                            $caseExact++;
                        } else {
                            $mismatches++;
                            $caseMatched = false;
                            $allResponsesMatched = false;
                        }
                    } else {
                        if ($expectedToken !== null && $observed === $expectedToken) {
                            $exactMatches++;
                            $caseExact++;
                        } else {
                            $mismatches++;
                            $caseMatched = false;
                            $allResponsesMatched = false;
                        }
                    }
                }
            }

            $caseResults[$caseId] = [
                'case_id' => $caseId,
                'matched' => $caseMatched,
                'expected_items' => $caseExpectedCount,
                'exact_matches' => $caseExact,
            ];
        }

        $responseCheckPassed = $evidenceComplete && $allResponsesMatched && $cases !== [];

        $rawScoreCheckPassed = false;
        if ($evidenceComplete && $responseCheckPassed) {
            try {
                $version = TestKitVersion::query()->findOrFail((int) $lockedRun->test_kit_version_id);
                $contract = app(KitCompositionContext::class)->resolveVersion($version)['item_map'];
                $metricIdByCode = [];
                foreach ((array) ($contract['metrics'] ?? []) as $metric) {
                    if (is_array($metric) && isset($metric['code'], $metric['metric_id'])) {
                        $metricIdByCode[(string) $metric['code']] = (int) $metric['metric_id'];
                    }
                }

                $allCasesScored = true;
                foreach ($cases as $case) {
                    $caseId = (string) ($case['case_id'] ?? '');
                    $caseObs = array_map(
                        static fn (array $o): array => [
                            'page_no' => (int) $o['page_no'],
                            'native_label' => (string) $o['native_label'],
                            'token' => $o['token'],
                        ],
                        $observationsByCase[$caseId] ?? [],
                    );
                    $recordedMeasures = is_array($case['recorded_measures'] ?? null) ? $case['recorded_measures'] : [];
                    $evaluatorContract = array_intersect_key($contract, array_flip([
                        'profile_version_id',
                        'content_sha256',
                        'items',
                        'bindings',
                        'metrics',
                        'geometry',
                    ]));
                    $evaluated = ($this->rawEvaluator ?? app(AssessmentRawEvaluator::class))->evaluate(
                        $evaluatorContract,
                        $caseObs,
                        $recordedMeasures,
                    );
                    $actualMetrics = array_map(
                        static fn (array $m): array => ['metric_id' => (int) $m['metric_id'], 'value' => (string) $m['value']],
                        $evaluated['metric_values'],
                    );
                    $expectedMetrics = [];
                    foreach ((array) ($case['expected_metrics'] ?? []) as $m) {
                        if (! is_array($m)) {
                            continue;
                        }
                        $metricId = isset($m['metric_id'])
                            ? (int) $m['metric_id']
                            : ($metricIdByCode[(string) ($m['metric_code'] ?? '')] ?? null);
                        if ($metricId !== null && isset($m['value'])) {
                            $expectedMetrics[] = [
                                'metric_id' => $metricId,
                                'value' => (string) $m['value'],
                            ];
                        }
                    }

                    usort($actualMetrics, static fn (array $l, array $r): int => $l['metric_id'] <=> $r['metric_id']);
                    usort($expectedMetrics, static fn (array $l, array $r): int => $l['metric_id'] <=> $r['metric_id']);
                    if ($actualMetrics !== $expectedMetrics || $expectedMetrics === []) {
                        $allCasesScored = false;
                        break;
                    }
                }
                $rawScoreCheckPassed = $allCasesScored;
            } catch (\Throwable) {
                $rawScoreCheckPassed = false;
            }
        }

        $passed = $evidenceComplete && $responseCheckPassed && $rawScoreCheckPassed;

        $comparison = [
            'schema_version' => self::COMPARISON_SCHEMA,
            'test_kit_version_id' => (int) $lockedRun->test_kit_version_id,
            'acceptance_plan_id' => (int) $lockedRun->acceptance_plan_id,
            'booklet_version_id' => (int) $lockedRun->booklet_version_id,
            'booklet_set_id' => (int) $lockedRun->booklet_set_id,
            'set_code' => (string) $lockedRun->declared_set_code,
            'evidence_vector_sha256' => (string) ($selectedEvidence['evidence_vector_sha256'] ?? ''),
            'evidence_complete' => $evidenceComplete,
            'response_check_passed' => $responseCheckPassed,
            'raw_score_check_passed' => $rawScoreCheckPassed,
            'total_cases' => count($cases),
            'case_results' => $caseResults,
            'total_items' => $totalItems,
            'exact_matches' => $exactMatches,
            'mismatches' => $mismatches,
            'blank_items' => $blankItems,
            'unresolved_items' => $unresolvedItems,
            'missing_items' => $missingItems,
            'passed' => $passed,
        ];

        $nextRevision = (int) $lockedRun->revision + 1;

        foreach ($captures as $capture) {
            $captureProjection = [
                'schema_version' => self::COMPARISON_SCHEMA,
                'run_id' => (int) $lockedRun->getKey(),
                'capture_id' => (int) $capture->getKey(),
                'run_revision' => $nextRevision,
                'effective' => $effectiveByCapture[(int) $capture->getKey()] ?? [],
                'comparison' => $comparison,
            ];
            $capture->forceFill([
                'status' => $passed
                    ? OmrDiagnosticCapture::STATUS_REVIEWED
                    : OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                'completed_at' => null,
                'projected_score_json' => $captureProjection,
            ])->saveOrFail();
        }

        $lockedRun->forceFill([
            'status' => OmrDiagnosticRun::STATUS_NEEDS_REVIEW,
            'phase' => OmrDiagnosticRun::PHASE_REVIEW,
            'revision' => $nextRevision,
        ])->saveOrFail();

        $result = [
            'outcome' => 'succeeded',
            'run_id' => (int) $lockedRun->getKey(),
            'run_revision' => $nextRevision,
            'capture_id' => count($effectiveByCapture) === 1 ? (int) array_key_first($effectiveByCapture) : null,
            'effective' => count($effectiveByCapture) === 1
                ? array_values($effectiveByCapture)[0]
                : ['captures' => $effectiveByCapture],
            'comparison' => $comparison,
            'projected_score' => $comparison,
            'receipt' => [
                'command_name' => self::COMMAND_NAME,
                'aggregate_type' => self::AGGREGATE_TYPE,
                'aggregate_id' => (string) $lockedRun->getKey(),
                'run_revision' => $nextRevision,
                'correlation_id' => $correlationId,
            ],
        ];

        $this->recordAuditEvent->handle(
            self::AUDIT_EVENT,
            [
                'run_id' => (int) $lockedRun->getKey(),
                'test_kit_version_id' => (int) $lockedRun->test_kit_version_id,
                'booklet_version_id' => (int) $lockedRun->booklet_version_id,
                'declared_set_code' => (string) $lockedRun->declared_set_code,
                'booklet_set_id' => (int) $lockedRun->booklet_set_id,
                'capture_count' => count($effectiveByCapture),
                'total_items' => $totalItems,
                'exact_matches' => $exactMatches,
                'mismatches' => $mismatches,
                'unresolved_items' => $unresolvedItems,
                'missing_items' => $missingItems,
                'response_check_passed' => $responseCheckPassed,
                'raw_score_check_passed' => $rawScoreCheckPassed,
                'passed' => $passed,
                'prior_run_revision' => $expectedRevision,
                'run_revision' => $nextRevision,
                'correlation_id' => $correlationId,
            ],
            (string) $actor->getKey(),
        );

        DB::table('omr_command_receipts')->where('id', $receiptId)->update([
            'status' => 'succeeded',
            'lease_expires_at' => null,
            'safe_result_json' => json_encode($result, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return $result;
    }

    private function assertDiagnosticKind(OmrDiagnosticRun $run): void
    {
        if ((string) $run->execution_kind !== OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC) {
            throw new RuntimeException('omr_diagnostic_execution_kind_invalid');
        }
    }

    private function assertAuthorized(User $actor, OmrDiagnosticRun $run): void
    {
        if ((int) $run->created_by_user_id !== (int) $actor->getKey()) {
            throw new RuntimeException('omr_diagnostic_owner_mismatch');
        }

        $scope = ['institution_id' => (int) $run->institution_id];
        $run->loadMissing('academicCycle');
        if ($run->academicCycle !== null && trim((string) $run->academicCycle->code) !== '') {
            $scope['cycle'] = (string) $run->academicCycle->code;
        }
        $decision = $this->authorizer->decide(
            $actor,
            CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
            $scope,
        );
        if (! $decision->allowed) {
            if ($decision->reason === 'scope_mismatch') {
                throw new ModelNotFoundException('Diagnostic run is out of scope.');
            }
            throw new RuntimeException('omr_diagnostic_compare_denied');
        }
    }

    private function resolveFinalizedSet(OmrDiagnosticRun $run): OmrBookletSet
    {
        $set = OmrBookletSet::query()
            ->where('booklet_version_id', (int) $run->booklet_version_id)
            ->where('set_code', (string) $run->declared_set_code)
            ->finalized()
            ->with(['items.aptitudeArea', 'bookletVersion'])
            ->lockForUpdate()
            ->first();
        if (! $set instanceof OmrBookletSet) {
            throw new RuntimeException('omr_diagnostic_declared_set_not_finalized');
        }
        $bookletVersion = $set->bookletVersion;
        if (! $bookletVersion instanceof OmrQuestionBookletVersion || ! $bookletVersion->isFinalized()) {
            throw new RuntimeException('omr_diagnostic_declared_booklet_not_finalized');
        }
        if ((int) $bookletVersion->template_version_id !== (int) $run->template_version_id) {
            throw new RuntimeException('omr_diagnostic_material_template_mismatch');
        }
        if ($set->items->isEmpty()) {
            throw new RuntimeException('omr_diagnostic_declared_set_empty');
        }

        return $set;
    }

    /**
     * @param  array<int, array<string, mixed>>  $effectiveByCapture
     * @return array<string, mixed>
     */
    private function compareEffectiveAnswers(array $effectiveByCapture, OmrBookletSet $set): array
    {
        $actual = [];
        foreach ($effectiveByCapture as $captureId => $projection) {
            foreach ((array) ($projection['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $actual[] = [...$item, 'capture_id' => (int) $captureId];
                }
            }
        }
        if ($actual === []) {
            throw new RuntimeException('omr_diagnostic_evidence_items_missing');
        }

        $setItems = $set->items->sortBy('global_position')->values()->all();
        $unused = array_fill(0, count($actual), true);
        $fallbackOrder = $this->canUseOrderedFallback($actual, count($setItems));
        $perArea = [];
        $itemResults = [];
        $exact = 0;
        $mismatches = 0;
        $blank = 0;
        $unresolved = 0;
        $missing = 0;

        foreach ($setItems as $index => $setItem) {
            $areaCode = strtoupper(trim((string) ($setItem->aptitudeArea->code ?? '')));
            $questionNo = (int) ($setItem->area_position ?? 0);
            $expectedOption = strtoupper(trim((string) $setItem->correct_option_code));
            $actualIndex = $this->findActualIndex($actual, $unused, $areaCode, $questionNo, $index, $fallbackOrder);
            $classification = 'missing';
            $match = null;
            $effectiveState = 'missing';
            $effectiveOption = null;
            $itemKey = null;
            $captureId = null;

            if ($actualIndex !== null) {
                $unused[$actualIndex] = false;
                $observed = $actual[$actualIndex];
                $itemKey = (string) ($observed['item_key'] ?? '');
                $captureId = (int) ($observed['capture_id'] ?? 0);
                $effectiveState = (string) ($observed['effective_state'] ?? 'unresolved');
                $effectiveOption = $observed['effective_option'] ?? null;
                if ($effectiveState === 'selected' && is_string($effectiveOption) && trim($effectiveOption) !== '') {
                    if (strtoupper(trim($effectiveOption)) === $expectedOption) {
                        $classification = 'exact';
                        $match = true;
                        $exact++;
                    } else {
                        $classification = 'mismatch';
                        $match = false;
                        $mismatches++;
                    }
                } elseif ($effectiveState === 'blank') {
                    $classification = 'blank';
                    $match = false;
                    $blank++;
                } else {
                    $classification = 'unresolved';
                    $match = null;
                    $unresolved++;
                }
            } else {
                $missing++;
            }

            if (! isset($perArea[$areaCode])) {
                $perArea[$areaCode] = [
                    'area_code' => $areaCode,
                    'total_items' => 0,
                    'exact_matches' => 0,
                    'mismatches' => 0,
                    'blank_items' => 0,
                    'unresolved_items' => 0,
                    'missing_items' => 0,
                ];
            }
            $perArea[$areaCode]['total_items']++;
            $perArea[$areaCode][match ($classification) {
                'exact' => 'exact_matches',
                'mismatch' => 'mismatches',
                'blank' => 'blank_items',
                'unresolved' => 'unresolved_items',
                default => 'missing_items',
            }]++;

            $itemResults[] = [
                'set_position' => (int) $setItem->global_position,
                'area_code' => $areaCode,
                'question_no' => $questionNo,
                'item_key' => $itemKey,
                'capture_id' => $captureId,
                'effective_state' => $effectiveState,
                'effective_option' => $effectiveOption,
                'classification' => $classification,
                'match' => $match,
            ];
        }

        $extraItems = [];
        foreach ($unused as $index => $isUnused) {
            if ($isUnused) {
                $extraItems[] = [
                    'item_key' => (string) ($actual[$index]['item_key'] ?? ''),
                    'capture_id' => (int) ($actual[$index]['capture_id'] ?? 0),
                    'effective_state' => (string) ($actual[$index]['effective_state'] ?? 'unresolved'),
                    'effective_option' => $actual[$index]['effective_option'] ?? null,
                    'classification' => 'extra',
                    'match' => false,
                ];
            }
        }

        $extra = count($extraItems);

        return [
            'schema_version' => self::COMPARISON_SCHEMA,
            'booklet_version_id' => (int) $set->booklet_version_id,
            'booklet_set_id' => (int) $set->getKey(),
            'set_code' => (string) $set->set_code,
            'total_items' => count($setItems),
            'matched_items' => count($setItems) - $missing,
            'exact_matches' => $exact,
            'mismatches' => $mismatches,
            'blank_items' => $blank,
            'unresolved_items' => $unresolved,
            'missing_items' => $missing,
            'extra_items' => $extra,
            'per_area' => array_values($perArea),
            'items' => $itemResults,
            'extras' => $extraItems,
            'is_complete' => $missing === 0 && $unresolved === 0 && $extra === 0,
            'passed' => count($setItems) > 0
                && $exact === count($setItems)
                && $mismatches === 0
                && $blank === 0
                && $unresolved === 0
                && $missing === 0
                && $extra === 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $actual
     */
    private function canUseOrderedFallback(array $actual, int $expectedCount): bool
    {
        if (count($actual) !== $expectedCount) {
            return false;
        }
        foreach ($actual as $item) {
            if (trim((string) ($item['area_code'] ?? '')) !== '' || $item['question_no'] !== null) {
                return false;
            }
            if (preg_match('/^.+_Q_\d+$/i', (string) ($item['item_key'] ?? '')) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $actual
     * @param  list<bool>  $unused
     */
    private function findActualIndex(
        array $actual,
        array $unused,
        string $areaCode,
        int $questionNo,
        int $expectedIndex,
        bool $fallbackOrder,
    ): ?int {
        foreach ($actual as $index => $item) {
            if (! $unused[$index]) {
                continue;
            }
            $itemArea = strtoupper(trim((string) ($item['area_code'] ?? '')));
            $itemQuestion = $item['question_no'] !== null ? (int) $item['question_no'] : null;
            if ($itemArea === '' || $itemQuestion === null) {
                $key = (string) ($item['item_key'] ?? '');
                if (preg_match('/^(.+)_Q_(\d+)$/i', $key, $matches) === 1) {
                    $itemArea = strtoupper(trim($matches[1]));
                    $itemQuestion = (int) $matches[2];
                }
            }
            if ($itemArea === $areaCode && $itemQuestion === $questionNo) {
                return $index;
            }
        }

        if ($fallbackOrder && isset($unused[$expectedIndex]) && $unused[$expectedIndex]) {
            return $expectedIndex;
        }

        return null;
    }

    private function lockReceipt(User $actor, int $runId, string $idempotencyKey): ?object
    {
        return DB::table('omr_command_receipts')
            ->where('actor_id', (int) $actor->getKey())
            ->where('command_name', self::COMMAND_NAME)
            ->where('aggregate_type', self::AGGREGATE_TYPE)
            ->where('aggregate_id', (string) $runId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
    }

    private function assertReceiptFingerprint(object $receipt, string $payloadFingerprint): void
    {
        if (! hash_equals((string) $receipt->payload_fingerprint, $payloadFingerprint)) {
            throw new RuntimeException('omr_diagnostic_compare_idempotency_conflict');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resultFromReceipt(object $receipt, OmrDiagnosticRun $run): array
    {
        $result = json_decode((string) $receipt->safe_result_json, true);
        if (! is_array($result)
            || (int) ($result['run_id'] ?? 0) !== (int) $run->getKey()
            || ! is_array($result['comparison'] ?? null)) {
            throw new RuntimeException('omr_diagnostic_compare_receipt_invalid');
        }

        return $result;
    }
}
