<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\IndividualResultMutationFence;
use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\RawMetricFormulaEngine;
use App\Assessment\Support\TestKitEligibility;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AcceptAssessmentRawResults
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly TestKitEligibility $testKitEligibility,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly IndividualResultMutationFence $mutationFence,
        private readonly RawMetricFormulaEngine $formulaEngine = new RawMetricFormulaEngine,
    ) {}

    /**
     * Accept complete metric subsets incrementally into assessment_raw_result_revisions.
     *
     * @param  array<mixed>  $metricValues
     * @return array{
     *     scoring_run_id: int,
     *     scoring_run_uuid: string,
     *     accepted_metrics: list<string>,
     *     binding_state: string,
     *     revisions: list<AssessmentRawResultRevision>
     * }
     */
    public function handle(
        AssessmentAdministration $administration,
        array $metricValues,
        string $source,
        User $actor,
        string $idempotencyKey,
        ?int $operationAcceptanceReceiptId = null,
    ): array {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new RuntimeException('assessment_raw_results_idempotency_key_required');
        }

        if (! in_array($source, [
            AssessmentScoringRun::SOURCE_MANUAL,
            AssessmentScoringRun::SOURCE_OMR,
            AssessmentScoringRun::SOURCE_IMPORT,
        ], true)) {
            throw new RuntimeException('assessment_raw_results_invalid_source');
        }

        $academicCycleId = (int) DB::table('assessment_administrations')
            ->join('exam_assignments', 'exam_assignments.id', '=', 'assessment_administrations.exam_assignment_id')
            ->join('exam_sessions', 'exam_sessions.id', '=', 'exam_assignments.exam_session_id')
            ->where('assessment_administrations.id', $administration->id)
            ->value('exam_sessions.academic_cycle_id');
        if ($academicCycleId < 1) {
            throw new RuntimeException('assessment_raw_results_exact_sitting_required');
        }

        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            $academicCycleId,
            fn (): array => DB::transaction(function () use (
                $administration,
                $metricValues,
                $source,
                $actor,
                $idempotencyKey,
                $operationAcceptanceReceiptId,
            ): array {
                $adminReference = AssessmentAdministration::query()->findOrFail($administration->id);
                $lockedAssignment = ExamAssignment::query()
                    ->lockForUpdate()
                    ->findOrFail($adminReference->exam_assignment_id);
                /** @var AssessmentAdministration $lockedAdmin */
                $lockedAdmin = AssessmentAdministration::query()
                    ->lockForUpdate()
                    ->findOrFail($adminReference->id);
                $requiredCapability = match ($source) {
                    AssessmentScoringRun::SOURCE_MANUAL => CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
                    AssessmentScoringRun::SOURCE_OMR => CapabilityCatalog::GUIDANCE_SCORING_OMR,
                    AssessmentScoringRun::SOURCE_IMPORT => CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
                };

                $lockedAdmin->load([
                    'testInstrumentVersion.instrument',
                    'testInstrumentVersion.metrics.inputs.inputMetric',
                    'admissionAttempt',
                    'examAssignment.examSession.academicCycle',
                ]);
                $this->mutationFence->assertMutable($lockedAdmin);

                if ($lockedAdmin->superseded_at !== null
                    || (int) $lockedAdmin->exam_assignment_id !== (int) $lockedAssignment->id
                    || (int) $lockedAdmin->admission_attempt_id !== (int) $lockedAdmin->admissionAttempt?->id
                    || $lockedAdmin->admissionAttempt?->status !== AdmissionAttempt::STATUS_ACCEPTED
                    || ! $lockedAssignment->isActive()
                    || ! $lockedAdmin->examAssignment?->isActive()
                    || (int) $lockedAssignment->admission_attempt_id !== (int) $lockedAdmin->admission_attempt_id
                    || (int) $lockedAssignment->exam_session_id !== (int) $lockedAdmin->examAssignment?->exam_session_id
                    || AssessmentAdministration::query()
                        ->where('exam_assignment_id', $lockedAdmin->exam_assignment_id)
                        ->whereNull('superseded_at')
                        ->where('id', '!=', $lockedAdmin->id)
                        ->exists()) {
                    throw ValidationException::withMessages([
                        'administration' => 'Raw input requires the current, non-finalized administration for the exact active sitting.',
                    ]);
                }

                $lockedAdmin->loadMissing('examAssignment.examSession.academicCycle');
                $institutionId = (int) ($lockedAdmin->examAssignment?->examSession?->academicCycle?->institution_id ?? 0);
                if ($institutionId < 1
                    || $institutionId !== (int) $lockedAdmin->admissionAttempt?->institution_id
                    || (int) $lockedAdmin->examAssignment?->admission_attempt_id !== (int) $lockedAdmin->admission_attempt_id) {
                    throw ValidationException::withMessages([
                        'administration' => 'Raw input is outside the current sitting and institutional owner scope.',
                    ]);
                }
                $scope = ['institution_id' => $institutionId];

                if (! $this->authorizer->decideFresh(
                    $actor,
                    $requiredCapability,
                    $scope,
                )->allowed) {
                    throw new AuthorizationException('unauthorized_scoring_ingress');
                }

                $version = $lockedAdmin->testInstrumentVersion;
                if ($version === null || $lockedAdmin->test_kit_version_id === null
                    || $lockedAdmin->manifest_sha256 === null) {
                    throw ValidationException::withMessages([
                        'test_kit_version_id' => 'New raw input requires an exact selected Test Kit binding.',
                    ]);
                }

                if ($version->status !== TestInstrumentVersion::STATUS_APPROVED
                    || $version->content_sha256 === null
                    || $lockedAdmin->procedure_snapshot_json['content_sha256'] !== $version->content_sha256) {
                    throw ValidationException::withMessages([
                        'test_instrument_version_id' => 'Raw input requires the exact current approved Setup procedure.',
                    ]);
                }

                /** @var Collection<int, TestInstrumentMetric> $profileMetrics */
                $profileMetrics = $version->metrics;

                // 1. Lock existing current revisions for this administration
                /** @var Collection<int, AssessmentRawResultRevision> $currentRevisionsByMetricId */
                $currentRevisionsByMetricId = AssessmentRawResultRevision::query()
                    ->where('administration_id', $lockedAdmin->id)
                    ->where('is_current', true)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('metric_id');

                // 2. Normalize supplied metric values
                $normalizedEntries = $this->normalizeMetricEntries($metricValues, $profileMetrics);
                if ($normalizedEntries === []) {
                    throw ValidationException::withMessages([
                        'metrics' => 'At least one valid metric value must be provided.',
                    ]);
                }
                foreach ($normalizedEntries as $entry) {
                    $allowedSources = $entry['metric']->input_contract_json['allowed_sources'] ?? null;
                    if (! is_array($allowedSources) || ! in_array($source, $allowedSources, true)) {
                        throw ValidationException::withMessages([
                            $entry['metric']->code => "The approved Setup does not permit {$source} input for metric {$entry['metric']->code}.",
                        ]);
                    }
                }

                // 3. Idempotency Check
                $existingRun = AssessmentScoringRun::query()
                    ->where('administration_id', $lockedAdmin->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($source === AssessmentScoringRun::SOURCE_OMR && $operationAcceptanceReceiptId === null) {
                    throw ValidationException::withMessages([
                        'acceptance_receipt_id' => 'OMR acceptance requires the exact current acceptance receipt for this operation.',
                    ]);
                }

                if ($existingRun !== null) {
                    if ((int) $existingRun->actor_user_id !== (int) $actor->id
                        || $existingRun->source !== $source) {
                        throw new RuntimeException('assessment_raw_results_idempotency_conflict');
                    }
                    if ($source === AssessmentScoringRun::SOURCE_OMR
                        && (int) $existingRun->acceptance_receipt_id !== $operationAcceptanceReceiptId) {
                        throw ValidationException::withMessages([
                            'acceptance_receipt_id' => 'An OMR idempotent replay must use the receipt pinned to its original operation.',
                        ]);
                    }

                    return $this->handleIdempotentReplay($existingRun, $normalizedEntries, $lockedAdmin);
                }

                $operationReceipt = $this->assertMaterialApplicability(
                    $lockedAdmin,
                    $version,
                    $source,
                    $source === AssessmentScoringRun::SOURCE_OMR ? $operationAcceptanceReceiptId : null,
                );

                // 4. Validate supplied metric values against profile metrics
                $this->validateSuppliedMetrics(
                    $normalizedEntries,
                    $currentRevisionsByMetricId,
                    $source,
                );

                // 5. Create AssessmentScoringRun
                $scoringRun = AssessmentScoringRun::query()->create([
                    'administration_id' => $lockedAdmin->id,
                    'run_uuid' => (string) Str::uuid(),
                    'source' => $source,
                    'actor_user_id' => $actor->id,
                    'idempotency_key' => $idempotencyKey,
                    'test_kit_version_id' => $operationReceipt?->test_kit_version_id,
                    'test_kit_version_revision' => $operationReceipt?->getAttribute('pinned_test_kit_version_revision'),
                    'manifest_sha256' => $operationReceipt?->manifest_sha256,
                    'acceptance_receipt_id' => $operationReceipt?->getKey(),
                    'created_at' => Carbon::now(),
                ]);

                // 6. Commit new AssessmentRawResultRevision rows for provided metrics
                $createdRevisions = [];
                $now = Carbon::now();

                foreach ($normalizedEntries as $entry) {
                    $metric = $entry['metric'];
                    $decimalValue = $entry['decimal'];
                    $inputValue = $entry['input_value'];

                    $existingCurrent = $currentRevisionsByMetricId->get($metric->id);
                    if ($existingCurrent !== null) {
                        $existingCurrent->update([
                            'is_current' => false,
                            'superseded_at' => $now,
                        ]);
                        $nextRevNo = (int) $existingCurrent->revision_no + 1;
                    } else {
                        $maxRev = AssessmentRawResultRevision::query()
                            ->where('administration_id', $lockedAdmin->id)
                            ->where('metric_id', $metric->id)
                            ->max('revision_no');
                        $nextRevNo = $maxRev !== null ? ((int) $maxRev + 1) : 1;
                    }

                    $provenance = [
                        'source' => $source,
                        'actor_user_id' => $actor->id,
                        'actor_name' => $actor->name,
                        'input_value' => $inputValue,
                        'unit' => $metric->unit,
                        'precision_scale' => $metric->precision_scale,
                        'procedure_reference' => $metric->procedure_reference,
                        'timestamp' => $now->toIso8601String(),
                    ];
                    if ($metric->item_codes_json !== null) {
                        $provenance['item_scope'] = [
                            'test_instrument_version_id' => (int) $metric->test_instrument_version_id,
                            'content_sha256' => (string) ($lockedAdmin->testInstrumentVersion?->content_sha256 ?? ''),
                            'item_codes' => $metric->item_codes_json,
                        ];
                    }
                    if ($existingCurrent?->source === AssessmentRawResultRevision::SOURCE_OMR
                        && $entry['override_omr']) {
                        $provenance['overridden_from_source'] = AssessmentRawResultRevision::SOURCE_OMR;
                        $provenance['overridden_revision_id'] = $existingCurrent->id;
                        $provenance['override_reason'] = $entry['override_reason'] ?: 'Manual override on scoring page';
                    }

                    $revision = AssessmentRawResultRevision::query()->create([
                        'administration_id' => $lockedAdmin->id,
                        'scoring_run_id' => $scoringRun->id,
                        'metric_id' => $metric->id,
                        'metric_code' => $metric->code,
                        'revision_no' => $nextRevNo,
                        'raw_value' => (string) $decimalValue,
                        'source' => $source,
                        'status' => AssessmentRawResultRevision::STATUS_VALID,
                        'provenance_json' => $provenance,
                        'is_current' => true,
                        'superseded_at' => null,
                        'created_at' => $now,
                    ]);

                    $createdRevisions[] = $revision;
                    $currentRevisionsByMetricId->put($metric->id, $revision);
                }

                // 7. Derive any composite metrics (e.g. sum_raw_metrics)
                $derivedRevisions = $this->deriveCompositeMetrics(
                    $lockedAdmin,
                    $scoringRun,
                    $profileMetrics,
                    $currentRevisionsByMetricId,
                    $now,
                );

                foreach ($derivedRevisions as $derivedRev) {
                    $createdRevisions[] = $derivedRev;
                }

                // 8. Invalidate norm conversions tied to the prior raw revision.
                $lockedAdmin->conversionRevisions()
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                // 9. Evaluate overall raw completeness.
                $allRequiredMet = $this->evaluateRawCompleteness(
                    $lockedAdmin,
                    $profileMetrics,
                    $currentRevisionsByMetricId,
                );

                $targetState = $allRequiredMet
                    ? AssessmentAdministration::STATE_AWAITING_NORM_CONVERSION
                    : AssessmentAdministration::STATE_RAW_INCOMPLETE;

                $lockedAdmin->binding_state = $targetState;
                $lockedAdmin->save();

                $acceptedMetricCodes = array_map(
                    static fn (AssessmentRawResultRevision $r): string => $r->metric_code,
                    $createdRevisions,
                );

                // 9. Record audit event
                $this->recordAuditEvent->handle('assessment.raw_results_accepted', [
                    'assessment_administration_id' => $lockedAdmin->id,
                    'scoring_run_id' => $scoringRun->id,
                    'scoring_run_uuid' => $scoringRun->run_uuid,
                    'source' => $source,
                    'accepted_metric_codes' => $acceptedMetricCodes,
                    'binding_state' => $targetState,
                    'idempotency_key' => $idempotencyKey,
                ], (string) $actor->id);

                return [
                    'scoring_run_id' => $scoringRun->id,
                    'scoring_run_uuid' => $scoringRun->run_uuid,
                    'accepted_metrics' => $acceptedMetricCodes,
                    'binding_state' => $targetState,
                    'revisions' => $createdRevisions,
                ];
            }),
        );
    }

    /**
     * @param  array<mixed>  $metricValues
     * @param  Collection<int, TestInstrumentMetric>  $profileMetrics
     * @return list<array{metric: TestInstrumentMetric, decimal: BigDecimal, input_value: mixed, override_omr: bool, override_reason: ?string}>
     */
    private function normalizeMetricEntries(array $metricValues, Collection $profileMetrics): array
    {
        $byId = $profileMetrics->keyBy('id');
        $byCode = $profileMetrics->keyBy(static fn (TestInstrumentMetric $m): string => strtoupper($m->code));
        $byAreaId = $profileMetrics
            ->filter(static fn (TestInstrumentMetric $m): bool => $m->construct_aptitude_area_id !== null)
            ->keyBy('construct_aptitude_area_id');

        $resolved = [];
        $isAssociative = ! array_is_list($metricValues);

        if ($isAssociative) {
            foreach ($metricValues as $key => $val) {
                $metric = null;
                if (is_int($key)) {
                    $metric = $byId->get($key) ?? $byAreaId->get($key);
                } elseif (is_string($key)) {
                    $upperKey = strtoupper(trim($key));
                    $metric = $byCode->get($upperKey);
                    if ($metric === null && is_numeric($key)) {
                        $intKey = (int) $key;
                        $metric = $byId->get($intKey) ?? $byAreaId->get($intKey);
                    }
                }

                if ($metric === null) {
                    throw ValidationException::withMessages([
                        'metrics' => "Unrecognized metric identifier: {$key}.",
                    ]);
                }

                $entry = is_array($val) ? $val : [];
                $this->addNormalizedEntry(
                    $resolved,
                    $metric,
                    $entry === [] ? $val : ($entry['value'] ?? $entry['raw_value'] ?? $entry['score'] ?? null),
                    (bool) ($entry['override_omr'] ?? false),
                    isset($entry['override_reason']) ? trim((string) $entry['override_reason']) : null,
                );
            }
        } else {
            foreach ($metricValues as $index => $item) {
                if (! is_array($item)) {
                    throw ValidationException::withMessages([
                        "metrics.{$index}" => 'Metric entry must be an array or key-value pair.',
                    ]);
                }

                $metric = null;
                $code = $item['code'] ?? $item['metric_code'] ?? null;
                if (is_string($code)) {
                    $metric = $byCode->get(strtoupper(trim($code)));
                }

                if ($metric === null && isset($item['metric_id'])) {
                    $metric = $byId->get((int) $item['metric_id']);
                }

                if ($metric === null && isset($item['aptitude_area_id'])) {
                    $metric = $byAreaId->get((int) $item['aptitude_area_id']);
                }

                if ($metric === null) {
                    $identifier = $code ?? $item['metric_id'] ?? $item['aptitude_area_id'] ?? "index {$index}";
                    throw ValidationException::withMessages([
                        "metrics.{$index}" => "Unrecognized metric identifier: {$identifier}.",
                    ]);
                }

                $val = $item['value'] ?? $item['raw_value'] ?? $item['score'] ?? null;
                $this->addNormalizedEntry(
                    $resolved,
                    $metric,
                    $val,
                    (bool) ($item['override_omr'] ?? false),
                    isset($item['override_reason']) ? trim((string) $item['override_reason']) : null,
                );
            }
        }

        return array_values($resolved);
    }

    /**
     * @param  array<int, array{metric: TestInstrumentMetric, decimal: BigDecimal, input_value: mixed, override_omr: bool, override_reason: ?string}>  $resolved
     */
    private function addNormalizedEntry(
        array &$resolved,
        TestInstrumentMetric $metric,
        mixed $val,
        bool $overrideOmr = false,
        ?string $overrideReason = null,
    ): void {
        if ($val === null || $val === '') {
            throw ValidationException::withMessages([
                $metric->code => "Measurement value for {$metric->code} cannot be null or empty.",
            ]);
        }

        try {
            $decimal = BigDecimal::of((string) $val);
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                $metric->code => "Enter a valid numeric measurement for {$metric->code}.",
            ]);
        }

        if (isset($resolved[$metric->id])) {
            if ($resolved[$metric->id]['decimal']->compareTo($decimal) !== 0
                || $resolved[$metric->id]['override_omr'] !== $overrideOmr
                || $resolved[$metric->id]['override_reason'] !== $overrideReason) {
                throw ValidationException::withMessages([
                    $metric->code => "Conflicting values submitted for metric {$metric->code}.",
                ]);
            }

            return;
        }

        $resolved[$metric->id] = [
            'metric' => $metric,
            'decimal' => $decimal,
            'input_value' => $val,
            'override_omr' => $overrideOmr,
            'override_reason' => $overrideReason,
        ];
    }

    /**
     * @param  list<array{metric: TestInstrumentMetric, decimal: BigDecimal, input_value: mixed, override_omr: bool, override_reason: ?string}>  $normalizedEntries
     * @param  Collection<int, AssessmentRawResultRevision>  $currentRevisionsByMetricId
     */
    private function validateSuppliedMetrics(
        array $normalizedEntries,
        Collection $currentRevisionsByMetricId,
        string $source,
    ): void {
        foreach ($normalizedEntries as $entry) {
            $metric = $entry['metric'];
            $inputContract = $metric->input_contract_json;
            if (! is_array($inputContract)
                || ! in_array($source, (array) ($inputContract['allowed_sources'] ?? []), true)) {
                throw ValidationException::withMessages([
                    $metric->code => "The approved Setup does not permit {$source} input for metric {$metric->code}.",
                ]);
            }
            $decimal = $entry['decimal'];

            // Composite metrics are derived and cannot be manually submitted
            if (in_array($metric->rule_kind, [
                TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
            ], true)) {
                throw ValidationException::withMessages([
                    $metric->code => "Composite metric {$metric->code} is derived from its component metrics and cannot be submitted directly.",
                ]);
            }

            // Manual/import overwrite of reviewed OMR result requires an explicit reasoned override
            if ($source !== AssessmentRawResultRevision::SOURCE_OMR) {
                $currentRev = $currentRevisionsByMetricId->get($metric->id);
                if ($currentRev !== null
                    && $currentRev->source === AssessmentRawResultRevision::SOURCE_OMR
                    && ! $entry['override_omr']) {
                    throw new RuntimeException('assessment_raw_metric_omr_overwrite_forbidden');
                }
            }

            // Precision check
            $scale = $decimal->getScale();
            $declaredScale = $metric->precision_scale;

            if ($declaredScale !== null) {
                if ($declaredScale === 0 && $scale > 0) {
                    throw ValidationException::withMessages([
                        $metric->code => "Metric {$metric->code} requires an integer value.",
                    ]);
                }
                if ($scale > $declaredScale) {
                    throw ValidationException::withMessages([
                        $metric->code => "Value precision for {$metric->code} ({$scale} places) exceeds the declared precision of {$declaredScale} places.",
                    ]);
                }
            } elseif ($scale > 6) {
                throw ValidationException::withMessages([
                    $metric->code => "Value precision for {$metric->code} exceeds the maximum supported 6 decimal places.",
                ]);
            }

            // Bounds checks
            if ($metric->min_raw_value !== null) {
                $min = BigDecimal::of((string) $metric->min_raw_value);
                if ($decimal->compareTo($min) < 0) {
                    throw ValidationException::withMessages([
                        $metric->code => "Value for {$metric->code} ({$decimal}) is below minimum bound ({$metric->min_raw_value}).",
                    ]);
                }
            }

            if ($metric->max_raw_value !== null) {
                $max = BigDecimal::of((string) $metric->max_raw_value);
                if ($decimal->compareTo($max) > 0) {
                    throw ValidationException::withMessages([
                        $metric->code => "Value for {$metric->code} ({$decimal}) exceeds maximum bound ({$metric->max_raw_value}).",
                    ]);
                }
            }
        }
    }

    /**
     * @param  list<array{metric: TestInstrumentMetric, decimal: BigDecimal, input_value: mixed, override_omr: bool, override_reason: ?string}>  $normalizedEntries
     * @return array{
     *     scoring_run_id: int,
     *     scoring_run_uuid: string,
     *     accepted_metrics: list<string>,
     *     binding_state: string,
     *     revisions: list<AssessmentRawResultRevision>
     * }
     */
    private function handleIdempotentReplay(
        AssessmentScoringRun $existingRun,
        array $normalizedEntries,
        AssessmentAdministration $lockedAdmin,
    ): array {
        /** @var Collection<int, AssessmentRawResultRevision> $runRevisions */
        $runRevisions = AssessmentRawResultRevision::query()
            ->where('scoring_run_id', $existingRun->id)
            ->get();

        $directRevisions = $runRevisions
            ->where('source', '!=', AssessmentRawResultRevision::SOURCE_DERIVED_COMPOSITE)
            ->keyBy('metric_id');

        if (count($normalizedEntries) !== $directRevisions->count()) {
            throw new RuntimeException('assessment_raw_results_idempotency_conflict');
        }

        foreach ($normalizedEntries as $entry) {
            $existingRev = $directRevisions->get($entry['metric']->id);
            $provenance = $existingRev?->provenance_json;
            if (is_string($provenance)) {
                $provenance = json_decode($provenance, true);
            }
            $wasOverride = is_array($provenance) && isset($provenance['overridden_revision_id']);
            $sameInput = is_array($provenance)
                && json_encode($provenance['input_value'] ?? null, JSON_THROW_ON_ERROR) === json_encode($entry['input_value'], JSON_THROW_ON_ERROR);
            $expectedOverrideReason = $entry['override_reason'] ?: 'Manual override on scoring page';
            $sameOverride = $wasOverride === $entry['override_omr']
                && (! $wasOverride || ($provenance['override_reason'] ?? null) === $expectedOverrideReason);
            if ($existingRev === null
                || BigDecimal::of((string) $existingRev->raw_value)->compareTo($entry['decimal']) !== 0
                || $existingRev->source !== $existingRun->source
                || ! $sameInput
                || ! $sameOverride) {
                throw new RuntimeException('assessment_raw_results_idempotency_conflict');
            }
        }

        return [
            'scoring_run_id' => $existingRun->id,
            'scoring_run_uuid' => $existingRun->run_uuid,
            'accepted_metrics' => $runRevisions->pluck('metric_code')->values()->all(),
            'binding_state' => $lockedAdmin->binding_state,
            'revisions' => $runRevisions->all(),
        ];
    }

    /**
     * @param  Collection<int, TestInstrumentMetric>  $profileMetrics
     * @param  Collection<int, AssessmentRawResultRevision>  $currentRevisionsByMetricId
     * @return list<AssessmentRawResultRevision>
     */
    private function deriveCompositeMetrics(
        AssessmentAdministration $lockedAdmin,
        AssessmentScoringRun $scoringRun,
        Collection $profileMetrics,
        Collection $currentRevisionsByMetricId,
        Carbon $now,
    ): array {
        $derivedRevisions = [];
        $compositeMetrics = $profileMetrics
            ->filter(static fn (TestInstrumentMetric $metric): bool => in_array($metric->rule_kind, [
                TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
            ], true))
            ->values();

        $maxPasses = $compositeMetrics->count();

        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $anyDerivedInPass = false;

            foreach ($compositeMetrics as $compositeMetric) {
                $inputs = $compositeMetric->inputs;
                if ($inputs->count() < ($compositeMetric->rule_kind === TestInstrumentMetric::RULE_SUM_RAW_METRICS ? 2 : 1)) {
                    continue;
                }

                $allComponentsValid = true;
                $componentRevisionIds = [];
                $componentCodes = [];
                $componentValues = [];
                $sum = BigDecimal::zero();

                foreach ($inputs as $input) {
                    $inputMetricId = $input->input_metric_id;
                    $compRev = $currentRevisionsByMetricId->get($inputMetricId);

                    if ($compRev === null
                        || $compRev->status !== AssessmentRawResultRevision::STATUS_VALID
                        || ! $compRev->is_current
                    ) {
                        $allComponentsValid = false;
                        break;
                    }

                    $componentRevisionIds[] = $compRev->id;
                    $componentCodes[] = $compRev->metric_code;
                    $sum = $sum->plus((string) $compRev->raw_value);
                    $componentValues[$compRev->metric_code] = [
                        'metric_code' => $compRev->metric_code,
                        'revision_id' => (int) $compRev->id,
                        'raw_value' => (string) $compRev->raw_value,
                        'source' => (string) $compRev->source,
                    ];
                }

                $existingCompositeRev = $currentRevisionsByMetricId->get($compositeMetric->id);

                if (! $allComponentsValid) {
                    if ($existingCompositeRev !== null) {
                        $existingCompositeRev->update([
                            'is_current' => false,
                            'superseded_at' => $now,
                        ]);
                        $currentRevisionsByMetricId->forget($compositeMetric->id);
                    }

                    continue;
                }
                $derivedValue = (string) $sum;
                if ($compositeMetric->rule_kind === TestInstrumentMetric::RULE_FORMULA_RAW_METRICS) {
                    try {
                        $derivedValue = $this->formulaEngine->evaluate(
                            $compositeMetric->formula_ast_json,
                            array_map(static fn (array $component): string => $component['raw_value'], $componentValues),
                            (int) $compositeMetric->precision_scale,
                        );
                    } catch (\InvalidArgumentException $exception) {
                        throw new RuntimeException('assessment_raw_formula_evaluation_failed', previous: $exception);
                    }
                }

                if ($existingCompositeRev !== null
                    && BigDecimal::of((string) $existingCompositeRev->raw_value)->compareTo($derivedValue) === 0
                    && ($existingCompositeRev->provenance_json['component_revision_ids'] ?? null) === $componentRevisionIds
                    && ($compositeMetric->rule_kind !== TestInstrumentMetric::RULE_FORMULA_RAW_METRICS
                        || ($existingCompositeRev->provenance_json['formula_snapshot']['ast'] ?? null) === $compositeMetric->formula_ast_json)) {
                    continue;
                }

                if ($existingCompositeRev !== null) {
                    $existingCompositeRev->update([
                        'is_current' => false,
                        'superseded_at' => $now,
                    ]);
                    $nextRevNo = (int) $existingCompositeRev->revision_no + 1;
                } else {
                    $maxRev = AssessmentRawResultRevision::query()
                        ->where('administration_id', $lockedAdmin->id)
                        ->where('metric_id', $compositeMetric->id)
                        ->max('revision_no');
                    $nextRevNo = $maxRev !== null ? ((int) $maxRev + 1) : 1;
                }

                $derivedProvenance = [
                    'rule_kind' => $compositeMetric->rule_kind,
                    'source' => AssessmentRawResultRevision::SOURCE_DERIVED_COMPOSITE,
                    'component_revision_ids' => $componentRevisionIds,
                    'component_metric_codes' => $componentCodes,
                    'unit' => $compositeMetric->unit,
                    'precision_scale' => $compositeMetric->precision_scale,
                    'timestamp' => $now->toIso8601String(),
                ];
                if ($compositeMetric->rule_kind === TestInstrumentMetric::RULE_FORMULA_RAW_METRICS) {
                    $derivedProvenance['formula_snapshot'] = [
                        'expression' => (string) $compositeMetric->formula_expression,
                        'ast' => $compositeMetric->formula_ast_json,
                        'algorithm_identity' => TestInstrumentMetric::FORMULA_ALGORITHM_IDENTITY,
                        'precision_scale' => (int) $compositeMetric->precision_scale,
                        'min_raw_value' => (string) $compositeMetric->min_raw_value,
                        'max_raw_value' => (string) $compositeMetric->max_raw_value,
                        'components' => array_values($componentValues),
                    ];
                }
                $newCompositeRev = AssessmentRawResultRevision::query()->create([
                    'administration_id' => $lockedAdmin->id,
                    'scoring_run_id' => $scoringRun->id,
                    'metric_id' => $compositeMetric->id,
                    'metric_code' => $compositeMetric->code,
                    'revision_no' => $nextRevNo,
                    'raw_value' => $derivedValue,
                    'source' => AssessmentRawResultRevision::SOURCE_DERIVED_COMPOSITE,
                    'status' => AssessmentRawResultRevision::STATUS_VALID,
                    'provenance_json' => $derivedProvenance,
                    'is_current' => true,
                    'superseded_at' => null,
                    'created_at' => $now,
                ]);

                $currentRevisionsByMetricId->put($compositeMetric->id, $newCompositeRev);
                $derivedRevisions[] = $newCompositeRev;
                $anyDerivedInPass = true;
            }

            if (! $anyDerivedInPass) {
                break;
            }
        }

        return $derivedRevisions;
    }

    /**
     * @param  Collection<int, TestInstrumentMetric>  $profileMetrics
     * @param  Collection<int, AssessmentRawResultRevision>  $currentRevisionsByMetricId
     */
    private function evaluateRawCompleteness(
        AssessmentAdministration $lockedAdmin,
        Collection $profileMetrics,
        Collection $currentRevisionsByMetricId,
    ): bool {
        $requiredCodes = collect();

        if (! empty($lockedAdmin->required_metric_set) && is_array($lockedAdmin->required_metric_set)) {
            foreach ($lockedAdmin->required_metric_set as $item) {
                if (is_array($item) && isset($item['code'])) {
                    $requiredCodes->push((string) $item['code']);
                } elseif (is_string($item)) {
                    $requiredCodes->push($item);
                }
            }
        }

        if ($requiredCodes->isEmpty()) {
            $requiredCodes = $profileMetrics
                ->where('is_required', true)
                ->pluck('code');
        }

        if ($requiredCodes->isEmpty()) {
            return false;
        }

        $validCurrentMetricCodes = $currentRevisionsByMetricId
            ->filter(static fn (AssessmentRawResultRevision $r): bool => $r->is_current && $r->status === AssessmentRawResultRevision::STATUS_VALID)
            ->pluck('metric_code')
            ->all();

        foreach ($requiredCodes as $requiredCode) {
            if (! in_array($requiredCode, $validCurrentMetricCodes, true)) {
                return false;
            }
        }

        return true;
    }

    private function assertMaterialApplicability(
        AssessmentAdministration $administration,
        TestInstrumentVersion $profileVersion,
        string $source,
        ?int $operationAcceptanceReceiptId = null,
    ): ?TestKitAcceptanceReceipt {
        $procedure = $administration->procedure_snapshot_json;
        if (! is_array($procedure)
            || ($procedure['material_requirement'] ?? null) !== $profileVersion->material_requirement
            || ($procedure['administration_procedure_reference'] ?? null) !== $profileVersion->administration_procedure_reference
            || ($procedure['content_sha256'] ?? null) !== $profileVersion->content_sha256
            || ($procedure['kit_manifest_sha256'] ?? null) !== $administration->manifest_sha256) {
            throw new RuntimeException('assessment_administration_procedure_snapshot_missing_or_stale');
        }
        if ($administration->test_kit_version_id === null || $administration->manifest_sha256 === null) {
            throw ValidationException::withMessages([
                'test_kit_version_id' => 'Raw input requires the exact selected Test Kit version.',
            ]);
        }

        $kitVersion = TestKitVersion::query()
            ->with(['testKit', 'testInstrumentVersion'])
            ->lockForUpdate()
            ->findOrFail((int) $administration->test_kit_version_id);
        if ((int) $kitVersion->test_instrument_version_id !== (int) $profileVersion->id
            || ! hash_equals((string) $kitVersion->manifest_sha256, (string) $administration->manifest_sha256)
            || (int) ($procedure['manifest_schema_version'] ?? 0) !== (int) $kitVersion->manifest_schema_version) {
            throw new RuntimeException('assessment_administration_test_kit_pins_mismatch');
        }

        try {
            $resolved = app(KitCompositionContext::class)->resolveVersion($kitVersion);
        } catch (\Throwable $exception) {
            throw new RuntimeException('assessment_administration_test_kit_configuration_stale', previous: $exception);
        }
        $requirements = (array) ($resolved['requirements'] ?? []);
        $requiredResources = (array) ($requirements['required_resources'] ?? []);
        $paperApplicable = array_intersect($requiredResources, ['answer_sheet', 'booklet_form', 'physical_procedure']) !== [];
        if (! $paperApplicable) {
            if ($administration->material_snapshot_id !== null
                || $administration->exam_session_seat_id !== null
                || $administration->material_set_id !== null
                || $administration->booklet_set_id !== null
                || $profileVersion->material_requirement !== TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER) {
                throw new RuntimeException('setup_only_assessment_must_not_pin_inapplicable_paper_material');
            }
        } elseif ($profileVersion->material_requirement !== TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED) {
            throw new RuntimeException('assessment_material_applicability_unresolved');
        }

        $frozenReceipt = null;
        if ($administration->acceptance_receipt_id !== null) {
            $frozenReceipt = TestKitAcceptanceReceipt::query()
                ->where('test_kit_version_id', $kitVersion->id)
                ->lockForUpdate()
                ->find((int) $administration->acceptance_receipt_id);
            if ($frozenReceipt === null
                || ! hash_equals((string) $kitVersion->manifest_sha256, (string) $frozenReceipt->manifest_sha256)) {
                throw new RuntimeException('assessment_administration_test_kit_frozen_receipt_mismatch');
            }
        }

        $operationReceipt = null;
        if ($source === AssessmentScoringRun::SOURCE_OMR) {
            if (! ($requirements['omr_applicable'] ?? false)) {
                throw ValidationException::withMessages([
                    'source' => 'The exact Setup does not declare OMR as an applicable metric input source.',
                ]);
            }
            $administration->loadMissing([
                'materialSnapshot.examSession.academicCycle',
                'examSessionSeat',
                'examAssignment.examSession.academicCycle',
            ]);
            $snapshot = $administration->materialSnapshot;
            $seat = $administration->examSessionSeat;
            if ($snapshot === null || $seat === null
                || (int) $snapshot->exam_session_id !== (int) $administration->examAssignment?->exam_session_id
                || (int) $seat->material_snapshot_id !== (int) $snapshot->id
                || (int) ($snapshot->test_kit_version_id ?? 0) !== (int) $kitVersion->id
                || (int) ($snapshot->test_instrument_version_id ?? 0) !== (int) $profileVersion->id
                || ! hash_equals((string) $snapshot->manifest_sha256, (string) $kitVersion->manifest_sha256)
                || $snapshot->acceptance_receipt_id === null
                || ($administration->acceptance_receipt_id !== null
                    && (int) $snapshot->acceptance_receipt_id !== (int) $administration->acceptance_receipt_id)) {
                throw new RuntimeException('omr_assessment_requires_exact_current_material_and_seat_pins');
            }
            $snapshotReceipt = TestKitAcceptanceReceipt::query()
                ->where('test_kit_version_id', $kitVersion->id)
                ->lockForUpdate()
                ->find((int) $snapshot->acceptance_receipt_id);
            if ($snapshotReceipt === null
                || $snapshotReceipt->result !== TestKitAcceptanceReceipt::RESULT_PASSED
                || ! hash_equals((string) $snapshotReceipt->manifest_sha256, (string) $kitVersion->manifest_sha256)) {
                throw new RuntimeException('omr_assessment_snapshot_original_receipt_mismatch');
            }
            $operationReceipt = TestKitAcceptanceReceipt::query()
                ->where('test_kit_version_id', $kitVersion->id)
                ->lockForUpdate()
                ->find($operationAcceptanceReceiptId);
            if ($operationReceipt === null
                || (int) $kitVersion->selected_acceptance_receipt_id !== (int) $operationReceipt->getKey()) {
                throw ValidationException::withMessages([
                    'acceptance_receipt_id' => 'The selected operation receipt is not the current persisted receipt for this Kit.',
                ]);
            }
            $operationReceipt->setAttribute('pinned_test_kit_version_revision', $kitVersion->revision);
        }

        $cycle = $administration->examAssignment?->examSession?->academicCycle;
        $operation = match ($source) {
            AssessmentScoringRun::SOURCE_MANUAL => 'manual',
            AssessmentScoringRun::SOURCE_IMPORT => 'import',
            AssessmentScoringRun::SOURCE_OMR => 'omr',
        };
        $eligibility = $this->testKitEligibility->evaluate(
            $kitVersion,
            $cycle instanceof AcademicCycle ? $cycle : null,
            TestKitEligibility::PURPOSE_PINNED_USE,
            $frozenReceipt,
            $operationReceipt,
            $operation,
        );
        if (! $eligibility['eligible']) {
            throw new RuntimeException('assessment_test_kit_ineligible:'.implode(',', array_column($eligibility['blockers'], 'code')));
        }

        return $operationReceipt;
    }
}
