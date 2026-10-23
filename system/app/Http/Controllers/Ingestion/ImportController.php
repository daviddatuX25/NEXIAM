<?php

namespace App\Http\Controllers\Ingestion;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\Http\Controllers\Controller;
use App\Ingestion\Actions\ApproveIngestionBatch;
use App\Ingestion\Actions\CommitAllInOneIngestionBatch;
use App\Ingestion\Actions\CommitApplicantIngestionBatch;
use App\Ingestion\Actions\CommitScoreIngestionBatch;
use App\Ingestion\Actions\RejectIngestionBatch;
use App\Ingestion\Actions\ResumeGuidanceIngestionBatch;
use App\Ingestion\Actions\StageImportBatch;
use App\Ingestion\Services\ErrorReportGenerator;
use App\Ingestion\Services\ResolvedImportContract;
use App\Ingestion\Services\SpreadsheetParser;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Ingestion\ImportBatch;
use App\Models\Ingestion\ImportBatchRow;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ingestion Engine Operational Controller.
 *
 * Orchestrates the staging, dual-authority review, error reporting, and official
 * commitment of bulk institutional examinee datasets.
 *
 * Intake Modes:
 * 1. all_in_one: Combined demographics + scores ingestion (requires Registrar & Guidance capabilities).
 * 2. multi_session: Workbook where each sheet represents a distinct physical room or scheduled exam session.
 * 3. applicants_only: Pure applicant demographics onboarding (Registrar-owned).
 * 4. scores_only: Direct assessment scoring intake matched to pre-existing applicant identifiers (Guidance-owned).
 *
 * Lifecycle:
 * - Pre-flight: Real-time analysis via ImportAnalyzeController (zero-database validation).
 * - Staging: Staged in import_batches & import_batch_rows with schema compliance checks.
 * - Dual Governance: Pending supervisor approval when configured, or auto-committed.
 * - Error Reconciliation: Rejected/invalid rows exportable via ErrorReportGenerator CSV stream.
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly StageImportBatch $stageBatch,
        private readonly CommitAllInOneIngestionBatch $commitAllInOne,
        private readonly CommitScoreIngestionBatch $commitScores,
        private readonly CommitApplicantIngestionBatch $commitApplicants,
        private readonly ResumeGuidanceIngestionBatch $resumeGuidance,
        private readonly ErrorReportGenerator $errorReportGenerator,
    ) {}

    public function show(ImportBatch $batch, Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        $this->assertCurrentInstitution($batch);
        if (! in_array($batch->status, [ImportBatch::STATUS_STAGED, ImportBatch::STATUS_PENDING_APPROVAL, ImportBatch::STATUS_AWAITING_GUIDANCE, ImportBatch::STATUS_REJECTED, ImportBatch::STATUS_FAILED, ImportBatch::STATUS_COMPLETED], true)) {
            abort(404);
        }
        $this->assertBatchViewCapability($batch, $user, $authorizer);

        $selection = $batch->topology_meta['assessment_selection'] ?? [];
        $kitVersionId = is_numeric($selection['test_kit_version_id'] ?? null)
            ? (int) $selection['test_kit_version_id']
            : null;
        $kitVersion = $kitVersionId === null
            ? null
            : TestKitVersion::query()->where('institution_id', $batch->institution_id)->findOrFail($kitVersionId);
        $contract = ResolvedImportContract::compile($batch->institution, $batch->academicCycle, $kitVersion);
        $isRegistrarConsent = in_array($batch->import_type, [ImportBatch::TYPE_ALL_IN_ONE, ImportBatch::TYPE_MULTI_SESSION], true)
            && $batch->status === ImportBatch::STATUS_PENDING_APPROVAL
            && $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT);
        $canScoreImports = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT);
        $isCombinedBatch = in_array($batch->import_type, [ImportBatch::TYPE_ALL_IN_ONE, ImportBatch::TYPE_MULTI_SESSION], true);
        $hideAssessmentDetails = $isRegistrarConsent || ($isCombinedBatch && ! $canScoreImports);
        $canSeeScores = ! $hideAssessmentDetails && $canScoreImports;
        $allowedFields = $isRegistrarConsent
            ? ['first_name', 'middle_name', 'last_name', 'suffix', 'birthdate', 'sex', 'applicant_number', 'course_preference_1', 'course_preference_2', 'course_preference_3', 'applicant_type', 'contact_email', 'contact_phone', 'address_line', 'city', 'province', 'zip_code', 'last_school_enrolled', 'strand']
            : array_values(array_unique([...$contract->requiredFields, ...$contract->optionalFields, 'first_name', 'middle_name', 'last_name', 'suffix', 'birthdate', 'sex', 'applicant_number', 'course_preference_1', 'course_preference_2', 'course_preference_3', 'applicant_type', 'contact_email', 'contact_phone', 'address_line', 'city', 'province', 'zip_code', 'last_school_enrolled', 'strand']));
        $sessionSourceHeaders = collect((array) ($selection['session_contracts'] ?? []))
            ->filter(fn (array $sessionContract): bool => ($sessionContract['input_kind'] ?? null) === 'external_final')
            ->flatMap(fn (array $sessionContract): array => array_values((array) ($sessionContract['source_header_map'] ?? [])))
            ->all();
        $sourceHeaders = $contract->finalOnly
            ? array_values($contract->metricCodeToSourceHeader)
            : $sessionSourceHeaders;
        $sessionMetricCodes = collect((array) ($selection['session_contracts'] ?? []))
            ->flatMap(fn (array $sessionContract): array => array_keys((array) ($sessionContract['metric_code_to_id'] ?? [])))
            ->all();
        $scoreValueFields = array_values(array_unique([
            ...array_keys($contract->aptitudeAreaCodeToId),
            ...array_keys($contract->metricCodeToId),
            ...$sessionMetricCodes,
        ]));
        $catalogScoreFields = array_values(array_unique([
            ...$scoreValueFields,
            ...$sourceHeaders,
        ]));
        $scoreFields = $canSeeScores
            ? array_values(array_unique(array_map(SpreadsheetParser::normalizeHeader(...), $catalogScoreFields)))
            : [];
        $rows = $batch->rows()->orderBy('row_index')->orderBy('id')->paginate(25)->through(function (ImportBatchRow $row) use ($allowedFields, $scoreFields, $hideAssessmentDetails): array {
            $payload = $row->normalized_payload ?? [];

            $errors = $row->errors ?? [];
            if ($hideAssessmentDetails) {
                $errors = array_values(array_filter($errors, fn (string $error): bool => ! str_contains(mb_strtolower($error), 'score') && ! str_contains(mb_strtolower($error), 'aptitude') && ! str_contains(mb_strtolower($error), 'percentile') && ! str_contains(mb_strtolower($error), 'source reference')));
            }

            return [
                'id' => $row->id,
                'source' => ['sheet_name' => $row->sheet_name, 'row_index' => $row->row_index],
                'status' => $row->status,
                'errors' => $errors,
                'reference_code' => $row->reference_code,
                'applicant_fields' => array_intersect_key($payload, array_flip($allowedFields)),
                'score_values' => array_intersect_key($payload, array_flip($scoreFields)),
                'guidance_status' => $row->guidance_status,
            ];
        });

        return Inertia::render('Import/Review', [
            'batch' => [...$this->batchSummary($batch), 'guidance_pending_rows' => $batch->rows()->where('status', ImportBatchRow::STATUS_APPLICANT_IMPORTED)->count()],
            'rows' => $rows,
            'review_mode' => $isRegistrarConsent ? 'registrar_consent' : 'standard',
            'office_view_mode' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT)
                ? 'registrar'
                : 'guidance',
            'capabilities' => [
                'can_approve' => $isRegistrarConsent,
                'can_resume' => $this->canResumeBatch($batch, $user, $authorizer, $scoreValueFields),
                'can_commit' => $batch->status === ImportBatch::STATUS_STAGED && match ($batch->import_type) {
                    ImportBatch::TYPE_APPLICANTS_ONLY => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT),
                    ImportBatch::TYPE_SCORES_ONLY => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT),
                    ImportBatch::TYPE_ALL_IN_ONE => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT)
                        && ($authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT)
                            || $batch->academicCycle->allow_guidance_all_in_one_intake),
                    ImportBatch::TYPE_MULTI_SESSION => false,
                    default => false,
                },
                'can_view_scores' => $canSeeScores,
                'can_import_exam_sessions' => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE),
                'can_download_errors' => $this->hasErrorRows($batch)
                    && match ($batch->import_type) {
                        ImportBatch::TYPE_APPLICANTS_ONLY => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT),
                        ImportBatch::TYPE_SCORES_ONLY, ImportBatch::TYPE_ALL_IN_ONE, ImportBatch::TYPE_MULTI_SESSION => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT),
                        default => false,
                    },
            ],
        ]);
    }

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $canApps = $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT);
        $canSchedule = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
        $canScores = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT);

        if (! $canApps && ! $canScores && ! $canSchedule) {
            abort(403, 'You do not have permission to access data ingestion.');
        }

        $institution = Institution::current();
        $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->first();
        if ($cycle === null) {
            $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->latest('id')->first();
        }

        $recentBatches = $cycle !== null
            ? ImportBatch::query()
                ->where('institution_id', $institution->id)
                ->where('academic_cycle_id', $cycle->id)
                ->with('createdBy:id,name')
                ->latest('id')
                ->take(10)
                ->get()
            : collect();
        $pendingQuery = ImportBatch::query()
            ->where('institution_id', $institution->id)
            ->where('status', ImportBatch::STATUS_PENDING_APPROVAL);
        if ($cycle !== null) {
            $pendingQuery->where('academic_cycle_id', $cycle->id);
        }
        $pendingApprovalCount = $canApps ? (clone $pendingQuery)->count() : 0;
        $pendingBatches = $canApps
            ? (clone $pendingQuery)
                ->with('createdBy:id,name')
                ->latest('id')
                ->paginate(10, ['*'], 'pending_page')
                ->through(fn ($batch) => $this->batchSummary($batch))
            : null;
        $awaitingRegistrarQuery = ImportBatch::query()
            ->where('institution_id', $institution->id)
            ->where('status', ImportBatch::STATUS_PENDING_APPROVAL);
        if ($cycle !== null) {
            $awaitingRegistrarQuery->where('academic_cycle_id', $cycle->id);
        }
        $awaitingRegistrarCount = ($canScores || $canSchedule) ? (clone $awaitingRegistrarQuery)->count() : 0;
        $awaitingRegistrarBatches = ($canScores || $canSchedule)
            ? (clone $awaitingRegistrarQuery)
                ->with('createdBy:id,name')
                ->latest('id')
                ->paginate(10, ['*'], 'awaiting_registrar_page')
                ->through(fn ($batch) => $this->batchSummary($batch))
            : null;

        $activeSessions = $cycle !== null
            ? ExamSession::query()
                ->where('academic_cycle_id', $cycle->id)
                ->with(['room:id,name', 'testKitVersion.testKit:id,code,name'])
                ->get(['id', 'code', 'room_id', 'starts_at', 'status', 'test_kit_version_id'])
            : collect();
        $importKitVersions = $canScores
            ? TestKitVersion::query()
                ->where('institution_id', $institution->id)
                ->whereIn('status', [TestKitVersion::STATUS_ACTIVE, TestKitVersion::STATUS_RETIRED])
                ->whereNotNull('test_instrument_version_id')
                ->whereNull('revoked_at')
                ->with([
                    'testKit:id,code,name',
                    'testInstrumentVersion:id,display_name',
                    'testInstrumentVersion.metrics:id,test_instrument_version_id,rule_kind',
                ])
                ->orderBy('test_kit_id')
                ->orderByDesc('version_number')
                ->get()
                ->map(fn (TestKitVersion $version): array => [
                    'id' => (int) $version->id,
                    'display_name' => (string) ($version->testKit?->name ?? $version->testKit?->code ?? 'Test Kit'),
                    'kit_code' => (string) ($version->testKit?->code ?? ''),
                    'version_number' => (int) $version->version_number,
                    'profile_name' => $version->testInstrumentVersion?->display_name,
                    'is_final_only' => $version->testInstrumentVersion?->isFinalOnly() ?? false,
                    'status' => (string) $version->status,
                ])
                ->all()
            : [];
        $importNormVersions = $canScores
            ? AssessmentNormPackageVersion::query()
                ->where('status', AssessmentNormPackageVersion::STATUS_APPROVED)
                ->whereHas('package', fn ($query) => $query->where('institution_id', $institution->id))
                ->with('package:id,code')
                ->orderBy('norm_package_id')
                ->orderByDesc('version_number')
                ->get()
                ->map(fn (AssessmentNormPackageVersion $version): array => [
                    'id' => (int) $version->id,
                    'label' => ($version->package?->code ?? 'Norm').' · '.$version->display_name.' v'.$version->version_number,
                ])
                ->all()
            : [];
        $preset = $request->query('preset', 'all_in_one');
        $requestedView = $request->query('view');
        $hasPendingWork = $canApps
            ? $pendingApprovalCount > 0
            : $awaitingRegistrarCount > 0;
        $view = in_array($requestedView, ['upload', 'pending', 'history'], true)
            ? $requestedView
            : ($request->query('preset') !== null
                ? 'upload'
                : ($hasPendingWork ? 'pending' : 'upload'));
        if ($view === 'pending' && ! $canApps && $awaitingRegistrarCount === 0) {
            $view = 'upload';
        }
        $specializedImports = [];
        if ($authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_OMR)) {
            $specializedImports[] = [
                'label' => 'OMR Answer-Sheet Bundle',
                'description' => 'Import a canonical answer-sheet template bundle.',
                'formats' => ['zip'],
                'href' => route('setup.exam-materials.answer-sheets.index'),
            ];
        }
        if ($authorizer->allows($user, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)) {
            $specializedImports[] = [
                'label' => 'Result-Sheet Document',
                'description' => 'Create a result-sheet template from a DOCX or ODT document.',
                'formats' => ['docx', 'odt'],
                'href' => route('setup.result-templates.create'),
            ];
            $specializedImports[] = [
                'label' => 'Admission Benchmark Package',
                'description' => 'Import an admission benchmark set for authorized cycle calibration.',
                'formats' => ['json', 'txt'],
                'href' => route('setup.decision-support.index'),
            ];
        }
        if (collect(DocumentSurfaceRegistry::definitions())
            ->keys()
            ->contains(fn (string $surface): bool => DocumentSurfaceRegistry::canAuthorSurface($authorizer, $user, $surface))) {
            $specializedImports[] = [
                'label' => 'Report Templates',
                'description' => 'Author DOCX templates for report surfaces available to this actor.',
                'formats' => ['docx'],
                'href' => route('setup.report-templates.index'),
            ];
        }

        return Inertia::render('Import/Index', [
            'page_state' => 'ready',
            'preset' => $preset,
            'view' => $view,
            'academic_cycle' => $cycle ? [
                'id' => $cycle->id,
                'label' => $cycle->staffLabel(),
                'is_active' => $cycle->is_active,
                'allow_guidance_all_in_one_intake' => $cycle->allow_guidance_all_in_one_intake,
            ] : null,
            'recent_batches' => $recentBatches->map(fn ($b) => $this->batchSummary($b)),
            'pending_approval_count' => $pendingApprovalCount,
            'pending_batches' => $pendingBatches,
            'awaiting_registrar_count' => $awaitingRegistrarCount,
            'awaiting_registrar_batches' => $awaitingRegistrarBatches,
            'office_view_mode' => $canApps ? 'registrar' : 'guidance',
            'active_sessions' => $activeSessions->map(fn ($session): array => [
                'id' => $session->id,
                'code' => $session->code,
                'room_name' => $session->room?->name ?? 'Unassigned',
                'starts_at' => $session->starts_at?->format('M d, Y h:i A'),
                'status' => $session->status,
                'test_kit_version_id' => $session->test_kit_version_id,
                'kit_label' => $session->testKitVersion?->testKit?->name
                    ?? $session->testKitVersion?->testKit?->code
                    ?? 'No Kit pinned',
            ]),
            'test_kit_versions' => $importKitVersions,
            'norm_versions' => $importNormVersions,
            'capabilities' => [
                'can_import_applications' => $canApps,
                'can_import_scores' => $canScores,
                'can_import_exam_sessions' => $canSchedule,
                'can_all_in_one' => $canScores,
            ],
            'specialized_imports' => $specializedImports,
        ]);
    }

    public function stage(Request $request, CapabilityAuthorizer $authorizer): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:25600'],
            'import_type' => ['required', 'string', 'in:all_in_one,multi_session,applicants_only,scores_only'],
            'academic_cycle_id' => ['required', 'integer'],
            'enabled_sheets' => ['nullable', 'array'],
            'enabled_sheets.*' => ['string'],
            'sheet_to_session_map' => ['nullable', 'array'],
            'target_exam_session_id' => ['required_if:import_type,scores_only', 'nullable', 'integer'],
            'test_kit_version_id' => ['required_if:import_type,scores_only,all_in_one', 'nullable', 'integer', 'exists:test_kit_versions,id'],
            'norm_package_version_id' => ['nullable', 'integer', 'exists:assessment_norm_package_versions,id'],
        ]);
        $importType = $validated['import_type'];
        $institution = Institution::current();
        $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->findOrFail($validated['academic_cycle_id']);
        $targetExamSession = isset($validated['target_exam_session_id'])
            ? ExamSession::query()
                ->where('academic_cycle_id', $cycle->id)
                ->findOrFail($validated['target_exam_session_id'])
            : null;
        $targetKitVersion = isset($validated['test_kit_version_id'])
            ? TestKitVersion::query()
                ->where('institution_id', $institution->id)
                ->findOrFail($validated['test_kit_version_id'])
            : null;
        $normPackageVersion = isset($validated['norm_package_version_id'])
            ? AssessmentNormPackageVersion::query()->with('package')
                ->whereHas('package', fn ($query) => $query->where('institution_id', $institution->id))
                ->findOrFail($validated['norm_package_version_id'])
            : null;
        $canApps = $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT);
        $canScores = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT);
        $canSchedule = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);

        if ($importType === ImportBatch::TYPE_ALL_IN_ONE && ! $canScores) {
            abort(403, 'Combined applicant and score imports must be initiated by Guidance.');
        }
        if ($importType === ImportBatch::TYPE_APPLICANTS_ONLY && ! $canApps) {
            abort(403, 'Admissions import capability required.');
        }
        if ($importType === ImportBatch::TYPE_SCORES_ONLY && ! $canScores) {
            abort(403, 'Scoring import capability required.');
        }
        if ($importType === ImportBatch::TYPE_MULTI_SESSION && ! $canSchedule) {
            abort(403, 'Exam scheduling capability required for multi-session imports.');
        }

        try {
            $batch = $this->stageBatch->handle(
                file: $validated['file'],
                institution: $institution,
                cycle: $cycle,
                importType: $importType,
                actor: $user,
                enabledSheetNames: $validated['enabled_sheets'] ?? [],
                sheetToSessionMap: $validated['sheet_to_session_map'] ?? [],
                targetExamSession: $targetExamSession,
                targetKitVersion: $targetKitVersion,
                normPackageVersion: $normPackageVersion,
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return redirect()->route('imports.review', $batch)->with(
            'status',
            'Batch #'.$batch->id.' staged with '.$batch->total_rows.' rows ('.$batch->valid_rows.' valid).',
        );
    }

    public function approve(
        Request $request,
        ImportBatch $batch,
        ApproveIngestionBatch $approveBatch,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        $this->assertCurrentInstitution($batch);
        abort_unless($authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT), 403);
        $validated = $request->validate([
            'selected_row_ids' => ['required', 'array', 'min:1'],
            'selected_row_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $result = $approveBatch->handle($batch, $validated['selected_row_ids'], $user, $authorizer);

        $message = $result->status === ImportBatch::STATUS_AWAITING_GUIDANCE
            ? 'Applicant consent recorded for '.$result->imported_rows.' rows; '.$result->failed_rows.' rows failed and '.$result->skipped_rows.' rows were skipped. Guidance execution is pending.'
            : 'No selected applicant rows could be created. '.$result->failed_rows.' rows failed; review the recorded errors.';

        return back()->with('status', $message);
    }

    public function reject(
        Request $request,
        ImportBatch $batch,
        RejectIngestionBatch $rejectBatch,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        $this->assertCurrentInstitution($batch);
        abort_unless($authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT), 403);
        $request->validate(['reason' => ['required', 'string', 'min:3']]);
        $rejectBatch->handle($batch, $request->string('reason')->toString(), $user, $authorizer);

        return back()->with('status', "Batch #{$batch->id} rejected.");
    }

    public function resume(ImportBatch $batch, Request $request, CapabilityAuthorizer $authorizer): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        $this->assertCurrentInstitution($batch);
        $request->validate(['selected_row_ids' => ['missing']]);
        $result = $this->resumeGuidance->handle($batch, $user, $authorizer);
        $message = $result->status === ImportBatch::STATUS_COMPLETED
            ? 'Guidance execution completed: '.$result->imported_rows.' rows processed, '.$result->failed_rows.' rows failed.'
            : 'Guidance execution saved: '.$result->imported_rows.' rows processed; '.$result->failed_rows.' rows failed and remain available to retry.';

        return back()->with('status', $message);
    }

    public function commit(Request $request, ImportBatch $batch, CapabilityAuthorizer $authorizer): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        $this->assertCurrentInstitution($batch);
        $validated = $request->validate([
            'selected_row_ids' => ['required', 'array', 'min:1'],
            'selected_row_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $selectedRowIds = $validated['selected_row_ids'] ?? [];

        $committed = match ($batch->import_type) {
            ImportBatch::TYPE_ALL_IN_ONE => $this->commitAllInOne->handle($batch, $selectedRowIds, $user, $authorizer),
            ImportBatch::TYPE_SCORES_ONLY => $this->commitScores->handle($batch, $selectedRowIds, $user, $authorizer),
            ImportBatch::TYPE_APPLICANTS_ONLY => $this->commitApplicants->handle($batch, $selectedRowIds, $user, $authorizer),
            ImportBatch::TYPE_MULTI_SESSION => abort(409, 'Registrar consent must be recorded before Guidance resumes this multi-session batch.'),
            default => abort(404),
        };

        return back()->with('status', 'Batch #'.$batch->id.' finished with '.$committed->imported_rows.' rows imported and '.$committed->failed_rows.' failed.');
    }

    public function downloadErrors(ImportBatch $batch, Request $request, CapabilityAuthorizer $authorizer): StreamedResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        $this->assertCurrentInstitution($batch);
        $this->assertBatchViewCapability($batch, $user, $authorizer);
        if (! in_array($batch->status, [ImportBatch::STATUS_STAGED, ImportBatch::STATUS_PENDING_APPROVAL, ImportBatch::STATUS_AWAITING_GUIDANCE, ImportBatch::STATUS_REJECTED, ImportBatch::STATUS_FAILED, ImportBatch::STATUS_COMPLETED], true)) {
            abort(404);
        }
        if (! $this->hasErrorRows($batch)) {
            abort(404);
        }
        if (in_array($batch->import_type, [ImportBatch::TYPE_ALL_IN_ONE, ImportBatch::TYPE_MULTI_SESSION], true)
            && ! $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT)) {
            abort(403);
        }

        return $this->errorReportGenerator->generate($batch);
    }

    private function assertCurrentInstitution(ImportBatch $batch): void
    {
        if ((int) $batch->institution_id !== (int) Institution::current()->id) {
            abort(404);
        }
    }

    /**
     * @param  list<string>  $scoreFields
     */
    private function canResumeBatch(ImportBatch $batch, User $user, CapabilityAuthorizer $authorizer, array $scoreFields): bool
    {
        if ($batch->status !== ImportBatch::STATUS_AWAITING_GUIDANCE) {
            return false;
        }
        $hasAuthority = $batch->import_type === ImportBatch::TYPE_ALL_IN_ONE
            ? $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT)
            : $batch->import_type === ImportBatch::TYPE_MULTI_SESSION
                && $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
        if (! $hasAuthority) {
            return false;
        }
        if ($batch->import_type === ImportBatch::TYPE_MULTI_SESSION
            && ! $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT)) {
            foreach ($batch->rows()->where('status', ImportBatchRow::STATUS_APPLICANT_IMPORTED)->get(['normalized_payload']) as $row) {
                foreach ($scoreFields as $code) {
                    $value = $row->normalized_payload[SpreadsheetParser::normalizeHeader($code)] ?? null;
                    if (is_numeric($value)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function assertBatchViewCapability(ImportBatch $batch, User $user, CapabilityAuthorizer $authorizer): void
    {
        $statuses = match ($batch->import_type) {
            ImportBatch::TYPE_APPLICANTS_ONLY, ImportBatch::TYPE_SCORES_ONLY => [ImportBatch::STATUS_STAGED, ImportBatch::STATUS_FAILED, ImportBatch::STATUS_COMPLETED],
            ImportBatch::TYPE_ALL_IN_ONE, ImportBatch::TYPE_MULTI_SESSION => [ImportBatch::STATUS_STAGED, ImportBatch::STATUS_PENDING_APPROVAL, ImportBatch::STATUS_AWAITING_GUIDANCE, ImportBatch::STATUS_REJECTED, ImportBatch::STATUS_FAILED, ImportBatch::STATUS_COMPLETED],
            default => [],
        };
        if (! in_array($batch->status, $statuses, true)) {
            abort(404);
        }
        $canApps = $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT);
        $canScores = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT);
        $allowed = match ($batch->import_type) {
            ImportBatch::TYPE_APPLICANTS_ONLY => $canApps,
            ImportBatch::TYPE_SCORES_ONLY => $canScores,
            ImportBatch::TYPE_ALL_IN_ONE => match ($batch->status) {
                ImportBatch::STATUS_PENDING_APPROVAL => $canApps || $canScores,
                ImportBatch::STATUS_AWAITING_GUIDANCE => $canScores,
                default => $canApps || $canScores,
            },
            ImportBatch::TYPE_MULTI_SESSION => $batch->status === ImportBatch::STATUS_PENDING_APPROVAL
                ? $canApps || $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
                : $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE),
            default => false,
        };
        if (! $allowed) {
            abort(403);
        }
    }

    private function hasErrorRows(ImportBatch $batch): bool
    {
        return $batch->rows()->where(function ($query): void {
            $query->whereIn('status', [ImportBatchRow::STATUS_INVALID, ImportBatchRow::STATUS_FAILED])
                ->orWhere(function ($query): void {
                    $query->where('status', ImportBatchRow::STATUS_APPLICANT_IMPORTED)
                        ->where('guidance_status', 'failed');
                });
        })->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function batchSummary(ImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'file_name' => $batch->file_name,
            'import_type' => $batch->import_type,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'valid_rows' => $batch->valid_rows,
            'imported_rows' => $batch->imported_rows,
            'invalid_rows' => $batch->invalid_rows,
            'failed_rows' => $batch->failed_rows,
            'skipped_rows' => $batch->skipped_rows,
            'created_by' => $batch->createdBy?->name,
            'committed_at' => $batch->committed_at?->format('M d, Y h:i A'),
            'created_at' => $batch->created_at?->format('M d, Y h:i A'),
        ];
    }
}
