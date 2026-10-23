<?php

namespace App\Assessment\Queries;

use App\Assessment\Support\GradingRoster;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AssessmentResultQuery
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Query typed assessment results for an admission attempt.
     *
     * @return array{
     *     attempt_id: int,
     *     administration_id: int|null,
     *     instrument_id: int|null,
     *     form_code: string|null,
     *     status: string,
     *     provenance: string,
     *     metrics: list<array<string, mixed>>,
     * }
     */
    public function forAttempt(AdmissionAttempt $attempt, ?string $requestedScale = null): array
    {
        $assignment = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->with('examSession.gradingSession')
            ->orderByDesc('id')
            ->first();

        if ($assignment !== null) {
            $administrations = AssessmentAdministration::query()
                ->active()
                ->where('exam_assignment_id', $assignment->id)
                ->where('admission_attempt_id', $attempt->id)
                ->with([
                    'testInstrumentVersion.instrument',
                    'testInstrumentVersion.metrics.construct',
                    'testInstrumentVersion.subtests',
                    'currentRawResultRevisions',
                    'currentConversionRevision',
                ])
                ->orderByDesc('binding_revision')
                ->orderByDesc('id')
                ->get();
            if ($administrations->count() === 0) {
                $grading = $assignment->examSession?->gradingSession;
                $records = $grading?->status === GradingSession::STATUS_FINALIZED
                    ? ScoreRecord::query()
                        ->where('admission_attempt_id', $attempt->id)
                        ->where('grading_session_id', $grading->id)
                        ->with('aptitudeArea')
                        ->orderBy('aptitude_area_id')
                        ->orderByDesc('id')
                        ->get()
                    : collect();

                return $this->projectLegacyRecords($records, $attempt, $requestedScale);
            }
            if ($administrations->count() !== 1) {
                return $this->projectLegacyRecords(collect(), $attempt, $requestedScale);
            }
            $administration = $administrations->first();
            $grading = $assignment->examSession?->gradingSession;
            $resultSets = $grading === null
                ? collect()
                : AssessmentResultSet::query()
                    ->where('administration_id', $administration->id)
                    ->where('exam_session_id', $assignment->exam_session_id)
                    ->where('grading_session_id', $grading->id)
                    ->where('status', AssessmentResultSet::STATUS_FINALIZED)
                    ->orderBy('id')
                    ->get();
            if ($resultSets->count() === 1) {
                return $this->projectFinalizedResultSet($resultSets->first(), $attempt, $requestedScale, $administration);
            }

            return $this->projectProfileAdministration($administration, $attempt, $requestedScale);
        }

        $resultSet = AssessmentResultSet::query()
            ->whereHas('administration', function (Builder $query) use ($attempt): void {
                $query->where('admission_attempt_id', $attempt->id);
            })
            ->where('status', AssessmentResultSet::STATUS_FINALIZED)
            ->with([
                'administration.testInstrumentVersion.instrument',
                'administration.testInstrumentVersion.metrics.construct',
                'administration.testInstrumentVersion.subtests',
            ])
            ->latest('id')
            ->first();
        if ($resultSet !== null) {
            return $this->projectFinalizedResultSet($resultSet, $attempt, $requestedScale, $resultSet->administration);
        }

        $latestGradingSessionId = ScoreRecord::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereHas('gradingSession', fn (Builder $query) => $query->where('status', GradingSession::STATUS_FINALIZED))
            ->orderByDesc('grading_session_id')
            ->value('grading_session_id');
        $records = $latestGradingSessionId === null
            ? collect()
            : ScoreRecord::query()
                ->where('admission_attempt_id', $attempt->id)
                ->where('grading_session_id', $latestGradingSessionId)
                ->with('aptitudeArea')
                ->orderBy('aptitude_area_id')
                ->orderByDesc('id')
                ->get();

        return $this->projectLegacyRecords($records, $attempt, $requestedScale);
    }

    /**
     * Return examinee-mapped results for a grading session using the typed projection.
     *
     * @return array<int, array{
     *     attempt_id: int,
     *     administration_id: int|null,
     *     instrument_id: int|null,
     *     form_code: string|null,
     *     status: string,
     *     provenance: string,
     *     metrics: list<array<string, mixed>>,
     * }>
     */
    public function forGradingSession(GradingSession $session, ?string $requestedScale = null): array
    {
        $rosterAttemptIds = GradingRoster::queryFor($session)
            ->pluck('admission_attempt_id')
            ->all();

        $scoredAttemptIds = $session->scoreRecords()
            ->pluck('admission_attempt_id')
            ->all();

        /** @var list<int> $allAttemptIds */
        $allAttemptIds = array_values(array_unique(array_filter([
            ...$rosterAttemptIds,
            ...$scoredAttemptIds,
        ])));

        if ($allAttemptIds === []) {
            $assignmentAttemptIds = ExamAssignment::query()
                ->where('exam_session_id', $session->exam_session_id)
                ->active()
                ->pluck('admission_attempt_id')
                ->all();
            $allAttemptIds = array_values(array_unique(array_filter($assignmentAttemptIds)));
        }

        if ($allAttemptIds === []) {
            return [];
        }

        /** @var Collection<int, AdmissionAttempt> $attempts */
        $attempts = AdmissionAttempt::query()
            ->whereIn('id', $allAttemptIds)
            ->with('identity')
            ->get()
            ->keyBy('id');

        /** @var Collection<int, Collection<int, AssessmentAdministration>> $administrations */
        $administrations = AssessmentAdministration::query()
            ->whereIn('admission_attempt_id', $allAttemptIds)
            ->whereHas('examAssignment', function (Builder $query) use ($session): void {
                $query->where('exam_session_id', $session->exam_session_id);
            })
            ->active()
            ->with([
                'testInstrumentVersion.instrument',
                'testInstrumentVersion.metrics.construct',
                'testInstrumentVersion.subtests',
                'currentRawResultRevisions',
                'currentConversionRevision',
            ])
            ->orderByDesc('binding_revision')
            ->orderByDesc('id')
            ->get()
            ->groupBy('admission_attempt_id');

        /** @var Collection<int, AssessmentResultSet> $resultSets */
        $resultSets = AssessmentResultSet::query()
            ->whereHas(
                'administration.examAssignment',
                fn (Builder $query): Builder => $query->where('exam_session_id', $session->exam_session_id),
            )
            ->where('grading_session_id', $session->id)
            ->where('status', AssessmentResultSet::STATUS_FINALIZED)
            ->with([
                'administration.testInstrumentVersion.instrument',
                'administration.testInstrumentVersion.metrics.construct',
                'administration.testInstrumentVersion.subtests',
            ])
            ->orderByDesc('id')
            ->get();

        $resultSetsByAdminId = $resultSets->keyBy('administration_id');
        $resultSetsByAttemptId = $resultSets->groupBy(fn (AssessmentResultSet $rs): ?int => $rs->administration?->admission_attempt_id);

        /** @var Collection<int, Collection<int, ScoreRecord>> $scoreRecords */
        $scoreRecords = ScoreRecord::query()
            ->whereIn('admission_attempt_id', $allAttemptIds)
            ->where('grading_session_id', $session->id)
            ->with('aptitudeArea')
            ->orderBy('aptitude_area_id')
            ->orderByDesc('id')
            ->get()
            ->groupBy('admission_attempt_id');

        $results = [];

        foreach ($allAttemptIds as $attemptId) {
            $attempt = $attempts->get($attemptId);
            if ($attempt === null) {
                continue;
            }

            $admin = $administrations->get($attemptId)?->first();
            $historicalSets = $resultSetsByAttemptId->get($attemptId) ?? collect();
            $resultSet = $admin !== null
                ? $resultSetsByAdminId->get($admin->id)
                : ($historicalSets->count() === 1 ? $historicalSets->first() : null);

            if ($resultSet !== null) {
                $results[$attemptId] = $this->projectFinalizedResultSet($resultSet, $attempt, $requestedScale, $admin ?? $resultSet->administration);
            } elseif ($admin !== null && $admin->testInstrumentVersion !== null) {
                $results[$attemptId] = $this->projectProfileAdministration($admin, $attempt, $requestedScale);
            } else {
                $records = $scoreRecords->get($attemptId) ?? collect();
                $results[$attemptId] = $this->projectLegacyRecords($records, $attempt, $requestedScale);
            }
        }

        return $results;
    }

    /**
     * @return array{
     *     attempt_id: int,
     *     administration_id: int|null,
     *     instrument_id: int|null,
     *     form_code: string|null,
     *     status: string,
     *     provenance: string,
     *     metrics: list<array<string, mixed>>,
     * }
     */
    private function projectFinalizedResultSet(
        AssessmentResultSet $resultSet,
        AdmissionAttempt $attempt,
        ?string $requestedScale = null,
        ?AssessmentAdministration $administration = null,
    ): array {
        $administration = $administration ?? $resultSet->administration;
        if ($administration !== null && ! $administration->relationLoaded('testInstrumentVersion')) {
            $administration->loadMissing([
                'testInstrumentVersion.instrument',
                'testInstrumentVersion.metrics.construct',
                'testInstrumentVersion.subtests',
            ]);
        }

        $version = $administration?->testInstrumentVersion;
        $instrument = $version?->instrument;
        $instrumentId = $instrument?->id !== null ? (int) $instrument->id : ($version?->test_instrument_id !== null ? (int) $version->test_instrument_id : null);
        $formCode = $version?->form_code;

        $rawVector = (array) ($resultSet->raw_revision_vector_json ?? []);
        $convVector = (array) ($resultSet->conversion_revision_vector_json ?? []);
        $typedOutputs = (array) ($convVector['typed_outputs'] ?? $convVector);

        $metrics = [];
        $versionMetrics = $version?->metrics ?? collect();

        if ($versionMetrics->isNotEmpty()) {
            foreach ($versionMetrics as $metric) {
                $code = (string) $metric->code;
                /** @var list<string> $supportedScales */
                $supportedScales = (array) ($metric->required_output_scales ?? []);
                $isScaleSupported = $requestedScale === null || in_array($requestedScale, $supportedScales, true);

                $rawEntry = $rawVector[$code] ?? $rawVector[strtoupper($code)] ?? null;
                $rawScore = null;
                if (is_array($rawEntry) && isset($rawEntry['raw_value']) && is_numeric($rawEntry['raw_value'])) {
                    $rawScore = (float) $rawEntry['raw_value'];
                } elseif (is_numeric($rawEntry)) {
                    $rawScore = (float) $rawEntry;
                }

                $metricOutputs = (array) ($typedOutputs[$code] ?? $typedOutputs[strtoupper($code)] ?? $convVector[$code] ?? []);
                if ($metricOutputs === [] && $metric->construct?->code !== null) {
                    $constructCode = (string) $metric->construct->code;
                    $metricOutputs = (array) ($typedOutputs[$constructCode] ?? $typedOutputs[strtoupper($constructCode)] ?? $convVector[$constructCode] ?? []);
                }
                $scale = null;
                $convertedScore = null;
                $percentile = null;
                $status = 'finalized';
                $reason = null;

                if ($requestedScale !== null) {
                    if ($isScaleSupported) {
                        $scale = $requestedScale;
                        if (isset($metricOutputs[$requestedScale])) {
                            $convertedScore = $metricOutputs[$requestedScale];
                            $percentile = in_array($requestedScale, ['percentile_rank', 'percentile', 'converted_score'], true)
                                && is_numeric($convertedScore)
                                ? (float) $convertedScore
                                : (isset($metricOutputs['percentile_rank']) && is_numeric($metricOutputs['percentile_rank'])
                                    ? (float) $metricOutputs['percentile_rank']
                                    : (isset($metricOutputs['percentile']) && is_numeric($metricOutputs['percentile']) ? (float) $metricOutputs['percentile'] : null));
                        } else {
                            $status = 'unavailable';
                            $reason = "Scale '{$requestedScale}' was not computed for metric '{$code}'.";
                        }
                    } else {
                        $scale = 'unavailable';
                        $status = 'unavailable';
                        $reason = "Scale '{$requestedScale}' is not supported for metric '{$code}'.";
                    }
                } else {
                    $scoreKey = $this->preferredConvertedOutputKey($metricOutputs);
                    if ($scoreKey !== null) {
                        $scale = $scoreKey;
                        $convertedScore = $metricOutputs[$scoreKey];
                        $percentile = is_numeric($convertedScore) ? (float) $convertedScore : null;
                    } else {
                        $scale = ! empty($supportedScales) ? $supportedScales[0] : null;
                    }
                }

                $metrics[] = [
                    'code' => $code,
                    'name' => (string) $metric->name,
                    'rule_kind' => (string) $metric->rule_kind,
                    'unit' => $metric->unit,
                    'min_raw_value' => $metric->min_raw_value !== null ? (float) $metric->min_raw_value : null,
                    'max_raw_value' => $metric->max_raw_value !== null ? (float) $metric->max_raw_value : null,
                    'precision_scale' => $metric->precision_scale !== null ? (int) $metric->precision_scale : null,
                    'is_required' => (bool) ($metric->is_required ?? true),
                    'required_output_scales' => $supportedScales,
                    'construct_code' => $metric->construct?->code,
                    'construct_name' => $metric->construct?->name,
                    'aptitude_area_id' => $metric->construct_aptitude_area_id,
                    'raw_score' => $rawScore,
                    'score' => $rawScore,
                    'converted_score' => $convertedScore,
                    'percentile' => $percentile,
                    'scale' => $scale,
                    'outputs' => $metricOutputs,
                    'source' => ($convVector['source'] ?? null) === 'manual_converted'
                        || ($rawVector === [] && $percentile !== null)
                        ? 'manual_converted'
                        : ($convVector['source'] ?? 'norm_converted'),
                    'provenance' => 'profile_bound',
                    'status' => ($rawVector === [] && $percentile !== null) || ($convVector['source'] ?? null) === 'manual_converted'
                        ? 'valid'
                        : $status,
                    'reason' => $reason,
                ];
            }
        } elseif ($administration !== null && is_array($administration->required_metric_set)) {
            foreach ($administration->required_metric_set as $metricDef) {
                $code = (string) ($metricDef['code'] ?? 'METRIC');
                /** @var list<string> $supportedScales */
                $supportedScales = (array) ($metricDef['required_output_scales'] ?? []);
                $isScaleSupported = $requestedScale === null || in_array($requestedScale, $supportedScales, true);

                $rawEntry = $rawVector[$code] ?? $rawVector[strtoupper($code)] ?? null;
                $rawScore = null;
                if (is_array($rawEntry) && isset($rawEntry['raw_value']) && is_numeric($rawEntry['raw_value'])) {
                    $rawScore = (float) $rawEntry['raw_value'];
                } elseif (is_numeric($rawEntry)) {
                    $rawScore = (float) $rawEntry;
                }

                $metricOutputs = (array) ($typedOutputs[$code] ?? $typedOutputs[strtoupper($code)] ?? $convVector[$code] ?? []);
                $metricSource = ($convVector['source'] ?? null) === 'manual_converted'
                    || ($rawVector === [] && $this->preferredConvertedOutputKey($metricOutputs) !== null)
                    ? 'manual_converted'
                    : ($convVector['source'] ?? 'norm_converted');
                $scale = null;
                $convertedScore = null;
                $percentile = null;
                $status = 'finalized';
                $reason = null;

                if ($requestedScale !== null) {
                    if ($isScaleSupported) {
                        $scale = $requestedScale;
                        if (isset($metricOutputs[$requestedScale])) {
                            $convertedScore = $metricOutputs[$requestedScale];
                            $percentile = is_numeric($convertedScore) ? (float) $convertedScore : null;
                        } else {
                            $status = 'unavailable';
                            $reason = "Scale '{$requestedScale}' was not computed for metric '{$code}'.";
                        }
                    } else {
                        $scale = 'unavailable';
                        $status = 'unavailable';
                        $reason = "Scale '{$requestedScale}' is not supported for metric '{$code}'.";
                    }
                } else {
                    $scoreKey = $this->preferredConvertedOutputKey($metricOutputs);
                    if ($scoreKey !== null) {
                        $scale = $scoreKey;
                        $convertedScore = $metricOutputs[$scoreKey];
                        $percentile = is_numeric($convertedScore) ? (float) $convertedScore : null;
                    } else {
                        $scale = ! empty($supportedScales) ? $supportedScales[0] : null;
                    }
                }

                $metrics[] = [
                    'code' => $code,
                    'name' => (string) ($metricDef['name'] ?? $code),
                    'rule_kind' => (string) ($metricDef['rule_kind'] ?? TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT),
                    'unit' => $metricDef['unit'] ?? null,
                    'min_raw_value' => isset($metricDef['min_raw_value']) && is_numeric($metricDef['min_raw_value']) ? (float) $metricDef['min_raw_value'] : null,
                    'max_raw_value' => isset($metricDef['max_raw_value']) && is_numeric($metricDef['max_raw_value']) ? (float) $metricDef['max_raw_value'] : null,
                    'precision_scale' => isset($metricDef['precision_scale']) ? (int) $metricDef['precision_scale'] : null,
                    'is_required' => true,
                    'required_output_scales' => $supportedScales,
                    'construct_code' => null,
                    'construct_name' => null,
                    'aptitude_area_id' => null,
                    'raw_score' => $rawScore,
                    'score' => $rawScore,
                    'converted_score' => $convertedScore,
                    'percentile' => $percentile,
                    'outputs' => $metricOutputs,
                    'source' => $metricSource,
                    'provenance' => 'profile_bound',
                    'status' => $metricSource === 'manual_converted' ? 'valid' : $status,
                    'reason' => $reason,
                ];
            }
        }

        return [
            'attempt_id' => (int) $attempt->id,
            'administration_id' => $administration !== null ? (int) $administration->id : null,
            'instrument_id' => $instrumentId,
            'form_code' => $formCode,
            'status' => 'finalized',
            'provenance' => 'profile_bound',
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{
     *     attempt_id: int,
     *     administration_id: int|null,
     *     instrument_id: int|null,
     *     form_code: string|null,
     *     status: string,
     *     provenance: string,
     *     metrics: list<array<string, mixed>>,
     * }
     */
    private function projectProfileAdministration(
        AssessmentAdministration $administration,
        AdmissionAttempt $attempt,
        ?string $requestedScale = null,
    ): array {
        /** @var TestInstrumentVersion $version */
        $version = $administration->testInstrumentVersion;

        return $this->projectProfileVersion($version, $attempt, $requestedScale, $administration);
    }

    /**
     * @return array{
     *     attempt_id: int,
     *     administration_id: int|null,
     *     instrument_id: int|null,
     *     form_code: string|null,
     *     status: string,
     *     provenance: string,
     *     metrics: list<array<string, mixed>>,
     * }
     */
    private function projectProfileVersion(
        TestInstrumentVersion $version,
        AdmissionAttempt $attempt,
        ?string $requestedScale = null,
        ?AssessmentAdministration $administration = null,
    ): array {
        $instrument = $version->instrument;
        $instrumentId = $instrument?->id !== null ? (int) $instrument->id : (int) $version->test_instrument_id;
        $formCode = $version->form_code;

        $metrics = $this->projectProfileMetrics($version, $attempt, $requestedScale, $administration);

        $status = $administration?->binding_state ?? 'awaiting_norm_conversion';

        return [
            'attempt_id' => (int) $attempt->id,
            'administration_id' => $administration !== null ? (int) $administration->id : null,
            'instrument_id' => $instrumentId,
            'form_code' => $formCode,
            'status' => $status,
            'provenance' => 'profile_bound',
            'metrics' => $metrics,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function projectProfileMetrics(
        TestInstrumentVersion $version,
        AdmissionAttempt $attempt,
        ?string $requestedScale = null,
        ?AssessmentAdministration $administration = null,
    ): array {
        $versionMetrics = $version->metrics;
        $metricEntries = [];

        $rawRevisions = collect();
        $conversionSource = null;

        if ($administration !== null) {
            $rawRevisions = $administration->relationLoaded('currentRawResultRevisions')
                ? $administration->currentRawResultRevisions->keyBy('metric_code')
                : AssessmentRawResultRevision::query()
                    ->where('administration_id', $administration->id)
                    ->where('is_current', true)
                    ->get()
                    ->keyBy('metric_code');

            /** @var AssessmentConversionRevision|null $convRev */
            $convRev = $administration->relationLoaded('currentConversionRevision')
                ? $administration->currentConversionRevision
                : AssessmentConversionRevision::query()
                    ->where('administration_id', $administration->id)
                    ->where('is_current', true)
                    ->first();

            $conversionSource = $convRev?->source;
            if ($convRev !== null && is_array($convRev->typed_outputs_json)) {
                $conversionOutputs = $convRev->typed_outputs_json;
            }
        }

        $bindingState = $administration?->binding_state ?? 'awaiting_norm_conversion';

        if ($versionMetrics->isNotEmpty()) {
            foreach ($versionMetrics as $metric) {
                $code = (string) $metric->code;
                /** @var list<string> $supportedScales */
                $supportedScales = (array) ($metric->required_output_scales ?? []);
                $isScaleSupported = $requestedScale === null || in_array($requestedScale, $supportedScales, true);

                $metricOutputs = (array) ($conversionOutputs[$code] ?? $conversionOutputs[strtoupper($code)] ?? []);
                if ($metricOutputs === [] && $metric->construct?->code !== null) {
                    $constructCode = (string) $metric->construct->code;
                    $metricOutputs = (array) ($conversionOutputs[$constructCode] ?? $conversionOutputs[strtoupper($constructCode)] ?? []);
                }

                $scale = null;
                $convertedScore = null;
                $percentile = null;
                $status = ! empty($metricOutputs) ? $bindingState : 'awaiting_norm_conversion';
                $reason = ! empty($metricOutputs) ? null : 'Awaiting approved norm conversion for bound instrument profile.';

                if ($requestedScale !== null) {
                    if ($isScaleSupported) {
                        $scale = $requestedScale;
                        if (isset($metricOutputs[$requestedScale])) {
                            $convertedScore = $metricOutputs[$requestedScale];
                            $percentile = is_numeric($convertedScore) ? (float) $convertedScore : null;
                            $status = $conversionSource === 'manual_converted' ? 'valid' : $bindingState;
                            $reason = null;
                        } else {
                            $status = ! empty($metricOutputs) ? 'unavailable' : 'awaiting_norm_conversion';
                            $reason = ! empty($metricOutputs)
                                ? "Scale '{$requestedScale}' was not computed for metric '{$code}'."
                                : 'Awaiting approved norm conversion for bound instrument profile.';
                        }
                    } else {
                        $scale = 'unavailable';
                        $status = 'unavailable';
                        $reason = "Scale '{$requestedScale}' is not supported for metric '{$code}'.";
                    }
                } else {
                    $scoreKey = $this->preferredConvertedOutputKey($metricOutputs);
                    if ($scoreKey !== null) {
                        $scale = $scoreKey;
                        $convertedScore = $metricOutputs[$scoreKey];
                        $percentile = is_numeric($convertedScore) ? (float) $convertedScore : null;
                        $status = $conversionSource === 'manual_converted' ? 'valid' : $bindingState;
                        $reason = null;
                    } elseif (! empty($supportedScales)) {
                        $scale = $supportedScales[0];
                    }
                }

                $rawScore = null;
                $rawRev = $rawRevisions->get($code) ?? $rawRevisions->get(strtoupper($code));
                if ($rawRev !== null && is_numeric($rawRev->raw_value)) {
                    $rawScore = (float) $rawRev->raw_value;
                }

                $metricEntries[] = [
                    'code' => $code,
                    'name' => (string) $metric->name,
                    'rule_kind' => (string) $metric->rule_kind,
                    'unit' => $metric->unit,
                    'min_raw_value' => $metric->min_raw_value !== null ? (float) $metric->min_raw_value : null,
                    'max_raw_value' => $metric->max_raw_value !== null ? (float) $metric->max_raw_value : null,
                    'precision_scale' => $metric->precision_scale !== null ? (int) $metric->precision_scale : null,
                    'is_required' => (bool) ($metric->is_required ?? true),
                    'required_output_scales' => $supportedScales,
                    'construct_code' => $metric->construct?->code,
                    'construct_name' => $metric->construct?->name,
                    'aptitude_area_id' => $metric->construct_aptitude_area_id,
                    'raw_score' => $rawScore,
                    'score' => $rawScore,
                    'converted_score' => $convertedScore,
                    'percentile' => $percentile,
                    'scale' => $scale,
                    'outputs' => $metricOutputs,
                    'source' => $conversionSource ?? ($rawScore === null && $percentile !== null ? 'manual_converted' : 'norm_converted'),
                    'provenance' => 'profile_bound',
                    'status' => $conversionSource === 'manual_converted' || ($rawScore === null && $percentile !== null)
                        ? 'valid'
                        : $status,
                    'reason' => $reason,
                ];
            }
        } elseif ($administration !== null && is_array($administration->required_metric_set)) {
            foreach ($administration->required_metric_set as $metricDef) {
                $code = (string) ($metricDef['code'] ?? 'METRIC');
                /** @var list<string> $supportedScales */
                $supportedScales = (array) ($metricDef['required_output_scales'] ?? []);
                $isScaleSupported = $requestedScale === null || in_array($requestedScale, $supportedScales, true);

                $metricOutputs = (array) ($conversionOutputs[$code] ?? $conversionOutputs[strtoupper($code)] ?? []);

                $scale = null;
                $convertedScore = null;
                $percentile = null;
                $status = ! empty($metricOutputs) ? $bindingState : 'awaiting_norm_conversion';
                $reason = ! empty($metricOutputs) ? null : 'Awaiting approved norm conversion for bound instrument profile.';

                if ($requestedScale !== null) {
                    if ($isScaleSupported) {
                        $scale = $requestedScale;
                        if (isset($metricOutputs[$requestedScale])) {
                            $convertedScore = $metricOutputs[$requestedScale];
                            $percentile = ($requestedScale === 'percentile' && is_numeric($convertedScore))
                                ? (float) $convertedScore
                                : (isset($metricOutputs['percentile']) && is_numeric($metricOutputs['percentile']) ? (float) $metricOutputs['percentile'] : null);
                            $status = $bindingState;
                            $reason = null;
                        } else {
                            $status = ! empty($metricOutputs) ? 'unavailable' : 'awaiting_norm_conversion';
                            $reason = ! empty($metricOutputs)
                                ? "Scale '{$requestedScale}' was not computed for metric '{$code}'."
                                : 'Awaiting approved norm conversion for bound instrument profile.';
                        }
                    } else {
                        $scale = 'unavailable';
                        $status = 'unavailable';
                        $reason = "Scale '{$requestedScale}' is not supported for metric '{$code}'.";
                    }
                } else {
                    $scoreKey = $this->preferredConvertedOutputKey($metricOutputs);
                    if ($scoreKey !== null) {
                        $scale = $scoreKey;
                        $convertedScore = $metricOutputs[$scoreKey];
                        $percentile = is_numeric($convertedScore) ? (float) $convertedScore : null;
                        $status = $conversionSource === 'manual_converted' ? 'valid' : $bindingState;
                        $reason = null;
                    } elseif (! empty($supportedScales)) {
                        $scale = $supportedScales[0];
                    }
                }

                $rawScore = null;
                $rawRev = $rawRevisions->get($code) ?? $rawRevisions->get(strtoupper($code));
                if ($rawRev !== null && is_numeric($rawRev->raw_value)) {
                    $rawScore = (float) $rawRev->raw_value;
                }

                $metricEntries[] = [
                    'code' => $code,
                    'name' => (string) ($metricDef['name'] ?? $code),
                    'rule_kind' => (string) ($metricDef['rule_kind'] ?? TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT),
                    'unit' => $metricDef['unit'] ?? null,
                    'min_raw_value' => isset($metricDef['min_raw_value']) && is_numeric($metricDef['min_raw_value']) ? (float) $metricDef['min_raw_value'] : null,
                    'max_raw_value' => isset($metricDef['max_raw_value']) && is_numeric($metricDef['max_raw_value']) ? (float) $metricDef['max_raw_value'] : null,
                    'precision_scale' => isset($metricDef['precision_scale']) ? (int) $metricDef['precision_scale'] : null,
                    'is_required' => true,
                    'required_output_scales' => $supportedScales,
                    'construct_code' => null,
                    'construct_name' => null,
                    'aptitude_area_id' => null,
                    'raw_score' => $rawScore,
                    'score' => $rawScore,
                    'converted_score' => $convertedScore,
                    'scale' => $scale,
                    'outputs' => $metricOutputs,
                    'source' => $conversionSource ?? ($rawScore === null && $percentile !== null ? 'manual_converted' : 'norm_converted'),
                    'provenance' => 'profile_bound',
                    'status' => $conversionSource === 'manual_converted' || ($rawScore === null && $percentile !== null)
                        ? 'valid'
                        : $status,
                    'reason' => $reason,
                ];
            }
        }

        return $metricEntries;
    }

    /** @param array<string, mixed> $outputs */
    private function preferredConvertedOutputKey(array $outputs): ?string
    {
        foreach (['percentile_rank', 'percentile', 'converted_score', 'category', 'stanine'] as $scale) {
            if (isset($outputs[$scale]) && is_scalar($outputs[$scale])) {
                return $scale;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, ScoreRecord>|array<int, ScoreRecord>  $records
     * @return array{
     *     attempt_id: int,
     *     administration_id: null,
     *     instrument_id: null,
     *     form_code: null,
     *     status: string,
     *     provenance: string,
     *     metrics: list<array<string, mixed>>,
     * }
     */
    private function projectLegacyRecords(
        Collection|array $records,
        AdmissionAttempt $attempt,
        ?string $requestedScale = null,
    ): array {
        $collection = $records instanceof Collection ? $records : collect($records);

        /** @var Collection<int, ScoreRecord> $uniqueRecords */
        $uniqueRecords = $collection
            ->unique('aptitude_area_id')
            ->sortBy(fn (ScoreRecord $r) => $r->aptitudeArea?->id ?? $r->aptitude_area_id)
            ->values();

        $mappedMetrics = [];

        foreach ($uniqueRecords as $record) {
            $area = $record->aptitudeArea;
            $rawScore = is_numeric($record->score) ? (float) $record->score : $record->score;
            $convertedScore = $record->converted_score;

            $scale = 'untyped_score';
            $status = 'untyped';
            $reason = 'Legacy score record does not contain verified percentile or typed scale metadata.';

            if ($requestedScale !== null && $requestedScale !== 'untyped_score') {
                $status = 'unavailable';
                $reason = "Requested scale '{$requestedScale}' is unavailable for legacy untyped scores.";
            }

            $mappedMetrics[] = [
                'code' => (string) ($area?->code ?? ('AREA_'.$record->aptitude_area_id)),
                'name' => (string) ($area?->name ?? ('Aptitude Area '.$record->aptitude_area_id)),
                'aptitude_area_id' => (int) $record->aptitude_area_id,
                'raw_score' => $rawScore,
                'score' => $record->score,
                'converted_score' => $convertedScore,
                'provenance' => 'legacy_untyped',
                'scale' => 'untyped_score',
                'percentile' => null,
                'source' => $record->source,
                'status' => $status,
                'reason' => $reason,
            ];
        }

        return [
            'attempt_id' => (int) $attempt->id,
            'administration_id' => null,
            'instrument_id' => null,
            'form_code' => null,
            'status' => $mappedMetrics === [] ? 'unscored' : 'legacy_untyped',
            'provenance' => 'legacy_untyped',
            'metrics' => $mappedMetrics,
        ];
    }
}
