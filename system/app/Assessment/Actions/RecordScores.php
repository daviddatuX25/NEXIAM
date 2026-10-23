<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\GradingRoster;
use App\Assessment\Support\IndividualResultMutationFence;
use App\Assessment\Support\ScoreBaseline;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\BindAssessmentAdministration;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Brick\Math\BigDecimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RecordScores
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly BindAssessmentAdministration $bindAdministration,
        private readonly AcceptAssessmentRawResults $acceptRawResults,
        private readonly ConvertAssessmentResults $convertResults,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly IndividualResultMutationFence $mutationFence,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    /**
     * Transactionally records manual raw metrics or final converted scores
     * for one attempt in a grading session.
     *
     * Idempotency: each domain row is upserted on the unique triple
     * (grading_session_id, admission_attempt_id, aptitude_area_id) with a
     * deterministic per-domain key `{key}:{attempt}:{area}`, so replaying the
     * same request is a no-op (same values, no new rows).
     *
     * @param  array<int, array<string, mixed>>  $scores
     * @return list<ScoreRecord>
     */
    public function handle(
        GradingSession $gradingSession,
        AdmissionAttempt $attempt,
        array $scores,
        User $actor,
        string $idempotencyKey,
        string $source = ScoreRecord::SOURCE_MANUAL,
        string $scoringMode = 'manual_raw',
        ?int $testKitVersionId = null,
        ?int $normPackageVersionId = null,
        ?string $expectedBaseline = null,
    ): array {
        $academicCycleId = (int) $gradingSession->examSession()->value('academic_cycle_id');

        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            $academicCycleId,
            fn (): array => DB::transaction(function () use (
                $gradingSession,
                $attempt,
                $scores,
                $actor,
                $idempotencyKey,
                $source,
                $scoringMode,
                $testKitVersionId,
                $normPackageVersionId,
                $expectedBaseline,
            ): array {
                $gradingSession = GradingSession::query()->lockForUpdate()->findOrFail($gradingSession->id);
                if ($gradingSession->status === GradingSession::STATUS_FINALIZED) {
                    throw new RuntimeException('Grading session is finalized.');
                }
                if (! GradingRoster::includesAttempt($gradingSession, (int) $attempt->id)) {
                    throw new RuntimeException('Attempt is not part of this grading session.');
                }

                $assignment = ExamAssignment::query()
                    ->where('exam_session_id', $gradingSession->exam_session_id)
                    ->where('admission_attempt_id', $attempt->id)
                    ->active()
                    ->lockForUpdate()
                    ->first();
                if ($assignment === null) {
                    throw ValidationException::withMessages([
                        'assignment' => 'Scoring requires the exact active assignment for this sitting.',
                    ]);
                }
                $current = AssessmentAdministration::query()
                    ->where('exam_assignment_id', $assignment->id)
                    ->active()
                    ->lockForUpdate()
                    ->first();
                if ($scoringMode === 'manual_raw' && isset($scores[0]['scoring_mode'])) {
                    $scoringMode = (string) $scores[0]['scoring_mode'];
                }
                if ($expectedBaseline !== null) {
                    $existingRawRun = $current === null ? null : AssessmentScoringRun::query()
                        ->where('administration_id', $current->id)
                        ->where('idempotency_key', trim($idempotencyKey))
                        ->first();
                    $existingFinalRevision = $current === null ? null : AssessmentConversionRevision::query()
                        ->where('administration_id', $current->id)
                        ->where('source', AssessmentConversionRevision::SOURCE_MANUAL_CONVERTED)
                        ->whereJsonContains('provenance_json->idempotency_key', trim($idempotencyKey))
                        ->first();

                    $hasRawReceipt = $existingRawRun !== null;
                    $hasFinalReceipt = $existingFinalRevision !== null;

                    if ($hasRawReceipt) {
                        if ((int) $existingRawRun->actor_user_id !== (int) $actor->id
                            || $existingRawRun->source !== $source
                            || ($testKitVersionId !== null && $existingRawRun->test_kit_version_id !== null && (int) $existingRawRun->test_kit_version_id !== (int) $testKitVersionId)
                            || $scoringMode !== 'manual_raw') {
                            throw new RuntimeException('assessment_raw_results_idempotency_conflict');
                        }

                        $existingConversion = AssessmentConversionRevision::query()
                            ->where('administration_id', $current->id)
                            ->where('is_current', true)
                            ->first();
                        $recordedNormId = $existingConversion?->norm_package_version_id === null
                            ? null
                            : (int) $existingConversion->norm_package_version_id;
                        if ($recordedNormId !== ($normPackageVersionId === null ? null : (int) $normPackageVersionId)) {
                            throw new RuntimeException('assessment_raw_results_idempotency_conflict');
                        }
                    } elseif ($hasFinalReceipt) {
                        if ((int) ($existingFinalRevision->provenance_json['actor_user_id'] ?? 0) !== (int) $actor->id
                            || ($existingFinalRevision->provenance_json['acquisition_channel'] ?? ScoreRecord::SOURCE_MANUAL) !== $source
                            || ($testKitVersionId !== null && (int) ($existingFinalRevision->provenance_json['test_kit_version_id'] ?? 0) !== (int) $testKitVersionId)
                            || $scoringMode !== 'manual_converted') {
                            throw new RuntimeException('assessment_external_final_idempotency_conflict');
                        }
                    } elseif (! hash_equals($expectedBaseline, app(ScoreBaseline::class)->for($gradingSession, $attempt))) {
                        abort(409, 'The applicant score changed since this row was loaded.');
                    }
                }
                if ($current !== null) {
                    $this->mutationFence->assertMutable($current);
                }
                if (! in_array($source, [
                    ScoreRecord::SOURCE_MANUAL,
                    ScoreRecord::SOURCE_IMPORT,
                    ScoreRecord::SOURCE_OMR,
                ], true)) {
                    throw ValidationException::withMessages([
                        'source' => 'Select a supported manual, import, or OMR scoring source.',
                    ]);
                }
                $selectedKitId = $testKitVersionId ?? $current?->test_kit_version_id ?? $gradingSession->examSession?->test_kit_version_id;
                if ($selectedKitId === null) {
                    throw ValidationException::withMessages([
                        'test_kit_version_id' => 'Select the exact Test Kit version before recording new scores.',
                    ]);
                }
                $kitVersion = TestKitVersion::query()->findOrFail($selectedKitId);
                if ($kitVersion->testInstrumentVersion?->isFinalOnly() && $scoringMode !== 'manual_converted') {
                    throw ValidationException::withMessages(['scoring_mode' => 'This Test Setup accepts supplied final aptitude scores only.']);
                }
                if ($scoringMode === 'manual_converted') {
                    if ($source === ScoreRecord::SOURCE_OMR) {
                        throw ValidationException::withMessages(['source' => 'OMR cannot supply externally calculated final scores.']);
                    }
                    $capability = $source === ScoreRecord::SOURCE_IMPORT
                        ? CapabilityCatalog::GUIDANCE_SCORING_IMPORT
                        : CapabilityCatalog::GUIDANCE_SCORING_MANUAL;
                    if (! $this->authorizer->decideFresh($actor, $capability, [
                        'institution_id' => (int) $kitVersion->institution_id,
                        'office' => 'guidance',
                    ])->allowed) {
                        throw new AuthorizationException('Scoring authority for the acquisition channel and Kit institution is required.');
                    }
                }
                $operation = $scoringMode === 'manual_converted'
                    ? 'external_final'
                    : match ($source) {
                        ScoreRecord::SOURCE_MANUAL => 'manual',
                        ScoreRecord::SOURCE_IMPORT => 'import',
                        ScoreRecord::SOURCE_OMR => 'omr',
                    };
                $administration = $this->bindAdministration->handle(
                    $assignment, $kitVersion, $actor, $operation, acquisitionChannel: $source,
                );
                $boundAssignment = ExamAssignment::query()->findOrFail($administration->exam_assignment_id);
                if ((int) $boundAssignment->id !== (int) $assignment->id
                    || (int) $boundAssignment->exam_session_id !== (int) $gradingSession->exam_session_id
                    || (int) $boundAssignment->admission_attempt_id !== (int) $attempt->id
                    || (int) $administration->admission_attempt_id !== (int) $attempt->id
                    || ! $boundAssignment->isActive()) {
                    throw ValidationException::withMessages([
                        'assignment' => 'The locked assessment binding no longer belongs to this grading session and applicant sitting.',
                    ]);
                }
                if ($scoringMode === 'manual_converted') {
                    return $this->recordConvertedScores($gradingSession, $attempt, $scores, $actor, $idempotencyKey, $administration, $source);
                }

                $administration->loadMissing(['testInstrumentVersion.metrics.construct']);
                $profileMetrics = $administration->testInstrumentVersion?->metrics ?? collect();
                $metricEntries = [];
                foreach ($scores as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }
                    $metric = null;
                    if (isset($entry['metric_id'])) {
                        $metric = $profileMetrics->firstWhere('id', (int) $entry['metric_id']);
                    } elseif (isset($entry['metric_code']) || isset($entry['code'])) {
                        $code = strtoupper((string) ($entry['metric_code'] ?? $entry['code']));
                        $metric = $profileMetrics->first(fn ($item) => strtoupper($item->code) === $code);
                    } elseif (isset($entry['aptitude_area_id'])) {
                        $areaId = (int) $entry['aptitude_area_id'];
                        $metric = $profileMetrics->first(fn ($item) => (int) $item->construct_aptitude_area_id === $areaId);
                    }
                    $metricEntries[] = [
                        'code' => $metric?->code ?? (string) ($entry['metric_code'] ?? $entry['code'] ?? $entry['metric_id'] ?? ''),
                        'value' => $entry['score'] ?? $entry['raw_value'] ?? $entry['value'] ?? null,
                        'override_omr' => (bool) ($entry['override_omr'] ?? false),
                        'override_reason' => $entry['override_reason'] ?? null,
                    ];
                }
                $ingressSource = $source;
                $operationReceiptId = null;
                if ($ingressSource === ScoreRecord::SOURCE_OMR) {
                    $existingOmrRun = AssessmentScoringRun::query()
                        ->where('administration_id', $administration->id)
                        ->where('idempotency_key', trim($idempotencyKey))
                        ->where('source', AssessmentScoringRun::SOURCE_OMR)
                        ->first();
                    if ($existingOmrRun !== null) {
                        $operationReceiptId = $existingOmrRun->acceptance_receipt_id;
                    } else {
                        $administration->loadMissing('testKitVersion.selectedAcceptanceReceipt');
                        $operationReceiptId = $administration->testKitVersion?->selectedAcceptanceReceipt?->getKey();
                    }
                }
                $rawResult = $this->acceptRawResults->handle(
                    administration: $administration,
                    metricValues: $metricEntries,
                    source: $ingressSource,
                    actor: $actor,
                    idempotencyKey: $idempotencyKey,
                    operationAcceptanceReceiptId: $operationReceiptId,
                );
                if ($rawResult['binding_state'] === AssessmentAdministration::STATE_AWAITING_NORM_CONVERSION
                    && $normPackageVersionId !== null) {
                    $normVersion = AssessmentNormPackageVersion::query()->findOrFail($normPackageVersionId);
                    $this->convertResults->handle($administration, $normVersion, $actor);
                }

                return $rawResult['revisions'];
            }),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $scores
     * @return list<ScoreRecord>
     */
    private function recordConvertedScores(
        GradingSession $gradingSession,
        AdmissionAttempt $attempt,
        array $scores,
        User $actor,
        string $idempotencyKey,
        ?AssessmentAdministration $administration,
        string $acquisitionChannel,
    ): array {
        if ($administration === null) {
            throw ValidationException::withMessages([
                'administration' => 'An external final requires the exact current sitting administration.',
            ]);
        }
        $this->mutationFence->assertMutable($administration);
        $administration->loadMissing(['testInstrumentVersion.metrics']);
        $metrics = $administration->testInstrumentVersion?->metrics ?? collect();
        $typedOutputs = [];
        $provenanceByMetric = [];
        $seenMetricCodes = [];
        foreach ($scores as $index => $entry) {
            if (! is_array($entry)) {
                throw ValidationException::withMessages([
                    "scores.{$index}" => 'External final entries must be typed metric values.',
                ]);
            }
            $metric = $this->resolveMetric($metrics, $entry);
            $typedValues = $entry['typed_outputs'] ?? null;
            $externalProvenance = trim((string) ($entry['external_provenance'] ?? ''));
            if ($externalProvenance === '') {
                $externalProvenance = 'Manual entry';
            }
            if ($metric === null || ! $metric->is_required || ! is_array($typedValues)) {
                throw ValidationException::withMessages([
                    "scores.{$index}" => 'Each external final requires a required metric and typed outputs.',
                ]);
            }
            $code = (string) $metric->code;
            if (isset($seenMetricCodes[$code])) {
                throw ValidationException::withMessages([
                    "scores.{$index}" => "External final contains duplicate metric {$code}.",
                ]);
            }
            $seenMetricCodes[$code] = true;
            $requiredScales = array_values(array_unique(array_map('strval', (array) $metric->required_output_scales)));
            if ($requiredScales === [] || array_diff($requiredScales, array_keys($typedValues)) !== []
                || array_diff(array_keys($typedValues), $requiredScales) !== []) {
                throw ValidationException::withMessages([
                    "scores.{$index}.typed_outputs" => "External final must provide exactly the approved output scales for {$code}.",
                ]);
            }
            foreach ($requiredScales as $scale) {
                $rawValue = $typedValues[$scale];
                if (! is_numeric($rawValue) || ! is_finite((float) $rawValue)) {
                    throw ValidationException::withMessages([
                        "scores.{$index}.typed_outputs.{$scale}" => "Output for {$code} ({$scale}) must be numeric.",
                    ]);
                }
                $decimal = BigDecimal::of((string) $rawValue);
                if ($metric->rule_kind === TestInstrumentMetric::RULE_EXTERNAL_FINAL
                    && $decimal->getScale() > (int) $metric->precision_scale) {
                    throw ValidationException::withMessages([
                        "scores.{$index}.typed_outputs.{$scale}" => "The supplied score exceeds {$metric->precision_scale} decimal places.",
                    ]);
                }
                if ($scale === 'percentile_rank') {
                    if ($decimal->compareTo('0') < 0 || $decimal->compareTo('100') > 0) {
                        throw ValidationException::withMessages([
                            "scores.{$index}.typed_outputs.{$scale}" => 'Percentile rank must be between 0 and 100.',
                        ]);
                    }
                    $typedOutputs[$code]['converted_score'] = (string) $decimal;
                } elseif ($scale === 'stanine') {
                    if ($decimal->compareTo('1') < 0 || $decimal->compareTo('9') > 0
                        || $decimal->getScale() > 0) {
                        throw ValidationException::withMessages([
                            "scores.{$index}.typed_outputs.{$scale}" => 'Stanine must be an integer between 1 and 9.',
                        ]);
                    }
                } else {
                    throw ValidationException::withMessages([
                        "scores.{$index}.typed_outputs.{$scale}" => "Output scale {$scale} is not supported for external finals.",
                    ]);
                }
                $typedOutputs[$code][$scale] = (string) $decimal;
            }
            $provenanceByMetric[$code] = $externalProvenance;
        }

        $requiredMetrics = $metrics->where('is_required', true);
        foreach ($requiredMetrics as $metric) {
            $code = (string) $metric->code;
            if (! isset($seenMetricCodes[$code])) {
                throw ValidationException::withMessages([
                    'scores' => "External final is incomplete; required metric {$code} is missing.",
                ]);
            }
        }
        ksort($typedOutputs);
        ksort($provenanceByMetric);
        $fingerprint = hash('sha256', json_encode([
            'typed_outputs' => $typedOutputs,
            'external_provenance' => $provenanceByMetric,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $previousExternalRevision = AssessmentConversionRevision::query()
            ->where('administration_id', $administration->id)
            ->where('source', AssessmentConversionRevision::SOURCE_MANUAL_CONVERTED)
            ->orderBy('revision_no')
            ->lockForUpdate()
            ->get()
            ->first(fn (AssessmentConversionRevision $revision): bool => ($revision->provenance_json['idempotency_key'] ?? null) === $idempotencyKey);
        if ($previousExternalRevision !== null) {
            if ((int) ($previousExternalRevision->provenance_json['actor_user_id'] ?? 0) !== (int) $actor->id
                || ($previousExternalRevision->provenance_json['acquisition_channel'] ?? ScoreRecord::SOURCE_MANUAL) !== $acquisitionChannel
                || ($administration->test_kit_version_id !== null && (int) ($previousExternalRevision->provenance_json['test_kit_version_id'] ?? 0) !== (int) $administration->test_kit_version_id)
                || ! hash_equals((string) ($previousExternalRevision->provenance_json['payload_sha256'] ?? ''), $fingerprint)) {
                throw new RuntimeException('assessment_external_final_idempotency_conflict');
            }

            return [];
        }

        $currentRevision = AssessmentConversionRevision::query()
            ->where('administration_id', $administration->id)
            ->where('is_current', true)
            ->lockForUpdate()
            ->first();
        if ($currentRevision !== null) {
            $currentRevision->update([
                'is_current' => false,
                'status' => AssessmentConversionRevision::STATUS_SUPERSEDED,
                'superseded_at' => now(),
            ]);
        }

        $nextRevisionNo = (int) AssessmentConversionRevision::query()
            ->where('administration_id', $administration->id)
            ->max('revision_no') + 1;
        $convertedAt = now();
        $conversionRevision = new AssessmentConversionRevision;
        $conversionRevision->forceFill([
            'administration_id' => $administration->id,
            'norm_package_version_id' => null,
            'source' => AssessmentConversionRevision::SOURCE_MANUAL_CONVERTED,
            'revision_no' => $nextRevisionNo,
            'typed_outputs_json' => $typedOutputs,
            'provenance_json' => [
                'source' => 'manual_converted',
                'acquisition_channel' => $acquisitionChannel,
                'external_provenance' => $provenanceByMetric,
                'actor_user_id' => $actor->id,
                'grading_session_id' => $gradingSession->id,
                'test_kit_version_id' => $administration->test_kit_version_id,
                'converted_at' => $convertedAt->toISOString(),
                'idempotency_key' => $idempotencyKey,
                'payload_sha256' => $fingerprint,
            ],
            'status' => AssessmentConversionRevision::STATUS_VALID,
            'converted_by_user_id' => $actor->id,
            'converted_at' => $convertedAt,
            'is_current' => true,
            'superseded_at' => null,
        ]);
        $conversionRevision->save();

        $administration->update(['binding_state' => AssessmentAdministration::STATE_READY_TO_FINALIZE]);
        $this->recordAuditEvent->handle('grading.score_recorded', [
            'administration_id' => $administration->id,
            'conversion_revision_id' => $conversionRevision->id,
            'attempt_id' => $attempt->id,
            'scoring_mode' => 'manual_converted',
            'acquisition_channel' => $acquisitionChannel,
            'external_provenance' => $provenanceByMetric,
        ], (string) $actor->id);

        return [];
    }

    private function resolveMetric($metrics, array $entry): mixed
    {
        if (isset($entry['metric_id'])) {
            return $metrics->firstWhere('id', (int) $entry['metric_id']);
        }

        $code = $entry['metric_code'] ?? $entry['code'] ?? null;
        if ($code !== null) {
            return $metrics->first(fn ($metric) => strtoupper($metric->code) === strtoupper((string) $code));
        }

        if (isset($entry['aptitude_area_id'])) {
            return $metrics->first(fn ($metric) => (int) $metric->construct_aptitude_area_id === (int) $entry['aptitude_area_id']);
        }

        return null;
    }
}
