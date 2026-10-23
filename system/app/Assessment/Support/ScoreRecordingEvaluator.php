<?php

namespace App\Assessment\Support;

use App\Examination\Support\PreflightResult;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AcademicCycle;
use RuntimeException;

final class ScoreRecordingEvaluator
{
    public function __construct(
        private readonly TestKitEligibility $eligibility,
    ) {}

    /**
     * @param  array{attempt_id:int,scores:list<array<string,mixed>>,test_kit_version_id?:int|null,norm_package_version_id?:int|null,scoring_mode?:string}  $input
     */
    public function evaluate(GradingSession $gradingSession, array $input): PreflightResult
    {
        $attemptId = $input['attempt_id'] ?? null;
        $scores = $input['scores'] ?? null;
        $scoringMode = $input['scoring_mode'] ?? 'manual_raw';
        if (! is_int($attemptId) || $attemptId < 1 || ! is_array($scores) || $scores === []) {
            throw new RuntimeException('scores_invalid');
        }

        $session = $gradingSession->loadMissing('examSession.academicCycle')->examSession;
        $cycle = $session?->academicCycle;
        $assignment = $session === null
            ? null
            : ExamAssignment::query()
                ->where('exam_session_id', $session->id)
                ->where('admission_attempt_id', $attemptId)
                ->active()
                ->first();
        $administration = $assignment === null
            ? null
            : AssessmentAdministration::query()
                ->where('exam_assignment_id', $assignment->id)
                ->active()
                ->first();
        $selectedKitId = is_numeric($input['test_kit_version_id'] ?? null)
            ? (int) $input['test_kit_version_id']
            : ($administration?->test_kit_version_id === null ? null : (int) $administration->test_kit_version_id);
        $kitVersion = $selectedKitId === null
            ? null
            : TestKitVersion::query()
                ->where('institution_id', $cycle?->institution_id)
                ->whereKey($selectedKitId)
                ->first();
        $profileMetrics = $kitVersion?->testInstrumentVersion?->metrics ?? collect();
        $isOpen = $gradingSession->status === GradingSession::STATUS_OPEN;
        $inRoster = GradingRoster::includesAttempt($gradingSession, $attemptId);
        $checks = [
            [
                'key' => 'grading_session_open',
                'label' => 'Grading session',
                'status' => $isOpen ? 'pass' : 'fail',
                'detail' => $isOpen ? 'Grading session is open' : 'Grading session is not open',
            ],
            [
                'key' => 'attempt_in_roster',
                'label' => 'Examinee roster',
                'status' => $inRoster ? 'pass' : 'fail',
                'detail' => $inRoster ? 'Examinee is eligible for scoring' : 'Examinee is not eligible for scoring',
            ],
        ];
        $assignmentIsValid = $assignment !== null && $cycle instanceof AcademicCycle;
        $checks[] = [
            'key' => 'active_assignment',
            'label' => 'Sitting assignment',
            'status' => $assignmentIsValid ? 'pass' : 'fail',
            'detail' => $assignmentIsValid ? 'Exact active assignment found' : 'An exact active assignment is required',
        ];

        $kitEligible = false;
        $purpose = $administration !== null
            && $selectedKitId !== null
            && (int) $administration->test_kit_version_id === $selectedKitId
                ? TestKitEligibility::PURPOSE_PINNED_USE
                : TestKitEligibility::PURPOSE_NEW_USE;
        $operation = $scoringMode === 'manual_converted' ? 'external_final' : 'manual';
        if ($kitVersion !== null && $cycle instanceof AcademicCycle
            && ($administration === null || (int) $administration->test_kit_version_id === (int) $kitVersion->id)) {
            $kitEligible = $this->eligibility->evaluate(
                $kitVersion,
                $cycle,
                $purpose,
                operation: $operation,
            )['eligible'];
        }
        $checks[] = [
            'key' => 'exact_kit_eligible',
            'label' => 'Exact Test Kit',
            'status' => $kitEligible ? 'pass' : 'fail',
            'detail' => $kitEligible ? 'Selected exact Test Kit supports this operation' : 'Select an eligible exact Test Kit version for this sitting',
        ];
        $normVersionId = is_numeric($input['norm_package_version_id'] ?? null)
            ? (int) $input['norm_package_version_id']
            : null;
        $normEligible = true;
        if ($scoringMode === 'manual_raw' && $normVersionId !== null) {
            $normVersion = AssessmentNormPackageVersion::query()
                ->whereKey($normVersionId)
                ->where('status', AssessmentNormPackageVersion::STATUS_APPROVED)
                ->whereHas('package', fn ($query) => $query->where('institution_id', $cycle?->institution_id))
                ->with('package')
                ->first();
            $profileVersion = $kitVersion?->testInstrumentVersion;
            $normEligible = $normVersion !== null
                && ($normVersion->form_code === null
                    || $profileVersion?->form_code === null
                    || $normVersion->form_code === $profileVersion->form_code)
                && ($normVersion->package?->is_synthetic !== true || $profileVersion?->instrument?->is_synthetic === true);
        }
        $checks[] = [
            'key' => 'explicit_norm_version',
            'label' => 'Norm package version',
            'status' => $normEligible ? 'pass' : 'fail',
            'detail' => $normVersionId === null
                ? 'No norm selected; accepted raw results remain awaiting conversion'
                : ($normEligible ? 'Explicit norm version matches the selected profile scope' : 'The explicit norm version is not approved for this profile scope'),
        ];

        $scoresValid = $kitEligible && $normEligible && $scores !== [];
        $diff = [];
        $seenExternalMetrics = [];
        if ($scoresValid) {
            foreach ($scores as $entry) {
                if (! is_array($entry)) {
                    $scoresValid = false;

                    continue;
                }
                $metric = null;
                if (isset($entry['metric_id'])) {
                    $metric = $profileMetrics->firstWhere('id', (int) $entry['metric_id']);
                } elseif (isset($entry['metric_code'])) {
                    $code = strtoupper((string) $entry['metric_code']);
                    $metric = $profileMetrics->first(fn (TestInstrumentMetric $item): bool => strtoupper($item->code) === $code);
                } elseif (isset($entry['aptitude_area_id'])) {
                    $areaId = (int) $entry['aptitude_area_id'];
                    $metric = $profileMetrics->first(fn (TestInstrumentMetric $item): bool => (int) $item->construct_aptitude_area_id === $areaId);
                }

                if (! $metric instanceof TestInstrumentMetric) {
                    $scoresValid = false;

                    continue;
                }

                if ($scoringMode === 'manual_converted') {
                    $outputs = $entry['typed_outputs'] ?? null;
                    $expectedScales = array_values($metric->required_output_scales ?? []);
                    if (! is_array($outputs)
                        || $expectedScales === []
                        || array_diff($expectedScales, array_keys($outputs)) !== []
                        || array_diff(array_keys($outputs), $expectedScales) !== []
                        || trim((string) ($entry['external_provenance'] ?? '')) === '') {
                        $scoresValid = false;

                        continue;
                    }
                    $metricCode = (string) $metric->code;
                    if (isset($seenExternalMetrics[$metricCode])) {
                        $scoresValid = false;

                        continue;
                    }
                    $seenExternalMetrics[$metricCode] = true;
                    foreach ($outputs as $value) {
                        if (! is_numeric($value) || (float) $value < 0) {
                            $scoresValid = false;
                            break;
                        }
                    }
                    $diff[] = [
                        'key' => 'metric_'.$metric->id,
                        'label' => $metric->name,
                        'before' => 'no prior external final shown',
                        'after' => json_encode($outputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
                    ];

                    continue;
                }

                $score = $entry['score'] ?? $entry['raw_score'] ?? null;
                if (! is_numeric($score)
                    || (float) $score < (float) ($metric->min_raw_value ?? 0)
                    || ($metric->max_raw_value !== null && (float) $score > (float) $metric->max_raw_value)) {
                    $scoresValid = false;

                    continue;
                }
                $diff[] = [
                    'key' => 'metric_'.$metric->id,
                    'label' => $metric->name,
                    'before' => 'current raw value',
                    'after' => (string) $score,
                ];
            }
            if ($scoringMode === 'manual_converted') {
                foreach ($profileMetrics->where('is_required', true) as $requiredMetric) {
                    if (! isset($seenExternalMetrics[(string) $requiredMetric->code])) {
                        $scoresValid = false;
                    }
                }
            }
        }
        $checks[] = [
            'key' => 'scores_within_bounds',
            'label' => $scoringMode === 'manual_converted' ? 'Typed external finals' : 'Raw scores',
            'status' => $scoresValid ? 'pass' : 'fail',
            'detail' => $scoresValid
                ? 'Scores match the exact profile metric and declared output contract'
                : 'One or more scores do not match the selected exact Kit and its metric contract',
        ];

        return new PreflightResult(
            outcome: $isOpen && $inRoster && $assignmentIsValid && $kitEligible && $normEligible && $scoresValid ? 'pass' : 'fail',
            checks: $checks,
            diff: $diff,
        );
    }
}
