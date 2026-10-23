<?php

namespace App\Assessment\Support;

use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AptitudeArea;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

class AssessmentAdministrationFinalizationPredicate
{
    public const STATE_RAW_INCOMPLETE = 'raw_incomplete';

    public const STATE_AWAITING_NORM_CONVERSION = 'awaiting_norm_conversion';

    public const STATE_READY_TO_FINALIZE = 'ready_to_finalize';

    public const STATE_FINALIZED = 'finalized';

    public function __construct(
        private readonly ScoreConverter $scoreConverter,
    ) {}

    /**
     * Evaluate one legacy attempt using the finalization rule for its exact
     * grading-session ledger.
     *
     * @param  Collection<int, AptitudeArea>  $areas
     * @param  Collection<int, ScoreRecord>  $scoreRecords
     * @return array{ok: bool, reason: ?string, state: string, details: array<string, mixed>}
     */
    public function evaluateLegacyIndividual(Collection $areas, Collection $scoreRecords): array
    {
        if ($areas->isEmpty()) {
            return [
                'ok' => false,
                'reason' => 'No active aptitude areas configured for grading.',
                'state' => self::STATE_RAW_INCOMPLETE,
                'details' => ['mode' => 'legacy', 'areas_count' => 0],
            ];
        }

        $recordsByArea = $scoreRecords->keyBy('aptitude_area_id');
        foreach ($areas as $area) {
            $record = $recordsByArea->get($area->id);
            if (! $record instanceof ScoreRecord) {
                return [
                    'ok' => false,
                    'reason' => 'A required legacy aptitude-area score is missing.',
                    'state' => self::STATE_RAW_INCOMPLETE,
                    'details' => ['mode' => 'legacy', 'aptitude_area_id' => (int) $area->id],
                ];
            }

            $rawScore = is_numeric($record->score) ? (int) round((float) $record->score) : false;
            if ($rawScore === false || $rawScore < 0 || $rawScore > (int) $area->max_items) {
                return [
                    'ok' => false,
                    'reason' => 'A required legacy aptitude-area score is outside its supported range.',
                    'state' => self::STATE_RAW_INCOMPLETE,
                    'details' => ['mode' => 'legacy', 'aptitude_area_id' => (int) $area->id],
                ];
            }

            if ($area->scoring_method === AptitudeArea::SCORING_METHOD_CONVERSION_TABLE
                && (! $area->relationLoaded('conversionRows')
                    || $this->scoreConverter->resolve($area->conversionRows->all(), $rawScore) === null)) {
                return [
                    'ok' => false,
                    'reason' => 'A required legacy aptitude-area score has no supported conversion.',
                    'state' => self::STATE_RAW_INCOMPLETE,
                    'details' => ['mode' => 'legacy', 'aptitude_area_id' => (int) $area->id],
                ];
            }
        }

        return [
            'ok' => true,
            'reason' => null,
            'state' => self::STATE_READY_TO_FINALIZE,
            'details' => ['mode' => 'legacy', 'areas_count' => $areas->count()],
        ];
    }

    /**
     * Evaluate the exact current administration's raw and typed final evidence.
     * This is shared by session finalization and individual readiness.
     *
     * @param  Collection<int, ScoreRecord>  $scoreRecords
     * @param  Collection<int, AssessmentRawResultRevision>  $currentRawRevisions
     * @return array{ok: bool, reason: ?string, state: string, details: array<string, mixed>}
     */
    public function evaluateAdministrationIndividual(
        AssessmentAdministration $administration,
        Collection $scoreRecords,
        Collection $currentRawRevisions,
        ?AssessmentConversionRevision $currentConversion,
    ): array {
        $requiredMetrics = $this->resolveRequiredMetrics($administration);
        if ($requiredMetrics === []) {
            return [
                'ok' => false,
                'reason' => 'Bound test instrument profile has no required metrics defined.',
                'state' => self::STATE_RAW_INCOMPLETE,
                'details' => ['mode' => 'profile_bound', 'administration_id' => (int) $administration->id],
            ];
        }

        $metricCodes = [];
        foreach ($requiredMetrics as $metric) {
            $code = $metric['code'] ?? null;
            $scales = array_values(array_unique(array_map('strval', (array) ($metric['required_output_scales'] ?? []))));
            if (! is_string($code) || $code === '' || isset($metricCodes[$code]) || $scales === []) {
                return [
                    'ok' => false,
                    'reason' => 'Bound test instrument profile has an invalid required score contract.',
                    'state' => self::STATE_RAW_INCOMPLETE,
                    'details' => ['mode' => 'profile_bound', 'administration_id' => (int) $administration->id],
                ];
            }
            $metricCodes[$code] = true;
        }

        if ($currentConversion?->source === AssessmentConversionRevision::SOURCE_MANUAL_CONVERTED) {
            if ($this->isValidExternalFinal($administration, $currentConversion, $requiredMetrics)) {
                return [
                    'ok' => true,
                    'reason' => null,
                    'state' => self::STATE_READY_TO_FINALIZE,
                    'details' => [
                        'mode' => 'profile_bound',
                        'scoring_mode' => 'manual_converted',
                        'administration_id' => (int) $administration->id,
                        'conversion_revision_id' => (int) $currentConversion->id,
                    ],
                ];
            }

            return [
                'ok' => false,
                'reason' => 'External final outputs or attribution do not match the bound profile.',
                'state' => self::STATE_RAW_INCOMPLETE,
                'details' => ['mode' => 'profile_bound', 'administration_id' => (int) $administration->id],
            ];
        }
        if (collect($requiredMetrics)->contains(fn (array $metric): bool => ($metric['rule_kind'] ?? null) === TestInstrumentMetric::RULE_EXTERNAL_FINAL)) {
            return [
                'ok' => false,
                'reason' => 'Awaiting complete supplied final aptitude scores and source references.',
                'state' => self::STATE_RAW_INCOMPLETE,
                'details' => ['mode' => 'profile_bound', 'scoring_mode' => 'manual_converted', 'administration_id' => (int) $administration->id],
            ];
        }

        $rawByCode = [];
        foreach ($currentRawRevisions->where('status', AssessmentRawResultRevision::STATUS_VALID) as $rawRevision) {
            $code = (string) $rawRevision->metric_code;
            if (isset($rawByCode[$code])) {
                return [
                    'ok' => false,
                    'reason' => 'More than one current raw revision exists for a required metric.',
                    'state' => self::STATE_RAW_INCOMPLETE,
                    'details' => ['mode' => 'profile_bound', 'administration_id' => (int) $administration->id],
                ];
            }
            $rawByCode[$code] = $rawRevision;
        }

        $requiredRawByCode = [];
        foreach ($requiredMetrics as $metric) {
            $code = (string) $metric['code'];
            $rawRevision = $rawByCode[$code] ?? null;
            $rawValue = $rawRevision instanceof AssessmentRawResultRevision && is_numeric($rawRevision->raw_value)
                ? (float) $rawRevision->raw_value
                : null;
            if ($rawValue === null || ! is_finite($rawValue)
                || (isset($metric['min_raw_value']) && $metric['min_raw_value'] !== null && $rawValue < (float) $metric['min_raw_value'])
                || (isset($metric['max_raw_value']) && $metric['max_raw_value'] !== null && $rawValue > (float) $metric['max_raw_value'])) {
                return [
                    'ok' => false,
                    'reason' => 'Incomplete scores: a required current raw metric is missing or invalid.',
                    'state' => self::STATE_RAW_INCOMPLETE,
                    'details' => [
                        'mode' => 'profile_bound',
                        'administration_id' => (int) $administration->id,
                        'metric_code' => $code,
                    ],
                ];
            }
            $requiredRawByCode[$code] = $rawRevision;
        }

        if ($currentConversion === null
            || ! $currentConversion->is_current
            || $currentConversion->status !== AssessmentConversionRevision::STATUS_VALID
            || $currentConversion->superseded_at !== null
            || $currentConversion->source !== AssessmentConversionRevision::SOURCE_NORM_CONVERTED
            || ! $this->hasCompleteTypedOutputs((array) $currentConversion->typed_outputs_json, $requiredMetrics)
            || ! $this->hasValidCurrentNormSource($administration, $currentConversion)
            || ! $this->hasCurrentRawDependencies($administration, $currentConversion, $requiredRawByCode)) {
            return [
                'ok' => false,
                'reason' => 'Awaiting approved norm conversion for the current required raw revisions.',
                'state' => self::STATE_AWAITING_NORM_CONVERSION,
                'details' => ['mode' => 'profile_bound', 'administration_id' => (int) $administration->id],
            ];
        }

        return [
            'ok' => true,
            'reason' => null,
            'state' => self::STATE_READY_TO_FINALIZE,
            'details' => [
                'mode' => 'profile_bound',
                'scoring_mode' => 'norm_converted',
                'administration_id' => (int) $administration->id,
                'conversion_revision_id' => (int) $currentConversion->id,
            ],
        ];
    }

    /**
     * Evaluate finalization readiness for a grading session.
     *
     * @return array{ok: bool, reason: ?string, state: string, details: array<string, mixed>}
     */
    public function evaluate(GradingSession $session): array
    {
        if ($session->status === GradingSession::STATUS_FINALIZED) {
            $eligibleAssignments = GradingRoster::queryFor($session)->get();
            $eligibleCount = $eligibleAssignments->count();

            return [
                'ok' => true,
                'reason' => null,
                'state' => self::STATE_FINALIZED,
                'details' => [
                    'mode' => 'finalized',
                    'eligible_count' => $eligibleCount,
                    'scored_count' => $eligibleCount,
                    'finalized_at' => $session->finalized_at?->toIso8601String(),
                ],
            ];
        }

        $sessionAssignmentQuery = ExamAssignment::query()->where('exam_session_id', $session->exam_session_id);

        $hasBindings = AssessmentAdministration::query()
            ->whereIn('exam_assignment_id', $sessionAssignmentQuery->select('id'))
            ->exists();

        $examSession = $session->examSession ?? ExamSession::query()->find($session->exam_session_id);
        $isProfileBound = $hasBindings || ($examSession?->test_instrument_version_id !== null);

        $eligibleAssignments = GradingRoster::queryFor($session)->get();
        $rosterAttemptIds = $eligibleAssignments->pluck('admission_attempt_id');

        if ($rosterAttemptIds->isEmpty()) {
            return [
                'ok' => false,
                'reason' => 'No applicants are assigned to this session; nothing to finalize.',
                'state' => self::STATE_RAW_INCOMPLETE,
                'details' => [
                    'mode' => $isProfileBound ? 'profile_bound' : 'legacy',
                    'eligible_count' => 0,
                    'scored_count' => 0,
                ],
            ];
        }

        if (! $isProfileBound) {
            return $this->evaluateLegacySession($session, $rosterAttemptIds);
        }

        return $this->evaluateProfileBoundSession($session, $eligibleAssignments);
    }

    /**
     * @param  Collection<int, GradingSession>  $gradingSessions
     * @return array<int, array{ok: bool, reason: ?string, state: string, details: array<string, mixed>}>
     */
    public function evaluateMany(Collection $gradingSessions): array
    {
        $evaluations = [];

        foreach ($gradingSessions as $gradingSession) {
            $evaluations[$gradingSession->id] = $this->evaluate($gradingSession);
        }

        return $evaluations;
    }

    /**
     * @param  Collection<int, int>  $rosterAttemptIds
     * @return array{ok: bool, reason: ?string, state: string, details: array<string, mixed>}
     */
    private function evaluateLegacySession(GradingSession $session, Collection $rosterAttemptIds): array
    {
        $areas = AptitudeArea::query()
            ->where('is_active', true)
            ->with('conversionRows')
            ->get();
        $recorded = $session->scoreRecords()
            ->whereIn('admission_attempt_id', $rosterAttemptIds)
            ->get()
            ->groupBy('admission_attempt_id');

        $incompleteAttemptIds = [];
        $scoredAttemptIds = [];
        foreach ($rosterAttemptIds as $attemptId) {
            $evaluation = $this->evaluateLegacyIndividual(
                $areas,
                $recorded->get($attemptId, collect()),
            );
            if (! $evaluation['ok']) {
                $incompleteAttemptIds[] = (int) $attemptId;
            } else {
                $scoredAttemptIds[] = (int) $attemptId;
            }
        }

        $eligibleCount = $rosterAttemptIds->count();
        $scoredCount = count(array_unique($scoredAttemptIds));

        if ($areas->isEmpty()) {
            return [
                'ok' => false,
                'reason' => 'No active aptitude areas configured for grading.',
                'state' => self::STATE_RAW_INCOMPLETE,
                'details' => [
                    'mode' => 'legacy',
                    'areas_count' => 0,
                    'eligible_count' => $eligibleCount,
                    'scored_count' => 0,
                ],
            ];
        }

        if ($incompleteAttemptIds !== []) {
            return [
                'ok' => false,
                'reason' => 'Incomplete scores: every applicant must be scored for every aptitude area before finalizing.',
                'state' => self::STATE_RAW_INCOMPLETE,
                'details' => [
                    'mode' => 'legacy',
                    'eligible_count' => $eligibleCount,
                    'scored_count' => $scoredCount,
                    'incomplete_count' => count(array_unique($incompleteAttemptIds)),
                    'incomplete_attempt_ids' => array_values(array_unique($incompleteAttemptIds)),
                ],
            ];
        }

        return [
            'ok' => true,
            'reason' => null,
            'state' => self::STATE_READY_TO_FINALIZE,
            'details' => [
                'mode' => 'legacy',
                'eligible_count' => $eligibleCount,
                'scored_count' => $eligibleCount,
                'areas_count' => $areas->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, ExamAssignment>  $eligibleAssignments
     * @return array{ok: bool, reason: ?string, state: string, details: array<string, mixed>}
     */
    private function evaluateProfileBoundSession(GradingSession $session, Collection $eligibleAssignments): array
    {
        $administrationsByAssignment = AssessmentAdministration::query()
            ->whereIn('exam_assignment_id', $eligibleAssignments->pluck('id'))
            ->whereNull('superseded_at')
            ->with([
                'testInstrumentVersion.metrics.construct',
                'testInstrumentVersion.instrument',
                'examAssignment.examSession.academicCycle',
                'examAssignment.examSession.gradingSession',
            ])
            ->orderBy('id')
            ->get()
            ->groupBy('exam_assignment_id');

        $missingBindingAssignmentIds = [];
        $ambiguousBindingAssignmentIds = [];
        $uniqueAdministrations = collect();
        foreach ($eligibleAssignments as $assignment) {
            $assignmentAdministrations = $administrationsByAssignment->get($assignment->id, collect());
            if ($assignmentAdministrations->isEmpty()) {
                $missingBindingAssignmentIds[] = (int) $assignment->id;
            } elseif ($assignmentAdministrations->count() > 1) {
                $ambiguousBindingAssignmentIds[] = (int) $assignment->id;
            } else {
                $uniqueAdministrations->put((int) $assignment->id, $assignmentAdministrations->first());
            }
        }

        $administrationIds = $uniqueAdministrations->pluck('id')->filter()->all();
        $attemptIds = $eligibleAssignments->pluck('admission_attempt_id');
        $recorded = $session->scoreRecords()
            ->whereIn('admission_attempt_id', $attemptIds)
            ->get()
            ->groupBy('admission_attempt_id');
        $rawRevisionsByAdmin = AssessmentRawResultRevision::query()
            ->whereIn('administration_id', $administrationIds)
            ->where('is_current', true)
            ->get()
            ->groupBy('administration_id');
        $conversionRevisionsByAdmin = AssessmentConversionRevision::query()
            ->whereIn('administration_id', $administrationIds)
            ->where('is_current', true)
            ->with(['rawDependencies', 'normPackageVersion.normPackage'])
            ->get()
            ->groupBy('administration_id');

        $scoredAttemptIds = [];
        $incompleteAttemptIds = [];
        $awaitingConversionAttemptIds = [];
        foreach ($eligibleAssignments as $assignment) {
            if (in_array((int) $assignment->id, $missingBindingAssignmentIds, true)
                || in_array((int) $assignment->id, $ambiguousBindingAssignmentIds, true)) {
                $incompleteAttemptIds[] = (int) $assignment->admission_attempt_id;

                continue;
            }

            /** @var AssessmentAdministration $administration */
            $administration = $uniqueAdministrations->get((int) $assignment->id);
            $conversionRevisions = $conversionRevisionsByAdmin->get($administration->id, collect());
            if ($conversionRevisions->count() > 1) {
                $incompleteAttemptIds[] = (int) $assignment->admission_attempt_id;

                continue;
            }

            $evaluation = $this->evaluateAdministrationIndividual(
                $administration,
                $recorded->get($assignment->admission_attempt_id, collect()),
                $rawRevisionsByAdmin->get($administration->id, collect()),
                $conversionRevisions->first(),
            );
            if ($evaluation['ok']) {
                $scoredAttemptIds[] = (int) $assignment->admission_attempt_id;

                continue;
            }

            if ($evaluation['state'] === self::STATE_AWAITING_NORM_CONVERSION) {
                $awaitingConversionAttemptIds[] = (int) $assignment->admission_attempt_id;
            } else {
                $incompleteAttemptIds[] = (int) $assignment->admission_attempt_id;
            }
        }

        $eligibleCount = $eligibleAssignments->count();
        $scoredCount = count(array_unique($scoredAttemptIds));

        if ($missingBindingAssignmentIds !== [] || $ambiguousBindingAssignmentIds !== []) {
            return [
                'ok' => false,
                'reason' => 'An exact unique active assessment administration binding is required for every eligible examinee.',
                'state' => self::STATE_RAW_INCOMPLETE,
                'details' => [
                    'mode' => 'profile_bound',
                    'eligible_count' => $eligibleCount,
                    'scored_count' => $scoredCount,
                    'missing_binding_assignment_ids' => $missingBindingAssignmentIds,
                    'ambiguous_binding_assignment_ids' => $ambiguousBindingAssignmentIds,
                    'incomplete_count' => count(array_unique($incompleteAttemptIds)),
                    'incomplete_attempt_ids' => array_values(array_unique($incompleteAttemptIds)),
                    'awaiting_conversion_attempt_ids' => array_values(array_unique($awaitingConversionAttemptIds)),
                ],
            ];
        }

        if ($incompleteAttemptIds !== []) {
            return [
                'ok' => false,
                'reason' => 'Incomplete scores: every applicant must have complete, valid typed scores for the exact bound administration.',
                'state' => self::STATE_RAW_INCOMPLETE,
                'details' => [
                    'mode' => 'profile_bound',
                    'eligible_count' => $eligibleCount,
                    'scored_count' => $scoredCount,
                    'incomplete_count' => count(array_unique($incompleteAttemptIds)),
                    'incomplete_attempt_ids' => array_values(array_unique($incompleteAttemptIds)),
                    'awaiting_conversion_attempt_ids' => array_values(array_unique($awaitingConversionAttemptIds)),
                ],
            ];
        }

        if ($awaitingConversionAttemptIds !== []) {
            return [
                'ok' => false,
                'reason' => 'Awaiting approved norm conversion for one or more current required raw vectors.',
                'state' => self::STATE_AWAITING_NORM_CONVERSION,
                'details' => [
                    'mode' => 'profile_bound',
                    'eligible_count' => $eligibleCount,
                    'scored_count' => $scoredCount,
                    'awaiting_conversion_count' => count(array_unique($awaitingConversionAttemptIds)),
                    'awaiting_conversion_attempt_ids' => array_values(array_unique($awaitingConversionAttemptIds)),
                ],
            ];
        }

        return [
            'ok' => true,
            'reason' => null,
            'state' => self::STATE_READY_TO_FINALIZE,
            'details' => [
                'mode' => 'profile_bound',
                'eligible_count' => $eligibleCount,
                'scored_count' => $eligibleCount,
                'requires_norm_conversion' => false,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $requiredMetrics
     */
    private function isValidExternalFinal(
        AssessmentAdministration $administration,
        AssessmentConversionRevision $revision,
        array $requiredMetrics,
    ): bool {
        if (! $revision->is_current
            || $revision->status !== AssessmentConversionRevision::STATUS_VALID
            || $revision->superseded_at !== null
            || $revision->norm_package_version_id !== null
            || $revision->raw_dependency_sha256 !== null
            || $revision->converted_by_user_id === null
            || $revision->converted_at === null
            || ! $revision->relationLoaded('rawDependencies')
            || $revision->rawDependencies->isNotEmpty()) {
            return false;
        }

        $provenance = (array) $revision->provenance_json;
        $externalProvenance = (array) ($provenance['external_provenance'] ?? []);
        $requiredCodes = array_map(static fn (array $metric): string => (string) $metric['code'], $requiredMetrics);
        $externalCodes = array_keys($externalProvenance);
        sort($requiredCodes);
        sort($externalCodes);
        $typedOutputs = (array) $revision->typed_outputs_json;
        $expectedGradingSessionId = (int) $administration->examAssignment?->examSession?->gradingSession?->id;
        if (($provenance['source'] ?? null) !== AssessmentConversionRevision::SOURCE_MANUAL_CONVERTED
            || $expectedGradingSessionId < 1
            || (int) ($provenance['grading_session_id'] ?? 0) !== $expectedGradingSessionId
            || (int) ($provenance['test_kit_version_id'] ?? 0) !== (int) $administration->test_kit_version_id
            || (int) ($provenance['actor_user_id'] ?? 0) < 1
            || (int) ($provenance['actor_user_id'] ?? 0) !== (int) $revision->converted_by_user_id
            || ! is_string($provenance['payload_sha256'] ?? null)
            || ! preg_match('/^[a-f0-9]{64}$/', $provenance['payload_sha256'])
            || $requiredCodes !== $externalCodes
            || ! $this->hasCompleteTypedOutputs($typedOutputs, $requiredMetrics)
            || ! hash_equals(
                $provenance['payload_sha256'],
                hash('sha256', json_encode([
                    'typed_outputs' => $typedOutputs,
                    'external_provenance' => $externalProvenance,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            )) {
            return false;
        }

        foreach ($externalProvenance as $value) {
            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $typedOutputs
     * @param  list<array<string, mixed>>  $requiredMetrics
     */
    private function hasCompleteTypedOutputs(array $typedOutputs, array $requiredMetrics): bool
    {
        if ($requiredMetrics === []) {
            return false;
        }

        $requiredCodes = array_map(static fn (array $metric): string => (string) ($metric['code'] ?? ''), $requiredMetrics);
        $outputCodes = array_keys($typedOutputs);
        sort($requiredCodes);
        sort($outputCodes);
        if ($requiredCodes !== $outputCodes) {
            return false;
        }
        foreach ($requiredMetrics as $metric) {
            $code = (string) ($metric['code'] ?? '');
            $scales = array_values(array_unique(array_map('strval', (array) ($metric['required_output_scales'] ?? []))));
            $outputs = $typedOutputs[$code] ?? null;
            if ($code === '' || $scales === [] || ! is_array($outputs)) {
                return false;
            }

            $allowedOutputKeys = $scales;
            if (in_array('percentile_rank', $scales, true)) {
                $allowedOutputKeys[] = 'converted_score';
            }
            if (array_diff(array_keys($outputs), $allowedOutputKeys) !== []) {
                return false;
            }

            foreach ($scales as $scale) {
                if (! array_key_exists($scale, $outputs) || ! $this->isValidTypedOutput($scale, $outputs[$scale])) {
                    return false;
                }
                if (($metric['rule_kind'] ?? null) === TestInstrumentMetric::RULE_EXTERNAL_FINAL
                    && BigDecimal::of((string) $outputs[$scale])->getScale() > (int) ($metric['precision_scale'] ?? 0)) {
                    return false;
                }
            }
            if (array_key_exists('converted_score', $outputs)
                && (! is_numeric($outputs['converted_score'])
                    || ! is_finite((float) $outputs['converted_score'])
                    || (float) $outputs['converted_score'] !== (float) $outputs['percentile_rank'])) {
                return false;
            }
        }

        return true;
    }

    private function isValidTypedOutput(string $scale, mixed $value): bool
    {
        if ($scale === 'category') {
            return is_string($value) && trim($value) !== '';
        }

        if (! is_numeric($value) || ! is_finite((float) $value)) {
            return false;
        }

        return match ($scale) {
            'percentile_rank' => (float) $value >= 0 && (float) $value <= 100,
            'stanine' => (float) $value >= 1 && (float) $value <= 9 && (float) $value === floor((float) $value),
            default => false,
        };
    }

    private function hasValidCurrentNormSource(
        AssessmentAdministration $administration,
        AssessmentConversionRevision $revision,
    ): bool {
        if (! $revision->relationLoaded('normPackageVersion')
            || ! $administration->relationLoaded('testInstrumentVersion')
            || ! $administration->relationLoaded('examAssignment')) {
            return false;
        }

        $normVersion = $revision->normPackageVersion;
        $profileVersion = $administration->testInstrumentVersion;
        $assignment = $administration->examAssignment;
        if ($normVersion === null
            || ! $normVersion->relationLoaded('normPackage')
            || $profileVersion === null
            || ! $profileVersion->relationLoaded('instrument')
            || $assignment === null
            || ! $assignment->relationLoaded('examSession')
            || ! $assignment->examSession?->relationLoaded('academicCycle')) {
            return false;
        }

        $normPackage = $normVersion->normPackage;
        $instrument = $profileVersion->instrument;
        $cycle = $assignment->examSession?->academicCycle;
        if ($normPackage === null || $instrument === null || $cycle === null
            || ! in_array($normVersion->status, [
                AssessmentNormPackageVersion::STATUS_APPROVED,
                AssessmentNormPackageVersion::STATUS_RETIRED,
            ], true)
            || $normVersion->approved_at === null
            || ! is_string($normVersion->content_sha256)
            || ! preg_match('/^[a-f0-9]{64}$/', $normVersion->content_sha256)
            || (int) $normPackage->institution_id !== (int) $cycle->institution_id
            || ($normPackage->is_synthetic && ! $instrument->is_synthetic)
            || ($normVersion->form_code !== null
                && $profileVersion->form_code !== null
                && $normVersion->form_code !== $profileVersion->form_code)) {
            return false;
        }

        return (int) $normVersion->id === (int) $revision->norm_package_version_id;
    }

    /**
     * @param  array<string, AssessmentRawResultRevision>  $requiredRawByCode
     */
    private function hasCurrentRawDependencies(
        AssessmentAdministration $administration,
        AssessmentConversionRevision $revision,
        array $requiredRawByCode,
    ): bool {
        if (! $revision->relationLoaded('rawDependencies')
            || ! is_string($revision->raw_dependency_sha256)
            || ! preg_match('/^[a-f0-9]{64}$/', $revision->raw_dependency_sha256)
            || $revision->norm_package_version_id === null) {
            return false;
        }

        $expectedDependencies = [];
        foreach ($requiredRawByCode as $rawRevision) {
            $expectedDependencies[] = [
                'administration_id' => (int) $administration->id,
                'metric_id' => (int) $rawRevision->metric_id,
                'raw_result_revision_id' => (int) $rawRevision->id,
            ];
        }
        usort($expectedDependencies, static fn (array $left, array $right): int => $left['metric_id'] <=> $right['metric_id']);
        $actualDependencies = $revision->rawDependencies
            ->map(static fn (AssessmentRawResultRevision $rawRevision): array => [
                'administration_id' => (int) $rawRevision->pivot->administration_id,
                'metric_id' => (int) $rawRevision->pivot->metric_id,
                'raw_result_revision_id' => (int) $rawRevision->id,
            ])
            ->sortBy('metric_id')
            ->values()
            ->all();

        return $expectedDependencies !== []
            && $expectedDependencies === $actualDependencies
            && hash_equals(
                $revision->raw_dependency_sha256,
                hash('sha256', json_encode($expectedDependencies, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveRequiredMetrics(AssessmentAdministration $administration): array
    {
        $metrics = $administration->required_metric_set ?: [];

        $profileVersion = $administration->testInstrumentVersion;
        if ($profileVersion !== null && $profileVersion->relationLoaded('metrics')) {
            $versionMetrics = $profileVersion->metrics->keyBy('code');
            if (empty($metrics)) {
                return $profileVersion->metrics
                    ->where('is_required', true)
                    ->values()
                    ->map(fn (TestInstrumentMetric $metric): array => [
                        'code' => $metric->code,
                        'construct_code' => $metric->construct?->code,
                        'name' => $metric->name,
                        'rule_kind' => $metric->rule_kind,
                        'unit' => $metric->unit,
                        'min_raw_value' => $metric->min_raw_value,
                        'max_raw_value' => $metric->max_raw_value,
                        'precision_scale' => $metric->precision_scale,
                        'required_output_scales' => $metric->required_output_scales ?? [],
                        'construct_aptitude_area_id' => $metric->construct_aptitude_area_id,
                    ])
                    ->all();
            }

            return array_values(array_map(function (array $metric) use ($versionMetrics): array {
                $versionMetric = $versionMetrics->get($metric['code'] ?? null);
                if (! isset($metric['construct_aptitude_area_id']) && $versionMetric !== null) {
                    $metric['construct_aptitude_area_id'] = $versionMetric->construct_aptitude_area_id;
                }
                if (! isset($metric['construct_code']) && $versionMetric !== null) {
                    $metric['construct_code'] = $versionMetric->construct?->code;
                }

                return $metric;
            }, $metrics));
        }

        return array_values($metrics);
    }
}
