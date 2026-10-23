<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Actions\ClearScores;
use App\Assessment\Actions\RecordScores;
use App\Assessment\Queries\ApprovedTestInstrumentSelectionQuery;
use App\Assessment\Support\GradingRoster;
use App\Assessment\Support\IndividualResultMutationFence;
use App\Assessment\Support\ScoreBaseline;
use App\Assessment\Support\ScoreConverter;
use App\Assessment\Support\ScoringSessionContextResolver;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\ClearScoresRequest;
use App\Http\Requests\Assessment\RecordScoresRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Testing\PracticeWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class GradingScoreController extends Controller
{
    public function show(
        Request $request,
        ExamSession|GradingSession|string|int $examSession,
        AdmissionAttempt $attempt,
        ScoringSessionContextResolver $contexts,
        CapabilityAuthorizer $authorizer,
        ApprovedTestInstrumentSelectionQuery $kitSelections,
        ScoreConverter $scoreConverter,
        ScoreBaseline $baselines,
    ): Response|JsonResponse {
        $sessionModel = $contexts->fromRoute($request);
        $gradingSession = $sessionModel instanceof GradingSession
            ? $sessionModel
            : $contexts->resolveForRead(
                $sessionModel,
                $request->user(),
                CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
            );
        abort_if($gradingSession === null, 404);
        $this->assertSessionAccess($request, $gradingSession, $authorizer);
        $this->assertInRoster($gradingSession, $attempt);
        $assignment = ExamAssignment::query()
            ->where('exam_session_id', $gradingSession->exam_session_id)
            ->where('admission_attempt_id', $attempt->id)
            ->active()
            ->first();

        $administration = $assignment === null
            ? null
            : AssessmentAdministration::query()
                ->where('exam_assignment_id', $assignment->id)
                ->active()
                ->with([
                    'testInstrumentVersion.instrument',
                    'testInstrumentVersion.metrics.inputs.inputMetric',
                    'testInstrumentVersion.metrics.construct.conversionRows',
                    'testInstrumentVersion.subtests',
                ])
                ->first();

        $sessionKitVersionId = $gradingSession->examSession?->test_kit_version_id === null
            ? null
            : (int) $gradingSession->examSession->test_kit_version_id;
        $currentKitVersionId = $administration?->test_kit_version_id === null
            ? $sessionKitVersionId
            : (int) $administration->test_kit_version_id;
        $requestedKitVersionId = $request->integer('test_kit_version_id') ?: null;
        if ($currentKitVersionId !== null
            && $requestedKitVersionId !== null
            && $currentKitVersionId !== $requestedKitVersionId) {
            abort(409, 'The selected Test Kit version does not match the locked session binding.');
        }
        $selectedKitVersionId = $currentKitVersionId ?? $requestedKitVersionId;
        $selectedKitVersion = null;
        if ($selectedKitVersionId !== null) {
            $selectedKitQuery = TestKitVersion::query()
                ->with('sourcePreparation')
                ->where('institution_id', (int) ($gradingSession->examSession?->academicCycle?->institution_id ?? Institution::current()->id))
                ->whereKey($selectedKitVersionId);
            if ($currentKitVersionId === null) {
                $selectedKitQuery->whereNull('revoked_at');
            }
            $selectedKitVersion = $selectedKitQuery->firstOrFail();
            if ($currentKitVersionId === null && $selectedKitVersion->status !== TestKitVersion::STATUS_ACTIVE) {
                abort(422, 'Select a currently active Test Kit version for new scoring work.');
            }
            if ($gradingSession->examSession?->test_instrument_version_id !== null
                && (int) $gradingSession->examSession->test_instrument_version_id !== (int) $selectedKitVersion->test_instrument_version_id) {
                abort(409, 'The selected Test Kit profile does not match the session profile pin.');
            }
        }

        $profileVersion = $administration?->testInstrumentVersion;
        if ($profileVersion === null && $selectedKitVersion?->test_instrument_version_id !== null) {
            $profileVersion = TestInstrumentVersion::query()
                ->with([
                    'instrument',
                    'metrics.inputs.inputMetric',
                    'metrics.construct.conversionRows',
                    'subtests',
                ])
                ->find($selectedKitVersion->test_instrument_version_id);
        }
        if ($profileVersion === null && $gradingSession->examSession?->test_instrument_version_id !== null) {
            $profileVersion = TestInstrumentVersion::query()
                ->with([
                    'instrument',
                    'metrics.inputs.inputMetric',
                    'metrics.construct.conversionRows',
                    'subtests',
                ])
                ->find($gradingSession->examSession->test_instrument_version_id);
        }

        $hasScoringProfile = $profileVersion !== null && $selectedKitVersionId !== null;
        $isProfileBound = $administration !== null;
        $sessionInstitutionId = (int) ($gradingSession->examSession?->academicCycle?->institution_id ?? Institution::current()->id);
        $testKitVersions = $kitSelections->activeKitVersionsForInstitution($sessionInstitutionId, $currentKitVersionId);
        $activeAreas = AptitudeArea::query()
            ->where('is_active', true)
            ->with('conversionRows')
            ->orderBy('id')
            ->get();
        $existingScores = $gradingSession->scoreRecords()
            ->where('admission_attempt_id', $attempt->id)
            ->get()
            ->keyBy('aptitude_area_id');

        $identity = $attempt->identity;
        $name = $identity
            ? trim(implode(' ', array_filter([
                $identity->first_name,
                $identity->middle_name,
                $identity->last_name,
                $identity->suffix,
            ])))
            : '—';

        $finalized = $gradingSession->status === GradingSession::STATUS_FINALIZED;
        $actor = $request->user();
        $canManageCatalog = $authorizer->allows(
            $actor,
            CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        );
        $rawRevisions = collect();
        $currentConversion = null;
        $normVersions = collect();
        $selectedNormVersion = null;

        if ($hasScoringProfile) {
            $metrics = $profileVersion?->metrics ?? collect();
            if ($administration !== null) {
                $rawRevisions = AssessmentRawResultRevision::query()
                    ->where('administration_id', $administration->id)
                    ->where('is_current', true)
                    ->get()
                    ->keyBy('metric_id');
                $currentConversion = AssessmentConversionRevision::query()
                    ->where('administration_id', $administration->id)
                    ->where('is_current', true)
                    ->first();
            }

            if ($profileVersion !== null) {
                $requiredMetricCodes = collect($administration?->required_metric_set ?? [])
                    ->map(fn ($item) => is_array($item) ? ($item['code'] ?? null) : $item)
                    ->filter()
                    ->values();
                if ($requiredMetricCodes->isEmpty()) {
                    $requiredMetricCodes = $metrics
                        ->where('is_required', true)
                        ->pluck('code')
                        ->values();
                }
                $requiredPercentileCodes = $metrics
                    ->whereIn('code', $requiredMetricCodes)
                    ->filter(fn (TestInstrumentMetric $metric): bool => in_array(
                        'percentile_rank',
                        (array) $metric->required_output_scales,
                        true,
                    ))
                    ->pluck('code')
                    ->values();

                $institutionId = (int) ($administration?->institutionId()
                    ?? $gradingSession->examSession?->academicCycle?->institution_id
                    ?? 0);
                $isSynthetic = $profileVersion->instrument?->is_synthetic === true;
                if ($institutionId > 0) {
                    $normVersions = AssessmentNormPackageVersion::query()
                        ->where('status', AssessmentNormPackageVersion::STATUS_APPROVED)
                        ->where(function ($query) use ($profileVersion): void {
                            $query->whereNull('form_code')
                                ->orWhere('form_code', $profileVersion->form_code);
                        })
                        ->whereHas('package', fn ($query) => $query
                            ->where('institution_id', $institutionId)
                            ->where('is_synthetic', $isSynthetic))
                        ->with(['tables', 'package:id,code'])
                        ->orderByDesc('approved_at')
                        ->get()
                        ->filter(function (AssessmentNormPackageVersion $version) use ($requiredPercentileCodes): bool {
                            $coveredCodes = $version->tables
                                ->where('output_scale', 'percentile_rank')
                                ->pluck('metric_code')
                                ->unique()
                                ->values();

                            return $requiredPercentileCodes->isEmpty()
                                ? $coveredCodes->isNotEmpty()
                                : $requiredPercentileCodes->diff($coveredCodes)->isEmpty();
                        })
                        ->values();

                    $hasExplicitNormParam = $request->query->has('norm_package_version_id');
                    $requestedNormVersionId = $request->integer('norm_package_version_id') ?: null;
                    $defaultNormVersionId = $currentConversion?->norm_package_version_id
                        ?? $selectedKitVersion?->sourcePreparation?->norm_package_version_id;
                    $effectiveNormVersionId = $hasExplicitNormParam
                        ? $requestedNormVersionId
                        : ($defaultNormVersionId === null ? null : (int) $defaultNormVersionId);
                    $selectedNormVersion = $effectiveNormVersionId === null
                        ? null
                        : $normVersions->firstWhere('id', $effectiveNormVersionId);
                    if ($requestedNormVersionId !== null && $selectedNormVersion === null) {
                        abort(422, 'The selected norm version is not applicable to this exact profile.');
                    }
                }
            }

            $typedOutputs = is_array($currentConversion?->typed_outputs_json)
                ? $currentConversion->typed_outputs_json
                : [];

            $domainPayload = $metrics->map(function (TestInstrumentMetric $metric) use (
                $rawRevisions,
                $existingScores,
                $typedOutputs,
                $selectedNormVersion,
                $currentConversion,
            ): array {
                $currentRev = $rawRevisions->get($metric->id);
                $existingScore = $metric->construct_aptitude_area_id === null
                    ? null
                    : $existingScores->get($metric->construct_aptitude_area_id);
                $rawScore = $currentRev?->raw_value;
                if (
                    $rawScore === null
                    && $existingScore !== null
                    && $existingScore->source !== AssessmentConversionRevision::SOURCE_MANUAL_CONVERTED
                ) {
                    $rawScore = $existingScore->score;
                }

                $normTable = $selectedNormVersion?->tables->first(
                    fn ($table): bool => $table->metric_code === $metric->code
                        && $table->output_scale === 'percentile_rank',
                );
                $conversionRows = $normTable === null
                    ? []
                    : collect($normTable->intervals_json ?? [])->map(fn (array $interval): array => [
                        'min_score' => $interval['min_score'] ?? $interval['min'] ?? null,
                        'max_score' => $interval['max_score'] ?? $interval['max'] ?? null,
                        'output_value' => $interval['output_value'] ?? $interval['percentile_rank'] ?? null,
                    ])->values()->all();

                $metricOutputs = $typedOutputs[$metric->code] ?? [];
                $convertedScore = is_array($metricOutputs)
                    ? ($metricOutputs['percentile_rank'] ?? null)
                    : null;
                if ($convertedScore === null && $normTable !== null && $rawScore !== null) {
                    $convertedScore = $scoreConverter->convertViaNormTable($normTable, (string) $rawScore);
                }

                $isDerived = in_array($metric->rule_kind, [
                    TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                    TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
                ], true);

                return [
                    ...($metric->formula_expression !== null ? [
                        'formula_expression' => (string) $metric->formula_expression,
                        'formula_ast_json' => $metric->formula_ast_json,
                        'formula_algorithm_identity' => TestInstrumentMetric::FORMULA_ALGORITHM_IDENTITY,
                    ] : []),
                    ...($metric->item_codes_json !== null ? ['item_codes' => $metric->item_codes_json] : []),
                    'metric_id' => $metric->id,
                    'aptitude_area_id' => $metric->construct_aptitude_area_id,
                    'code' => $metric->code,
                    'metric_code' => $metric->code,
                    'name' => $metric->name,
                    'unit' => $metric->unit,
                    'min_raw_value' => $metric->min_raw_value !== null ? (float) $metric->min_raw_value : null,
                    'max_raw_value' => $metric->max_raw_value !== null ? (float) $metric->max_raw_value : null,
                    'precision_scale' => (int) $metric->precision_scale,
                    'rule_kind' => $metric->rule_kind,
                    'min' => $metric->min_raw_value !== null ? (float) $metric->min_raw_value : 0.0,
                    'max' => $metric->max_raw_value !== null ? (float) $metric->max_raw_value : null,
                    'min_score' => $metric->min_raw_value !== null ? (float) $metric->min_raw_value : 0.0,
                    'max_score' => $metric->max_raw_value !== null ? (float) $metric->max_raw_value : null,
                    'precision' => (int) $metric->precision_scale,
                    'scoring_method' => $metric->rule_kind,
                    'max_items' => $metric->rule_kind === TestInstrumentMetric::RULE_FORMULA_RAW_METRICS
                        ? null
                        : ($metric->max_raw_value !== null ? (int) $metric->max_raw_value : null),
                    'is_required' => (bool) $metric->is_required,
                    'read_only' => $isDerived,
                    'is_derived' => $isDerived,
                    'source' => $currentRev?->source,
                    'overridden_from_omr' => ($currentRev?->provenance_json['overridden_from_source'] ?? null) === 'omr',
                    'override_reason' => $currentRev?->provenance_json['override_reason'] ?? null,
                    'input_codes' => $isDerived
                        ? $metric->inputs->map(fn ($input) => $input->inputMetric?->code)->filter()->values()->all()
                        : [],
                    'construct_code' => $metric->construct?->code,
                    'construct_name' => $metric->construct?->name,
                    'conversion_rows' => $conversionRows,
                    'required_output_scales' => array_values($metric->required_output_scales ?? []),
                    'typed_outputs' => is_array($metricOutputs) ? $metricOutputs : [],
                    'external_provenance' => (string) ($currentConversion?->provenance_json['external_provenance'][$metric->code] ?? ''),
                    'score' => is_numeric($rawScore) ? (float) $rawScore : null,
                    'raw_value' => $rawScore !== null ? (string) $rawScore : null,
                    'status' => $currentRev?->status ?? 'pending',
                    'converted_score' => $convertedScore,
                ];
            })->values()->all();

            $linkedMetrics = $metrics
                ->filter(fn (TestInstrumentMetric $metric): bool => $metric->construct_aptitude_area_id !== null && $metric->construct !== null);
            $constructAreas = $linkedMetrics->isNotEmpty()
                ? $linkedMetrics->map(fn (TestInstrumentMetric $metric): AptitudeArea => $metric->construct)->unique('id')->values()
                : $activeAreas;
            $constructs = $constructAreas->map(function (AptitudeArea $area) use (
                $metrics,
                $linkedMetrics,
                $existingScores,
                $typedOutputs,
                $currentConversion,
            ): array {
                $metric = $linkedMetrics->firstWhere('construct_aptitude_area_id', $area->id)
                    ?? $metrics->firstWhere('code', $area->code);
                $metricCode = $metric?->code ?? $area->code;
                $outputs = $typedOutputs[$metricCode] ?? $typedOutputs[$area->code] ?? [];
                $convertedScore = is_array($outputs)
                    ? ($outputs['percentile_rank'] ?? $outputs['converted_score'] ?? null)
                    : null;
                $existingScore = $existingScores->get($area->id);
                if ($convertedScore === null && $currentConversion === null) {
                    $convertedScore = $existingScore?->converted_score;
                }

                return [
                    'id' => $area->id,
                    'aptitude_area_id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                    'metric_code' => $metricCode,
                    'final_score' => $convertedScore,
                    'source' => $currentConversion?->source ?? ($existingScore !== null ? 'legacy' : null),
                ];
            })->values()->all();
        } else {
            $domainPayload = $activeAreas->map(function (AptitudeArea $area) use ($existingScores): array {
                $existingScore = $existingScores->get($area->id);
                $rawScore = $existingScore?->score;

                return [
                    'id' => $area->id,
                    'metric_id' => null,
                    'aptitude_area_id' => $area->id,
                    'code' => $area->code,
                    'metric_code' => $area->code,
                    'name' => $area->name,
                    'unit' => 'points',
                    'min_raw_value' => 0.0,
                    'max_raw_value' => $area->max_items !== null ? (float) $area->max_items : null,
                    'precision_scale' => 0,
                    'rule_kind' => $area->scoring_method,
                    'scoring_method' => $area->scoring_method,
                    'max_items' => $area->max_items,
                    'is_required' => true,
                    'read_only' => false,
                    'is_derived' => false,
                    'source' => $existingScore?->source,
                    'overridden_from_omr' => false,
                    'override_reason' => null,
                    'input_codes' => [],
                    'construct_code' => $area->code,
                    'construct_name' => $area->name,
                    'conversion_rows' => $area->conversionRows->map(fn ($row): array => [
                        'min_score' => (int) $row->min_score,
                        'max_score' => (int) $row->max_score,
                        'output_value' => $row->output_value,
                    ])->values()->all(),
                    'score' => $rawScore,
                    'raw_value' => $rawScore !== null ? (string) $rawScore : null,
                    'converted_score' => $existingScore?->converted_score,
                ];
            })->values()->all();

            $constructs = $activeAreas->map(fn (AptitudeArea $area): array => [
                'id' => $area->id,
                'aptitude_area_id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                'metric_code' => $area->code,
                'final_score' => $existingScores->get($area->id)?->converted_score,
                'source' => $existingScores->get($area->id)?->source,
            ])->values()->all();
        }

        $individualFrozen = $administration !== null
            && app(IndividualResultMutationFence::class)->isFrozen($administration);
        $canWrite = ! $finalized && ! $individualFrozen && $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_SCORING_MANUAL, [
            'office' => 'guidance',
            'institution_id' => $sessionInstitutionId,
        ]);
        $props = [
            'page_state' => $finalized || $individualFrozen ? 'locked' : 'ready',
            'degraded' => config('xiam.mode') === 'degraded',
            'exam_session_id' => $gradingSession->exam_session_id,
            'grading_status' => $gradingSession->status,
            'grading_version' => $gradingSession->version,
            'can_write' => $canWrite,
            'individual_frozen' => $individualFrozen,
            'urls' => [
                'hub' => route('scoring.index'),
                'roster' => route('scoring.sessions.show', ['examSession' => $gradingSession->exam_session_id]),
                'update' => route('scoring.sessions.attempts.scores.update', [
                    'examSession' => $gradingSession->exam_session_id,
                    'attempt' => $attempt->id,
                ]),
                'destroy' => route('scoring.sessions.attempts.scores.destroy', [
                    'examSession' => $gradingSession->exam_session_id,
                    'attempt' => $attempt->id,
                ]),
            ],
            'session_id' => $gradingSession->id,
            'session_status' => $gradingSession->status,
            'finalized' => $finalized,
            'is_profile_bound' => $isProfileBound,
            'has_scoring_profile' => $hasScoringProfile,
            'is_final_only' => $profileVersion?->isFinalOnly() ?? false,
            'test_kit_versions' => $testKitVersions,
            'selected_test_kit_version_id' => $selectedKitVersionId,
            'test_kit_binding_persisted' => $currentKitVersionId !== null,
            'norm_versions' => $profileVersion?->isFinalOnly() ? [] : $normVersions->map(fn (AssessmentNormPackageVersion $version): array => [
                'id' => (int) $version->id,
                'label' => ($version->package?->code ?? 'Norm').' · '.$version->display_name.' v'.$version->version_number,
            ])->values()->all(),
            'selected_norm_package_version_id' => $profileVersion?->isFinalOnly() || $selectedNormVersion === null ? null : (int) $selectedNormVersion->id,
            'scoring_mode' => $profileVersion?->isFinalOnly()
                || ($currentConversion?->source === AssessmentConversionRevision::SOURCE_MANUAL_CONVERTED && $rawRevisions->isEmpty())
                    ? 'manual_converted'
                    : 'manual_raw',
            'can_manage_catalog' => $canManageCatalog,
            'setup_links' => $canManageCatalog ? [
                'test_kits' => '/setup/exam-materials/test-kits',
            ] : null,
            'instrument_profile' => $hasScoringProfile && $profileVersion ? [
                'version_id' => $profileVersion->id,
                'display_name' => $profileVersion->display_name ?? $profileVersion->instrument?->name ?? 'Instrument Profile',
                'version_number' => $profileVersion->version_number,
                'form_code' => $profileVersion->form_code,
                'is_final_only' => $profileVersion->isFinalOnly(),
            ] : null,
            'attempt' => [
                'id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'name' => $name !== '' ? $name : '—',
            ],
            'exam' => [
                'code' => $gradingSession->examSession?->code ?? '—',
                'starts_at' => $gradingSession->examSession?->starts_at?->format('Y-m-d H:i'),
                'room_name' => $gradingSession->examSession?->room?->name ?? '—',
            ],
            'domains' => $domainPayload,
            'metrics' => $hasScoringProfile ? $domainPayload : [],
            'constructs' => $constructs,
            'search_context' => [
                'academic_cycle_id' => $gradingSession->examSession?->academic_cycle_id,
                'grading_session_id' => $gradingSession->id,
                'exam_session_id' => $gradingSession->exam_session_id,
            ],
            'binding_state' => $administration?->binding_state,
            'administration_state' => $administration?->binding_state,
            'score_baseline' => $baselines->for($gradingSession, $attempt),
        ];

        return $request->expectsJson()
            ? response()->json($props)
            : Inertia::render('Assessment/GradingScoreInput', $props);
    }

    public function update(
        RecordScoresRequest $request,
        ExamSession|GradingSession|string|int $examSession,
        AdmissionAttempt $attempt,
        RecordScores $action,
        CapabilityAuthorizer $authorizer,
        ScoringSessionContextResolver $contexts,
        ScoreBaseline $baselines,
    ): RedirectResponse|JsonResponse {
        $sessionModel = $contexts->fromRoute($request);
        $gradingSession = $sessionModel instanceof GradingSession
            ? $sessionModel
            : $contexts->requireForWrite(
                $sessionModel,
                $request->user(),
                CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
            );
        $this->assertSessionAccess($request, $gradingSession, $authorizer);
        $this->assertInRoster($gradingSession, $attempt);
        try {
            $action->handle(
                $gradingSession,
                $attempt,
                $request->validated('scores'),
                $request->user(),
                $request->string('idempotency_key')->toString(),
                scoringMode: $request->string('scoring_mode', 'manual_raw')->toString(),
                testKitVersionId: $request->validated('test_kit_version_id') === null
                    ? null
                    : (int) $request->validated('test_kit_version_id'),
                normPackageVersionId: $request->validated('norm_package_version_id') === null
                    ? null
                    : (int) $request->validated('norm_package_version_id'),
                expectedBaseline: $request->validated('expected_baseline'),
            );
        } catch (Throwable $exception) {
            if ($request->expectsJson()) {
                if ($exception instanceof RuntimeException && $exception->getMessage() === 'assessment_post_finalization_correction_blocked') {
                    abort(409, 'This individual result is frozen and can no longer be edited.');
                }
                throw $exception;
            }

            return back()->withErrors(['scores' => $exception->getMessage()])->with('page_state', 'validation');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Scores saved.',
                'score_baseline' => $baselines->for($gradingSession, $attempt),
            ]);
        }

        return redirect()->route('scoring.sessions.show', ['examSession' => $gradingSession->exam_session_id])
            ->with('status', 'Scores saved.');
    }

    public function destroy(
        ClearScoresRequest $request,
        ExamSession|GradingSession|string|int $examSession,
        AdmissionAttempt $attempt,
        ClearScores $action,
        CapabilityAuthorizer $authorizer,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $sessionModel = $contexts->fromRoute($request);
        $gradingSession = $sessionModel instanceof GradingSession
            ? $sessionModel
            : $contexts->requireForWrite(
                $sessionModel,
                $request->user(),
                CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
            );
        $this->assertSessionAccess($request, $gradingSession, $authorizer);
        $this->assertInRoster($gradingSession, $attempt);
        try {
            $action->handle($gradingSession, $attempt, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['scores' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return redirect()->route('scoring.sessions.show', ['examSession' => $gradingSession->exam_session_id])
            ->with('status', 'Scores cleared.');
    }

    private function assertSessionAccess(
        Request $request,
        GradingSession $gradingSession,
        CapabilityAuthorizer $authorizer,
    ): void {
        $gradingSession->loadMissing('examSession.academicCycle', 'examSession.room');
        $sessionInstitutionId = (int) $gradingSession->examSession?->academicCycle?->institution_id;
        if ($sessionInstitutionId !== (int) ($request->user()?->staffProfile?->institution_id ?? Institution::current()->id)
            || (string) ($gradingSession->examSession?->practice_fixture_set_id ?? '') !== (string) (app(PracticeWorkspace::class)->currentId() ?? '')) {
            abort(404);
        }

        abort_unless($authorizer->decideFresh($request->user(), CapabilityCatalog::GUIDANCE_SCORING_MANUAL, [
            'institution_id' => $sessionInstitutionId,
            'office' => 'guidance',
        ])->allowed, 403);
    }

    private function assertInRoster(GradingSession $gradingSession, AdmissionAttempt $attempt): void
    {
        if (! GradingRoster::includesAttempt($gradingSession, (int) $attempt->id)) {
            abort(SymfonyResponse::HTTP_NOT_FOUND, 'Attempt is not part of this grading session.');
        }
    }
}
