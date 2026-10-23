<?php

namespace App\Consultation\Support;

use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AptitudeArea;
use Brick\Math\BigDecimal;

final class ConsultationScoreProjection
{
    /**
     * @return array{
     *     selected_assignment_id: int|null,
     *     selected_administration_id: int|null,
     *     selected_grading_session_id: int|null,
     *     assessment_context: array{
     *         version: 1,
     *         assignment_id: int|null,
     *         exam_session_id: int|null,
     *         grading_session_id: int|null,
     *         administration_id: int|null,
     *         result_set_id: int|null
     *     },
     *     profile_bound: bool,
     *     assessment_detail: array{
     *         version: 1,
     *         status: 'finalized'|'legacy_untyped'|'unavailable',
     *         profile_bound: bool,
     *         capture_mode: 'omr'|'manual'|'import'|'mixed'|'legacy'|'manual_converted',
     *         instrument_name: string|null,
     *         form_code: string|null,
     *         subtests: list<array{
     *             code: string,
     *             name: string,
     *             metric_name: string,
     *             construct_name: string|null,
     *             subtest_name: string|null,
     *             metric_kind: 'direct'|'derived',
     *             raw_score: string|null,
     *             raw_min_score: string|null,
     *             raw_max_score: string|null,
     *             percentile: float|null,
     *             stanine: int|null,
     *             converted_label: string|null,
     *             source: string|null,
     *             capture_source: string|null,
     *             external_source_reference: string|null,
     *             norm_label: string|null,
     *             components: list<array{code: string, name: string, raw_value: string, source: string}>,
     *             derivation: string
     *         }>
     *     },
     *     series: list<array{
     *         area_code: string,
     *         area_name: string,
     *         score: mixed,
     *         raw_score: mixed,
     *         percentile: float|null,
     *         percentile_scale: string|null,
     *         available: bool,
     *         converted_label: string|null,
     *         source: string|null,
     *         scale: string|null
     *     }>
     * }
     */
    public function forAttempt(AdmissionAttempt $attempt): array
    {
        $assignment = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->with('examSession')
            ->orderByDesc('id')
            ->first();
        $sessionId = $assignment?->exam_session_id;
        $examSession = $assignment?->examSession;

        $administration = $assignment === null ? null : AssessmentAdministration::query()
            ->active()
            ->where('exam_assignment_id', $assignment->id)
            ->where('admission_attempt_id', $attempt->id)
            ->with([
                'testInstrumentVersion.metrics.construct',
                'testInstrumentVersion.metrics.inputs.inputMetric',
                'testInstrumentVersion.metrics.subtest',
            ])
            ->orderByDesc('binding_revision')
            ->orderByDesc('id')
            ->first();
        $hasAssignmentAdministration = $assignment !== null && ($administration !== null || AssessmentAdministration::query()
            ->where('exam_assignment_id', $assignment->id)
            ->where('admission_attempt_id', $attempt->id)
            ->exists());
        $profileBound = $examSession?->test_instrument_version_id !== null || $hasAssignmentAdministration;
        $administrationMatchesSession = $administration !== null
            && ($examSession?->test_instrument_version_id === null
                || (int) $administration->test_instrument_version_id === (int) $examSession->test_instrument_version_id);

        $grading = $sessionId === null ? null : GradingSession::query()
            ->where('exam_session_id', $sessionId)
            ->first();

        $resultSets = $profileBound && $administrationMatchesSession && $grading !== null
            ? AssessmentResultSet::query()
                ->where('administration_id', $administration->id)
                ->where('exam_session_id', $sessionId)
                ->where('grading_session_id', $grading->id)
                ->where('status', AssessmentResultSet::STATUS_FINALIZED)
                ->orderBy('id')
                ->get()
            : collect();
        $resultSet = $resultSets->count() === 1 ? $resultSets->first() : null;

        $legacyRecords = ! $profileBound && $grading !== null
            ? ScoreRecord::query()
                ->where('grading_session_id', $grading->id)
                ->where('admission_attempt_id', $attempt->id)
                ->get()
                ->keyBy('aptitude_area_id')
            : collect();

        $metricsByCode = [];
        $metricsByConstructId = [];
        $metricsByConstructCode = [];
        foreach ($administration?->testInstrumentVersion?->metrics ?? [] as $metric) {
            $metricsByCode[strtoupper((string) $metric->code)] = $metric;

            if ($metric->construct_aptitude_area_id !== null) {
                $metricsByConstructId[(int) $metric->construct_aptitude_area_id] = $metric;
            }

            if ($metric->construct?->code !== null) {
                $metricsByConstructCode[strtoupper((string) $metric->construct->code)] = $metric;
            }
        }

        $rawVector = (array) ($resultSet?->raw_revision_vector_json ?? []);
        $rawVector = self::hydrateRetainedProvenance($rawVector, $administration, $metricsByCode);
        $conversionVector = (array) ($resultSet?->conversion_revision_vector_json ?? []);
        $conversionProvenance = is_array($conversionVector['provenance'] ?? null) ? $conversionVector['provenance'] : [];
        $externalSourceByMetric = is_array($conversionProvenance['external_provenance'] ?? null)
            ? $conversionProvenance['external_provenance']
            : [];
        $typedOutputs = (array) ($conversionVector['typed_outputs'] ?? $conversionVector);
        $series = [];
        $subtestDetails = [];
        $normPackageVersionId = $conversionVector['norm_package_version_id'] ?? null;
        $normVersion = $resultSet !== null
            && is_numeric($normPackageVersionId)
            && (int) $normPackageVersionId > 0
            ? AssessmentNormPackageVersion::query()->find((int) $normPackageVersionId)
            : null;
        $normLabel = $normVersion === null ? null : trim((string) $normVersion->display_name);
        if ($normVersion !== null && $normLabel === '') {
            $normLabel = 'Norm version '.(int) $normVersion->version_number;
        }
        $conversionSource = strtolower((string) ($conversionVector['source'] ?? $conversionVector['typed_outputs_source'] ?? ''));
        $captureMode = self::captureMode($rawVector, $profileBound);
        $normLabelsById = $normVersion === null ? [] : [(int) $normVersion->id => $normLabel];
        if ($profileBound && $conversionSource === 'manual_converted') {
            $captureMode = 'manual_converted';
        }

        foreach (AptitudeArea::query()->where('is_active', true)->orderBy('id')->get() as $area) {
            $areaAliases = BenchmarkInputSchema::constructAliases((string) $area->code);
            $metric = $metricsByCode[strtoupper((string) $area->code)]
                ?? $metricsByConstructId[(int) $area->id]
                ?? $metricsByConstructCode[strtoupper((string) $area->code)]
                ?? null;
            foreach ($areaAliases as $alias) {
                $metric ??= $metricsByCode[strtoupper($alias)]
                    ?? $metricsByConstructCode[strtoupper($alias)]
                    ?? null;
            }
            $metricCode = $metric?->code === null ? null : (string) $metric->code;
            $rawEntry = $resultSet !== null && $metricCode !== null
                ? self::vectorEntry($rawVector, $metricCode)
                : [];
            $metricOutputs = $resultSet !== null && $metricCode !== null
                ? self::vectorEntry($typedOutputs, $metricCode) ?: self::vectorEntry($conversionVector, $metricCode)
                : [];
            if ($metricOutputs === [] && $metric?->construct?->code !== null) {
                $metricOutputs = self::vectorEntry($typedOutputs, (string) $metric->construct->code)
                    ?: self::vectorEntry($conversionVector, (string) $metric->construct->code);
            }
            $percentileRank = self::validPercentile($metricOutputs['percentile_rank'] ?? null);
            $percentile = $percentileRank
                ?? self::validPercentile($metricOutputs['percentile'] ?? null)
                ?? self::validPercentile($metricOutputs['converted_score'] ?? null);
            $percentileScale = $percentileRank !== null
                ? 'percentile_rank'
                : ($percentile !== null ? 'percentile' : null);
            $scale = $percentileScale ?? (array_key_exists('category', $metricOutputs) ? 'category' : null);
            $displayScore = $percentile;
            $manualConverted = $conversionSource === 'manual_converted';
            $captureSource = $manualConverted ? 'External final (not calculated locally)' : null;
            $legacyRecord = ! $profileBound ? $legacyRecords->get($area->id) : null;
            $legacyRawScore = self::finiteNumber($legacyRecord?->score);
            $legacyConverted = $legacyRecord?->converted_score;
            $hasLegacyConverted = is_numeric($legacyConverted) && is_finite((float) $legacyConverted);
            $legacyConvertedScore = $hasLegacyConverted ? (float) $legacyConverted : null;
            $rawScore = $profileBound
                ? ($rawEntry['raw_value'] ?? null)
                : $legacyRawScore;
            $convertedLabel = $profileBound
                ? (is_string($metricOutputs['category'] ?? null) ? $metricOutputs['category'] : null)
                : (is_string($legacyConverted) && ! $hasLegacyConverted ? $legacyConverted : null);
            $source = $profileBound
                ? ($manualConverted ? 'manual_converted' : (is_string($rawEntry['source'] ?? null) ? $rawEntry['source'] : null))
                : (is_string($legacyRecord?->source) ? $legacyRecord->source : null);
            $legacyPercentile = ($legacyConvertedScore !== null && $legacyConvertedScore >= 0.0 && $legacyConvertedScore <= 100.0)
                ? $legacyConvertedScore
                : null;
            if (! $profileBound && $legacyRecord !== null) {
                $legacyConvertedLabel = is_string($legacyConverted) ? trim($legacyConverted) : null;
                if ($legacyConvertedLabel === '' || is_numeric($legacyConvertedLabel)) {
                    $legacyConvertedLabel = null;
                }

                $legacyConversionSummary = $legacyConverted === null
                    ? null
                    : ($legacyConvertedLabel === null
                        ? 'Historical conversion recorded (scale untyped)'
                        : "Historical conversion: {$legacyConvertedLabel} (scale untyped)");
                $legacyDerivation = $legacyRawScore !== null
                    ? 'Raw: '.self::formatNumber($legacyRawScore).' points'
                        .($legacyConversionSummary === null ? '' : ' ➔ '.$legacyConversionSummary)
                    : ($legacyConversionSummary ?? 'Legacy result recorded without a displayable score');

                $subtestDetails[] = [
                    'code' => (string) $area->code,
                    'name' => self::cleanSubtestName((string) $area->name),
                    'raw_score' => $legacyRawScore,
                    'unit' => $legacyRawScore === null ? 'unknown' : 'points',
                    'percentile' => null,
                    'stanine' => null,
                    'converted_label' => $legacyConvertedLabel,
                    'capture_source' => 'Legacy',
                    'derivation' => $legacyDerivation,
                ];
            }

            $series[] = [
                'area_code' => BenchmarkInputSchema::canonicalConstructCode((string) $area->code) ?? (string) $area->code,
                'area_name' => (string) $area->name,
                'score' => $profileBound ? $displayScore : ($legacyConverted ?? $legacyRecord?->score),
                'raw_score' => $rawScore,
                'percentile' => $profileBound ? $percentile : $legacyPercentile,
                'percentile_scale' => $profileBound ? $percentileScale : null,
                'available' => $profileBound ? $percentile !== null : $legacyPercentile !== null,
                'converted_label' => $convertedLabel,
                'source' => $source,
                'capture_source' => $profileBound ? ($captureSource ?? self::captureSourceLabel($source, true)) : null,
                'scale' => $profileBound ? $scale : ($legacyPercentile === null ? null : 'legacy'),
            ];
        }
        if ($profileBound) {
            foreach ($administration?->testInstrumentVersion?->metrics ?? [] as $metric) {
                $metricCode = (string) $metric->code;
                $rawEntry = $resultSet !== null
                    ? self::vectorEntry($rawVector, $metricCode)
                    : [];
                $metricOutputs = $resultSet !== null
                    ? (self::vectorEntry($typedOutputs, $metricCode) ?: self::vectorEntry($conversionVector, $metricCode))
                    : [];
                if ($metricOutputs === [] && $metric->construct?->code !== null) {
                    $metricOutputs = self::vectorEntry($typedOutputs, (string) $metric->construct->code)
                        ?: self::vectorEntry($conversionVector, (string) $metric->construct->code);
                }
                $metricNormPackageVersionId = $metricOutputs['norm_package_version_id'] ?? $normPackageVersionId;
                $metricNormLabel = null;
                if (is_numeric($metricNormPackageVersionId) && (int) $metricNormPackageVersionId > 0) {
                    $metricNormId = (int) $metricNormPackageVersionId;
                    if (! array_key_exists($metricNormId, $normLabelsById)) {
                        $metricNorm = AssessmentNormPackageVersion::query()->find($metricNormId);
                        $normLabelsById[$metricNormId] = $metricNorm === null
                            ? null
                            : (trim((string) $metricNorm->display_name) ?: 'Norm version '.(int) $metricNorm->version_number);
                    }
                    $metricNormLabel = $normLabelsById[$metricNormId];
                }
                $rawScore = self::finiteNumber($rawEntry['raw_value'] ?? $rawEntry[0] ?? null);
                $percentile = self::validPercentile($metricOutputs['percentile_rank'] ?? null)
                    ?? self::validPercentile($metricOutputs['percentile'] ?? null)
                    ?? self::validPercentile($metricOutputs['converted_score'] ?? null);
                $stanine = self::validStanine($metricOutputs['stanine'] ?? null);
                $convertedLabel = is_string($metricOutputs['category'] ?? null)
                    ? trim($metricOutputs['category'])
                    : null;
                if ($convertedLabel === '') {
                    $convertedLabel = null;
                }

                if ($rawScore === null && $percentile === null && $stanine === null && $convertedLabel === null) {
                    continue;
                }

                $unit = is_string($metric->unit) ? trim($metric->unit) : '';
                if ($unit === '') {
                    $unit = $rawScore !== null
                        ? 'unknown'
                        : ($percentile !== null
                            ? 'percentile_rank'
                            : ($stanine !== null ? 'stanine' : 'category'));
                }

                $provenance = is_array($rawEntry['provenance'] ?? null)
                    ? $rawEntry['provenance']
                    : (is_array($rawEntry['provenance_json'] ?? null) ? $rawEntry['provenance_json'] : []);
                $formulaSnapshot = is_array($provenance['formula_snapshot'] ?? null)
                    ? $provenance['formula_snapshot']
                    : [];
                $components = self::frozenComponents($rawVector, $provenance, $formulaSnapshot, $metricsByCode);
                $formulaExpression = is_string($formulaSnapshot['expression'] ?? null)
                    ? trim($formulaSnapshot['expression'])
                    : null;
                if ($formulaExpression === '' || $formulaExpression === null) {
                    if (($provenance['rule_kind'] ?? null) === 'sum_raw_metrics' && $components !== []) {
                        $formulaExpression = implode(' + ', array_column($components, 'code'));
                    }
                }
                $formulaSubstitution = $formulaExpression !== null && $components !== []
                    ? self::formulaSubstitution($formulaExpression, $components)
                    : null;
                $ruleKind = $provenance['rule_kind'] ?? $metric->rule_kind;
                $metricKind = in_array($ruleKind, [
                    TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                    TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
                ], true) ? 'derived' : 'direct';

                $hasFormulaBounds = is_numeric($formulaSnapshot['min_raw_value'] ?? null)
                    && is_numeric($formulaSnapshot['max_raw_value'] ?? null);
                $rawMaximumScore = $hasFormulaBounds
                    ? (string) $formulaSnapshot['max_raw_value']
                    : ($ruleKind === TestInstrumentMetric::RULE_SUM_RAW_METRICS
                        ? self::frozenSumMaximum($metric, $provenance, $components)
                        : ($ruleKind === TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT && is_numeric($metric->max_raw_value)
                            ? (string) $metric->max_raw_value
                            : null));

                $metricName = trim((string) ($metric->name ?? $metricCode));
                $externalSourceReference = $externalSourceByMetric[$metricCode]
                    ?? $externalSourceByMetric[strtoupper($metricCode)]
                    ?? null;
                $subtestDetails[] = [
                    'code' => $metricCode,
                    'name' => $metricName === '' ? $metricCode : $metricName,
                    'metric_name' => $metricName === '' ? $metricCode : $metricName,
                    'construct_name' => $metric->construct?->name === null ? null : (string) $metric->construct->name,
                    'subtest_name' => $metric->subtest?->name === null ? null : (string) $metric->subtest->name,
                    'metric_kind' => $metricKind,
                    'raw_score' => is_numeric($rawEntry['raw_value'] ?? null) ? (string) $rawEntry['raw_value'] : null,
                    'raw_min_score' => $hasFormulaBounds ? (string) $formulaSnapshot['min_raw_value'] : null,
                    'raw_max_score' => $rawMaximumScore,
                    'unit' => $unit,
                    'percentile' => $percentile,
                    'stanine' => $stanine,
                    'converted_label' => $convertedLabel,
                    'capture_source' => self::captureSourceLabel(
                        $conversionSource === 'manual_converted'
                            ? $conversionSource
                            : ($rawEntry['source'] ?? ($conversionVector['source'] ?? $conversionVector['typed_outputs_source'] ?? null)),
                        true,
                    ),
                    'external_source_reference' => $conversionSource === 'manual_converted' && is_string($externalSourceReference)
                        ? $externalSourceReference
                        : null,
                    'norm_label' => $metricNormLabel,
                    'source' => $conversionSource === 'manual_converted'
                        ? $conversionSource
                        : (is_string($rawEntry['source'] ?? null) ? $rawEntry['source'] : null),
                    'formula_expression' => $formulaExpression,
                    'formula_substitution' => $formulaSubstitution,
                    'components' => $components,
                    'derivation' => self::derivationSummary(is_numeric($rawEntry['raw_value'] ?? null) ? (string) $rawEntry['raw_value'] : null, $unit, $percentile, $stanine, $convertedLabel),
                ];
            }
        }

        return [
            'selected_assignment_id' => $assignment === null ? null : (int) $assignment->id,
            'selected_administration_id' => $administration === null ? null : (int) $administration->id,
            'selected_grading_session_id' => $grading === null ? null : (int) $grading->id,
            'assessment_context' => [
                'version' => 1,
                'assignment_id' => $assignment === null ? null : (int) $assignment->id,
                'exam_session_id' => $sessionId === null ? null : (int) $sessionId,
                'grading_session_id' => $grading === null ? null : (int) $grading->id,
                'administration_id' => $administration === null ? null : (int) $administration->id,
                'result_set_id' => $resultSet === null ? null : (int) $resultSet->id,
            ],
            'profile_bound' => $profileBound,
            'assessment_detail' => [
                'version' => 1,
                'status' => $resultSet !== null
                    ? 'finalized'
                    : (! $profileBound && $grading !== null ? 'legacy_untyped' : 'unavailable'),
                'capture_mode' => $captureMode,
                'instrument_name' => $administration?->testInstrumentVersion?->display_name ?? null,
                'form_code' => $administration?->testInstrumentVersion?->form_code ?? null,
                'subtests' => $subtestDetails,
            ],
            'series' => $series,
        ];
    }

    /**
     * @param  array{series?: list<array<string, mixed>>}  $projection
     * @return array{series: list<array<string, mixed>>}
     */
    public static function admissionInputSeries(array $projection): array
    {
        $series = [];

        foreach ($projection['series'] ?? [] as $score) {
            if (! is_array($score)) {
                continue;
            }

            $inputScore = [
                'area_code' => is_string($score['area_code'] ?? null)
                    ? (BenchmarkInputSchema::canonicalConstructCode($score['area_code']) ?? $score['area_code'])
                    : null,
                'percentile' => $score['percentile'] ?? null,
                'scale' => $score['percentile_scale'] ?? $score['scale'] ?? null,
                'available' => $score['available'] ?? false,
                'capture_source' => $score['capture_source'] ?? null,
            ];

            $series[] = $inputScore;
        }

        return ['series' => $series];
    }

    /** @param array<string, mixed> $vector @return array<string, mixed> */
    private static function vectorEntry(array $vector, string $metricCode): array
    {
        $entry = $vector[$metricCode]
            ?? $vector[strtoupper($metricCode)]
            ?? $vector[strtolower($metricCode)]
            ?? null;
        if (is_array($entry)) {
            return $entry;
        }

        foreach (BenchmarkInputSchema::constructAliases($metricCode) as $alias) {
            $entry = $vector[$alias] ?? $vector[strtoupper($alias)] ?? $vector[strtolower($alias)] ?? null;
            if (is_array($entry)) {
                return $entry;
            }
        }

        return [];
    }

    /**
     * Hydrate provenance omitted by older snapshots only from their exact retained raw revision IDs.
     *
     * @param  array<string, mixed>  $rawVector
     * @param  array<string, TestInstrumentMetric>  $metricsByCode
     * @return array<string, mixed>
     */
    private static function hydrateRetainedProvenance(
        array $rawVector,
        ?AssessmentAdministration $administration,
        array $metricsByCode,
    ): array {
        if ($administration === null || $rawVector === []) {
            return $rawVector;
        }

        $revisionIds = [];
        foreach ($rawVector as $entry) {
            if (! is_array($entry)
                || array_key_exists('provenance', $entry)
                || array_key_exists('provenance_json', $entry)) {
                continue;
            }
            $revisionId = $entry['id'] ?? $entry['revision_id'] ?? null;
            if (is_numeric($revisionId) && (int) $revisionId > 0) {
                $revisionIds[(int) $revisionId] = true;
            }
        }
        if ($revisionIds === []) {
            return $rawVector;
        }

        $retainedRevisions = AssessmentRawResultRevision::query()
            ->where('administration_id', $administration->id)
            ->whereIn('id', array_keys($revisionIds))
            ->get([
                'id',
                'administration_id',
                'metric_id',
                'metric_code',
                'revision_no',
                'raw_value',
                'source',
                'status',
                'provenance_json',
            ])
            ->keyBy('id');

        foreach ($rawVector as $vectorCode => $entry) {
            if (! is_array($entry)
                || array_key_exists('provenance', $entry)
                || array_key_exists('provenance_json', $entry)) {
                continue;
            }
            $revisionId = $entry['id'] ?? $entry['revision_id'] ?? null;
            if (! is_numeric($revisionId)) {
                continue;
            }
            $revision = $retainedRevisions->get((int) $revisionId);
            $metricCode = $entry['metric_code'] ?? $vectorCode;
            $metricId = $entry['metric_id'] ?? null;
            $metric = is_string($metricCode) ? ($metricsByCode[strtoupper($metricCode)] ?? null) : null;
            if ($revision === null
                || ! is_string($vectorCode)
                || $vectorCode !== $metricCode
                || ! is_numeric($metricId)
                || (int) $metricId !== (int) $metric->id
                || (int) $revision->administration_id !== (int) $administration->id
                || (int) $revision->metric_id !== (int) $metricId
                || $revision->metric_code !== $metricCode
                || (string) $revision->revision_no !== (string) ($entry['revision_no'] ?? null)
                || (string) $revision->raw_value !== (string) ($entry['raw_value'] ?? null)
                || $revision->source !== ($entry['source'] ?? null)
                || $revision->status !== ($entry['status'] ?? null)) {
                continue;
            }

            $provenance = is_array($revision->provenance_json) ? $revision->provenance_json : [];
            if ($provenance !== []) {
                $rawVector[$vectorCode]['provenance'] = $provenance;
            }
        }

        return $rawVector;
    }

    private static function validPercentile(mixed $value): ?float
    {
        $number = self::finiteNumber($value);

        return $number !== null && $number >= 0.0 && $number <= 100.0 ? $number : null;
    }

    private static function finiteNumber(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }

    private static function captureMode(array $rawVector, bool $profileBound): string
    {
        if (! $profileBound) {
            return 'legacy';
        }

        $sources = [];
        foreach ($rawVector as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $provenance = is_array($entry['provenance'] ?? null)
                ? $entry['provenance']
                : (is_array($entry['provenance_json'] ?? null) ? $entry['provenance_json'] : []);
            $formulaSnapshot = is_array($provenance['formula_snapshot'] ?? null)
                ? $provenance['formula_snapshot']
                : [];
            $components = is_array($formulaSnapshot['components'] ?? null) ? $formulaSnapshot['components'] : [];
            $entrySources = $components !== []
                ? array_column(array_filter($components, 'is_array'), 'source')
                : [$entry['source'] ?? null];
            foreach ($entrySources as $source) {
                $source = is_string($source) ? strtolower($source) : null;
                if (in_array($source, [
                    AssessmentRawResultRevision::SOURCE_OMR,
                    AssessmentRawResultRevision::SOURCE_MANUAL,
                    AssessmentRawResultRevision::SOURCE_IMPORT,
                ], true)) {
                    $sources[$source] = true;
                }
            }
        }

        if (count($sources) === 1) {
            return (string) array_key_first($sources);
        }

        return count($sources) > 1 ? 'mixed' : 'legacy';
    }

    private static function captureSourceLabel(mixed $source, bool $profileBound): string
    {
        if (! $profileBound) {
            return 'Legacy';
        }

        return match (is_string($source) ? strtolower($source) : '') {
            'manual_converted' => 'External final (not calculated locally)',
            AssessmentRawResultRevision::SOURCE_OMR => 'OMR-derived',
            AssessmentRawResultRevision::SOURCE_MANUAL, 'synthetic-finalized-fixture' => 'Manually entered',
            AssessmentRawResultRevision::SOURCE_IMPORT => 'Imported',
            AssessmentRawResultRevision::SOURCE_DERIVED_COMPOSITE => 'Derived from raw metrics',
            default => 'Not recorded',
        };
    }

    /**
     * @param  array<string, mixed>  $provenance
     * @param  array<string, mixed>  $formulaSnapshot
     * @param  array<string, mixed>  $metricsByCode
     * @return list<array{code: string, name: string, raw_value: string, source: string}>
     */
    private static function frozenComponents(
        array $rawVector,
        array $provenance,
        array $formulaSnapshot,
        array $metricsByCode,
    ): array {
        $snapshotComponents = is_array($formulaSnapshot['components'] ?? null)
            ? $formulaSnapshot['components']
            : [];
        $components = [];
        if ($snapshotComponents !== []) {
            foreach ($snapshotComponents as $component) {
                if (! is_array($component)
                    || ! is_string($component['metric_code'] ?? null)
                    || ! is_numeric($component['raw_value'] ?? null)) {
                    return [];
                }
                $code = (string) $component['metric_code'];
                $componentRevisionId = $component['revision_id'] ?? null;
                $componentEntry = self::vectorEntry($rawVector, $code);
                $entryRevisionId = $componentEntry['revision_id'] ?? $componentEntry['id'] ?? null;
                if (! is_numeric($componentRevisionId)
                    || ! is_numeric($entryRevisionId)
                    || (int) $entryRevisionId !== (int) $componentRevisionId
                    || (string) ($componentEntry['raw_value'] ?? '') !== (string) $component['raw_value']
                    || ! is_string($component['source'] ?? null)
                    || ($componentEntry['source'] ?? null) !== $component['source']) {
                    return [];
                }
                $metric = $metricsByCode[strtoupper($code)] ?? null;
                $components[] = [
                    'code' => $code,
                    'name' => trim((string) ($metric?->name ?? $code)) ?: $code,
                    'raw_value' => (string) $component['raw_value'],
                    'source' => implode(', ', self::frozenLeafSources(
                        $rawVector,
                        $code,
                        $component['revision_id'] ?? null,
                        $component['source'] ?? null,
                    )),
                ];
            }

            return $components;
        }

        $codes = is_array($provenance['component_metric_codes'] ?? null)
            ? $provenance['component_metric_codes']
            : [];
        $revisionIds = is_array($provenance['component_revision_ids'] ?? null)
            ? $provenance['component_revision_ids']
            : [];
        if ($codes === [] || count($codes) !== count($revisionIds)) {
            return [];
        }

        foreach ($codes as $index => $code) {
            $revisionId = $revisionIds[$index] ?? null;
            if (! is_string($code) || ! is_numeric($revisionId)) {
                return [];
            }
            $entry = self::vectorEntry($rawVector, $code);
            $entryRevisionId = $entry['revision_id'] ?? $entry['id'] ?? null;
            if (! is_numeric($entryRevisionId)
                || (int) $entryRevisionId !== (int) $revisionId
                || ! is_numeric($entry['raw_value'] ?? null)) {
                return [];
            }
            $metric = $metricsByCode[strtoupper($code)] ?? null;
            $components[] = [
                'code' => $code,
                'name' => trim((string) ($metric?->name ?? $code)) ?: $code,
                'raw_value' => (string) $entry['raw_value'],
                'source' => implode(', ', self::frozenLeafSources($rawVector, $code, $revisionId, $entry['source'] ?? null)),
            ];
        }

        return $components;
    }

    /**
     * Resolve a composite's leaf sources only through its frozen component revision IDs.
     *
     * @param  array<string, mixed>  $rawVector
     * @param  array<string, true>  $visited
     * @return list<string>
     */
    private static function frozenLeafSources(
        array $rawVector,
        string $metricCode,
        mixed $revisionId,
        mixed $source,
        int $depth = 0,
        array $visited = [],
    ): array {
        if (strtolower((string) $source) !== AssessmentRawResultRevision::SOURCE_DERIVED_COMPOSITE
            || $depth >= 32
            || ! is_numeric($revisionId)) {
            return [self::captureSourceLabel($source, true)];
        }

        $identity = strtoupper($metricCode).':'.(int) $revisionId;
        if (isset($visited[$identity])) {
            return [self::captureSourceLabel($source, true)];
        }
        $visited[$identity] = true;

        $entry = self::vectorEntry($rawVector, $metricCode);
        $entryRevisionId = $entry['revision_id'] ?? $entry['id'] ?? null;
        if (! is_numeric($entryRevisionId) || (int) $entryRevisionId !== (int) $revisionId) {
            return [self::captureSourceLabel($source, true)];
        }

        $provenance = is_array($entry['provenance'] ?? null)
            ? $entry['provenance']
            : (is_array($entry['provenance_json'] ?? null) ? $entry['provenance_json'] : []);
        $formulaSnapshot = is_array($provenance['formula_snapshot'] ?? null)
            ? $provenance['formula_snapshot']
            : [];
        $snapshotComponents = is_array($formulaSnapshot['components'] ?? null)
            ? $formulaSnapshot['components']
            : [];
        $children = [];
        if ($snapshotComponents !== []) {
            foreach ($snapshotComponents as $component) {
                if (! is_array($component) || ! is_string($component['metric_code'] ?? null)) {
                    continue;
                }
                $children = [
                    ...$children,
                    ...self::frozenLeafSources(
                        $rawVector,
                        $component['metric_code'],
                        $component['revision_id'] ?? null,
                        $component['source'] ?? null,
                        $depth + 1,
                        $visited,
                    ),
                ];
            }
        } else {
            $codes = is_array($provenance['component_metric_codes'] ?? null)
                ? $provenance['component_metric_codes']
                : [];
            $revisionIds = is_array($provenance['component_revision_ids'] ?? null)
                ? $provenance['component_revision_ids']
                : [];
            foreach ($codes as $index => $code) {
                if (! is_string($code) || ! is_numeric($revisionIds[$index] ?? null)) {
                    continue;
                }
                $childEntry = self::vectorEntry($rawVector, $code);
                $childRevisionId = $childEntry['revision_id'] ?? $childEntry['id'] ?? null;
                if (! is_numeric($childRevisionId) || (int) $childRevisionId !== (int) $revisionIds[$index]) {
                    continue;
                }
                $children = [
                    ...$children,
                    ...self::frozenLeafSources(
                        $rawVector,
                        $code,
                        $childRevisionId,
                        $childEntry['source'] ?? null,
                        $depth + 1,
                        $visited,
                    ),
                ];
            }
        }

        return $children === []
            ? [self::captureSourceLabel($source, true)]
            : array_values(array_unique($children));
    }

    /**
     * @param  array<string, mixed>  $provenance
     * @param  list<array{code: string, name: string, raw_value: string, source: string}>  $components
     */
    private static function frozenSumMaximum(
        TestInstrumentMetric $metric,
        array $provenance,
        array $components,
    ): ?string {
        $codes = is_array($provenance['component_metric_codes'] ?? null)
            ? $provenance['component_metric_codes']
            : [];
        $revisionIds = is_array($provenance['component_revision_ids'] ?? null)
            ? $provenance['component_revision_ids']
            : [];
        if ($components === [] || count($codes) !== count($components) || count($revisionIds) !== count($components)) {
            return null;
        }

        $inputs = $metric->inputs;
        if ($inputs->count() !== count($components)) {
            return null;
        }

        $maximum = BigDecimal::of('0');
        foreach ($inputs as $index => $input) {
            $inputMetric = $input->inputMetric;
            $component = $components[$index] ?? null;
            if ($inputMetric === null
                || ! is_string($codes[$index] ?? null)
                || strtoupper($codes[$index]) !== strtoupper($component['code'] ?? '')
                || strtoupper($inputMetric->code) !== strtoupper($component['code'] ?? '')
                || $inputMetric->unit !== $metric->unit
                || ! is_numeric($inputMetric->max_raw_value)) {
                return null;
            }
            $maximum = $maximum->plus(BigDecimal::of((string) $inputMetric->max_raw_value));
        }

        return (string) $maximum;
    }

    /**
     * @param  list<array{code: string, name: string, raw_value: string, source: string}>  $components
     */
    private static function formulaSubstitution(string $expression, array $components): string
    {
        $values = [];
        foreach ($components as $component) {
            $values[strtoupper($component['code'])] = $component['raw_value'];
        }

        return preg_replace_callback(
            '/\b[A-Za-z][A-Za-z0-9_]*\b(?!\s*\()/',
            static fn (array $match): string => $values[strtoupper($match[0])] ?? $match[0],
            $expression,
        ) ?? $expression;
    }

    private static function cleanSubtestName(string $name): string
    {
        $canonicalMap = [
            'General Ability' => 'Logic / General Ability',
            'Profile Verbal Reasoning' => 'Verbal Reasoning',
            'Verbal Aptitude' => 'Verbal Reasoning',
            'Profile Quantitative Reasoning' => 'Quantitative Reasoning',
            'Numerical Aptitude' => 'Quantitative Reasoning',
            'Spatial Aptitude' => 'Spatial Aptitude',
            'Perceptual Aptitude' => 'Memory',
        ];

        return $canonicalMap[trim($name)] ?? (str_starts_with($name, 'Profile ') ? substr($name, strlen('Profile ')) : $name);
    }

    private static function validStanine(mixed $value): ?int
    {
        $number = self::finiteNumber($value);

        return $number !== null && $number >= 1.0 && $number <= 9.0 && floor($number) === $number
            ? (int) $number
            : null;
    }

    private static function derivationSummary(
        ?string $rawScore,
        string $unit,
        ?float $percentile,
        ?int $stanine,
        ?string $convertedLabel,
    ): string {
        $outputs = [];
        if ($percentile !== null) {
            $outputs[] = self::formatNumber($percentile).' PR';
        }
        if ($stanine !== null) {
            $outputs[] = 'Stanine '.$stanine;
        }
        if ($convertedLabel !== null) {
            $outputs[] = $convertedLabel;
        }

        if ($rawScore !== null) {
            $summary = 'Raw: '.BigDecimal::of($rawScore)->strippedOfTrailingZeros();

            return $outputs === []
                ? $summary.($unit === 'unknown' ? '' : ' '.$unit)
                : $summary.' ➔ '.implode(', ', $outputs);
        }
        if ($percentile !== null) {
            return 'Recorded percentile: '.self::formatNumber($percentile).' PR';
        }
        if ($stanine !== null) {
            return 'Recorded stanine: '.$stanine;
        }
        if ($convertedLabel !== null) {
            return 'Recorded category: '.$convertedLabel;
        }

        return 'No score detail recorded';
    }

    private static function formatNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
    }
}
