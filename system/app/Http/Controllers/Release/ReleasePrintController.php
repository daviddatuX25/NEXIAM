<?php

namespace App\Http\Controllers\Release;

use App\Assessment\Support\IndividualScoringReadiness;
use App\Assessment\Support\ScoringSessionContextResolver;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResultSheet\MarkResultSheetsPrintedRequest;
use App\Http\Requests\ResultSheet\RequestResultSheetPrintRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\ResultSheet\Actions\MarkResultSheetsPrinted;
use App\ResultSheet\Actions\RequestResultSheetPrint;
use App\ResultSheet\Queries\PrintJobHistoryQuery;
use App\ResultSheet\Rendering\PreparedResultDocumentRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * bulk job requests, and append-only physical print confirmation/correction.
 * Status and downloads ride the shared printing.jobs.show/download routes.
 */
class ReleasePrintController extends Controller
{
    public function index(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ExamSession|GradingSession|string|int|null $examSession = null,
        ?ScoringSessionContextResolver $contexts = null,
        ?PrintJobHistoryQuery $historyQuery = null,
        ?IndividualScoringReadiness $scoringReadiness = null,
    ): Response {
        $contexts ??= app(ScoringSessionContextResolver::class);
        $historyQuery ??= app(PrintJobHistoryQuery::class);
        $scoringReadiness ??= app(IndividualScoringReadiness::class);
        [$examSession, $gradingSession] = $this->resolveSessionContext($request, $examSession, $authorizer, $contexts);

        $template = ResultSheetTemplate::active();

        $attemptIds = $gradingSession->scoredAttemptIds();
        $attempts = $attemptIds === []
            ? collect()
            : AdmissionAttempt::query()
                ->with('identity')
                ->whereIn('id', $attemptIds)
                ->get()
                ->values();

        $documents = $attemptIds === []
            ? collect()
            : ResultDocument::query()
                ->whereIn('admission_attempt_id', $attemptIds)
                ->where('status', ResultDocument::STATUS_READY)
                ->orderByDesc('version')
                ->get()
                ->groupBy('admission_attempt_id');
        $releases = $attemptIds === []
            ? collect()
            : ResultRelease::query()
                ->whereIn('admission_attempt_id', $attemptIds)
                ->where('is_current', true)
                ->whereNull('unreleased_at')
                ->get()
                ->groupBy('admission_attempt_id');
        $receipts = $attemptIds === []
            ? collect()
            : ResultDocumentPrintReceipt::query()
                ->whereIn('admission_attempt_id', $attemptIds)
                ->where('operation', 'confirmed')
                ->where('is_current', true)
                ->get()
                ->groupBy('admission_attempt_id');
        $readinessByAttempt = $scoringReadiness->forAttempts($attempts);
        $rows = $attempts->map(function (AdmissionAttempt $attempt) use ($documents, $releases, $receipts, $readinessByAttempt, $gradingSession, $examSession): array {
            $readyDocuments = $documents->get($attempt->id, collect());
            $candidateDocument = $readyDocuments->firstWhere('id', $attempt->current_result_document_id);
            $readiness = $readinessByAttempt[$attempt->id] ?? [];
            $prepared = $candidateDocument !== null && $this->preparedDocumentMatchesCurrentReadiness(
                $candidateDocument,
                $readiness,
                $gradingSession,
                $examSession,
            );
            $document = $prepared ? $candidateDocument : null;
            $currentReleases = $releases->get($attempt->id, collect());
            $deliveryChannels = $currentReleases->pluck('channel')->filter()->unique()->values()->all();
            $printed = $document !== null && $receipts->get($attempt->id, collect())
                ->contains(fn (ResultDocumentPrintReceipt $receipt): bool => (int) $receipt->result_document_id === (int) $document->id);
            $identity = $attempt->identity;
            $name = trim(implode(' ', array_filter([
                $identity?->first_name,
                $identity?->middle_name,
                $identity?->last_name,
                $identity?->suffix,
            ])));

            return [
                'attempt_id' => $attempt->id,
                'document_id' => $document?->id,
                'name' => $name !== '' ? $name : '—',
                'reference' => (string) $attempt->reference_code,
                'scoring_ready' => (bool) ($readiness['ready'] ?? false),
                'scoring_reason' => $readiness['reason'] ?? 'Scoring readiness could not be established.',
                'result_set_id' => $readiness['result_set_id'] ?? null,
                'document_ready' => $document !== null,
                'printed' => $printed,
                'released' => $currentReleases->isNotEmpty(),
                'delivery_channels' => $deliveryChannels,
            ];
        })->all();

        $search = trim((string) $request->input('search', ''));
        $sort = (string) $request->input('sort', 'reference');
        $dir = strtolower((string) $request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($search !== '') {
            $pattern = mb_strtolower($search);
            $rows = array_filter($rows, fn ($r) => str_contains(mb_strtolower((string) $r['name']), $pattern) || str_contains(mb_strtolower((string) $r['reference']), $pattern));
        }

        $sortOptions = [
            ['value' => 'reference', 'label' => 'Reference Code'],
            ['value' => 'name', 'label' => 'Applicant Name'],
            ['value' => 'printed', 'label' => 'Printed Status'],
            ['value' => 'document_ready', 'label' => 'Rating Sheet Status'],
        ];

        usort($rows, function ($a, $b) use ($sort, $dir) {
            $cmp = match ($sort) {
                'name' => strcasecmp((string) $a['name'], (string) $b['name']),
                'printed' => ((int) $a['printed']) <=> ((int) $b['printed']),
                'document_ready' => ((int) $a['document_ready']) <=> ((int) $b['document_ready']),
                default => strcasecmp((string) $a['reference'], (string) $b['reference']),
            };

            return $dir === 'desc' ? -$cmp : $cmp;
        });

        return Inertia::render('Release/PrintBatch', [
            'page_state' => 'ready',
            'session' => [
                'exam_session_id' => $examSession->id,
                'code' => (string) $examSession->code,
                'exam_date' => $examSession->starts_at?->format('F d, Y'),
                'room_name' => (string) ($examSession->room?->name ?? '—'),
                'status' => (string) $gradingSession->status,
            ],
            'applicants' => array_values($rows),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'dir' => $dir,
            ],
            'sort_options' => $sortOptions,
            'template_present' => $template !== null,
            'can_prepare' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_RESULTS_PREPARE),
            'job_history' => $historyQuery->forSession($gradingSession),
            'job_poll_route' => route('printing.jobs.show', ['printJob' => 'JOB_ID_PLACEHOLDER']),
        ]);
    }

    public function bulkPreview(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ExamSession|GradingSession|string|int|null $examSession = null,
        ?ScoringSessionContextResolver $contexts = null,
        ?PreparedResultDocumentRenderer $preparedRenderer = null,
        ?IndividualScoringReadiness $scoringReadiness = null,
    ): Response {
        $contexts ??= app(ScoringSessionContextResolver::class);
        $preparedRenderer ??= app(PreparedResultDocumentRenderer::class);
        $scoringReadiness ??= app(IndividualScoringReadiness::class);
        [$examSession, $gradingSession] = $this->resolveSessionContext($request, $examSession, $authorizer, $contexts);

        $documentIds = array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) $request->query('document_ids', ''))),
            fn (int $id): bool => $id > 0,
        )));

        $sessionAttemptIds = $gradingSession->scoredAttemptIds();
        $documents = ResultDocument::query()
            ->whereIn('id', $documentIds)
            ->whereIn('admission_attempt_id', $sessionAttemptIds)
            ->where('status', ResultDocument::STATUS_READY)
            ->with('admissionAttempt.identity')
            ->get();
        abort_unless($documentIds !== [] && $documents->count() === count($documentIds), 404);

        $previewAttempts = $documents->map(fn (ResultDocument $document): ?AdmissionAttempt => $document->admissionAttempt)
            ->filter()
            ->unique('id')
            ->values();
        $readinessByAttempt = $scoringReadiness->forAttempts($previewAttempts);
        $paperSize = 'a4';
        foreach ($documents as $document) {
            $attempt = $document->admissionAttempt;
            abort_unless((int) $attempt->current_result_document_id === (int) $document->id, 404);
            abort_unless($this->preparedDocumentMatchesCurrentReadiness(
                $document,
                $readinessByAttempt[$attempt->id] ?? [],
                $gradingSession,
                $examSession,
            ), 404);
            $snapshot = is_array($document->snapshot_payload_json) ? $document->snapshot_payload_json : [];
            $data = $snapshot;
            unset($data['_template'], $data['_template_source'], $data['_assessment_result_set_id']);
            $artifacts = $preparedRenderer->render($document);
            $paperSize = $snapshot['_template']['paper_size'] ?? $paperSize;
            $pdfPreviews[] = [
                'document_id' => $document->id,
                'name' => $data['name'] ?? '—',
                'reference' => $data['reference'] ?? (string) $attempt->reference_code,
                'status' => 'ready',
                'src' => 'data:application/pdf;base64,'.base64_encode($artifacts['pdf']).'#toolbar=0',
                'job_id' => null,
                'download_url' => null,
            ];
        }

        return Inertia::render('Release/ResultSheetBulk', [
            'page_state' => 'ready',
            'session' => ['exam_session_id' => $examSession->id],
            'pdfPreviews' => $pdfPreviews,
            'document_ids' => $documents->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'sheetsHtml' => [],
            'paper_size' => $paperSize,
            'template_error' => null,
        ]);
    }

    public function showApplicant(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ExamSession|GradingSession|string|int|null $examSession = null,
        ?ScoringSessionContextResolver $contexts = null,
        ?AdmissionAttempt $attempt = null,
        ?PreparedResultDocumentRenderer $preparedRenderer = null,
        ?IndividualScoringReadiness $scoringReadiness = null,
    ): Response {
        $contexts ??= app(ScoringSessionContextResolver::class);
        $preparedRenderer ??= app(PreparedResultDocumentRenderer::class);
        $scoringReadiness ??= app(IndividualScoringReadiness::class);
        $attempt ??= AdmissionAttempt::query()->findOrFail((int) $request->route('attempt'));
        [$examSession, $gradingSession] = $this->resolveSessionContext($request, $examSession, $authorizer, $contexts);

        abort_unless(in_array((int) $attempt->id, $gradingSession->scoredAttemptIds(), true), 404);
        $document = ResultDocument::query()
            ->whereKey($attempt->current_result_document_id)
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', ResultDocument::STATUS_READY)
            ->firstOrFail();
        $readiness = $scoringReadiness->forAttempt($attempt);
        abort_unless($this->preparedDocumentMatchesCurrentReadiness($document, $readiness, $gradingSession, $examSession), 404);
        $snapshot = is_array($document->snapshot_payload_json) ? $document->snapshot_payload_json : [];
        $data = $snapshot;
        unset($data['_template'], $data['_template_source'], $data['_assessment_result_set_id']);
        $artifacts = $preparedRenderer->render($document);

        return Inertia::render('Release/ResultSheet', [
            'applicant' => array_merge($data, ['attempt_id' => (int) $attempt->id, 'exam_session_id' => (int) $examSession->id, 'document_id' => $document->id]),
            'sheetHtml' => null,
            'pdf_base64' => base64_encode($artifacts['pdf']),
            'pdf_url' => null,
            'preview_job_id' => null,
            'paper_size' => $snapshot['_template']['paper_size'] ?? 'a4',
            'template_error' => null,
        ]);
    }

    public function request(
        RequestResultSheetPrintRequest $request,
        CapabilityAuthorizer $authorizer,
        RequestResultSheetPrint $action,
    ): JsonResponse {
        abort_unless($authorizer->allowsAny($request->user(), [CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE]), 403);

        $job = $action->handle(
            $request->user(),
            array_map('intval', $request->validated('document_ids')),
            (int) $request->integer('copies', 1),
        );

        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
            'artifact_type' => $job->artifact_type,
            'progress_total' => $job->progress_total,
            'progress_done' => $job->progress_done,
        ], 202);
    }

    public function markPrinted(
        MarkResultSheetsPrintedRequest $request,
        CapabilityAuthorizer $authorizer,
        MarkResultSheetsPrinted $action,
    ): RedirectResponse {
        abort_unless($authorizer->allowsAny($request->user(), [CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE]), 403);

        $count = $action->handle(
            $request->user(),
            array_map('intval', $request->validated('document_ids')),
            (bool) $request->boolean('printed'),
            $request->validated('reason'),
            $request->validated('idempotency_key'),
        );

        return back()->with('status', $request->boolean('printed')
            ? "Marked {$count} result sheets as printed."
            : "Recorded print-confirmation corrections for {$count} result sheets.");
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    private function preparedDocumentMatchesCurrentReadiness(
        ResultDocument $document,
        array $readiness,
        GradingSession $gradingSession,
        ExamSession $examSession,
    ): bool {
        if (! ($readiness['ready'] ?? false)
            || (int) ($readiness['exam_session_id'] ?? 0) !== (int) $examSession->id
            || (int) ($readiness['grading_session_id'] ?? 0) !== (int) $gradingSession->id) {
            return false;
        }

        $snapshotResultSetId = is_array($document->snapshot_payload_json)
            ? (int) ($document->snapshot_payload_json['_assessment_result_set_id'] ?? 0)
            : 0;
        $resultSetId = (int) ($readiness['result_set_id'] ?? 0);
        if ($resultSetId > 0) {
            return $snapshotResultSetId === $resultSetId;
        }

        return ($readiness['administration_id'] ?? null) === null
            && $snapshotResultSetId === 0
            && $gradingSession->status === GradingSession::STATUS_FINALIZED;
    }

    /**
     * @return array{0: ExamSession, 1: GradingSession}
     */
    private function resolveSessionContext(
        Request $request,
        ExamSession|GradingSession|string|int|null $sessionParam,
        CapabilityAuthorizer $authorizer,
        ScoringSessionContextResolver $contexts,
    ): array {
        abort_unless(
            $authorizer->allowsAny($request->user(), [
                CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
                CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
            ]),
            403,
        );

        $capability = $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_RESULTS_PREPARE)
            ? CapabilityCatalog::GUIDANCE_RESULTS_PREPARE
            : CapabilityCatalog::GUIDANCE_RESULTS_RELEASE;

        $resolved = $sessionParam instanceof ExamSession || $sessionParam instanceof GradingSession
            ? $sessionParam
            : null;

        if ($resolved === null) {
            $routeGrading = $request->route('gradingSession');
            if ($routeGrading !== null) {
                $resolved = $routeGrading instanceof GradingSession
                    ? $routeGrading
                    : GradingSession::query()->find((int) $routeGrading);
            }
        }

        if ($resolved === null) {
            $routeExam = $request->route('examSession');
            if ($routeExam !== null) {
                $resolved = $routeExam instanceof ExamSession
                    ? $routeExam
                    : ExamSession::query()->find((int) $routeExam);
            }
        }

        if ($resolved === null && is_numeric($sessionParam) && (int) $sessionParam > 0) {
            $resolved = ExamSession::query()->find((int) $sessionParam)
                ?? GradingSession::query()->find((int) $sessionParam);
        }

        abort_if($resolved === null, 404);

        if ($resolved instanceof GradingSession) {
            $gradingSession = $resolved->loadMissing('examSession.academicCycle', 'examSession.room');
            $examSession = $gradingSession->examSession;
            abort_if($examSession === null, 404);
            $contexts->resolveForRead($examSession, $request->user(), $capability);
        } else {
            $examSession = $resolved->loadMissing('academicCycle', 'room');
            $gradingSession = $contexts->resolveForRead($examSession, $request->user(), $capability);
            abort_if($gradingSession === null, 404);
        }

        return [$examSession, $gradingSession];
    }
}
