<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\IndividualResultMutationFence;
use App\Assessment\Support\ScoreConverter;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Examination\AssessmentAdministration;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConvertAssessmentResults
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly IndividualResultMutationFence $mutationFence,
        private readonly ScoreConverter $scoreConverter = new ScoreConverter,
    ) {}

    /**
     * Compute and freeze typed conversion outputs against exact raw and norm revisions.
     */
    public function handle(
        AssessmentAdministration $administration,
        AssessmentNormPackageVersion $normVersion,
        User $actor,
    ): AssessmentConversionRevision {
        $academicCycleId = (int) DB::table('assessment_administrations')
            ->join('exam_assignments', 'exam_assignments.id', '=', 'assessment_administrations.exam_assignment_id')
            ->join('exam_sessions', 'exam_sessions.id', '=', 'exam_assignments.exam_session_id')
            ->where('assessment_administrations.id', $administration->id)
            ->value('exam_sessions.academic_cycle_id');
        if ($academicCycleId < 1) {
            throw new RuntimeException('assessment_conversion_exact_sitting_required');
        }

        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            $academicCycleId,
            fn (): AssessmentConversionRevision => DB::transaction(function () use ($administration, $normVersion, $actor): AssessmentConversionRevision {
                /** @var AssessmentAdministration $lockedAdmin */
                $lockedAdmin = AssessmentAdministration::query()
                    ->lockForUpdate()
                    ->findOrFail($administration->id);
                $lockedAdmin->load([
                    'testInstrumentVersion.instrument',
                    'testInstrumentVersion.metrics',
                    'admissionAttempt',
                    'examAssignment.examSession.academicCycle',
                    'testKitVersion',
                ]);

                $cycle = $lockedAdmin->examAssignment?->examSession?->academicCycle;
                $institutionId = (int) ($cycle?->institution_id ?? 0);
                $assignment = $lockedAdmin->examAssignment;
                $this->mutationFence->assertMutable($lockedAdmin);
                if ($institutionId < 1
                    || $lockedAdmin->superseded_at !== null
                    || $assignment === null
                    || ! $assignment->isActive()
                    || (int) $assignment->admission_attempt_id !== (int) $lockedAdmin->admission_attempt_id
                    || $lockedAdmin->admissionAttempt?->status !== AdmissionAttempt::STATUS_ACCEPTED
                    || (int) $lockedAdmin->admissionAttempt?->institution_id !== $institutionId
                    || $lockedAdmin->test_kit_version_id === null
                    || (int) $lockedAdmin->testKitVersion?->test_instrument_version_id !== (int) $lockedAdmin->test_instrument_version_id
                    || ! hash_equals((string) $lockedAdmin->manifest_sha256, (string) $lockedAdmin->testKitVersion?->manifest_sha256)
                    || AssessmentAdministration::query()
                        ->where('exam_assignment_id', $assignment->id)
                        ->whereNull('superseded_at')
                        ->where('id', '!=', $lockedAdmin->id)
                        ->exists()) {
                    throw new RuntimeException('assessment_conversion_requires_current_exact_sitting_binding');
                }
                $scope = [
                    'institution_id' => $institutionId,
                    'academic_cycle_id' => (int) $cycle->id,
                    'office' => 'guidance',
                ];
                if (! $this->authorizer->decideFresh(
                    $actor,
                    CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
                    $scope,
                )->allowed) {
                    throw new AuthorizationException('unauthorized_norm_conversion');
                }

                /** @var Collection<string, AssessmentRawResultRevision> $rawRevisions */
                $rawRevisions = $lockedAdmin->rawRevisions()
                    ->where('is_current', true)
                    ->where('status', 'valid')
                    ->orderBy('metric_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('metric_code');
                $currentConversion = AssessmentConversionRevision::query()
                    ->where('administration_id', $lockedAdmin->id)
                    ->where('is_current', true)
                    ->lockForUpdate()
                    ->first();
                $typedOutputs = [];

                $normVersion = AssessmentNormPackageVersion::query()
                    ->whereKey($normVersion->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $normVersion->load(['normPackage', 'tables']);

                if ($normVersion->status !== AssessmentNormPackageVersion::STATUS_APPROVED) {
                    throw new RuntimeException("assessment_norm_version_not_approved: Norm package version must be approved (current status: '{$normVersion->status}').");
                }

                if ((int) $normVersion->normPackage?->institution_id !== $institutionId) {
                    throw new RuntimeException('assessment_norm_package_institution_mismatch: Norm package must belong to the administration institution.');
                }

                $administrationFormCode = $lockedAdmin->testInstrumentVersion?->form_code;
                if (
                    $normVersion->form_code !== null
                    && $administrationFormCode !== null
                    && $normVersion->form_code !== $administrationFormCode
                ) {
                    throw new RuntimeException("assessment_norm_form_code_mismatch: Norm package form '{$normVersion->form_code}' does not match administration profile form '{$administrationFormCode}'.");
                }

                if (
                    $normVersion->normPackage?->is_synthetic === true
                    && $lockedAdmin->testInstrumentVersion?->instrument?->is_synthetic !== true
                ) {
                    throw new RuntimeException('assessment_synthetic_norm_package_requires_synthetic_instrument: Synthetic norm packages cannot convert scores on a real institutional instrument.');
                }
                if ($rawRevisions->isEmpty()) {
                    throw new RuntimeException('assessment_raw_results_missing: No valid current raw revisions found for administration.');
                }

                /** @var Collection<int, AssessmentNormTable> $tables */
                $tables = $normVersion->tables;

                $versionMetrics = $lockedAdmin->testInstrumentVersion?->metrics?->keyBy('code');

                /** @var array<string, list<string>> $metricsToConvert */
                $metricsToConvert = [];

                if (! empty($lockedAdmin->required_metric_set) && is_array($lockedAdmin->required_metric_set)) {
                    foreach ($lockedAdmin->required_metric_set as $item) {
                        $code = is_array($item) ? ($item['code'] ?? null) : (is_string($item) ? $item : null);
                        if ($code === null) {
                            continue;
                        }

                        $scales = is_array($item) ? ($item['required_output_scales'] ?? []) : [];
                        if (empty($scales) && $versionMetrics !== null && $versionMetrics->has($code)) {
                            $scales = $versionMetrics->get($code)?->required_output_scales ?? [];
                        }

                        $metricsToConvert[(string) $code] = (array) $scales;
                    }
                } elseif ($versionMetrics !== null && $versionMetrics->isNotEmpty()) {
                    foreach ($versionMetrics as $m) {
                        if ($m->is_required ?? true) {
                            $metricsToConvert[(string) $m->code] = (array) ($m->required_output_scales ?? []);
                        }
                    }
                }

                if (empty($metricsToConvert)) {
                    foreach ($rawRevisions->keys() as $metricCode) {
                        $metricsToConvert[(string) $metricCode] = [];
                    }
                }

                /** @var list<array{administration_id: int, metric_id: int, raw_result_revision_id: int}> $rawDependencies */
                $rawDependencies = [];

                foreach ($metricsToConvert as $metricCode => $scales) {
                    if (! $rawRevisions->has($metricCode)) {
                        throw new RuntimeException("assessment_raw_metric_missing: Missing valid current raw revision for required metric '{$metricCode}'.");
                    }

                    $rawRevision = $rawRevisions->get($metricCode);
                    $rawDependencies[] = [
                        'administration_id' => (int) $lockedAdmin->id,
                        'metric_id' => (int) $rawRevision->metric_id,
                        'raw_result_revision_id' => (int) $rawRevision->id,
                    ];
                    $rawValue = $rawRevision->raw_value;

                    if (empty($scales)) {
                        $scales = $tables->where('metric_code', $metricCode)
                            ->pluck('output_scale')
                            ->unique()
                            ->values()
                            ->all();

                        if (empty($scales)) {
                            throw new RuntimeException("assessment_norm_table_missing: No norm tables found for metric '{$metricCode}'.");
                        }
                    }

                    $hasStanineTable = $tables->contains(
                        fn (AssessmentNormTable $table): bool => $table->metric_code === $metricCode && $table->output_scale === 'stanine'
                    );
                    if (! $hasStanineTable) {
                        unset($typedOutputs[$metricCode]['stanine']);
                    }
                    $hasPercentileRankTable = $tables->contains(
                        fn (AssessmentNormTable $table): bool => $table->metric_code === $metricCode && $table->output_scale === 'percentile_rank'
                    );
                    if (in_array('stanine', $scales, true) && ! $hasStanineTable && ! $hasPercentileRankTable) {
                        throw new RuntimeException("assessment_norm_table_missing: Missing percentile rank norm table for metric '{$metricCode}'.");
                    }
                    if (in_array('stanine', $scales, true) && ! $hasStanineTable
                        && $hasPercentileRankTable
                        && ! in_array('percentile_rank', $scales, true)) {
                        $scales[] = 'percentile_rank';
                    }

                    foreach (array_unique($scales) as $scale) {
                        if ($scale === 'stanine' && ! $hasStanineTable) {
                            continue;
                        }

                        $matchingTable = $tables->first(
                            fn (AssessmentNormTable $table): bool => $table->metric_code === $metricCode && $table->output_scale === $scale
                        );

                        if ($matchingTable === null) {
                            throw new RuntimeException("assessment_norm_table_missing: Missing norm table for metric '{$metricCode}' and output scale '{$scale}'.");
                        }

                        $metric = $versionMetrics?->get($metricCode);
                        if ($metric instanceof TestInstrumentMetric) {
                            $this->assertDecimalGridCoverage($metric, $matchingTable);
                        }
                        $outputValue = $this->scoreConverter->convertViaNormTable($matchingTable, $rawValue);

                        if ($outputValue === null) {
                            throw new RuntimeException("assessment_conversion_failed: Raw value '{$rawValue}' for metric '{$metricCode}' could not be mapped in norm table for '{$scale}'.");
                        }

                        $typedOutputs[$metricCode][$scale] = (string) $outputValue;
                        if ($scale === 'percentile_rank') {
                            $typedOutputs[$metricCode]['converted_score'] = (string) $outputValue;
                        }
                    }

                }
                usort(
                    $rawDependencies,
                    static fn (array $left, array $right): int => $left['metric_id'] <=> $right['metric_id'],
                );
                $rawDependencySha256 = hash(
                    'sha256',
                    json_encode($rawDependencies, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                );

                $now = Carbon::now();

                $latestRevNo = (int) AssessmentConversionRevision::query()
                    ->where('administration_id', $lockedAdmin->id)
                    ->max('revision_no');

                $nextRevisionNo = $latestRevNo + 1;

                $conversionRevision = new AssessmentConversionRevision;
                $conversionRevision->forceFill([
                    'administration_id' => $lockedAdmin->id,
                    'norm_package_version_id' => $normVersion->id,
                    'source' => AssessmentConversionRevision::SOURCE_NORM_CONVERTED,
                    'revision_no' => $nextRevisionNo,
                    'typed_outputs_json' => $typedOutputs,
                    'raw_dependency_sha256' => $rawDependencySha256,
                    'status' => AssessmentConversionRevision::STATUS_PENDING,
                    'converted_by_user_id' => $actor->id,
                    'converted_at' => $now,
                    'is_current' => false,
                    'superseded_at' => null,
                ]);
                $conversionRevision->save();

                foreach ($rawDependencies as $dependency) {
                    DB::table('assessment_conversion_raw_dependencies')->insert([
                        'conversion_revision_id' => $conversionRevision->id,
                        ...$dependency,
                    ]);
                }

                AssessmentConversionRevision::query()
                    ->where('administration_id', $lockedAdmin->id)
                    ->where('is_current', true)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (AssessmentConversionRevision $prior) use ($now): void {
                        $prior->update([
                            'is_current' => false,
                            'status' => AssessmentConversionRevision::STATUS_SUPERSEDED,
                            'superseded_at' => $now,
                        ]);
                    });

                $conversionRevision->status = AssessmentConversionRevision::STATUS_VALID;
                $conversionRevision->is_current = true;
                $conversionRevision->save();

                $lockedAdmin->binding_state = AssessmentAdministration::STATE_READY_TO_FINALIZE;
                $lockedAdmin->save();
                $administration->binding_state = AssessmentAdministration::STATE_READY_TO_FINALIZE;
                $this->recordAuditEvent->handle(
                    'assessment.results_converted',
                    [
                        'administration_id' => $lockedAdmin->id,
                        'norm_package_version_id' => $normVersion->id,
                        'conversion_revision_id' => $conversionRevision->id,
                        'revision_no' => $nextRevisionNo,
                        'actor_id' => $actor->id,
                    ],
                    (string) $actor->id,
                );

                return $conversionRevision;
            }),
        );
    }

    private function assertDecimalGridCoverage(TestInstrumentMetric $metric, AssessmentNormTable $table): void
    {
        $intervals = is_array($table->intervals_json) ? $table->intervals_json : [];
        if ($intervals === []) {
            throw new RuntimeException("assessment_norm_table_missing: No intervals found for metric '{$metric->code}'.");
        }
        $precision = max(0, (int) $metric->precision_scale);
        $ranges = [];
        try {
            foreach ($intervals as $interval) {
                if (! is_array($interval)
                    || ! array_key_exists('min_score', $interval)
                    || ! array_key_exists('max_score', $interval)) {
                    throw new \InvalidArgumentException;
                }
                $minimum = BigDecimal::of((string) $interval['min_score'])
                    ->toScale($precision, RoundingMode::Unnecessary);
                $maximum = BigDecimal::of((string) $interval['max_score'])
                    ->toScale($precision, RoundingMode::Unnecessary);
                if ($minimum->compareTo($maximum) > 0) {
                    throw new \InvalidArgumentException;
                }
                $ranges[] = [$minimum, $maximum];
            }
        } catch (\InvalidArgumentException|NumberFormatException|RoundingNecessaryException) {
            throw new RuntimeException("assessment_norm_grid_incompatible: Norm table for '{$metric->code}' is not expressed on the metric's declared decimal grid.");
        }

        usort($ranges, static fn (array $left, array $right): int => $left[0]->compareTo($right[0]));
        $step = BigDecimal::of($precision === 0 ? '1' : '0.'.str_repeat('0', $precision - 1).'1');
        $previousMaximum = null;
        foreach ($ranges as [$minimum, $maximum]) {
            if ($previousMaximum !== null
                && ($minimum->compareTo($previousMaximum) <= 0
                    || $minimum->compareTo($previousMaximum->plus($step)) !== 0)) {
                throw new RuntimeException("assessment_norm_grid_incompatible: Norm table for '{$metric->code}' has an overlap or gap on the metric's declared decimal grid.");
            }
            $previousMaximum = $maximum;
        }
    }
}
