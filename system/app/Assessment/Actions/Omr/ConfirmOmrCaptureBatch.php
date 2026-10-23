<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Omr\Queries\OmrRunStatusSnapshot;
use App\Assessment\Omr\Review\GradingOmrEffectiveAnswerProjector;
use App\Assessment\Support\AssessmentRawEvaluator;
use App\Assessment\Support\GradingRoster;
use App\Assessment\Support\KitCompositionContext;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use RuntimeException;

/**
 * Commit reviewed OMR observations through the frozen Test Kit and shared
 * production raw evaluator. Replaying a confirmed batch is a no-op.
 */
final class ConfirmOmrCaptureBatch
{
    private readonly AssessmentRawEvaluator $rawEvaluator;

    private readonly KitCompositionContext $compositionContext;

    private readonly GradingOmrEffectiveAnswerProjector $projector;

    private readonly AssignmentPlanningMutex $planningMutex;

    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AcceptAssessmentRawResults $acceptRawResults,
        ?GradingOmrEffectiveAnswerProjector $projector = null,
        ?AssessmentRawEvaluator $rawEvaluator = null,
        ?KitCompositionContext $compositionContext = null,
        ?AssignmentPlanningMutex $planningMutex = null,
    ) {
        $this->projector = $projector ?? app(GradingOmrEffectiveAnswerProjector::class);
        $this->rawEvaluator = $rawEvaluator ?? new AssessmentRawEvaluator;
        $this->compositionContext = $compositionContext ?? app(KitCompositionContext::class);
        $this->planningMutex = $planningMutex ?? app(AssignmentPlanningMutex::class);
    }

    public function handle(OmrCaptureBatch $batch, User $actor, ?int $expectedRevision = null): OmrCaptureBatch
    {
        $gradingSessionId = OmrCaptureBatch::query()->whereKey($batch->id)->value('grading_session_id');
        $cycleId = (int) GradingSession::query()->findOrFail($gradingSessionId)
            ->examSession()->value('academic_cycle_id');

        return $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_CONSULTATION, $cycleId, function () use ($batch, $actor, $expectedRevision): OmrCaptureBatch {
            $batch = OmrCaptureBatch::query()->findOrFail($batch->id);
            $gradingSession = GradingSession::query()->lockForUpdate()->findOrFail($batch->grading_session_id);
            $batch = OmrCaptureBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($batch->status === OmrCaptureBatch::STATUS_CONFIRMED) {
                return $batch;
            }

            if ($batch->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('Only an open OMR batch can be confirmed.');
            }

            if ($gradingSession->status === GradingSession::STATUS_FINALIZED) {
                throw new RuntimeException('Grading session is finalized; OMR confirm is closed.');
            }

            if (OmrPendingCaptureSet::query()
                ->where('production_batch_id', $batch->id)
                ->whereNull('adopted_sheet_id')
                ->whereIn('status', [
                    OmrPendingCaptureSet::STATUS_PENDING,
                    OmrPendingCaptureSet::STATUS_CAPTURED,
                    OmrPendingCaptureSet::STATUS_READY,
                ])
                ->where('expires_at', '>', now())
                ->exists()) {
                throw new RuntimeException('Complete or expire pending OMR page sets before confirming the batch.');
            }

            $sheets = $batch->sheets()->orderBy('id')->lockForUpdate()->get();
            if ($expectedRevision !== null) {
                $revision = app(OmrRunStatusSnapshot::class)
                    ->forBatch($batch->load(['sheets.pages', 'sheets.issues', 'sheets.processingRuns']))['revision'];
                if ($revision !== $expectedRevision) {
                    throw new RuntimeException('The OMR run changed after validation. Review the current status before confirming.');
                }
            }

            if ($sheets->isEmpty()) {
                throw new RuntimeException('Nothing to confirm.');
            }

            $boundAttempts = [];
            foreach ($sheets as $sheet) {
                if ($sheet->admission_attempt_id === null) {
                    continue;
                }
                if (isset($boundAttempts[(int) $sheet->admission_attempt_id])) {
                    throw new RuntimeException('Two sheets are bound to the same examinee; resolve the duplicate before confirming.');
                }
                $boundAttempts[(int) $sheet->admission_attempt_id] = true;
            }

            $batch->update([
                'status' => OmrCaptureBatch::STATUS_CLOSING,
                'version' => (int) $batch->version + 1,
                'closing_by_user_id' => $actor->id,
                'closing_at' => now(),
            ]);

            $unreviewed = [];

            foreach ($sheets as $sheet) {
                /** @var OmrCaptureSheet $sheet */
                if ($sheet->status === OmrCaptureSheet::STATUS_COMMITTED) {
                    continue;
                }

                if ($sheet->status !== OmrCaptureSheet::STATUS_REVIEWED) {
                    $unreviewed[] = '#'.$sheet->id;
                }

            }

            if ($unreviewed !== []) {
                throw new RuntimeException('Sheets '.implode(', ', $unreviewed).' must be reviewed before confirming the batch.');
            }

            $productionContexts = [];
            $projectionsBySheetId = [];
            foreach ($sheets as $sheet) {
                /** @var OmrCaptureSheet $sheet */
                if ($sheet->status === OmrCaptureSheet::STATUS_COMMITTED || $sheet->wasAbstained()) {
                    continue;
                }

                if ($sheet->status !== OmrCaptureSheet::STATUS_REVIEWED) {
                    continue;
                }

                if ($sheet->hasOpenRequiredIssues()) {
                    throw new RuntimeException('Resolve required OMR issues on sheet #'.$sheet->id.' before confirming.');
                }

                $productionContext = $this->resolveProductionContext($sheet, $gradingSession);
                $productionContexts[(int) $sheet->getKey()] = $productionContext;
                $projection = $this->projector->project($sheet, $productionContext['composition']);
                $projectionsBySheetId[(int) $sheet->getKey()] = $projection;
                if ($projection['has_unresolved']) {
                    throw new RuntimeException('Resolve unresolved items on sheet #'.$sheet->id.' before confirming.');
                }
                if ($projection['has_stale']) {
                    throw new RuntimeException('Re-review stale items on sheet #'.$sheet->id.' before confirming.');
                }
            }

            foreach ($sheets as $sheet) {
                /** @var OmrCaptureSheet $sheet */
                if ($sheet->status === OmrCaptureSheet::STATUS_COMMITTED) {
                    continue;
                }

                if (! $sheet->wasAbstained()) {
                    $this->writeSheet(
                        $sheet,
                        $gradingSession,
                        $actor,
                        $productionContexts[(int) $sheet->getKey()],
                        $projectionsBySheetId[(int) $sheet->getKey()],
                    );
                }

                $sheet->update([
                    'status' => OmrCaptureSheet::STATUS_COMMITTED,
                    'version' => (int) $sheet->version + 1,
                    'committed_by_user_id' => $actor->id,
                    'committed_at' => now(),
                ]);
            }

            $batch->update([
                'status' => OmrCaptureBatch::STATUS_CONFIRMED,
                'version' => (int) $batch->version + 1,
                'confirmed_by_user_id' => $actor->id,
                'confirmed_at' => now(),
            ]);

            $this->recordAuditEvent->handle('grading.omr_batch_confirmed', [
                'batch_id' => $batch->id,
                'grading_session_id' => $gradingSession->id,
                'sheet_count' => $sheets->count(),
            ], (string) $actor->id);

            return $batch->fresh(['sheets']) ?? $batch;
        });
    }

    /**
     * @param  array{administration: AssessmentAdministration, kit_version: TestKitVersion, composition: array<string, mixed>}  $productionContext
     * @param  array<string, mixed>  $projection
     */
    private function writeSheet(
        OmrCaptureSheet $sheet,
        GradingSession $gradingSession,
        User $actor,
        array $productionContext,
        array $projection,
    ): void {
        if ($sheet->admission_attempt_id === null
            || ! GradingRoster::includesAttempt($gradingSession, (int) $sheet->admission_attempt_id)) {
            throw new RuntimeException('Bind this sheet to an eligible examinee before confirming.');
        }

        $administration = $productionContext['administration'];
        $kitVersion = $productionContext['kit_version'];
        if (($projection['has_unresolved'] ?? true) || ($projection['has_stale'] ?? true)) {
            throw new RuntimeException('The reviewed observations are unresolved or stale.');
        }

        $metricValues = $this->evaluateKitMetricValues(
            $productionContext['composition']['item_map'],
            $projection['items'] ?? [],
            $kitVersion,
        );
        $hasManualScores = AssessmentRawResultRevision::query()
            ->where('administration_id', $administration->id)
            ->where('is_current', true)
            ->where('source', 'manual')
            ->whereIn('metric_code', array_keys($metricValues))
            ->exists();
        if ($hasManualScores) {
            throw new RuntimeException('Manual raw scores already accepted for this examinee. Reasoned correction is required to replace manual scores.');
        }

        $this->acceptRawResults->handle(
            administration: $administration,
            metricValues: $metricValues,
            source: 'omr',
            actor: $actor,
            idempotencyKey: 'omr:sheet:'.$sheet->id.':'.$sheet->version,
            operationAcceptanceReceiptId: $kitVersion->selectedAcceptanceReceipt?->getKey(),
        );
    }

    /**
     * @return array{administration: AssessmentAdministration, kit_version: TestKitVersion, composition: array<string, mixed>}
     */
    private function resolveProductionContext(OmrCaptureSheet $sheet, GradingSession $gradingSession): array
    {
        if ($sheet->admission_attempt_id === null
            || ! GradingRoster::includesAttempt($gradingSession, (int) $sheet->admission_attempt_id)) {
            throw new RuntimeException('Bind this sheet to an eligible examinee before confirming.');
        }

        $assignments = ExamAssignment::query()
            ->where('exam_session_id', $gradingSession->exam_session_id)
            ->where('admission_attempt_id', $sheet->admission_attempt_id)
            ->active()
            ->orderBy('id')
            ->get();
        if ($assignments->count() !== 1) {
            throw new RuntimeException('An exact unique active session assignment is required before OMR confirmation.');
        }
        /** @var ExamAssignment $assignment */
        $assignment = $assignments->first();
        $administration = $this->activeAdministrationForAssignment($assignment);
        $snapshot = $administration?->materialSnapshot;
        if ($administration === null
            || $administration->binding_kind !== AssessmentAdministration::KIND_SCHEDULED_MATERIAL
            || (int) $administration->exam_assignment_id !== (int) $assignment->id
            || (int) $administration->admission_attempt_id !== (int) $assignment->admission_attempt_id
            || (int) ($snapshot?->exam_session_id ?? 0) !== (int) $gradingSession->exam_session_id
            || $snapshot?->test_kit_version_id === null
            || $snapshot?->acceptance_receipt_id === null
            || ! is_string($snapshot?->manifest_sha256)
            || ! hash_equals((string) $administration->manifest_sha256, $snapshot->manifest_sha256)
            || (int) $administration->test_kit_version_id !== (int) $snapshot->test_kit_version_id
            || (int) $administration->acceptance_receipt_id !== (int) $snapshot->acceptance_receipt_id
            || (int) $administration->exam_session_seat_id !== (int) $administration->examSessionSeat?->id
            || (int) $administration->examSessionSeat?->material_snapshot_id !== (int) $snapshot->id) {
            throw new RuntimeException('A matching frozen Test Kit administration for this exact sitting is required.');
        }

        $kitVersion = TestKitVersion::query()
            ->with(['templateVersion', 'bookletVersion', 'bookletSet', 'testInstrumentVersion.metrics.subtest', 'selectedAcceptanceReceipt'])
            ->findOrFail((int) $snapshot->test_kit_version_id);
        if ((int) $kitVersion->test_instrument_version_id !== (int) $administration->test_instrument_version_id
            || ! is_string($kitVersion->manifest_sha256)
            || ! hash_equals($snapshot->manifest_sha256, $kitVersion->manifest_sha256)) {
            throw new RuntimeException('The submitted administration and frozen Test Kit profile do not match.');
        }

        return [
            'administration' => $administration,
            'kit_version' => $kitVersion,
            'composition' => $this->compositionContext->resolveVersion($kitVersion),
        ];
    }

    private function activeAdministrationForAssignment(?ExamAssignment $assignment): ?AssessmentAdministration
    {
        if ($assignment === null) {
            return null;
        }

        $administrations = AssessmentAdministration::query()
            ->where('exam_assignment_id', $assignment->id)
            ->active()
            ->with(['testInstrumentVersion.metrics.subtest', 'materialSnapshot', 'examSessionSeat'])
            ->orderBy('id')
            ->get();
        if ($administrations->count() !== 1) {
            throw new RuntimeException('An exact unique active administration is required for the sitting assignment.');
        }

        return $administrations->first();
    }

    /**
     * @param  array<string, mixed>  $contract
     * @param  list<array<string, mixed>>  $projectedItems
     * @return array<string, string>
     */
    private function evaluateKitMetricValues(array $contract, array $projectedItems, TestKitVersion $kitVersion): array
    {
        $projectedByPosition = [];
        foreach ($projectedItems as $item) {
            $position = (int) ($item['position'] ?? 0);
            if ($position < 1 || isset($projectedByPosition[$position])) {
                throw new RuntimeException('The reviewed observations do not match the exact Test Kit item map.');
            }
            $projectedByPosition[$position] = $item;
        }

        $physicalRowsByPosition = [];
        foreach ($contract['composition_rows'] as $row) {
            if (! is_array($row) || ($row['disposition'] ?? null) !== 'physical') {
                continue;
            }
            $position = (int) ($row['global_position'] ?? 0);
            if ($position < 1 || isset($physicalRowsByPosition[$position])) {
                throw new RuntimeException('The reviewed observations do not match the exact Test Kit item map.');
            }
            $physicalRowsByPosition[$position] = $row;
        }

        $observations = [];
        foreach ($contract['bindings'] as $binding) {
            $position = (int) $binding['global_position'];
            $item = $projectedByPosition[$position] ?? null;
            $physicalRow = $physicalRowsByPosition[$position] ?? null;
            if ($item === null
                || $physicalRow === null
                || (int) ($item['test_instrument_item_id'] ?? 0) !== (int) $binding['item_id']
                || (int) ($item['test_instrument_subtest_id'] ?? 0) !== (int) $binding['subtest_id']
                || (int) ($item['page_no'] ?? 0) !== (int) $binding['page_no']
                || (string) ($item['native_label'] ?? '') !== (string) $binding['native_label']
                || (string) ($item['physical_item_uid'] ?? '') !== (string) ($physicalRow['physical_item_uid'] ?? '')) {
                throw new RuntimeException('The reviewed observations do not match the exact Test Kit item map.');
            }
            $state = $item['effective_state'] ?? null;
            if (! in_array($state, ['selected', 'blank'], true)) {
                throw new RuntimeException('The reviewed observations do not match the exact Test Kit item map.');
            }

            $state = $item['effective_state'] ?? null;
            if (! in_array($state, ['selected', 'blank'], true)) {
                throw new RuntimeException('The reviewed observations do not match the exact Test Kit item map.');
            }

            $token = $state === 'selected' ? ($item['effective_option'] ?? null) : null;
            if ($state === 'selected' && ! is_string($token)) {
                throw new RuntimeException('The reviewed observations do not match the exact Test Kit item map.');
            }
            $observations[] = [
                'page_no' => $binding['page_no'],
                'native_label' => $binding['native_label'],
                'token' => $token,
            ];
        }

        if (count($projectedByPosition) !== count($contract['bindings'])) {
            throw new RuntimeException('The reviewed observations do not match the exact Test Kit item map.');
        }

        $omrContract = $contract;
        $excludedMetricIds = [];
        foreach ($contract['metrics'] as $metric) {
            if ($metric['rule_kind'] === TestInstrumentMetric::RULE_RECORDED_MEASURE) {
                $excludedMetricIds[(int) $metric['metric_id']] = true;
            }
        }
        do {
            $excludedMetricCount = count($excludedMetricIds);
            foreach ($contract['metrics'] as $metric) {
                if (in_array($metric['rule_kind'], [
                    TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                    TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
                ], true) && array_intersect_key(
                    array_fill_keys($metric['input_metric_ids'], true),
                    $excludedMetricIds,
                ) !== []) {
                    $excludedMetricIds[(int) $metric['metric_id']] = true;
                }
            }
        } while (count($excludedMetricIds) !== $excludedMetricCount);

        $omrContract = array_intersect_key($contract, array_flip([
            'profile_version_id',
            'content_sha256',
            'items',
            'bindings',
            'metrics',
            'geometry',
        ]));
        $omrContract['metrics'] = array_values(array_filter(
            $contract['metrics'],
            static fn (array $metric): bool => ! isset($excludedMetricIds[(int) $metric['metric_id']]),
        ));
        $evaluated = $this->rawEvaluator->evaluate($omrContract, $observations, []);
        $metricsById = $kitVersion->testInstrumentVersion->metrics->keyBy('id');
        $metricValues = [];
        foreach ($evaluated['metric_values'] as $value) {
            $metric = $metricsById->get((int) $value['metric_id']);
            if ($metric === null) {
                throw new RuntimeException('The raw evaluator returned a metric outside the pinned profile.');
            }
            if (in_array($metric->rule_kind, [
                TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
            ], true)) {
                continue;
            }
            $metricValues[$metric->code] = $value['value'];
        }

        return $metricValues;
    }
}
