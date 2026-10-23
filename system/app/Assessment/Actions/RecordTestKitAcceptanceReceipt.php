<?php

declare(strict_types=1);

namespace App\Assessment\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Support\AssessmentRawEvaluator;
use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitAcceptancePlan;
use App\Assessment\Support\TestKitCurrentAcceptanceStatus;
use App\Assessment\Support\TestKitCurrentEvidence;
use App\Assessment\Support\TestKitEligibility;
use App\Assessment\Support\TestKitManifestBuilder;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitAcceptancePlanRecord;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Appends software acceptance proof for one completed diagnostic run. */
final class RecordTestKitAcceptanceReceipt
{
    public const PROTOCOL_SCHEMA_VERSION = 'nexiam.test-kit.acceptance-protocol.v1';

    public const PLAN_SCHEMA_VERSION = 'nexiam.test-kit.acceptance-plan.v1';

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitManifestBuilder $manifestBuilder,
        private readonly AssessmentRawEvaluator $rawEvaluator,
        private readonly RecordAuditEvent $audit,
        private readonly KitCompositionContext $compositionContext,
        private readonly TestKitCurrentAcceptanceStatus $acceptanceStatus,
        private readonly ?TestKitAcceptancePlan $planService = null,
        private readonly ?TestKitCurrentEvidence $currentEvidence = null,
        private readonly ?CommandIdempotency $idempotency = null,
        private readonly ?TestKitEligibility $eligibility = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        User $actor,
        TestKitVersion $version,
        OmrDiagnosticRun $run,
        int $expectedRunRevision,
        string $protocolSchemaVersion = self::PROTOCOL_SCHEMA_VERSION,
        ?string $protocolSha256 = null,
        string $planSchemaVersion = self::PLAN_SCHEMA_VERSION,
        ?string $planSha256 = null,
        ?string $idempotencyKey = null,
    ): array {
        if ($run->diagnostic_purpose === 'mock_rehearsal') {
            throw new RuntimeException('test_kit_acceptance_mock_rehearsal_forbidden');
        }
        $version->loadMissing('testKit');
        $scope = [
            'office' => 'guidance',
            'institution_id' => (int) $version->testKit->institution_id,
        ];
        $decision = $this->authorizer->decideFresh(
            $actor,
            CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
            $scope,
        );
        if (! $decision->allowed) {
            throw new AuthorizationException('Test Kit acceptance is not authorized.');
        }

        return DB::transaction(function () use (
            $actor,
            $version,
            $run,
            $expectedRunRevision,
            $protocolSchemaVersion,
            $protocolSha256,
            $planSchemaVersion,
            $planSha256,
            $idempotencyKey,
            $scope,
        ): array {
            $family = TestKit::query()->lockForUpdate()->findOrFail((int) $version->test_kit_id);
            if ((int) $family->institution_id !== $scope['institution_id']) {
                throw new RuntimeException('test_kit_acceptance_scope_mismatch');
            }
            $decision = $this->authorizer->decideFresh(
                $actor,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                $scope,
            );
            if (! $decision->allowed) {
                throw new AuthorizationException('Test Kit acceptance is not authorized.');
            }
            $lockedVersion = TestKitVersion::query()->lockForUpdate()->findOrFail((int) $version->getKey());
            $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail((int) $run->getKey());
            if ($lockedRun->diagnostic_purpose === 'mock_rehearsal') {
                throw new RuntimeException('test_kit_acceptance_mock_rehearsal_forbidden');
            }
            if ($lockedRun->execution_kind !== OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC
                || $lockedRun->diagnostic_purpose !== OmrDiagnosticRun::PURPOSE_KIT_ACCEPTANCE
                || $lockedRun->status !== OmrDiagnosticRun::STATUS_COMPLETED) {
                throw new RuntimeException('test_kit_acceptance_requires_completed_diagnostic');
            }
            if ((int) $lockedRun->revision !== $expectedRunRevision) {
                throw new RuntimeException('test_kit_acceptance_run_revision_stale');
            }
            if ((int) $lockedRun->test_kit_id !== (int) $lockedVersion->test_kit_id
                || (int) $lockedRun->test_kit_version_id !== (int) $lockedVersion->getKey()
                || (int) $lockedRun->test_instrument_version_id !== (int) $lockedVersion->test_instrument_version_id
                || (int) $lockedRun->booklet_set_id !== (int) $lockedVersion->booklet_set_id
                || ! hash_equals((string) $lockedRun->manifest_sha256, (string) $lockedVersion->manifest_sha256)
                || (int) $lockedRun->institution_id !== $scope['institution_id']
                || (int) $lockedRun->template_version_id !== (int) $lockedVersion->template_version_id
                || (int) $lockedRun->booklet_version_id !== (int) $lockedVersion->booklet_version_id
                || $lockedRun->declared_set_code !== 'A') {
                throw new RuntimeException('test_kit_acceptance_run_material_mismatch');
            }

            $idempotencyKey = $idempotencyKey !== null ? trim($idempotencyKey) : null;
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $idempotencyService = $this->idempotency ?? app(CommandIdempotency::class);
                $idempotencyService->claim(
                    $actor,
                    'test_kit.record_acceptance_receipt',
                    (string) $lockedVersion->getKey(),
                    [
                        'test_kit_version_id' => (int) $lockedVersion->getKey(),
                        'diagnostic_run_id' => (int) $lockedRun->getKey(),
                        'expected_run_revision' => $expectedRunRevision,
                    ],
                    $idempotencyKey,
                );
            }
            $manifestValid = true;
            try {
                $this->manifestBuilder->verify($lockedVersion);
            } catch (RuntimeException) {
                $manifestValid = false;
            }
            $pinnedPlanId = (int) ($lockedRun->acceptance_plan_id ?? 0);
            if ($pinnedPlanId < 1) {
                throw new RuntimeException('test_kit_acceptance_pinned_plan_missing');
            }
            $planRecord = TestKitAcceptancePlanRecord::query()
                ->whereKey($pinnedPlanId)
                ->where('test_kit_version_id', (int) $lockedVersion->getKey())
                ->first();
            if ($planRecord === null) {
                throw new RuntimeException('test_kit_acceptance_pinned_plan_missing');
            }

            $pinnedProtocolSchema = (string) $lockedRun->acceptance_protocol_schema_version;
            $pinnedProtocolSha = (string) $lockedRun->acceptance_protocol_sha256;
            $pinnedPlanSchema = (string) $lockedRun->acceptance_plan_schema_version;
            $pinnedPlanSha = (string) $lockedRun->acceptance_plan_sha256;

            if (! hash_equals((string) $planRecord->manifest_sha256, (string) $lockedRun->manifest_sha256)
                || $planRecord->protocol_schema_version !== $pinnedProtocolSchema
                || ! hash_equals((string) $planRecord->protocol_sha256, $pinnedProtocolSha)
                || $planRecord->plan_schema_version !== $pinnedPlanSchema
                || ! hash_equals((string) $planRecord->plan_sha256, $pinnedPlanSha)) {
                throw new RuntimeException('test_kit_acceptance_pinned_plan_mismatch');
            }

            $planService = $this->planService ?? new TestKitAcceptancePlan;
            $canonicalProtocol = $planService->protocolIdentity();
            $canonicalProtocolSchema = (string) $canonicalProtocol['schema_version'];
            $canonicalProtocolDigest = (string) $canonicalProtocol['sha256'];

            $pinnedProtocolCurrent = $pinnedProtocolSchema === $canonicalProtocolSchema
                && hash_equals($canonicalProtocolDigest, $pinnedProtocolSha);

            $suppliedProtocolMatchesPinned = ($protocolSchemaVersion === $pinnedProtocolSchema || $protocolSchemaVersion === self::PROTOCOL_SCHEMA_VERSION)
                && ($protocolSha256 === null || hash_equals($pinnedProtocolSha, $protocolSha256));

            $protocolCurrent = $pinnedProtocolCurrent && $suppliedProtocolMatchesPinned;

            $suppliedPlanMatchesPinned = ($planSchemaVersion === $pinnedPlanSchema || $planSchemaVersion === self::PLAN_SCHEMA_VERSION)
                && ($planSha256 === null || hash_equals($pinnedPlanSha, $planSha256));

            $planCurrent = $suppliedPlanMatchesPinned;
            $evidenceService = $this->currentEvidence ?? app(TestKitCurrentEvidence::class);
            $selectedEvidence = $evidenceService->forRun($lockedRun, true);
            $captures = $selectedEvidence['captures'];
            $revisionVector = $selectedEvidence['evidence_vector'];
            $revisionVectorHash = (string) $selectedEvidence['evidence_vector_sha256'];
            $evidenceErrors = (array) ($selectedEvidence['errors'] ?? []);
            $evidenceComplete = ($selectedEvidence['complete'] ?? false) && $evidenceErrors === [];

            $sealedHash = (string) ($lockedRun->sealed_evidence_vector_sha256 ?? '');
            $sealedRevision = (int) ($lockedRun->sealed_evidence_revision ?? 0);
            $sealValid = $lockedRun->sealed_at !== null
                && $sealedRevision === (int) $lockedRun->revision
                && $sealedRevision === $expectedRunRevision
                && hash_equals($sealedHash, $revisionVectorHash);

            $capturesById = [];
            $allCapturesReviewed = $captures->isNotEmpty();
            foreach ($captures as $capture) {
                $capturesById[(int) $capture->getKey()] = $capture;
                $projection = is_array($capture->projected_score_json) ? $capture->projected_score_json : [];
                $effective = is_array($capture->effective_answers_json) ? $capture->effective_answers_json : [];
                if (! in_array($capture->status, [
                    OmrDiagnosticCapture::STATUS_REVIEWED,
                    OmrDiagnosticCapture::STATUS_COMPLETED,
                ], true)
                    || ($effective['is_complete'] ?? false) !== true) {
                    $allCapturesReviewed = false;
                }
            }

            $profile = $lockedVersion->testInstrumentVersion()->firstOrFail();
            $template = $lockedVersion->templateVersion()->firstOrFail();
            $booklet = $lockedVersion->bookletVersion()->firstOrFail();
            $set = $lockedVersion->bookletSet()->firstOrFail();
            $workerCommit = (string) ($lockedRun->worker_commit ?? '');
            $workerIdentityPresent = trim($workerCommit) !== '';
            if (! $workerIdentityPresent) {
                $workerCommit = 'unreported';
            }

            $contract = null;
            try {
                $itemMap = $this->compositionContext->resolveVersion($lockedVersion)['item_map'];
                $contract = array_intersect_key($itemMap, array_flip([
                    'profile_version_id',
                    'content_sha256',
                    'items',
                    'bindings',
                    'metrics',
                    'geometry',
                ]));
            } catch (RuntimeException) {
                $contract = null;
            }

            $observationsByCase = [];
            foreach ((array) ($revisionVector['entries'] ?? []) as $entry) {
                $caseId = (string) ($entry['case_id'] ?? '');
                $pageNo = (int) ($entry['page_no'] ?? 0);
                $cId = (int) ($entry['capture_id'] ?? 0);
                $cap = $capturesById[$cId] ?? null;
                $effective = $cap !== null && is_array($cap->effective_answers_json) ? $cap->effective_answers_json : [];
                $effItems = [];
                foreach ((array) ($effective['items'] ?? []) as $it) {
                    if (is_array($it) && isset($it['item_key'])) {
                        $effItems[(string) $it['item_key']] = $it;
                    }
                }
                foreach ((array) ($entry['items'] ?? []) as $item) {
                    $itemKey = (string) ($item['item_key'] ?? '');
                    $identity = is_array($item['item_identity'] ?? null) ? $item['item_identity'] : [];
                    $nativeLabel = (string) ($identity['native_label'] ?? $itemKey);
                    $identKey = $pageNo.':'.$nativeLabel;
                    $effItem = $effItems[$itemKey] ?? null;
                    $state = is_array($effItem) ? (string) ($effItem['effective_state'] ?? 'unresolved') : 'unresolved';
                    $token = $state === 'blank' ? null : ($effItem['effective_option'] ?? null);
                    $observationsByCase[$caseId][$identKey] = [
                        'page_no' => $pageNo,
                        'native_label' => $nativeLabel,
                        'token' => $token,
                        'state' => $state,
                    ];
                }
            }

            $plan = is_array($lockedRun->acceptance_plan_json) ? $lockedRun->acceptance_plan_json : (is_array($planRecord->plan_payload) ? $planRecord->plan_payload : []);
            $cases = (array) ($plan['cases'] ?? []);

            $allCasesPresent = count($cases) > 0 && array_diff(array_column($cases, 'case_id'), array_keys($observationsByCase)) === [];
            $allCasesResponseTruthMatch = $allCasesPresent && $contract !== null;
            $allCasesRawScoreMatch = $allCasesPresent && $contract !== null;
            $caseResults = [];

            if ($contract !== null && $allCasesPresent) {
                foreach ($cases as $case) {
                    $caseId = (string) ($case['case_id'] ?? '');
                    $caseObs = $observationsByCase[$caseId] ?? [];
                    $expectedPages = (array) ($case['pages'] ?? []);
                    $expectedMetrics = (array) ($case['expected_metrics'] ?? []);
                    $recordedMeasures = (array) ($case['recorded_measures'] ?? []);

                    $caseExpectedResponses = [];
                    foreach ($expectedPages as $page) {
                        $pNo = (int) ($page['page_no'] ?? 0);
                        foreach ((array) ($page['responses'] ?? []) as $resp) {
                            $label = (string) ($resp['native_label'] ?? '');
                            $token = array_key_exists('token', $resp) ? $resp['token'] : null;
                            $caseExpectedResponses[$pNo.':'.$label] = $token;
                        }
                    }

                    $caseResponseMatch = true;
                    if (count($caseObs) !== count($caseExpectedResponses)) {
                        $caseResponseMatch = false;
                    } else {
                        foreach ($caseExpectedResponses as $idKey => $expectedToken) {
                            if (! array_key_exists($idKey, $caseObs) || $caseObs[$idKey]['token'] !== $expectedToken) {
                                $caseResponseMatch = false;
                                break;
                            }
                        }
                    }
                    if (! $caseResponseMatch) {
                        $allCasesResponseTruthMatch = false;
                    }

                    $observationsForCase = [];
                    foreach ($contract['bindings'] as $binding) {
                        $idKey = (int) $binding['page_no'].':'.(string) $binding['native_label'];
                        $observed = $caseObs[$idKey] ?? null;
                        $observationsForCase[] = [
                            'page_no' => (int) $binding['page_no'],
                            'native_label' => (string) $binding['native_label'],
                            'token' => $observed['token'] ?? null,
                        ];
                    }

                    $caseRawMatch = false;
                    try {
                        $evaluated = $this->rawEvaluator->evaluate($contract, $observationsForCase, $recordedMeasures);
                        $actualRaw = array_map(
                            static fn (array $metric): array => ['metric_id' => (int) $metric['metric_id'], 'value' => (string) $metric['value']],
                            $evaluated['metric_values'],
                        );
                        $expectedRaw = array_map(
                            static fn (array $metric): array => ['metric_id' => (int) $metric['metric_id'], 'value' => (string) $metric['value']],
                            $expectedMetrics,
                        );
                        usort($actualRaw, static fn (array $left, array $right): int => $left['metric_id'] <=> $right['metric_id']);
                        usort($expectedRaw, static fn (array $left, array $right): int => $left['metric_id'] <=> $right['metric_id']);
                        $caseRawMatch = $actualRaw === $expectedRaw && $expectedRaw !== [];
                    } catch (\Throwable) {
                        $caseRawMatch = false;
                    }
                    if (! $caseRawMatch) {
                        $allCasesRawScoreMatch = false;
                    }

                    $caseResults[$caseId] = [
                        'response_truth_match' => $caseResponseMatch,
                        'raw_score_match' => $caseRawMatch,
                        'passed' => $caseResponseMatch && $caseRawMatch,
                    ];
                }
            } else {
                $allCasesResponseTruthMatch = false;
                $allCasesRawScoreMatch = false;
            }

            $checks = [
                ['code' => 'manifest_hash_current', 'passed' => $manifestValid],
                ['code' => 'run_revision_current', 'passed' => (int) $lockedRun->revision === $expectedRunRevision],
                ['code' => 'protocol_schema_current', 'passed' => $protocolCurrent],
                ['code' => 'plan_schema_current', 'passed' => $planCurrent],
                ['code' => 'diagnostic_evidence_current', 'passed' => $evidenceComplete && $allCapturesReviewed],
                ['code' => 'worker_identity_present', 'passed' => $workerIdentityPresent],
                ['code' => 'exact_item_map_supported', 'passed' => $contract !== null],
                ['code' => 'all_plan_cases_present', 'passed' => $allCasesPresent],
                ['code' => 'independent_response_truth_match', 'passed' => $allCasesResponseTruthMatch],
                ['code' => 'independent_raw_score_match', 'passed' => $allCasesRawScoreMatch],
                ['code' => 'sealed_evidence_current', 'passed' => $sealValid],
            ];

            $kernelCurrent = ($plan['scoring_kernel_identity'] ?? null) === AssessmentRawEvaluator::KERNEL_VERSION
                && $lockedRun->scoring_kernel_identity === AssessmentRawEvaluator::KERNEL_VERSION;
            $canonicalPlanCurrent = false;
            try {
                $currentPlan = $planService->resolveForStart($actor, $lockedVersion, $pinnedPlanId);
                $canonicalPlanCurrent = $currentPlan['acceptance_plan_json'] === $plan
                    && hash_equals((string) $currentPlan['acceptance_plan_sha256'], $pinnedPlanSha);
            } catch (RuntimeException) {
                $canonicalPlanCurrent = false;
            }
            $passed = $kernelCurrent
                && $canonicalPlanCurrent
                && $this->acceptanceStatus->completeCheckReport([
                    'checks' => $checks,
                    'case_results' => $caseResults,
                ], $plan) && $this->acceptanceStatus->workerIdentityMatchesRun($lockedRun, $plan);
            $callerEvidenceFingerprint = hash('sha256', json_encode([
                'test_kit_version_id' => (int) $lockedVersion->getKey(),
                'diagnostic_run_id' => (int) $lockedRun->getKey(),
                'expected_run_revision' => $expectedRunRevision,
                'protocol_schema_version' => $protocolSchemaVersion,
                'protocol_sha256' => $protocolSha256,
                'plan_schema_version' => $planSchemaVersion,
                'plan_sha256' => $planSha256,
                'evidence_revision_vector_sha256' => $revisionVectorHash,
                'idempotency_key' => $idempotencyKey,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            $checkReport = [
                'checks' => $checks,
                'case_results' => $caseResults,
                'caller_evidence_fingerprint' => $callerEvidenceFingerprint,
                'supplied_evidence' => [
                    'protocol_schema_version' => $protocolSchemaVersion,
                    'protocol_sha256' => $protocolSha256,
                    'plan_schema_version' => $planSchemaVersion,
                    'plan_sha256' => $planSha256,
                    'idempotency_key' => $idempotencyKey,
                ],
                'pinned_identity' => [
                    'acceptance_plan_id' => $pinnedPlanId,
                    'acceptance_protocol_schema_version' => $pinnedProtocolSchema,
                    'acceptance_protocol_sha256' => $pinnedProtocolSha,
                    'acceptance_plan_schema_version' => $pinnedPlanSchema,
                    'acceptance_plan_sha256' => $pinnedPlanSha,
                ],
            ];
            $existingReceipt = TestKitAcceptanceReceipt::query()
                ->where('diagnostic_run_id', (int) $lockedRun->getKey())
                ->where('run_revision', (int) $lockedRun->revision)
                ->where('acceptance_protocol_schema_version', $pinnedProtocolSchema)
                ->where('acceptance_protocol_sha256', $pinnedProtocolSha)
                ->where('acceptance_plan_schema_version', $pinnedPlanSchema)
                ->where('acceptance_plan_sha256', $pinnedPlanSha)
                ->where('manifest_sha256', (string) $lockedRun->manifest_sha256)
                ->lockForUpdate()
                ->first();

            if ($existingReceipt !== null) {
                $existingReport = is_array($existingReceipt->check_report_json) ? $existingReceipt->check_report_json : [];
                $existingFingerprint = (string) ($existingReport['caller_evidence_fingerprint'] ?? '');
                $historicalWorkerIdentity = $lockedRun->expected_worker_identity_json;
                $plannedWorkerIdentity = $plan['expected_worker_identity'] ?? null;
                if (is_array($historicalWorkerIdentity) && is_array($plannedWorkerIdentity)) {
                    ksort($historicalWorkerIdentity, SORT_STRING);
                    ksort($plannedWorkerIdentity, SORT_STRING);
                }
                $historicalWorkerIdentityMatches = is_array($historicalWorkerIdentity)
                    && is_array($plannedWorkerIdentity)
                    && $historicalWorkerIdentity !== []
                    && $historicalWorkerIdentity === $plannedWorkerIdentity
                    && $workerCommit !== ''
                    && hash_equals((string) ($plannedWorkerIdentity['engine_commit'] ?? ''), $workerCommit);
                $historicalKernel = (string) ($plan['scoring_kernel_identity'] ?? '');
                $historicalKernelMatches = $historicalKernel !== ''
                    && (string) $lockedRun->scoring_kernel_identity === $historicalKernel
                    && (string) $existingReceipt->scoring_kernel_identity === $historicalKernel;
                $pinnedIdentity = is_array($existingReport['pinned_identity'] ?? null) ? $existingReport['pinned_identity'] : [];
                $isReplay = (int) $existingReceipt->test_kit_version_id === (int) $lockedVersion->getKey()
                    && (int) $existingReceipt->acceptance_plan_id === $pinnedPlanId
                    && hash_equals((string) $existingReceipt->evidence_revision_vector_sha256, $revisionVectorHash)
                    && hash_equals($existingFingerprint, $callerEvidenceFingerprint)
                    && $planRecord->plan_payload === $plan
                    && $historicalWorkerIdentityMatches
                    && $historicalKernelMatches
                    && (string) $existingReceipt->worker_commit === $workerCommit
                    && ($existingReceipt->result === TestKitAcceptanceReceipt::RESULT_PASSED
                        || $existingReceipt->result === TestKitAcceptanceReceipt::RESULT_FAILED)
                    && (int) ($pinnedIdentity['acceptance_plan_id'] ?? 0) === $pinnedPlanId
                    && ($pinnedIdentity['acceptance_protocol_schema_version'] ?? null) === $pinnedProtocolSchema
                    && hash_equals((string) ($pinnedIdentity['acceptance_protocol_sha256'] ?? ''), $pinnedProtocolSha)
                    && ($pinnedIdentity['acceptance_plan_schema_version'] ?? null) === $pinnedPlanSchema
                    && hash_equals((string) ($pinnedIdentity['acceptance_plan_sha256'] ?? ''), $pinnedPlanSha);

                if ($isReplay) {
                    return $existingReceipt->toArray();
                }

                throw new RuntimeException('test_kit_acceptance_receipt_conflict');
            }

            if (! ($this->eligibility ?? app(TestKitEligibility::class))->allowsReacceptance($lockedVersion)) {
                throw new RuntimeException('test_kit_acceptance_version_not_eligible_for_reacceptance');
            }

            $receipt = TestKitAcceptanceReceipt::query()->create([
                'public_id' => (string) Str::uuid(),
                'test_kit_version_id' => (int) $lockedRun->test_kit_version_id,
                'diagnostic_run_id' => (int) $lockedRun->getKey(),
                'acceptance_plan_id' => $pinnedPlanId,
                'template_version_id' => (int) $lockedRun->template_version_id,
                'booklet_version_id' => (int) $lockedRun->booklet_version_id,
                'booklet_set_code' => (string) $lockedRun->declared_set_code,
                'run_revision' => (int) $lockedRun->revision,
                'evidence_revision_vector_json' => $revisionVector,
                'evidence_revision_vector_sha256' => $revisionVectorHash,
                'acceptance_protocol_schema_version' => $pinnedProtocolSchema,
                'acceptance_protocol_sha256' => $pinnedProtocolSha,
                'acceptance_plan_schema_version' => $pinnedPlanSchema,
                'acceptance_plan_sha256' => $pinnedPlanSha,
                'scoring_kernel_identity' => AssessmentRawEvaluator::KERNEL_VERSION,
                'worker_commit' => $workerCommit,
                'manifest_sha256' => (string) $lockedRun->manifest_sha256,
                'result' => $passed ? TestKitAcceptanceReceipt::RESULT_PASSED : TestKitAcceptanceReceipt::RESULT_FAILED,
                'check_report_json' => $checkReport,
                'confidential_evidence_references' => [
                    'capture_ids' => $captures->modelKeys(),
                    'revision_vector_sha256' => $revisionVectorHash,
                ],
                'created_by_user_id' => (int) $actor->getKey(),
                'created_at' => now(),
            ]);

            $this->audit->handle('test_kit.acceptance_receipt_recorded', [
                'test_kit_version_id' => (int) $lockedVersion->getKey(),
                'acceptance_receipt_id' => (int) $receipt->getKey(),
                'diagnostic_run_id' => (int) $lockedRun->getKey(),
                'result' => (string) $receipt->result,
                'manifest_sha256' => (string) $lockedVersion->manifest_sha256,
                'run_revision' => (int) $lockedRun->revision,
            ], (string) $actor->getKey());

            return $receipt->toArray();
        });
    }
}
