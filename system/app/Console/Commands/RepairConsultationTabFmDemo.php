<?php

namespace App\Console\Commands;

use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\ImportAdmissionBenchmarkSet;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentSubtest;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RepairConsultationTabFmDemo extends Command
{
    protected $signature = 'consultation:tabfm-demo-repair {--attempt=15 : The admission attempt ID to repair}';

    protected $description = 'Repair the synthetic TabFM benchmark and typed assessment fixture for consultation';

    /** @var list<string> */
    private const APTITUDE_CODES = ['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE'];

    /** @var list<string> */
    private const PROGRAM_CODES = ['BSIT', 'BSEd', 'BEEd', 'BPEd', 'BSBA', 'BSEntrep', 'BSMath', 'BA-EL', 'BA-Psych', 'BASS', 'BPA'];

    /** @var array<string, array{percentile_rank:string}> */
    private const TYPED_OUTPUTS = [
        'LOGIC' => ['percentile_rank' => '82'],
        'PROFILE_VERBAL' => ['percentile_rank' => '78'],
        'PROFILE_QUANT' => ['percentile_rank' => '85'],
        'SPATIAL' => ['percentile_rank' => '76'],
        'MEMORY' => ['percentile_rank' => '74'],
        'LANGUAGE' => ['percentile_rank' => '72'],
    ];

    /** @var array<string, array{raw_value: string, source: string, status: string}> */
    private const RAW_OUTPUTS = [
        'LOGIC' => ['raw_value' => '21.000000', 'source' => 'synthetic-finalized-fixture', 'status' => 'valid'],
        'PROFILE_VERBAL' => ['raw_value' => '20.000000', 'source' => 'synthetic-finalized-fixture', 'status' => 'valid'],
        'PROFILE_QUANT' => ['raw_value' => '22.000000', 'source' => 'synthetic-finalized-fixture', 'status' => 'valid'],
        'SPATIAL' => ['raw_value' => '19.000000', 'source' => 'synthetic-finalized-fixture', 'status' => 'valid'],
        'MEMORY' => ['raw_value' => '18.000000', 'source' => 'synthetic-finalized-fixture', 'status' => 'valid'],
        'LANGUAGE' => ['raw_value' => '18.000000', 'source' => 'synthetic-finalized-fixture', 'status' => 'valid'],
    ];

    public function handle(
        ImportAdmissionBenchmarkSet $importBenchmarkSet,
        ActivateAdmissionBenchmarkSet $activateBenchmarkSet,
        TagudinUndergraduateCatalog $catalog,
        TestInstrumentProfileValidator $profileValidator,
    ): int {
        if (! app()->environment('local', 'testing')) {
            $this->error('This synthetic repair is restricted to local and testing environments.');

            return self::FAILURE;
        }

        $attemptId = filter_var($this->option('attempt'), FILTER_VALIDATE_INT);
        if (! is_int($attemptId) || $attemptId < 1) {
            $this->error('The --attempt value must be a positive admission attempt ID.');

            return self::FAILURE;
        }

        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $attempt = AdmissionAttempt::query()->findOrFail($attemptId);
        if ((int) $attempt->institution_id !== (int) $institution->id) {
            $this->error("Admission attempt {$attemptId} is not part of SYN-INST-001.");

            return self::FAILURE;
        }
        if ((int) $attempt->academic_cycle_id !== (int) $cycle->id) {
            $this->error("Admission attempt {$attemptId} is not part of SYN-CYCLE-2026.");

            return self::FAILURE;
        }

        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

        [$benchmarkSet, $resultSet] = DB::transaction(function () use (
            $institution,
            $cycle,
            $attempt,
            $actor,
            $catalog,
            $importBenchmarkSet,
            $activateBenchmarkSet,
            $profileValidator,
        ): array {
            $benchmarkSet = $this->ensureBenchmarkSuccessor(
                $institution,
                $cycle,
                $catalog,
                $importBenchmarkSet,
                $activateBenchmarkSet,
            );
            $profileVersion = $this->ensureTestInstrumentVersion($institution, $actor, $profileValidator);
            [$assignment, $gradingSession] = $this->ensureCompletedAssignment($attempt, $cycle, $profileVersion, $actor);
            $resultSet = $this->attachTypedAssessmentResultSet(
                $attempt,
                $assignment,
                $gradingSession,
                $profileVersion,
                $actor,
            );

            return [$benchmarkSet, $resultSet];
        });

        $activeActivation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
        if ((int) ($activeActivation?->benchmark_set_id ?? 0) !== (int) $benchmarkSet->id) {
            throw new RuntimeException('The repaired benchmark set is not the active set after activation.');
        }

        $this->info('Active benchmark set ID: '.$benchmarkSet->id);
        $this->info("Candidate {$attemptId} assessment result set ID: {$resultSet->id}");
        $this->info('Consultation TabFM demo repair confirmed: benchmark activation and typed assessment results are ready.');

        return self::SUCCESS;
    }

    private function ensureBenchmarkSuccessor(
        Institution $institution,
        AcademicCycle $cycle,
        TagudinUndergraduateCatalog $catalog,
        ImportAdmissionBenchmarkSet $importBenchmarkSet,
        ActivateAdmissionBenchmarkSet $activateBenchmarkSet,
    ): AdmissionBenchmarkSet {
        $successorName = 'Synthetic Tagudin Undergraduate Benchmark Fixture (v6)';
        $successor = AdmissionBenchmarkSet::query()
            ->where('institution_id', $institution->id)
            ->where('name', $successorName)
            ->orderByDesc('version')
            ->first();

        if ($successor === null) {
            $currentActivation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
            if ($currentActivation === null) {
                throw new RuntimeException('Cannot create the benchmark successor because the cycle has no current predecessor activation.');
            }

            $sourceSet = AdmissionBenchmarkSet::query()
                ->with(['exemplars.program', 'exemplars.values.feature'])
                ->whereKey($currentActivation->benchmark_set_id)
                ->firstOrFail();
            $programs = $this->programDefinitions($catalog);
            $exemplars = $this->copyCanonicalExemplars($sourceSet, $programs);

            $context = BenchmarkExecutionContext::trustedSeed($institution, 'seed');
            $successor = $importBenchmarkSet->handle($institution, $cycle, $context, [
                'name' => $successorName,
                'change_reason' => 'Synthetic development repair: retain the 11-program, 55-exemplar fixture with the canonical six aptitude feature schema.',
                'programs' => $programs,
                'synthesized_exemplars' => $exemplars,
            ], 'synthetic-tagudin-undergraduate-benchmark-fixture-v6');
        }

        $successor = AdmissionBenchmarkSet::query()
            ->with(['features', 'exemplars.program', 'exemplars.values.feature'])
            ->findOrFail($successor->id);
        $this->assertCanonicalSuccessor($successor);

        $currentActivation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
        if ((int) ($currentActivation?->benchmark_set_id ?? 0) !== (int) $successor->id) {
            $context = BenchmarkExecutionContext::trustedSeed($institution, 'seed');
            $currentActivationId = $currentActivation?->id;
            $activateBenchmarkSet->handle(
                $cycle,
                $successor,
                $context,
                $currentActivationId === null ? null : (int) $currentActivationId,
                (string) $successor->content_sha256,
                'synthetic-tagudin-undergraduate-benchmark-fixture-v6-activation-from-'.($currentActivationId ?? 'none'),
            );
        }

        return $successor;
    }

    /** @return list<array<string, mixed>> */
    private function programDefinitions(TagudinUndergraduateCatalog $catalog): array
    {
        $programsByCode = array_column($catalog->programs(), null, 'code');
        $programs = [];
        foreach (self::PROGRAM_CODES as $code) {
            if (! isset($programsByCode[$code])) {
                throw new RuntimeException("The Tagudin undergraduate catalog is missing program {$code}.");
            }
            $programs[] = $programsByCode[$code];
        }

        return $programs;
    }

    /**
     * @param  list<array<string, mixed>>  $programs
     * @return list<array<string, mixed>>
     */
    private function copyCanonicalExemplars(AdmissionBenchmarkSet $sourceSet, array $programs): array
    {
        $programsByCode = array_column($programs, null, 'code');
        $acceptedKeys = array_fill_keys([
            ...self::APTITUDE_CODES,
            'gwa',
            'shs_strand',
        ], true);
        $exemplars = [];

        foreach ($sourceSet->exemplars as $source) {
            $programCode = (string) ($source->program?->code ?? '');
            if ($programCode === 'BA-SocSci') {
                $programCode = 'BASS';
            }
            if (! isset($programsByCode[$programCode])) {
                throw new RuntimeException('The active predecessor contains an exemplar outside the 11-program fixture.');
            }

            $values = [];
            foreach ($source->values as $sourceValue) {
                $sourceKey = (string) $sourceValue->feature?->feature_key;
                $featureKey = $sourceKey;
                if (! isset($acceptedKeys[$featureKey])) {
                    continue;
                }

                $value = $sourceValue->is_missing
                    ? null
                    : ($sourceValue->feature?->logical_type === 'categorical'
                        ? $sourceValue->categorical_value
                        : ($sourceValue->numeric_value === null ? null : (float) $sourceValue->numeric_value));
                $values[$featureKey] = $value;
            }

            foreach (self::APTITUDE_CODES as $code) {
                if (! isset($values[$code]) || ! is_numeric($values[$code])) {
                    throw new RuntimeException("Predecessor exemplar {$programCode}/{$source->tier_level} is missing canonical aptitude value {$code}.");
                }
            }

            $exemplars[] = [
                'program_code' => $programCode,
                'program_name' => (string) $programsByCode[$programCode]['name'],
                'tier_level' => (int) $source->tier_level,
                'tier_label' => (string) $source->tier_label,
                'target_class' => (int) $source->target_class,
                'values' => $values,
            ];
        }

        if (count($exemplars) !== 55) {
            throw new RuntimeException('The active predecessor must contain exactly 55 exemplars before it can be repaired.');
        }

        return $exemplars;
    }

    private function assertCanonicalSuccessor(AdmissionBenchmarkSet $set): void
    {
        if ($set->status !== 'published'
            || ! hash_equals((string) $set->content_sha256, $set->computedContentDigest())
            || ! hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())
            || $set->exemplars->count() !== 55) {
            throw new RuntimeException('The v3 benchmark successor is not an intact published 55-exemplar set.');
        }

        $featuresByKey = $set->features->keyBy('feature_key');
        if ($featuresByKey->has('VERBAL') || $featuresByKey->has('QUANT')) {
            throw new RuntimeException('The v3 benchmark successor still contains unpopulated legacy aptitude features.');
        }

        foreach (self::APTITUDE_CODES as $code) {
            $feature = $featuresByKey->get($code);
            if ($feature === null
                || ($feature->source_definition_json['source_code'] ?? null) !== $code
                || ($feature->source_definition_json['scale'] ?? null) !== 'percentile'
                || ($feature->source_definition_json['norm_version'] ?? null) !== '2026-v1') {
                throw new RuntimeException("The v3 benchmark successor is missing percentile metadata for {$code}.");
            }
        }

        $programTiers = [];
        foreach ($set->exemplars as $exemplar) {
            $programCode = (string) ($exemplar->program?->code ?? '');
            $tier = (int) $exemplar->tier_level;
            if (! in_array($programCode, self::PROGRAM_CODES, true)
                || $tier < 1
                || $tier > 5
                || isset($programTiers[$programCode][$tier])) {
                throw new RuntimeException('The v3 benchmark successor does not contain a unique tier row for each fixture program.');
            }
            $programTiers[$programCode][$tier] = true;

            foreach (self::APTITUDE_CODES as $code) {
                $value = $exemplar->values->first(
                    fn ($item): bool => $item->feature?->feature_key === $code,
                );
                if ($value === null || $value->is_missing || $value->numeric_value === null) {
                    throw new RuntimeException("The v3 benchmark successor exemplar {$exemplar->id} has no numeric value for {$code}.");
                }
            }
        }

        $actualProgramCodes = array_keys($programTiers);
        $expectedProgramCodes = self::PROGRAM_CODES;
        sort($actualProgramCodes);
        sort($expectedProgramCodes);
        if ($actualProgramCodes !== $expectedProgramCodes) {
            throw new RuntimeException('The v3 benchmark successor must cover all 11 fixture programs.');
        }

        foreach ($programTiers as $tiers) {
            $tierLevels = array_keys($tiers);
            sort($tierLevels);
            if ($tierLevels !== [1, 2, 3, 4, 5]) {
                throw new RuntimeException('The v3 benchmark successor must have all five tiers for each fixture program.');
            }
        }

    }

    private function ensureTestInstrumentVersion(Institution $institution, User $actor, TestInstrumentProfileValidator $profileValidator): TestInstrumentVersion
    {
        $areas = AptitudeArea::query()
            ->where('is_active', true)
            ->whereIn('code', self::APTITUDE_CODES)
            ->orderBy('id')
            ->get()
            ->keyBy('code');
        if ($areas->count() !== count(self::APTITUDE_CODES)) {
            throw new RuntimeException('All six canonical active aptitude areas are required for the typed demo profile.');
        }

        $instrument = TestInstrument::query()
            ->where('institution_id', $institution->id)
            ->where('code', 'TABFM-CONSULTATION-DEMO')
            ->first();
        if ($instrument === null) {
            $instrument = new TestInstrument;
            $instrument->forceFill([
                'institution_id' => $institution->id,
                'code' => 'TABFM-CONSULTATION-DEMO',
                'is_synthetic' => true,
            ])->save();
        } elseif (! $instrument->is_synthetic) {
            throw new RuntimeException('TABFM-CONSULTATION-DEMO is already owned by a non-synthetic test instrument.');
        }

        $currentVersion = TestInstrumentVersion::query()
            ->with(['metrics.subtest', 'subtests'])
            ->where('test_instrument_id', $instrument->id)
            ->where('status', TestInstrumentVersion::STATUS_APPROVED)
            ->first();
        if ($currentVersion !== null && $this->hasCanonicalMetrics($currentVersion, $areas)) {
            if ((int) $instrument->current_approved_version_id !== (int) $currentVersion->id) {
                $instrument->forceFill(['current_approved_version_id' => $currentVersion->id])->save();
            }

            return $currentVersion;
        }

        if ($currentVersion !== null) {
            $currentVersion->forceFill([
                'status' => TestInstrumentVersion::STATUS_RETIRED,
                'retired_by_user_id' => $actor->id,
                'retired_at' => now(),
            ])->save();
        }

        $versionNumber = (int) TestInstrumentVersion::query()
            ->where('test_instrument_id', $instrument->id)
            ->max('version_number') + 1;
        $version = TestInstrumentVersion::query()->create([
            'test_instrument_id' => $instrument->id,
            'version_number' => $versionNumber,
            'status' => TestInstrumentVersion::STATUS_DRAFT,
            'display_name' => 'Consultation TabFM Typed Aptitude Demo',
            'publisher_name' => 'Synthetic NEXIAM Development Fixture',
            'form_code' => 'TABFM-CMI-DEMO',
            'edition' => 'Synthetic Demo Profile',
            'language' => 'English',
            'source_reference' => 'demo://tabfm-consultation-typed-assessment',
            'permitted_use_reference' => 'demo://software-rehearsal-only',
            'created_by_user_id' => $actor->id,
            'lock_version' => 1,
        ]);

        foreach (self::APTITUDE_CODES as $position => $code) {
            $area = $areas->get($code);
            $subtest = TestInstrumentSubtest::query()->create([
                'test_instrument_version_id' => $version->id,
                'code' => $code,
                'name' => (string) $area->name,
                'display_order' => $position + 1,
                'response_mode' => 'recorded_measure',
                'item_order_policy' => 'fixed',
            ]);

            TestInstrumentMetric::query()->create([
                'test_instrument_version_id' => $version->id,
                'code' => $code,
                'name' => $code.' Percentile Rank',
                'rule_kind' => TestInstrumentMetric::RULE_RECORDED_MEASURE,
                'test_instrument_subtest_id' => $subtest->id,
                'unit' => 'percentile_rank',
                'min_raw_value' => '0.000000',
                'max_raw_value' => '100.000000',
                'precision_scale' => 2,
                'is_required' => true,
                'required_output_scales' => ['percentile_rank'],
                'procedure_reference' => 'Synthetic demo percentile-rank measurement procedure.',
                'construct_aptitude_area_id' => $area->id,
            ]);
        }

        $normalizedProfile = $profileValidator->normalizeVersion($version);
        $version->forceFill([
            'status' => TestInstrumentVersion::STATUS_APPROVED,
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
            'administration_procedure_reference' => 'Synthetic demo procedure.',
            'source_reviewed_by_user_id' => $actor->id,
            'source_reviewed_at' => now(),
            'approved_by_user_id' => $actor->id,
            'approved_at' => now(),
            'content_sha256' => $profileValidator->digest($normalizedProfile),
            'lock_version' => 2,
        ])->save();
        $instrument->forceFill(['current_approved_version_id' => $version->id])->save();

        return $version->load(['metrics.subtest', 'subtests']);
    }

    /** @param Collection<string, AptitudeArea> $areas */
    private function hasCanonicalMetrics(TestInstrumentVersion $version, Collection $areas): bool
    {
        $metricsByCode = $version->metrics->keyBy('code');
        $subtestsByCode = $version->subtests->keyBy('code');
        foreach (self::APTITUDE_CODES as $code) {
            $metric = $metricsByCode->get($code);
            $subtest = $subtestsByCode->get($code);
            if ($metric === null
                || $subtest === null
                || (int) $metric->construct_aptitude_area_id !== (int) $areas->get($code)->id
                || (int) $metric->test_instrument_subtest_id !== (int) $subtest->id
                || $metric->rule_kind !== TestInstrumentMetric::RULE_RECORDED_MEASURE
                || $subtest->response_mode !== 'recorded_measure'
                || ! $metric->is_required
                || ! in_array('percentile_rank', (array) $metric->required_output_scales, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{ExamAssignment, GradingSession} */
    private function ensureCompletedAssignment(
        AdmissionAttempt $attempt,
        AcademicCycle $cycle,
        TestInstrumentVersion $profileVersion,
        User $actor,
    ): array {
        $latestAssignment = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($latestAssignment !== null) {
            $session = ExamSession::query()->with('gradingSession')->findOrFail($latestAssignment->exam_session_id);
            $gradingSession = $session->gradingSession;
            if ((int) $session->academic_cycle_id === (int) $cycle->id
                && $session->status === ExamSession::STATUS_COMPLETED
                && $gradingSession?->status === GradingSession::STATUS_FINALIZED
                && ($session->test_instrument_version_id === null
                    || (int) $session->test_instrument_version_id === (int) $profileVersion->id)) {
                return [$latestAssignment, $gradingSession];
            }
        }

        if ($latestAssignment !== null) {
            $latestAssignment->update([
                'status' => ExamAssignment::STATUS_REMOVED,
                'ended_at' => now(),
                'end_reason' => 'Replaced by the local synthetic TabFM consultation demo repair fixture.',
                'version' => (int) $latestAssignment->version + 1,
            ]);
        }

        $room = Room::query()
            ->where('institution_id', $cycle->institution_id)
            ->where('code', 'RM-101')
            ->where('is_active', true)
            ->firstOrFail();
        $baseCode = 'TABFM-DEMO-REPAIR-'.$attempt->id;
        $suffix = 0;

        while (true) {
            $code = $baseCode.($suffix === 0 ? '' : '-'.($suffix + 1));
            $session = ExamSession::query()
                ->where('academic_cycle_id', $cycle->id)
                ->where('code', $code)
                ->first();

            if ($session !== null) {
                $assignment = ExamAssignment::query()
                    ->where('exam_session_id', $session->id)
                    ->where('admission_attempt_id', $attempt->id)
                    ->first();
                $gradingSession = GradingSession::query()->where('exam_session_id', $session->id)->first();
                $latestActiveId = ExamAssignment::query()
                    ->active()
                    ->where('admission_attempt_id', $attempt->id)
                    ->max('id');

                if ($assignment !== null
                    && $assignment->isActive()
                    && (int) $assignment->id === (int) $latestActiveId
                    && $session->status === ExamSession::STATUS_COMPLETED
                    && $gradingSession?->status === GradingSession::STATUS_FINALIZED
                    && ($session->test_instrument_version_id === null
                        || (int) $session->test_instrument_version_id === (int) $profileVersion->id)) {
                    return [$assignment, $gradingSession];
                }

                $suffix++;

                continue;
            }

            $endsAt = now()->subDays(3)->addHours(2);
            $session = ExamSession::query()->create([
                'academic_cycle_id' => $cycle->id,
                'test_instrument_version_id' => $profileVersion->id,
                'room_id' => $room->id,
                'code' => $code,
                'label' => 'Synthetic completed TabFM consultation repair fixture',
                'status' => ExamSession::STATUS_COMPLETED,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'starts_at' => now()->subDays(3),
                'effective_end_at' => $endsAt,
                'version' => 1,
                'published_at' => now()->subDays(4),
                'closed_at' => $endsAt,
            ]);
            $assignment = ExamAssignment::query()->create([
                'exam_session_id' => $session->id,
                'admission_attempt_id' => $attempt->id,
                'status' => ExamAssignment::STATUS_ASSIGNED,
                'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                'origin' => ExamAssignment::ORIGIN_MANUAL,
                'version' => 1,
            ]);
            $gradingSession = GradingSession::query()->create([
                'exam_session_id' => $session->id,
                'status' => GradingSession::STATUS_FINALIZED,
                'version' => 1,
                'opened_at' => now()->subDays(2),
                'finalized_at' => now()->subDay(),
                'opened_by_user_id' => $actor->id,
                'finalized_by_user_id' => $actor->id,
            ]);

            return [$assignment, $gradingSession];
        }
    }

    private function attachTypedAssessmentResultSet(
        AdmissionAttempt $attempt,
        ExamAssignment $assignment,
        GradingSession $gradingSession,
        TestInstrumentVersion $profileVersion,
        User $actor,
    ): AssessmentResultSet {
        $administration = AssessmentAdministration::query()
            ->active()
            ->where('exam_assignment_id', $assignment->id)
            ->where('admission_attempt_id', $attempt->id)
            ->where('test_instrument_version_id', $profileVersion->id)
            ->orderByDesc('binding_revision')
            ->orderByDesc('id')
            ->first();

        if ($administration === null) {
            $administration = AssessmentAdministration::query()->create([
                'institution_id' => $attempt->institution_id,
                'academic_cycle_id' => $attempt->academic_cycle_id,
                'exam_assignment_id' => $assignment->id,
                'admission_attempt_id' => $attempt->id,
                'binding_kind' => AssessmentAdministration::KIND_DIRECT_MANUAL,
                'binding_revision' => 1,
                'test_instrument_version_id' => $profileVersion->id,
                'required_metric_set' => [],
                'binding_state' => AssessmentAdministration::STATE_AWAITING_NORM_CONVERSION,
                'bound_at' => now()->subMinutes(10),
            ]);
        }

        $existingResultSet = AssessmentResultSet::query()
            ->where('administration_id', $administration->id)
            ->where('exam_session_id', $assignment->exam_session_id)
            ->where('grading_session_id', $gradingSession->id)
            ->where('status', AssessmentResultSet::STATUS_FINALIZED)
            ->orderByDesc('id')
            ->first();

        if ($existingResultSet !== null && $existingResultSet->raw_revision_vector_json === []) {
            $existingResultSet->raw_revision_vector_json = self::RAW_OUTPUTS;
            $existingResultSet->save();
        }

        $existingOutputs = $existingResultSet?->conversion_revision_vector_json['typed_outputs'] ?? null;
        if (is_array($existingOutputs) && $existingOutputs === self::TYPED_OUTPUTS) {
            return $existingResultSet;
        }

        return AssessmentResultSet::query()->create([
            'administration_id' => $administration->id,
            'exam_session_id' => $assignment->exam_session_id,
            'grading_session_id' => $gradingSession->id,
            'raw_revision_vector_json' => self::RAW_OUTPUTS,
            'conversion_revision_vector_json' => [
                'typed_outputs' => self::TYPED_OUTPUTS,
            ],
            'status' => AssessmentResultSet::STATUS_FINALIZED,
            'finalized_by_user_id' => $actor->id,
            'finalized_at' => now(),
        ]);
    }
}
