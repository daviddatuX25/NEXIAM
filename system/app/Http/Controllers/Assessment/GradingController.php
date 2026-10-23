<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\Omr\AdoptOmrPendingCapture;
use App\Assessment\Actions\Omr\AuthorizeOmrPaperReproduction;
use App\Assessment\Actions\Omr\CaptureGenericOmrImage;
use App\Assessment\Actions\Omr\ConfirmOmrCaptureBatch;
use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\PreflightOmrCapture;
use App\Assessment\Actions\Omr\RefreshOmrPendingResolution;
use App\Assessment\Actions\Omr\RejectOmrCaptureBatch;
use App\Assessment\Actions\Omr\ReplaceOmrSheetPage;
use App\Assessment\Actions\Omr\ResolveOmrApplicantException;
use App\Assessment\Actions\Omr\ResolveOmrCaptureSheetIssue;
use App\Assessment\Actions\Omr\ResolveOmrDuplicateDisposition;
use App\Assessment\Actions\Omr\ResolveOmrPendingCapture;
use App\Assessment\Actions\Omr\ResolveOmrStagedPreflightImage;
use App\Assessment\Actions\Omr\RetryOmrCaptureSheet;
use App\Assessment\Actions\Omr\ReviewOmrCaptureSheet;
use App\Assessment\Actions\Omr\UploadOmrCaptureSheet;
use App\Assessment\Actions\OpenGradingSession;
use App\Assessment\Omr\OmrCaptureThumbnailService;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrRecoveryCatalog;
use App\Assessment\Omr\OmrScannerContextOutcome;
use App\Assessment\Omr\OmrScannerSessionResolver;
use App\Assessment\Omr\Operational\ProductionOmrAuthorityAdapter;
use App\Assessment\Omr\Queries\OmrEvidenceCache;
use App\Assessment\Omr\Queries\OmrEvidencePage;
use App\Assessment\Omr\Queries\OmrEvidenceQuery;
use App\Assessment\Omr\Queries\OmrRunStatusSnapshot;
use App\Assessment\Queries\ApprovedTestInstrumentSelectionQuery;
use App\Assessment\Queries\GradingOmrCapturePresenter;
use App\Assessment\Queries\GradingSessionListQuery;
use App\Assessment\Support\AssessmentAdministrationFinalizationPredicate;
use App\Assessment\Support\GradingRoster;
use App\Assessment\Support\ScoringSessionContextResolver;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\AdoptOmrPendingCaptureRequest;
use App\Http\Requests\Assessment\AuthorizeOmrPaperReproductionRequest;
use App\Http\Requests\Assessment\DiscoverOmrSessionRequest;
use App\Http\Requests\Assessment\FinalizeGradingSessionRequest;
use App\Http\Requests\Assessment\OpenGradingSessionRequest;
use App\Http\Requests\Assessment\PreflightOmrCaptureRequest;
use App\Http\Requests\Assessment\ReplaceOmrSheetPageRequest;
use App\Http\Requests\Assessment\ResolveOmrApplicantExceptionRequest;
use App\Http\Requests\Assessment\ResolveOmrDuplicateDispositionRequest;
use App\Http\Requests\Assessment\ResolveOmrPendingApplicantRequest;
use App\Http\Requests\Assessment\ResolveOmrPendingDisplayCodeRequest;
use App\Http\Requests\Assessment\ResolveOmrSheetIssueRequest;
use App\Http\Requests\Assessment\RetryOmrPendingCaptureRequest;
use App\Http\Requests\Assessment\ReviewOmrSheetRequest;
use App\Http\Requests\Assessment\SelectOmrPendingSessionRequest;
use App\Http\Requests\Assessment\StoreOmrBatchRequest;
use App\Http\Requests\Assessment\StoreOmrScannerCaptureRequest;
use App\Http\Requests\Assessment\StoreOmrSheetRequest;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrPendingCapture;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Support\Http\MediaStreamResponder;
use App\Support\SensitiveAssetStore;
use App\Testing\PracticeWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Domain 4: Assessment, Grading & OMR Computer Vision
 *
 * Orchestrates optical mark recognition (OMR) processing batches, sheet capture resolution,
 * and psychometric grade aggregation across defined institutional aptitude areas.
 *
 * Architectural Components & Pipeline:
 * - OMR Sheet Capture: Ingestion of high-speed scanner feeds and browser camera captures.
 * - Anomaly Resolution: Automated flagging of double marks, light bubbles, and applicant code mismatches.
 * - Grade Computation: Conversion of raw bubble counts into normalized percentiles and aptitude scores.
 * - Integrity Gate: Requires grading session finalization before scores can transition to Results Release.
 *
 * Pedagogical / Interactive Walkthrough Pairing:
 * - Paired with 'assessment-grading-dashboard' tour via Xiam NPC (staff-copilot persona).
 */
class GradingController extends Controller
{
    public function index(
        Request $request,
        GradingSessionListQuery $listQuery,
        CapabilityAuthorizer $authorizer,
        ApprovedTestInstrumentSelectionQuery $kitSelections,
    ): Response {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['active', 'all', 'open', 'review', 'finalized', 'repair_required'])],
            'academic_cycle_id' => ['nullable', 'integer', 'min:1'],
            'room_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $status = $status !== '' ? $status : 'active';
        $cycleId = isset($validated['academic_cycle_id']) ? (int) $validated['academic_cycle_id'] : null;
        $roomId = isset($validated['room_id']) ? (int) $validated['room_id'] : null;
        $institutionId = (int) ($request->user()?->actorScope()?->institutionId ?? 0);
        abort_if($institutionId < 1, 403);
        $actor = $request->user();
        $canImportScores = $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_IMPORT, [
            'institution_id' => $institutionId,
            'office' => 'guidance',
        ]);
        $canUseOmr = $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, [
            'institution_id' => $institutionId,
            'office' => 'guidance',
        ]);
        $canManageCatalog = $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, [
            'institution_id' => $institutionId,
            'office' => 'guidance',
        ]);
        $data = $listQuery->forInertia(
            $institutionId,
            search: $search,
            status: $status,
            cycleId: $cycleId,
            roomId: $roomId,
        );

        $activeCycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->first();
        $kitVersions = $kitSelections->importKitVersionsForInstitution($institutionId);
        $scoreImportTargets = ExamSession::query()
            ->where('academic_cycle_id', $activeCycle?->id)
            ->where('status', ExamSession::STATUS_COMPLETED)
            ->with('testKitVersion.testKit:id,code,name')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (ExamSession $session): array => [
                'id' => (int) $session->id,
                'label' => trim($session->code.' · '.($session->label ?? '')),
                'test_kit_version_id' => $session->test_kit_version_id === null ? null : (int) $session->test_kit_version_id,
                'kit_label' => $session->testKitVersion?->testKit?->name
                    ?? $session->testKitVersion?->testKit?->code
                    ?? 'Test Kit',
            ])
            ->all();
        $normVersions = AssessmentNormPackageVersion::query()
            ->where('status', AssessmentNormPackageVersion::STATUS_APPROVED)
            ->whereHas('package', fn ($query) => $query->where('institution_id', $institutionId))
            ->with('package:id,code')
            ->orderBy('norm_package_id')
            ->orderByDesc('version_number')
            ->get()
            ->map(fn (AssessmentNormPackageVersion $version): array => [
                'id' => (int) $version->id,
                'label' => ($version->package?->code ?? 'Norm').' · '.$version->display_name.' v'.$version->version_number,
            ])
            ->all();
        $pageState = config('xiam.mode') === 'degraded' ? 'degraded' : 'ready';

        return Inertia::render('Assessment/Grading', [
            'page_state' => $pageState,
            'sessions' => $data['sessions'],
            'candidate_exam_sessions' => $data['candidate_exam_sessions'],
            'aptitude_areas_count' => $data['aptitude_areas_count'],
            'degraded' => config('xiam.mode') === 'degraded',
            'aptitude_areas' => $data['aptitude_areas'],
            'cycles' => $data['cycles'],
            'rooms' => $data['rooms'],
            'active_academic_cycle' => $activeCycle === null ? null : [
                'id' => $activeCycle->id,
                'label' => $activeCycle->staffLabel(),
            ],
            'score_import_targets' => $scoreImportTargets,
            'import_kit_versions' => $kitVersions,
            'import_norm_versions' => $normVersions,
            'can_import_scores' => $canImportScores,
            'scanner_url' => $canUseOmr ? route('scoring.omr.scanner') : null,
            'can_manage_catalog' => $canManageCatalog,
            'setup_links' => $canManageCatalog ? [
                'test_kits' => '/setup/exam-materials/test-kits',
            ] : null,
            'filters' => $data['filters'],
            'summary' => $data['summary'],
            'sort_options' => $data['sort_options'],
        ]);
    }

    public function store(OpenGradingSessionRequest $request, OpenGradingSession $action): RedirectResponse
    {
        $examSession = ExamSession::query()->findOrFail($request->integer('exam_session_id'));

        try {
            $gradingSession = $action->handle($examSession, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['open' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return redirect()->route('grading.sessions.show', $gradingSession->id)
            ->with('status', $gradingSession->wasRecentlyCreated
                ? 'Grading session opened.'
                : 'Grading session already exists.');
    }

    public function show(
        Request $request,
        ExamSession|GradingSession|string|int $examSession,
        ScoringSessionContextResolver $contexts,
        CapabilityAuthorizer $authorizer,
        ?AssessmentAdministrationFinalizationPredicate $predicate = null,
    ): Response {
        $examSession = $contexts->fromRoute($request);

        if ($examSession instanceof GradingSession) {
            $gradingSession = $examSession;
            $examSession = $gradingSession->examSession;
            abort_if($examSession === null, 404);
        } else {
            $gradingSession = $contexts->resolveForRead(
                $examSession,
                $request->user(),
                CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
            );
        }
        abort_if($gradingSession === null, 404);
        $gradingSession->loadMissing('examSession.academicCycle', 'examSession.room');
        $examSession = $gradingSession->examSession;
        $sessionInstitutionId = (int) $examSession?->academicCycle?->institution_id;
        if ($sessionInstitutionId !== (int) ($request->user()?->staffProfile?->institution_id ?? Institution::current()->id)
            || (string) ($examSession?->practice_fixture_set_id ?? '') !== (string) (app(PracticeWorkspace::class)->currentId() ?? '')) {
            abort(404);
        }
        $actor = $request->user();
        $institution = Institution::query()->findOrFail($sessionInstitutionId);
        abort_unless($authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_SCORING_MANUAL, [
            'institution_id' => $sessionInstitutionId,
            'office' => 'guidance',
        ])->allowed, 403);
        $canManageCatalog = $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, [
            'institution_id' => $institution->id,
            'office' => 'guidance',
        ]);

        $predicate = $predicate ?? app(AssessmentAdministrationFinalizationPredicate::class);
        $predicateResult = $predicate->evaluate($gradingSession);
        $readinessState = $predicateResult['state'];

        $gradingSession->loadMissing('examSession.testInstrumentVersion.metrics.construct');
        $sessionProfile = $gradingSession->examSession?->testInstrumentVersion;
        $sessionProfileMetrics = $sessionProfile?->metrics ?? collect();
        $areaCount = $sessionProfile !== null
            ? $sessionProfileMetrics->where('is_required', true)->count()
            : AptitudeArea::query()->where('is_active', true)->count();

        $assignments = ExamAssignment::query()
            ->where('exam_session_id', $gradingSession->exam_session_id)
            ->active()
            ->with(['attendanceRecord', 'submissionRecord', 'admissionAttempt.identity'])
            ->get();
        $eligible = GradingRoster::filterEligible($assignments);
        $eligibleAttemptIds = $eligible->pluck('admission_attempt_id');
        $administrations = AssessmentAdministration::query()
            ->whereIn('exam_assignment_id', $eligible->pluck('id'))
            ->active()
            ->with([
                'currentRawResultRevisions',
                'currentConversionRevision',
                'testInstrumentVersion.metrics.construct',
            ])
            ->get()
            ->keyBy('exam_assignment_id');
        $recordsByAttempt = $gradingSession->scoreRecords()
            ->whereIn('admission_attempt_id', $eligibleAttemptIds)
            ->get([
                'id',
                'admission_attempt_id',
                'aptitude_area_id',
                'score',
                'converted_score',
                'source',
            ])
            ->groupBy('admission_attempt_id');

        $applicants = $eligible->map(function (ExamAssignment $assignment) use (
            $recordsByAttempt,
            $administrations,
            $areaCount,
            $sessionProfileMetrics,
            $examSession,
        ): array {
            $attempt = $assignment->admissionAttempt;
            $identity = $attempt?->identity;
            $name = $identity
                ? trim(implode(' ', array_filter([
                    $identity->first_name,
                    $identity->middle_name,
                    $identity->last_name,
                    $identity->suffix,
                ])))
                : '—';
            $attemptRecords = $recordsByAttempt->get($assignment->admission_attempt_id, collect());
            $administration = $administrations->get($assignment->id);
            $profileMetrics = $administration?->testInstrumentVersion?->metrics ?? $sessionProfileMetrics;
            $isProfileBound = $administration !== null || $profileMetrics->isNotEmpty();
            $currentRawRevisions = $administration?->currentRawResultRevisions ?? collect();
            $currentConversion = $administration?->currentConversionRevision;

            if ($isProfileBound) {
                $requiredMetricCodes = collect($administration?->required_metric_set ?? [])
                    ->map(fn ($item) => is_array($item) ? ($item['code'] ?? null) : $item)
                    ->filter()
                    ->unique()
                    ->values();
                if ($requiredMetricCodes->isEmpty()) {
                    $requiredMetricCodes = $profileMetrics
                        ->where('is_required', true)
                        ->pluck('code')
                        ->unique()
                        ->values();
                }

                $typedOutputs = is_array($currentConversion?->typed_outputs_json)
                    ? $currentConversion->typed_outputs_json
                    : [];
                $domainsTotal = $requiredMetricCodes->count();
                $domainsComplete = $requiredMetricCodes->filter(function (string $metricCode) use ($profileMetrics, $typedOutputs): bool {
                    $metric = $profileMetrics->firstWhere('code', $metricCode);
                    $outputCodes = array_filter([
                        $metricCode,
                        $metric?->construct?->code,
                    ]);
                    foreach ($outputCodes as $code) {
                        $outputs = $typedOutputs[$code] ?? null;
                        if (! is_array($outputs)) {
                            continue;
                        }
                        $score = $outputs['percentile_rank'] ?? $outputs['converted_score'] ?? null;
                        if (is_numeric($score) && (float) $score >= 0 && (float) $score <= 100) {
                            return true;
                        }
                    }

                    return false;
                })->count();
                $hasScoringEntries = $currentRawRevisions->isNotEmpty()
                    || $currentConversion !== null
                    || $attemptRecords->isNotEmpty();
                $allRequiredConverted = $domainsTotal > 0 && $domainsComplete >= $domainsTotal;
                $isReady = in_array($administration?->binding_state, [
                    AssessmentAdministration::STATE_READY_TO_FINALIZE,
                    AssessmentAdministration::STATE_FINALIZED,
                ], true) || $allRequiredConverted;
            } else {
                $domainsComplete = $attemptRecords->pluck('aptitude_area_id')->unique()->count();
                $domainsTotal = $areaCount;
                $hasScoringEntries = $attemptRecords->isNotEmpty();
                $isReady = $domainsTotal > 0 && $domainsComplete >= $domainsTotal;
            }

            $progressState = $isReady
                ? 'complete'
                : ($administration?->binding_state === AssessmentAdministration::STATE_AWAITING_NORM_CONVERSION
                    ? 'awaiting_norms'
                    : ($hasScoringEntries ? 'incomplete' : 'not_started'));

            $sourceValues = $currentRawRevisions
                ->where('source', '!=', AssessmentRawResultRevision::SOURCE_DERIVED_COMPOSITE)
                ->pluck('source')
                ->merge($attemptRecords->pluck('source'))
                ->unique()
                ->values();
            $sourceMode = $currentConversion?->source === AssessmentConversionRevision::SOURCE_MANUAL_CONVERTED
                ? 'Direct Converted'
                : ($sourceValues->contains(AssessmentRawResultRevision::SOURCE_OMR)
                    && $sourceValues->contains(AssessmentRawResultRevision::SOURCE_MANUAL)
                    ? 'OMR + Manual'
                    : ($sourceValues->contains(AssessmentRawResultRevision::SOURCE_OMR)
                        ? 'OMR'
                        : ($sourceValues->contains(AssessmentRawResultRevision::SOURCE_MANUAL)
                            ? 'Manual Raw'
                            : ($sourceValues->contains(AssessmentRawResultRevision::SOURCE_IMPORT)
                                ? 'Import'
                                : '—'))));

            return [
                'attempt_id' => $assignment->admission_attempt_id,
                'reference_code' => $attempt?->reference_code ?? '—',
                'name' => $name !== '' ? $name : '—',
                'progress_state' => $progressState,
                'source_mode' => $sourceMode,
                'domains_complete' => $domainsComplete,
                'domains_total' => $domainsTotal,
                'scored' => $progressState === 'complete',
                'score_url' => route('scoring.sessions.attempts.scores', [
                    'examSession' => $examSession->id,
                    'attempt' => $assignment->admission_attempt_id,
                ], false),
            ];
        })->values();

        $validated = $request->validate([
            'focus_attempt_id' => [
                'nullable',
                'integer',
                Rule::in($eligible->pluck('admission_attempt_id')->all()),
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'progress' => ['nullable', 'string', Rule::in(['all', 'complete', 'incomplete', 'awaiting_norms', 'not_started'])],
            'source_mode' => ['nullable', 'string', Rule::in(['all', 'omr', 'omr_manual', 'manual_raw', 'direct_converted'])],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $progressFilter = (string) ($validated['progress'] ?? 'all');
        $sourceModeFilter = (string) ($validated['source_mode'] ?? 'all');
        $filteredApplicants = $applicants->filter(function (array $applicant) use ($search, $progressFilter, $sourceModeFilter): bool {
            if ($search !== ''
                && mb_stripos($applicant['name'], $search) === false
                && mb_stripos($applicant['reference_code'], $search) === false
            ) {
                return false;
            }

            if ($progressFilter !== 'all' && $applicant['progress_state'] !== $progressFilter) {
                return false;
            }

            $sourceFilter = [
                'all' => null,
                'omr' => 'OMR',
                'omr_manual' => 'OMR + Manual',
                'manual_raw' => 'Manual Raw',
                'direct_converted' => 'Direct Converted',
            ][$sourceModeFilter];

            return $sourceFilter === null || $applicant['source_mode'] === $sourceFilter;
        })->values()->all();

        $stats = $this->rosterStats($assignments);
        $progressStats = [
            'total' => $applicants->count(),
            'scored' => $applicants->where('scored', true)->count(),
            'pending' => $applicants->where('scored', false)->count(),
        ];

        $pageState = config('xiam.mode') === 'degraded' ? 'degraded' : 'ready';

        return Inertia::render('Assessment/GradingSession', [
            'page_state' => $pageState,
            'is_final_only' => $sessionProfile?->isFinalOnly() ?? false,
            'session' => [
                'id' => $examSession->id,
                'exam_session_id' => $examSession->id,
                'exam_code' => $examSession->code ?? '—',
                'starts_at' => $examSession->starts_at?->format('Y-m-d H:i'),
                'room_name' => $examSession->room?->name ?? '—',
                'status' => $gradingSession->status,
                'grading_status' => $gradingSession->status,
                'version' => (int) $gradingSession->version,
                'grading_version' => (int) $gradingSession->version,
                'opened_at' => $gradingSession->opened_at?->toIso8601String(),
                'finalized_at' => $gradingSession->finalized_at?->toIso8601String(),
                'finalized_by_name' => $gradingSession->finalizedBy?->name,
                'readiness_state' => $readinessState,
                'score_url' => route('scoring.sessions.show', $examSession),
                'finalize_url' => route('scoring.sessions.finalize', ['examSession' => $examSession->id]),
            ],
            'applicants' => $filteredApplicants,
            'focus_attempt_id' => $validated['focus_attempt_id'] ?? null,
            'roster_stats' => $stats,
            'progress_stats' => $progressStats,
            'filters' => [
                'search' => $search,
                'progress' => $progressFilter,
                'source_mode' => $sourceModeFilter,
            ],
            'active_academic_cycle_id' => $examSession->academic_cycle_id,
            'aptitude_areas_count' => $areaCount,
            'can_finalize' => $gradingSession->status !== GradingSession::STATUS_FINALIZED
                && $predicateResult['ok'],
            'readiness_state' => $readinessState,
            'readiness' => [
                'state' => $readinessState,
                'ok' => $predicateResult['ok'],
                'reason' => $predicateResult['reason'],
                'details' => $predicateResult['details'],
            ],
            'can_manage_catalog' => $canManageCatalog,
            'setup_links' => $canManageCatalog ? [
                'test_kits' => '/setup/exam-materials/test-kits',
            ] : null,
        ]);
    }

    public function omrScanner(
        DiscoverOmrSessionRequest $request,
        OmrScannerSessionResolver $resolver,
        OmrRecoveryCatalog $recoveryCatalog,
        OmrPendingCaptureAccess $pendingAccess,
        RefreshOmrPendingResolution $refreshPendingResolution,
    ): Response|RedirectResponse {
        $applicantNumber = $request->validated('applicant_number');
        $pendingId = $request->integer('pending_capture');
        $pending = $pendingId > 0
            ? OmrPendingCapture::query()
                ->with(['gradingSession.examSession.room'])
                ->findOrFail($pendingId)
            : null;

        if ($pending !== null) {
            $pendingAccess->assertPending($request->user(), $pending);
        }

        if ($pending?->status === OmrPendingCapture::STATUS_ADOPTED && $pending->adopted_sheet_id !== null) {
            $sheet = $pending->adoptedSheet()->with('batch')->first();
            if ($sheet?->batch !== null) {
                return redirect()->route('scoring.sessions.omr', [
                    'examSession' => $sheet->batch->gradingSession->exam_session_id,
                    'omr_sheet' => $sheet->id,
                ]);
            }
        }
        if ($pending !== null) {
            $pending = $refreshPendingResolution->handle($pending, $request->user());
        }

        $context = $pending === null && is_string($applicantNumber) && $applicantNumber !== ''
            ? $resolver->discover($applicantNumber, $request->user())
            : null;

        if ($pending === null
            && $context?->outcome === OmrScannerContextOutcome::AutoResolved
            && $context->gradingSession !== null) {
            return redirect()
                ->route('scoring.sessions.omr', ['examSession' => $context->gradingSession->exam_session_id])
                ->with('status', 'Session resolved from the Applicant Number. Continue scanning without selecting it again.');
        }

        $recoveryCode = $pending !== null
            ? $this->pendingRecoveryCode($pending)
            : $context?->resolutionCode;
        $recovery = $recoveryCode === null
            ? null
            : $recoveryCatalog->for($recoveryCode);

        if ($pending !== null && $recovery !== null) {
            $recovery['technical_detail'] = [
                'analysis_code' => $pending->analysis_code,
                'pending_capture_id' => $pending->id,
            ];
            $recovery['destination_url'] = $this->pendingRecoveryDestination($pending, $recovery);
        }

        $pendingResolution = $pending !== null && is_array($pending->resolution_json)
            ? $pending->resolution_json
            : [];
        $pendingCandidates = is_array($pendingResolution['candidates'] ?? null)
            ? $pendingResolution['candidates']
            : [];

        return Inertia::render('Assessment/Omr/Scanner', [
            'applicant_number' => $pending?->detected_applicant_number ?? $applicantNumber,
            'outcome' => $pending?->status === OmrPendingCapture::STATUS_READY
                ? OmrScannerContextOutcome::AutoResolved->value
                : ($pending !== null ? OmrScannerContextOutcome::SelectionRequired->value : $context?->outcome->value),
            'candidates' => $pending !== null ? $pendingCandidates : ($context?->candidates ?? []),
            'recovery' => $recovery,
            'pending_capture' => $pending === null ? null : $this->pendingCapturePayload($pending),
            'capture_url' => route('scoring.omr.scanner.capture'),
        ]);
    }

    public function storeOmrScannerCapture(
        StoreOmrScannerCaptureRequest $request,
        CaptureGenericOmrImage $action,
    ): RedirectResponse {
        try {
            $pending = $action->handle(
                $request->user(),
                (string) $request->file('image')->get(),
                (string) $request->file('image')->getMimeType(),
                (string) $request->string('client_capture_id'),
                (string) $request->string('idempotency_key'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['scanner' => $exception->getMessage()]);
        }

        return redirect()
            ->route('scoring.omr.scanner', ['pending_capture' => $pending->id])
            ->with('status', 'Capture preserved. Resolving the sheet identity and session.');
    }

    public function retryOmrScannerCapture(
        RetryOmrPendingCaptureRequest $request,
        OmrPendingCapture $pending,
        CaptureGenericOmrImage $action,
    ): RedirectResponse {
        try {
            $action->retry(
                $pending,
                $request->user(),
                $request->integer('expected_version'),
                (string) $request->string('idempotency_key'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['scanner' => $exception->getMessage()]);
        }

        return back()->with('status', 'The preserved capture was queued for another analysis pass.');
    }

    public function resolveOmrPendingApplicant(
        ResolveOmrPendingApplicantRequest $request,
        OmrPendingCapture $pending,
        ResolveOmrPendingCapture $action,
    ): RedirectResponse {
        try {
            $action->resolveApplicantNumber(
                $pending,
                $request->user(),
                (string) $request->string('applicant_number'),
                $request->integer('expected_version'),
                (string) $request->string('idempotency_key'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['scanner' => $exception->getMessage()]);
        }

        return back()->with('status', 'Applicant Number matched against eligible session records.');
    }

    public function selectOmrPendingSession(
        SelectOmrPendingSessionRequest $request,
        OmrPendingCapture $pending,
        ResolveOmrPendingCapture $action,
    ): RedirectResponse {
        try {
            $gradingSessionId = $request->integer('grading_session_id');
            if ($gradingSessionId === 0 && $request->filled('exam_session_id')) {
                $examSession = ExamSession::query()->find($request->integer('exam_session_id'));
                $gradingSessionId = (int) ($examSession?->gradingSession?->id ?? 0);
            }

            $action->selectSession(
                $pending,
                $request->user(),
                $gradingSessionId,
                $request->integer('expected_version'),
                (string) $request->string('idempotency_key'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['scanner' => $exception->getMessage()]);
        }

        return back()->with('status', 'Eligible session selected. Review the preserved capture before saving.');
    }

    public function resolveOmrPendingDisplayCode(
        ResolveOmrPendingDisplayCodeRequest $request,
        OmrPendingCapture $pending,
        ResolveOmrPendingCapture $action,
    ): RedirectResponse {
        try {
            $action->resolveDisplayCode(
                $pending,
                $request->user(),
                (string) $request->string('display_code'),
                $request->boolean('manual_confirmed'),
                $request->integer('expected_version'),
                (string) $request->string('idempotency_key'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['scanner' => $exception->getMessage()]);
        }

        return back()->with('status', 'Display Code recorded. The preserved image is being analyzed against its trusted template.');
    }

    public function adoptOmrPendingCapture(
        AdoptOmrPendingCaptureRequest $request,
        OmrPendingCapture $pending,
        AdoptOmrPendingCapture $action,
    ): RedirectResponse {
        try {
            $sheet = $action->handle(
                $pending,
                $request->user(),
                $request->integer('expected_version'),
                (string) $request->string('idempotency_key'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['scanner' => $exception->getMessage()]);
        }

        return redirect()->route('scoring.sessions.omr', [
            'examSession' => $sheet->batch->gradingSession->exam_session_id,
            'omr_sheet' => $sheet->id,
        ])->with('status', 'Capture adopted into the session workbench. Review it before confirming the batch.');
    }

    private function pendingRecoveryCode(OmrPendingCapture $pending): ?string
    {
        if ($pending->status === OmrPendingCapture::STATUS_READY || $pending->status === OmrPendingCapture::STATUS_ADOPTED) {
            return null;
        }
        $resolution = is_array($pending->resolution_json) ? $pending->resolution_json : [];

        return is_string($resolution['resolution_code'] ?? null)
            ? $resolution['resolution_code']
            : ($pending->analysis_code ?? 'applicant_unresolved');
    }

    /** @return array<string, mixed> */
    private function pendingCapturePayload(OmrPendingCapture $pending): array
    {
        $resolution = is_array($pending->resolution_json) ? $pending->resolution_json : [];
        $candidates = is_array($resolution['candidates'] ?? null) ? $resolution['candidates'] : [];
        $recoveryCode = $this->pendingRecoveryCode($pending);
        $session = $pending->gradingSession;

        return [
            'id' => (int) $pending->id,
            'status' => $pending->status,
            'version' => (int) $pending->version,
            'page_no' => (int) $pending->page_no,
            'qr_state' => $pending->qr_state,
            'analysis_code' => $pending->analysis_code,
            'analysis_detail' => $pending->analysis_detail,
            'detected_applicant_number' => $pending->detected_applicant_number,
            'detected_applicant_confidence' => $pending->detected_applicant_confidence,
            'resolution_code' => $recoveryCode,
            'candidates' => $candidates,
            'session' => $session === null ? null : [
                'id' => (int) $session->id,
                'exam_code' => (string) ($session->examSession?->code ?? '—'),
                'room_name' => (string) ($session->examSession?->room?->name ?? '—'),
            ],
            'actions' => [
                'retry' => route('scoring.omr.scanner.capture.retry', $pending),
                'resolve_applicant' => route('scoring.omr.scanner.capture.resolve-applicant', $pending),
                'select_session' => route('scoring.omr.scanner.capture.select-session', $pending),
                'resolve_display_code' => route('scoring.omr.scanner.capture.resolve-display-code', $pending),
                'adopt' => route('scoring.omr.scanner.capture.adopt', $pending),
            ],
        ];
    }

    /** @param array<string, mixed> $recovery */
    private function pendingRecoveryDestination(OmrPendingCapture $pending, array $recovery): ?string
    {
        if ($recovery['primary_action'] !== 'open_monitoring') {
            return null;
        }
        $candidates = is_array($pending->resolution_json['candidates'] ?? null)
            ? $pending->resolution_json['candidates']
            : [];
        $candidate = $candidates[0] ?? null;
        $monitoring = is_array($candidate) && is_string($candidate['monitoring_url'] ?? null)
            ? $candidate['monitoring_url']
            : null;
        if ($monitoring === null) {
            return null;
        }
        $returnTo = '/scoring/omr?pending_capture='.(int) $pending->id;
        $separator = str_contains($monitoring, '?') ? '&' : '?';

        return $monitoring.$separator.'return_to='.rawurlencode($returnTo);
    }

    public function omr(
        Request $request,
        ExamSession $examSession,
        ScoringSessionContextResolver $contexts,
        ProductionOmrAuthorityAdapter $authority,
        GradingOmrCapturePresenter $presenter,
    ): Response {
        $gradingSession = $contexts->resolveForRead(
            $examSession,
            $request->user(),
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
        abort_if($gradingSession === null, 404);

        return Inertia::render(
            'Assessment/Omr/Capture',
            $presenter->present($request, $gradingSession, $authority),
        );
    }

    public function omrStatus(
        Request $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        ScoringSessionContextResolver $contexts,
        OmrRunStatusSnapshot $snapshot,
    ): JsonResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, false);
        abort_unless((int) $batch->grading_session_id === (int) $gradingSession->id, 404);

        return response()->json($snapshot->forBatch($batch));
    }

    public function omrEvidence(
        Request $request,
        ExamSession $examSession,
        int $sheet,
        OmrEvidenceQuery $evidence,
        OmrEvidenceCache $cache,
        CapabilityAuthorizer $authorizer,
        ScoringSessionContextResolver $contexts,
    ): JsonResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, false);
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR), 403);
        $sheetModel = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();
        $parameters = [
            'method' => $request->query('method', 'pre_shaded'),
            'capture_id' => $request->query('capture_id'),
            'area' => $request->query('area', 'all'),
            'filter' => $request->query('filter'),
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', OmrEvidencePage::DEFAULT_PER_PAGE),
            'blind' => (bool) $request->boolean('blind') || (bool) config('omr.blind_review', false),
        ];
        try {
            $page = $evidence->paginateGradingSheet($sheetModel, $request->user(), $parameters);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'evidence_query_invalid',
                'detail' => $exception->getMessage(),
            ], 422);
        }
        $etag = $cache->etag($page);
        $headers = [
            'Cache-Control' => 'private, no-store',
            'ETag' => $etag,
            'Vary' => 'Accept, If-None-Match',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if (trim((string) $request->header('If-None-Match', '')) === $etag) {
            return response()->json(null, 304, $headers);
        }

        return response()->json($page->toArray(), 200, $headers);
    }

    public function omrImage(
        Request $request,
        ExamSession $examSession,
        int $sheet,
        ScoringSessionContextResolver $contexts,
        SensitiveAssetStore $assetStore,
        CapabilityAuthorizer $authorizer,
        OmrCaptureThumbnailService $thumbnailService,
        MediaStreamResponder $responder,
        ?string $sha256 = null,
    ): \Illuminate\Http\Response {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, false);

        return $this->serveOmrImage(
            $request,
            $gradingSession,
            $sheet,
            1,
            $sha256,
            $assetStore,
            $authorizer,
            $thumbnailService,
            $responder,
        );
    }

    public function omrPageImage(
        Request $request,
        ExamSession $examSession,
        int $sheet,
        int $page,
        ScoringSessionContextResolver $contexts,
        SensitiveAssetStore $assetStore,
        CapabilityAuthorizer $authorizer,
        OmrCaptureThumbnailService $thumbnailService,
        MediaStreamResponder $responder,
        ?string $sha256 = null,
    ): \Illuminate\Http\Response {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, false);

        return $this->serveOmrImage(
            $request,
            $gradingSession,
            $sheet,
            $page,
            $sha256,
            $assetStore,
            $authorizer,
            $thumbnailService,
            $responder,
        );
    }

    private function serveOmrImage(
        Request $request,
        GradingSession $gradingSession,
        int $sheet,
        int $page,
        ?string $sha256,
        SensitiveAssetStore $assetStore,
        CapabilityAuthorizer $authorizer,
        OmrCaptureThumbnailService $thumbnailService,
        MediaStreamResponder $responder,
    ): \Illuminate\Http\Response {
        $variant = $request->query('variant');
        abort_unless($variant === null || $variant === 'thumbnail', 404);
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR), 403);
        $capture = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();

        if ((int) $capture->expected_page_count <= 1 && $capture->pages()->doesntExist()) {
            $assetPath = $capture->aligned_asset_path ?: $capture->asset_path;
            $assetHmac = $capture->aligned_asset_hmac ?: $capture->asset_hmac;
            $mime = $capture->aligned_mime ?: $capture->original_mime;
            $knownHash = $capture->aligned_hash;
        } else {
            $pageRow = $capture->pages()->where('page_no', $page)->firstOrFail();
            $assetPath = $pageRow->aligned_asset_path ?: $pageRow->asset_path;
            $assetHmac = $pageRow->aligned_asset_hmac ?: $pageRow->asset_hmac;
            $mime = $pageRow->aligned_mime ?: $pageRow->original_mime;
            $knownHash = $pageRow->aligned_hash;
        }
        abort_unless(is_string($assetPath) && trim($assetPath) !== '' && is_string($assetHmac) && trim($assetHmac) !== '', 404);

        if ($variant === 'thumbnail') {
            abort_unless($sha256 === null, 404);

            try {
                $thumbnail = $thumbnailService->thumbnail($assetPath, $assetHmac, (string) $mime);
            } catch (\Throwable) {
                abort(404);
            }

            return $responder->inline($request, $thumbnail, 'image/webp', 'private, no-store', 'omr-thumbnail.webp');
        }

        try {
            $bytes = $assetStore->read($assetPath, $assetHmac);
        } catch (\Throwable) {
            abort(404);
        }
        $contentHash = hash('sha256', $bytes);
        if ($sha256 !== null) {
            abort_unless(preg_match('/^[a-f0-9]{64}$/i', $sha256) === 1 && hash_equals($contentHash, strtolower($sha256)), 404);
        }
        if (is_string($knownHash) && preg_match('/^[a-f0-9]{64}$/i', $knownHash) === 1) {
            abort_unless(hash_equals(strtolower($knownHash), $contentHash), 404);
        }
        $etag = '"'.$contentHash.'"';
        $headers = [
            'Cache-Control' => $sha256 !== null
                ? 'private, max-age=31536000, immutable'
                : 'private, no-store',
            'ETag' => $etag,
            'Vary' => 'Accept, If-None-Match',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if (trim((string) $request->header('If-None-Match', '')) === $etag) {
            return response('', 304, $headers);
        }
        $safeMime = match (strtolower((string) $mime)) {
            'image/png' => 'image/png',
            'image/jpeg', 'image/jpg' => 'image/jpeg',
            'image/webp' => 'image/webp',
            default => 'application/octet-stream',
        };
        $extension = match ($safeMime) {
            'image/png' => '.png',
            'image/jpeg' => '.jpg',
            'image/webp' => '.webp',
            default => '.bin',
        };

        return response($bytes, 200, [
            ...$headers,
            'Content-Type' => $safeMime,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => 'inline; filename="omr-evidence-'.$contentHash.$extension.'"',
        ]);
    }

    /**
     * Read-only capture preflight (tasks 10.7/10.18). The batch's pinned
     * template version is the geometry authority; marker batches run the
     * CropOnMarkers-only worker diagnostic, scan batches answer
     * `not_applicable`. Never recognizes, scores, or mutates state.
     */
    public function preflightCapture(
        PreflightOmrCaptureRequest $request,
        ExamSession $examSession,
        int $batch,
        PreflightOmrCapture $preflight,
        ScoringSessionContextResolver $contexts,
        ResolveOmrStagedPreflightImage $stagedOriginal,
    ): JsonResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, false);
        $batchModel = OmrCaptureBatch::query()
            ->whereKey($batch)
            ->where('grading_session_id', $gradingSession->id)
            ->with('templateVersion')
            ->firstOrFail();

        try {
            $original = $request->filled('staged_frame_id')
                ? $stagedOriginal->handle(
                    $batchModel,
                    $request->user(),
                    (string) $request->validated('pairing_id'),
                    (string) $request->validated('camera_command_id'),
                    (string) $request->validated('staged_frame_id'),
                    (string) $request->header('X-Omr-Camera-Role', ''),
                    (string) $request->header('X-Omr-Camera-Token', ''),
                    (int) $request->header('X-Omr-Camera-Generation', 0),
                )
                : ['bytes' => (string) $request->file('image')->get(), 'mime' => (string) $request->file('image')->getMimeType()];
            $result = $preflight->handle(
                $batchModel,
                $original['bytes'],
                $original['mime'],
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        return response()->json([
            'mode' => $result->mode,
            'outcome' => $result->outcome,
            'code' => $result->code,
            'detail' => $result->detail,
            'orientation_hint' => $result->orientationHint,
            'marker_matches' => $result->markerMatches,
            'gates' => $result->gates,
            'normalized_dimensions' => $result->normalizedDimensions,
            'submit_allowed' => $result->submitAllowed,
            'calibration_status' => 'unqualified',
            'rectification' => $result->rectification,
            'timings' => [
                'preprocess_ms' => $result->preprocessMs,
                'preflight_ms' => $result->preflightMs,
            ],
        ]);
    }

    public function storeOmrBatch(
        StoreOmrBatchRequest $request,
        ExamSession $examSession,
        CreateOmrCaptureBatch $action,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, true);
        try {
            $batch = $action->handle(
                $gradingSession,
                $request->user(),
                (string) $request->string('idempotency_key'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('OMR batch #%d is ready.', $batch->id));
    }

    public function authorizeOmrPaperReproduction(
        AuthorizeOmrPaperReproductionRequest $request,
        ExamSession $examSession,
        AuthorizeOmrPaperReproduction $action,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, true);
        try {
            $authorization = $action->handle(
                $gradingSession,
                $request->user(),
                (string) $request->string('display_code'),
                $request->integer('page_no'),
                $request->integer('quantity'),
                (string) $request->string('reason'),
                (string) $request->string('idempotency_key'),
                $request->date('expires_at'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['reproduction' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf(
            'Emergency copy authorization #%d is open for %d capture%s.',
            $authorization->id,
            $authorization->maximum_claims,
            $authorization->maximum_claims === 1 ? '' : 's',
        ));
    }

    public function storeOmrSheet(
        StoreOmrSheetRequest $request,
        ExamSession $examSession,
        UploadOmrCaptureSheet $upload,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, true);
        $batch = OmrCaptureBatch::query()
            ->where('grading_session_id', $gradingSession->id)
            ->whereIn('status', OmrCaptureBatch::ACTIVE_STATUSES)
            ->firstOrFail();

        try {
            $sheet = $upload->handle(
                $batch,
                (string) $request->file('image')->get(),
                (string) $request->file('image')->getMimeType(),
                (string) $request->string('capture_mode'),
                (string) $request->string('client_capture_id'),
                $request->user(),
                null,
                (int) ($request->integer('page_no') ?: 1),
                null,
                $request->input('qr_state') !== null ? (string) $request->input('qr_state') : null,
                $request->input('display_code') !== null ? (string) $request->input('display_code') : null,
                (bool) $request->boolean('manual_confirmed'),
                $request->input('observed_set_code') !== null ? (string) $request->input('observed_set_code') : null,
                clientQuad: $request->validated('client_quad'),
            );
            $sheet->refresh();
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return redirect()->route('scoring.sessions.omr', ['examSession' => $examSession])->with('status', sprintf('Scan #%d is queued for analysis.', $sheet->id));
    }

    public function reviewOmrSheet(
        ReviewOmrSheetRequest $request,
        ExamSession $examSession,
        int $sheet,
        ReviewOmrCaptureSheet $action,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, true);
        $capture = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();

        try {
            $action->handle(
                $capture,
                $request->user(),
                $request->input('action') === 'abstain' ? (string) $request->input('abstain_reason') : null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('Scan #%d review saved.', $capture->id));
    }

    public function resolveOmrApplicant(
        ResolveOmrApplicantExceptionRequest $request,
        ExamSession $examSession,
        int $sheet,
        ResolveOmrApplicantException $action,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, true);
        $capture = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();
        $assignment = ExamAssignment::query()
            ->whereKey($request->integer('exam_assignment_id'))
            ->where('exam_session_id', $gradingSession->exam_session_id)
            ->firstOrFail();

        try {
            $action->handle($capture, $assignment, $request->string('reason')->toString(), $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('Scan #%d applicant exception resolved.', $capture->id));
    }

    public function resolveOmrDuplicate(
        ResolveOmrDuplicateDispositionRequest $request,
        ExamSession $examSession,
        int $sheet,
        ResolveOmrDuplicateDisposition $action,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, true);
        $survivor = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();
        $rejected = OmrCaptureSheet::query()
            ->whereKey($request->integer('rejected_sheet_id'))
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();

        try {
            $action->handle(
                $survivor,
                $rejected,
                $request->string('reason')->toString(),
                $request->user(),
                $request->integer('survivor_version'),
                $request->integer('rejected_version'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('Scan #%d retained; duplicate #%d rejected.', $survivor->id, $rejected->id));
    }

    public function resolveOmrIssue(
        ResolveOmrSheetIssueRequest $request,
        ExamSession $examSession,
        int $sheet,
        int $issue,
        ResolveOmrCaptureSheetIssue $action,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, true);
        $capture = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();
        $captureIssue = $capture->issues()->whereKey($issue)->firstOrFail();

        try {
            $action->handle(
                $captureIssue,
                $request->user(),
                (string) $request->input('resolution_code'),
                (string) $request->input('resolution_reason'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('OMR issue #%d resolved.', $captureIssue->id));
    }

    public function confirmOmrBatch(
        Request $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        ConfirmOmrCaptureBatch $action,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, true);
        if ((int) $batch->grading_session_id !== (int) $gradingSession->id) {
            abort(404);
        }

        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $action->handle($batch, $request->user(), (int) $validated['revision']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('OMR batch #%d confirmed.', $batch->id));
    }

    public function rejectOmrBatch(
        Request $request,
        ExamSession $examSession,
        OmrCaptureBatch $batch,
        RejectOmrCaptureBatch $action,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, true);
        if ((int) $batch->grading_session_id !== (int) $gradingSession->id) {
            abort(404);
        }

        try {
            $action->handle($batch, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return redirect()
            ->route('grading.sessions.show', $gradingSession)
            ->with('status', sprintf('OMR batch #%d rejected.', $batch->id));
    }

    public function retryOmrSheet(
        Request $request,
        ExamSession $examSession,
        int $sheet,
        RetryOmrCaptureSheet $action,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, true);
        $capture = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();

        try {
            $action->handle($capture, $request->user(), (int) ($request->integer('page_no') ?: 1));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('Scan #%d queued for re-analysis.', $capture->id));
    }

    public function replaceOmrSheetPage(
        ReplaceOmrSheetPageRequest $request,
        ExamSession $examSession,
        int $sheet,
        int $page,
        ReplaceOmrSheetPage $action,
        ScoringSessionContextResolver $contexts,
    ): RedirectResponse {
        $gradingSession = $this->omrContext($examSession, $request, $contexts, true);
        $capture = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $payload = $request->validated();
        $file = $payload['image'];

        try {
            $action->handle(
                $capture,
                $page,
                (int) $payload['expected_source_page_id'],
                (int) $payload['expected_revision'],
                (string) $file->get(),
                (string) $file->getMimeType(),
                $actor,
                (string) $payload['replacement_reason'],
                (string) $payload['idempotency_key'],
                null,
                is_array($payload['client_quad'] ?? null) ? $payload['client_quad'] : null,
                (string) ($payload['crop_policy'] ?? 'auto_document_quad'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('Scan #%d page %d replaced.', $capture->id, $page));
    }

    public function finalize(
        FinalizeGradingSessionRequest $request,
        ExamSession|GradingSession|string|int $examSession,
        ScoringSessionContextResolver $contexts,
        FinalizeGradingSession $action,
    ): RedirectResponse {
        $examSession = $contexts->fromRoute($request);

        if ($examSession instanceof GradingSession) {
            $gradingSession = $examSession;
        } else {
            $gradingSession = $contexts->requireForWrite(
                $examSession,
                $request->user(),
                CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
            );
        }

        try {
            $action->execute(
                $request->user(),
                $gradingSession,
                (int) $request->validated('expected_version'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['finalize' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return redirect()
            ->route('scoring.sessions.show', ['examSession' => $gradingSession->exam_session_id])
            ->with('status', 'Grading session finalized.');
    }

    private function omrContext(
        ExamSession $examSession,
        Request $request,
        ScoringSessionContextResolver $contexts,
        bool $write,
    ): GradingSession {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        if ($write) {
            return $contexts->requireForWrite(
                $examSession,
                $actor,
                CapabilityCatalog::GUIDANCE_SCORING_OMR,
            );
        }

        $gradingSession = $contexts->resolveForRead(
            $examSession,
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
        abort_if($gradingSession === null, 404);

        return $gradingSession;
    }

    /**
     * @param  Collection<int, ExamAssignment>  $assignments
     * @return array{assigned: int, present_and_submitted: int, absent: int, pending_attendance: int, no_submission: int}
     */
    private function rosterStats(Collection $assignments): array
    {
        $absent = 0;
        $pending = 0;
        $present = 0;
        $noSubmission = 0;

        foreach ($assignments as $assignment) {
            $outcome = $assignment->attendanceRecord?->outcome;

            if ($outcome === null) {
                $pending++;
            } elseif ($outcome === ExamAttendanceRecord::OUTCOME_ABSENT) {
                $absent++;
            } else {
                $present++;
                if ($assignment->submissionRecord === null) {
                    $noSubmission++;
                }
            }
        }

        return [
            'assigned' => $assignments->count(),
            'present_and_submitted' => $present - $noSubmission,
            'absent' => $absent,
            'pending_attendance' => $pending,
            'no_submission' => $noSubmission,
        ];
    }
}
