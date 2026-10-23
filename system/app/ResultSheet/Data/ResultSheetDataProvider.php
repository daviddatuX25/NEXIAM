<?php

namespace App\ResultSheet\Data;

use App\Admission\Support\ApplicantReference;
use App\Admission\Support\GwaScaleNormalizer;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Program;
use App\Models\Release\ConsultationDecision;
use App\ResultSheet\Support\TemplateShortValue;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Builds the applicant data array consumed by the placeholder engine for one
 * admission attempt. When a frozen individual AssessmentResultSet exists for
 * the exact current sitting, scores come from its immutable vectors without
 * requiring the parent grading session to be finalized.
 *
 * @phpstan-type ApplicantData array{id: int, name: string, family_name: string, first_name: string, middle_name: string, suffix: string, sex: string, gwa: string, course_applied: string, course_applied_code: string, last_school_enrolled: string, last_school_short: string, strand: string, strand_or_prev_course: string, strand_or_prev_course_short: string, applicant_type: string, reference: string, exam_date: string, exam_time: string, exam_date_start: string, exam_date_end: string, exam_time_start: string, exam_time_end: string, room_name: string, scores: list<array{domain: string, area_code: string, raw: string, max: int, converted: string, rating: string, pct: int, scale: string|null}>, overall_pct: int|null, recommended_course: string, recommended_course_code: string, counselor_comments: string, counselor_name: string}
 */
final class ResultSheetDataProvider
{
    /**
     * @return ApplicantData
     */
    public function forAttempt(AdmissionAttempt $attempt, ?AssessmentResultSet $frozenResultSet = null): array
    {
        $identity = $attempt->identity;
        if ($frozenResultSet !== null
            && (int) $frozenResultSet->administration?->admission_attempt_id !== (int) $attempt->id) {
            throw new InvalidArgumentException('The frozen result set does not belong to this applicant.');
        }
        $grading = $frozenResultSet !== null ? $frozenResultSet->gradingSession : $this->currentGradingFor($attempt);
        $resultSet = $frozenResultSet ?? $this->currentResultSetFor($attempt, $grading);

        $isProfileBound = $resultSet !== null
            || $grading?->examSession?->test_instrument_version_id !== null
            || AssessmentAdministration::query()
                ->where('admission_attempt_id', $attempt->id)
                ->whereNull('superseded_at')
                ->exists();

        if ($resultSet !== null) {
            $scores = $this->scoresForResultSet($resultSet);
            $overallPct = $this->overallPctForResultSet($resultSet);
        } else {
            $scores = $grading === null
                || $isProfileBound
                || $grading->status !== GradingSession::STATUS_FINALIZED
                ? []
                : $this->scoresFor($grading, $attempt->id);
            // A reportable overall must come from an explicitly defined instrument metric.
            $overallPct = null;
        }
        $exam = $this->examFor($attempt, $grading);
        $recommended = $this->recommendedProgram($attempt);
        $decision = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->with('counselor')
            ->latest('id')
            ->first();

        $baseData = [
            'id' => $attempt->id,
            'name' => trim(implode(' ', array_filter([
                $identity?->first_name,
                $identity?->middle_name,
                $identity?->last_name,
                $identity?->suffix,
            ]))),
            'family_name' => $identity?->last_name ?? '',
            'first_name' => $identity?->first_name ?? '',
            'middle_name' => $identity?->middle_name ?? '',
            'suffix' => $identity?->suffix ?? '',
            'sex' => $identity?->sex ?? '',
            ...(\Closure::fromCallable(static function () use ($attempt): array {
                $gwaInfo = GwaScaleNormalizer::fromAttempt($attempt);

                return [
                    'gwa' => $gwaInfo['formatted_normalized'],
                    'gwa_original' => $gwaInfo['formatted_original'],
                    'gwa_scale' => $gwaInfo['gwa_scale_label'],
                    'gwa_display' => $gwaInfo['formatted_combined'],
                ];
            }))(),
            'course_applied' => $this->programName($attempt->program_preference_1_code),
            'course_applied_code' => (string) ($attempt->program_preference_1_code ?? ''),
            ...TemplateShortValue::fields(
                $attempt->last_school_enrolled,
                $attempt->strand,
                $attempt->applicant_type === 'new' ? 'history' : 'previous-course',
            ),
            'strand' => (string) ($attempt->strand ?? ''),
            'strand_or_prev_course' => (string) ($attempt->strand ?? $attempt->last_school_enrolled ?? ''),
            'applicant_type' => (string) ($attempt->applicant_type ?? ''),
            'reference' => $attempt->reference_code,
            'examinee_number' => ApplicantReference::paddedDigits($attempt->reference_code, $attempt->academicCycle) ?? (string) $attempt->reference_code,
            'sequence_number' => ApplicantReference::compactNumber($attempt->reference_code, $attempt->academicCycle) ?? (string) $attempt->reference_code,
            'exam_date' => $exam['date'],
            'exam_time' => $exam['time'],
            'exam_date_start' => $exam['date'],
            'exam_date_end' => $exam['date'],
            'exam_time_start' => $exam['time'],
            'exam_time_end' => $exam['time_end'],
            'room_name' => $exam['room'],
            'scores' => $scores,
            'overall_pct' => $overallPct,
            'recommended_course' => $recommended['name'],
            'recommended_course_code' => $recommended['code'],
            'counselor_comments' => (string) ($decision?->notes ?? ''),
            'counselor_name' => (string) ($decision?->counselor?->name ?? ''),
        ];

        if ($resultSet !== null) {
            $templateFields = $this->templateFieldsForResultSet($resultSet);

            return array_merge($baseData, $templateFields);
        }

        return $baseData;
    }

    /**
     * @param  list<int>  $attemptIds
     * @return array<int, ApplicantData>
     */
    public function forAttempts(array $attemptIds): array
    {
        $attempts = AdmissionAttempt::query()
            ->with('identity')
            ->whereIn('id', $attemptIds)
            ->get()
            ->keyBy('id');

        $data = [];

        foreach ($attemptIds as $id) {
            $attempt = $attempts->get($id);

            if ($attempt === null) {
                continue;
            }

            $data[$id] = $this->forAttempt($attempt);
        }

        return $data;
    }

    private function currentGradingFor(AdmissionAttempt $attempt): ?GradingSession
    {
        $assignment = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->with('examSession.gradingSession')
            ->orderByDesc('id')
            ->first();

        return $assignment?->examSession?->gradingSession;
    }

    private function currentResultSetFor(AdmissionAttempt $attempt, ?GradingSession $grading): ?AssessmentResultSet
    {
        if ($grading === null) {
            return null;
        }

        $assignment = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->where('exam_session_id', $grading->exam_session_id)
            ->orderByDesc('id')
            ->first();
        if ($assignment === null) {
            return null;
        }

        $administrations = AssessmentAdministration::query()
            ->active()
            ->where('exam_assignment_id', $assignment->id)
            ->where('admission_attempt_id', $attempt->id)
            ->orderBy('id')
            ->get(['id']);
        if ($administrations->count() !== 1) {
            return null;
        }

        $resultSets = AssessmentResultSet::query()
            ->where('administration_id', $administrations->first()->id)
            ->where('grading_session_id', $grading->id)
            ->where('exam_session_id', $assignment->exam_session_id)
            ->where('status', AssessmentResultSet::STATUS_FINALIZED)
            ->with([
                'administration.testInstrumentVersion.instrument',
                'administration.testInstrumentVersion.metrics.construct',
                'administration.testInstrumentVersion.subtests',
            ])
            ->orderBy('id')
            ->get();

        return $resultSets->count() === 1 ? $resultSets->first() : null;
    }

    /**
     * @return list<array{domain: string, area_code: string, raw: string, max: int, converted: string, rating: string, pct: int, scale: string|null}>
     */
    private function scoresForResultSet(AssessmentResultSet $resultSet): array
    {
        $rawVector = (array) ($resultSet->raw_revision_vector_json ?? []);
        $convVector = (array) ($resultSet->conversion_revision_vector_json ?? []);
        $typedOutputs = (array) ($convVector['typed_outputs'] ?? $convVector);
        $metrics = $resultSet->administration?->testInstrumentVersion?->metrics ?? collect();
        $scores = [];

        foreach ($metrics as $metric) {
            $code = (string) $metric->code;
            $construct = $metric->construct;
            $rawEntry = $rawVector[$code] ?? $rawVector[strtoupper($code)] ?? null;
            $raw = is_array($rawEntry) && isset($rawEntry['raw_value'])
                ? number_format((float) $rawEntry['raw_value'], 2, '.', '')
                : (is_numeric($rawEntry) ? number_format((float) $rawEntry, 2, '.', '') : '');
            $metricOutputs = (array) ($typedOutputs[$code] ?? $typedOutputs[strtoupper($code)] ?? $convVector[$code] ?? []);
            if ($metricOutputs === [] && $construct?->code !== null) {
                $constructCode = (string) $construct->code;
                $metricOutputs = (array) ($typedOutputs[$constructCode] ?? $typedOutputs[strtoupper($constructCode)] ?? $convVector[$constructCode] ?? []);
            }

            $scale = null;
            $converted = '';
            foreach (['percentile_rank', 'percentile', 'converted_score'] as $candidateScale) {
                if (array_key_exists($candidateScale, $metricOutputs) && is_numeric($metricOutputs[$candidateScale])) {
                    $scale = $candidateScale;
                    $converted = (string) $metricOutputs[$candidateScale];
                    break;
                }
            }
            if ($converted === '' && is_scalar($metricOutputs['category'] ?? null)) {
                $converted = (string) $metricOutputs['category'];
            }
            $scores[] = [
                'domain' => (string) ($construct?->name ?? $metric->name ?? $code),
                'area_code' => (string) ($construct?->code ?? $code),
                'raw' => $raw,
                'max' => (int) ($metric->max_raw_value ?? $construct?->max_items ?? 0),
                'converted' => $converted,
                'rating' => $scale === 'percentile_rank' ? $converted.' percentile rank' : $converted,
                'pct' => $scale !== null && is_numeric($converted) ? (int) round((float) $converted) : 0,
                'scale' => $scale,
            ];
        }

        if ($scores !== []) {
            return $scores;
        }

        foreach (array_unique(array_merge(array_keys($rawVector), array_keys($typedOutputs))) as $code) {
            if (in_array($code, ['typed_outputs', 'status', 'conversion_revision_id', 'norm_package_version_id', 'revision_no'], true)) {
                continue;
            }
            $rawEntry = $rawVector[$code] ?? null;
            $raw = is_array($rawEntry) && isset($rawEntry['raw_value'])
                ? number_format((float) $rawEntry['raw_value'], 2, '.', '')
                : (is_numeric($rawEntry) ? number_format((float) $rawEntry, 2, '.', '') : '');
            $outputs = (array) ($typedOutputs[$code] ?? []);
            $scale = null;
            $converted = '';
            foreach (['percentile_rank', 'percentile', 'converted_score'] as $candidateScale) {
                if (array_key_exists($candidateScale, $outputs) && is_numeric($outputs[$candidateScale])) {
                    $scale = $candidateScale;
                    $converted = (string) $outputs[$candidateScale];
                    break;
                }
            }
            if ($converted === '' && is_scalar($outputs['category'] ?? null)) {
                $converted = (string) $outputs['category'];
            }
            $scores[] = [
                'domain' => (string) $code,
                'area_code' => (string) $code,
                'raw' => $raw,
                'max' => 0,
                'converted' => $converted,
                'rating' => $scale === 'percentile_rank' ? $converted.' percentile rank' : $converted,
                'pct' => $scale !== null && is_numeric($converted) ? (int) round((float) $converted) : 0,
                'scale' => $scale,
            ];
        }

        return $scores;
    }

    private function overallPctForResultSet(AssessmentResultSet $resultSet): ?int
    {
        $convVector = (array) ($resultSet->conversion_revision_vector_json ?? []);
        $typedOutputs = (array) ($convVector['typed_outputs'] ?? $convVector);
        $overallCodes = ['OVERALL', 'COMPOSITE', 'GENERAL_ABILITY', 'GA', 'TOTAL'];
        $administration = $resultSet->administration;
        $definedOverallCodes = [];

        foreach ($administration?->testInstrumentVersion?->metrics ?? collect() as $metric) {
            $code = strtoupper((string) $metric->code);
            $isNamedOverall = in_array($code, $overallCodes, true);
            $isNamedRawComposite = str_ends_with($code, '_RAW')
                && in_array($metric->rule_kind, [
                    TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                    TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
                ], true)
                && in_array(substr($code, 0, -4), $overallCodes, true);
            if ($isNamedOverall || $isNamedRawComposite) {
                $definedOverallCodes[] = $code;
            }
        }

        foreach ((array) ($administration?->required_metric_set ?? []) as $metricDefinition) {
            if (! is_array($metricDefinition) || ! isset($metricDefinition['code'])) {
                continue;
            }

            $code = strtoupper((string) $metricDefinition['code']);
            $ruleKind = (string) ($metricDefinition['rule_kind'] ?? '');
            $isNamedOverall = in_array($code, $overallCodes, true);
            $isNamedRawComposite = str_ends_with($code, '_RAW')
                && in_array($ruleKind, [
                    TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                    TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
                ], true)
                && in_array(substr($code, 0, -4), $overallCodes, true);

            if ($isNamedOverall || $isNamedRawComposite) {
                $definedOverallCodes[] = $code;
            }
        }

        foreach ($typedOutputs as $metricCode => $outputs) {
            if (! in_array(strtoupper((string) $metricCode), $definedOverallCodes, true) || ! is_array($outputs)) {
                continue;
            }

            foreach (['percentile_rank', 'percentile', 'converted_score'] as $scale) {
                $percentile = $outputs[$scale] ?? null;
                if (is_numeric($percentile)) {
                    return min(100, max(0, (int) round((float) $percentile)));
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function templateFieldsForResultSet(AssessmentResultSet $resultSet): array
    {
        $convVector = (array) ($resultSet->conversion_revision_vector_json ?? []);
        $vectorOutputs = (array) ($convVector['typed_outputs'] ?? $convVector);
        $supportedScales = array_flip([...TestInstrumentMetric::OUTPUT_SCALES, 'percentile', 'converted_score']);
        $typedOutputs = [];

        foreach ($vectorOutputs as $metricCode => $scales) {
            if (! is_array($scales)) {
                continue;
            }

            $metricScales = array_intersect_key($scales, $supportedScales);
            if ($metricScales !== []) {
                $typedOutputs[(string) $metricCode] = $metricScales;
            }
        }

        $fields = [
            'result_set_id' => $resultSet->id,
            'result_set_status' => $resultSet->status,
            'finalized_at' => $resultSet->finalized_at?->toIso8601String() ?? '',
            'finalized_scales' => $typedOutputs,
        ];

        foreach ($typedOutputs as $metricCode => $scales) {
            $codeSlug = Str::slug((string) $metricCode, '_');
            $codeLower = strtolower((string) $metricCode);

            foreach ($scales as $scaleName => $scaleValue) {
                if (! is_scalar($scaleValue)) {
                    continue;
                }

                $scaleSlug = Str::slug((string) $scaleName, '_');
                $fields[$metricCode.'_'.$scaleName] = (string) $scaleValue;
                $fields[$codeSlug.'_'.$scaleSlug] = (string) $scaleValue;
                $fields[$codeLower.'_'.$scaleSlug] = (string) $scaleValue;
            }
        }

        return $fields;
    }

    /**
     * @return list<array{domain: string, area_code: string, raw: string, max: int, converted: string, rating: string, pct: int, scale: string|null}>
     */
    private function scoresFor(GradingSession $grading, int $attemptId): array
    {
        $records = ScoreRecord::query()
            ->where('grading_session_id', $grading->id)
            ->where('admission_attempt_id', $attemptId)
            ->with('aptitudeArea')
            ->get();

        $areas = AptitudeArea::query()
            ->where('is_active', true)
            ->get(['id', 'code', 'name', 'max_items'])
            ->keyBy('id');

        $scores = [];

        foreach ($records as $record) {
            $area = $areas->get($record->aptitude_area_id);

            if ($area === null) {
                continue;
            }

            $raw = (string) $record->score;
            $converted = (string) ($record->converted_score ?? '');
            $max = (int) ($area->max_items ?? 0);

            $pct = $max > 0 ? min(100, max(0, (int) round(((float) $raw / $max) * 100))) : 0;
            $scores[] = [
                'domain' => (string) $area->name,
                'area_code' => (string) $area->code,
                'raw' => $raw,
                'max' => $max,
                'converted' => $converted,
                'rating' => $converted,
                'pct' => $pct,
                'scale' => null,
            ];
        }

        return $scores;
    }

    /**
     * @return array{date: string, time: string, time_end: string, room: string}
     */
    private function examFor(AdmissionAttempt $attempt, ?GradingSession $grading): array
    {
        $examSession = $grading?->examSession ?? ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->latest('id')
            ->first()?->examSession;

        if ($examSession === null) {
            return ['date' => '', 'time' => '', 'time_end' => '', 'room' => ''];
        }

        return [
            'date' => $examSession->starts_at?->format('F d, Y') ?? '',
            'time' => $examSession->starts_at?->format('g:i A') ?? '',
            'time_end' => $examSession->effective_end_at?->format('g:i A') ?? '',
            'room' => (string) ($examSession->room?->name ?? ''),
        ];
    }

    /**
     * @return array{name: string, code: string}
     */
    private function recommendedProgram(AdmissionAttempt $attempt): array
    {
        $selection = ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('source', ProgramSelection::SOURCE_GUIDANCE_DEFAULT)
            ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
            ->with('program')
            ->latest('id')
            ->first();

        $code = $selection?->program?->code ?? $attempt->program_preference_1_code ?? '';
        $name = $selection?->program?->name ?? $this->programName($code);

        return ['name' => $name, 'code' => (string) $code];
    }

    private function programName(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        return (string) Program::query()->where('code', $code)->value('name');
    }
}
