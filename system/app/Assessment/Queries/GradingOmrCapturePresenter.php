<?php

namespace App\Assessment\Queries;

use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRecoveryCatalog;
use App\Assessment\Omr\OmrScannerContextOutcome;
use App\Assessment\Omr\Operational\OmrBrowserPayloadRedactor;
use App\Assessment\Omr\Operational\ProductionOmrAuthorityAdapter;
use App\Assessment\Omr\Queries\OmrReviewGeometryQuery;
use App\Assessment\Omr\Queries\OmrRunStatusSnapshot;
use App\Assessment\Support\GradingRoster;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetIssue;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrPaperReproductionAuthorization;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrSupplierStockAllocation;
use App\Models\Assessment\OmrSupplierStockLot;
use App\Models\Examination\ExamAssignment;
use App\Printing\BundleRenderer;
use Illuminate\Http\Request;

final class GradingOmrCapturePresenter
{
    public function __construct(
        private readonly BundleRenderer $bundleRenderer,
        private readonly OmrReviewGeometryQuery $reviewGeometryQuery,
        private readonly OmrCaptureResolver $captureResolver,
        private readonly OmrRecoveryCatalog $recoveryCatalog,
        private readonly OmrRunStatusSnapshot $runStatusSnapshot,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(
        Request $request,
        GradingSession $gradingSession,
        ProductionOmrAuthorityAdapter $authority,
    ): array {
        $gradingSession->loadMissing([
            'examSession.room',
            'examSession.academicCycle',
            'examSession.currentMaterialSnapshot.templateVersion.template',
            'examSession.currentMaterialSnapshot.bookletVersion.booklet',
        ]);
        $examSession = $gradingSession->examSession;
        abort_unless($examSession !== null, 404);
        $materialSnapshot = $gradingSession->examSession?->currentMaterialSnapshot;
        $materialTemplateVersion = $materialSnapshot?->templateVersion;
        $academicCycle = $gradingSession->examSession?->academicCycle;
        $qrDisabledIssuances = [];
        if ($materialTemplateVersion?->qr_mode === OmrQrMode::Disabled && $academicCycle !== null) {
            $qrDisabledIssuances = OmrQrDisabledIssuance::query()
                ->select(['id', 'issuance_code', 'copy_policy', 'page_count', 'intended_copy_count'])
                ->where('template_version_id', $materialSnapshot->template_version_id)
                ->where('institution_id', $academicCycle->institution_id)
                ->where('academic_cycle_id', $academicCycle->id)
                ->where('status', OmrQrDisabledIssuance::STATUS_PRINTED)
                ->withCount([
                    'pageClaims as page_one_claims_count' => fn ($query) => $query->where('page_no', 1),
                ])
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->map(static fn (OmrQrDisabledIssuance $issuance): array => [
                    'id' => (int) $issuance->id,
                    'issuance_code' => $issuance->issuance_code,
                    'copy_policy' => $issuance->copy_policy,
                    'page_count' => (int) $issuance->page_count,
                    'intended_copy_count' => (int) $issuance->intended_copy_count,
                    'remaining_claims' => $issuance->copy_policy === OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE
                        ? max(0, (int) $issuance->intended_copy_count - (int) $issuance->page_one_claims_count)
                        : null,
                ])
                ->values()
                ->all();
        }
        $batch = OmrCaptureBatch::query()
            ->where('grading_session_id', $gradingSession->id)
            ->whereIn('status', OmrCaptureBatch::ACTIVE_STATUSES)
            ->with([
                'templateVersion',
                'bookletVersion.questions.aptitudeArea',
                'sheets' => fn ($query) => $query->orderByDesc('created_at')->with(['pages.blankInstanceClaim', 'pages.currentRevision', 'issues']),
            ])
            ->first();
        $supplierStockAllocations = [];
        $supplierStockLots = [];
        $isUploadedVersion = $materialTemplateVersion !== null
            && OmrSourcePolicy::sourceKindForVersion($materialTemplateVersion) === OmrSourcePolicy::UPLOADED_ORIGINAL;
        if ($isUploadedVersion && $academicCycle !== null) {
            $supplierStockLots = OmrSupplierStockLot::query()
                ->where('template_version_id', $materialSnapshot->template_version_id)
                ->where('institution_id', $academicCycle->institution_id)
                ->where('academic_cycle_id', $academicCycle->id)
                ->where('status', OmrSupplierStockLot::STATUS_AVAILABLE)
                ->orderByDesc('received_at')
                ->limit(100)
                ->get()
                ->map(static fn (OmrSupplierStockLot $lot): array => [
                    'id' => (int) $lot->id,
                    'vendor_name' => $lot->vendor_name,
                    'source_reference' => $lot->source_reference,
                    'page_count' => (int) $lot->page_count,
                    'received_quantity' => (int) $lot->received_quantity,
                    'available_quantity' => (int) $lot->available_quantity,
                ])
                ->values()
                ->all();
            if ($batch !== null) {
                $supplierStockAllocations = OmrSupplierStockAllocation::query()
                    ->with('lot:id,vendor_name,source_reference')
                    ->where('batch_id', $batch->id)
                    ->where('template_version_id', $materialSnapshot->template_version_id)
                    ->where('institution_id', $academicCycle->institution_id)
                    ->where('academic_cycle_id', $academicCycle->id)
                    ->whereIn('status', [OmrSupplierStockAllocation::STATUS_ACTIVE, OmrSupplierStockAllocation::STATUS_EXHAUSTED])
                    ->orderByDesc('allocated_at')
                    ->get()
                    ->map(static fn (OmrSupplierStockAllocation $allocation): array => [
                        'id' => (int) $allocation->id,
                        'lot_id' => (int) $allocation->lot_id,
                        'vendor_name' => $allocation->lot?->vendor_name,
                        'source_reference' => $allocation->lot?->source_reference,
                        'copy_policy' => $allocation->copy_policy,
                        'page_count' => (int) $allocation->page_count,
                        'quantity' => (int) $allocation->quantity,
                        'used_quantity' => (int) $allocation->used_quantity,
                        'remaining_claims' => $allocation->remainingQuantity(),
                        'status' => $allocation->status,
                    ])
                    ->values()
                    ->all();
            }
        }
        $template = $batch?->templateVersion;
        $identityRegions = $template?->identity_regions_json ?? [];
        $nativeTemplate = is_array($template?->native_bundle_json)
            ? ($template->native_bundle_json['template'] ?? [])
            : [];
        $isCanonicalVersion = is_array($template?->authoring_manifest['artifact_set'] ?? null);
        $reviewOverlayRegions = $template === null || $isCanonicalVersion
            ? []
            : $this->bundleRenderer->reviewOverlayRegions($nativeTemplate, (array) $template->print_overlay_json);
        $identityRegion = $this->identityRegionAt($identityRegions);
        $rawTemplateItems = $batch === null || $batch->bookletVersion === null
            ? []
            : $this->reviewGeometryQuery->itemsFor($batch->templateVersion, $batch->bookletVersion);
        $templateItems = array_map(static function (array $item): array {
            unset($item['correct_option_code']);

            return $item;
        }, $rawTemplateItems);

        $roster = GradingRoster::queryFor($gradingSession)
            ->with('admissionAttempt.identity')
            ->get()
            ->map(function (ExamAssignment $assignment): array {
                $identity = $assignment->admissionAttempt?->identity;

                return [
                    'exam_assignment_id' => $assignment->id,
                    'id' => $assignment->admission_attempt_id,
                    'applicant_number' => $assignment->admissionAttempt?->reference_code,
                    'reference_code' => $assignment->admissionAttempt?->reference_code ?? '—',
                    'name' => $identity
                        ? trim(implode(' ', array_filter([$identity->first_name, $identity->last_name])))
                        : '—',
                ];
            })
            ->values()
            ->all();

        $reproductionAuthorizations = OmrPaperReproductionAuthorization::query()
            ->where('exam_session_id', $gradingSession->exam_session_id)
            ->with(['blankInstancePage.instance', 'authorizedBy'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $providerDriver = (string) config('omr.provider.driver', 'fake');
        $provider = $providerDriver === 'fake'
            ? [
                'name' => config('omr.provider.name', 'nexiam.fake'),
                'version' => config('omr.provider.version', '1'),
                'synthetic' => true,
            ]
            : [
                'name' => config('omr.omrchecker.engine_name', 'OMRChecker'),
                'version' => config('omr.omrchecker.engine_commit', 'unknown'),
                'synthetic' => false,
            ];

        $monitoringUrl = $gradingSession->examSession === null
            ? null
            : route('exams.sessions.monitoring', $gradingSession->examSession);

        $workbenchProjection = null;
        if ($batch !== null) {
            $actor = $request->user();
            abort_unless($actor !== null, 401);

            $workbenchProjection = $authority
                ->project(
                    sprintf('production:%d:%d', (int) $gradingSession->id, (int) $batch->id),
                    (int) $actor->getAuthIdentifier(),
                )
                ->toSafeArray();
        }

        return [
            'page_state' => $batch ? 'ready' : 'empty',
            'scanner_context' => [
                'outcome' => OmrScannerContextOutcome::RouteBound->value,
                'grading_session_id' => (int) $gradingSession->id,
            ],
            'camera_enabled' => (bool) config('omr.camera_enabled', true),
            'provider' => $provider,
            'material' => $materialSnapshot === null ? null : [
                'template_version_id' => (int) $materialSnapshot->template_version_id,
                'template_label' => sprintf(
                    '%s v%s',
                    $materialTemplateVersion?->template?->name ?? 'Answer Sheet',
                    $materialTemplateVersion?->version ?? '?',
                ),
                'qr_mode' => $materialTemplateVersion?->qr_mode?->value,
                'registration_mode' => $materialTemplateVersion?->registration_mode?->value,
                'source_kind' => $materialTemplateVersion !== null
                    ? OmrSourcePolicy::sourceKindForVersion($materialTemplateVersion)
                    : null,
                'identity_method' => is_array($materialTemplateVersion?->authoring_manifest['identity_method'] ?? null)
                    ? $materialTemplateVersion->authoring_manifest['identity_method']
                    : null,
                'booklet_version_id' => (int) $materialSnapshot->booklet_version_id,
                'booklet_label' => sprintf(
                    '%s v%s',
                    $materialSnapshot->bookletVersion?->booklet?->name ?? 'Booklet',
                    $materialSnapshot->bookletVersion?->version ?? '?',
                ),
            ],
            'roster' => $roster,
            'session' => [
                'id' => $gradingSession->id,
                'exam_session_id' => (int) $examSession->id,
                'exam_code' => $examSession->code,
                'room_name' => $examSession->room?->name ?? '—',
                'status' => $gradingSession->status,
            ],
            'reproduction_authorizations' => $reproductionAuthorizations->map(
                function (OmrPaperReproductionAuthorization $authorization): array {
                    $expired = $authorization->status === OmrPaperReproductionAuthorization::STATUS_OPEN
                        && $authorization->expires_at?->isPast();

                    return [
                        'id' => $authorization->id,
                        'display_code' => $authorization->blankInstancePage?->instance?->display_code,
                        'page_no' => (int) ($authorization->blankInstancePage?->page_no ?? 0),
                        'maximum_claims' => (int) $authorization->maximum_claims,
                        'used_claims' => (int) $authorization->used_claims,
                        'remaining_claims' => $expired ? 0 : max(0, (int) $authorization->maximum_claims - (int) $authorization->used_claims),
                        'status' => $expired ? 'expired' : $authorization->status,
                        'reason' => $authorization->reason,
                        'expires_at' => $authorization->expires_at?->toIso8601String(),
                        'authorized_by' => $authorization->authorizedBy?->name,
                    ];
                },
            )->values()->all(),
            'batch' => $batch ? [
                'id' => $batch->id,
                'status' => $batch->status,
                'version' => (int) $batch->version,
                'template' => [
                    'page_width' => (int) ($template?->page_width ?? 1),
                    'page_height' => (int) ($template?->page_height ?? 1),
                    'page_count' => (int) ($template?->pageCount() ?? 1),
                    'identity_region' => $identityRegion,
                    'identity_regions' => $this->identityRegionsByPage(
                        $identityRegions,
                        (int) ($template?->pageCount() ?? 1),
                        $reviewOverlayRegions,
                    ),
                    'items' => $templateItems,
                ],
                'sheets' => $batch->sheets->map(function (OmrCaptureSheet $sheet) use (
                    $examSession,
                    $monitoringUrl,
                ): array {
                    $resolution = $this->captureResolver->resolveSheet($sheet);
                    $failureCode = $resolution->failureCode();
                    $recovery = $failureCode === null ? null : $this->recoveryCatalog->for($failureCode);
                    if ($recovery !== null) {
                        $recovery['support_code'] = sprintf('OMR-%d-%s', $sheet->id, strtoupper($failureCode));
                        $recovery['technical_detail'] = OmrBrowserPayloadRedactor::redact(
                            $resolution->failure?->context ?? [],
                        );
                        $recovery['destination_url'] = match ($recovery['primary_action']) {
                            'open_monitoring' => $monitoringUrl,
                            'open_exam_setup' => route('exams.sessions.show', $examSession),
                            'open_sheet_builder' => route('setup.omr.templates.builder'),
                            'return_to_grading' => route('scoring.index'),
                            default => null,
                        };
                    }

                    return [
                        'id' => $sheet->id,
                        'status' => $sheet->status,
                        'version' => (int) $sheet->version,
                        'capture_mode' => $sheet->capture_mode,
                        'client_capture_id' => $sheet->client_capture_id,
                        'expected_page_count' => (int) $sheet->expected_page_count,
                        'confidence' => $sheet->sheet_confidence,
                        'identity_confidence' => $sheet->detected_applicant_confidence,
                        'detected_applicant_number' => $sheet->detected_applicant_number,
                        'admission_attempt_id' => $sheet->admission_attempt_id,
                        'proposal' => OmrBrowserPayloadRedactor::redact(
                            is_array($sheet->proposal_json) ? $sheet->proposal_json : [],
                        ),
                        'reviewed' => OmrBrowserPayloadRedactor::redact(
                            is_array($sheet->reviewed_json) ? $sheet->reviewed_json : [],
                        ),
                        'resolution' => OmrBrowserPayloadRedactor::redact($resolution->toArray()),
                        'recovery' => $recovery,
                        'issues' => $sheet->issues->map(fn (OmrCaptureSheetIssue $issue): array => [
                            'id' => $issue->id,
                            'page_no' => $issue->page_no,
                            'area_code' => $issue->area_code,
                            'question_no' => $issue->question_no,
                            'issue_code' => $issue->issue_code,
                            'severity' => $issue->severity,
                            'state' => $issue->state,
                            'evidence' => OmrBrowserPayloadRedactor::redact(
                                is_array($issue->evidence_json) ? $issue->evidence_json : [],
                            ),
                            'resolution_code' => $issue->resolution_code,
                            'resolution_reason' => $issue->resolution_reason,
                        ])->values()->all(),
                        'image_url' => route('scoring.sessions.omr.sheets.image', [
                            'examSession' => $examSession->id,
                            'sheet' => $sheet->id,
                        ]),
                        'thumbnail_url' => route('scoring.sessions.omr.sheets.image', [
                            'examSession' => $examSession->id,
                            'sheet' => $sheet->id,
                        ]).'?variant=thumbnail',
                        'pages' => $sheet->pages->sortBy('page_no')->map(fn (OmrCaptureSheetPage $page): array => [
                            'id' => (int) $page->getKey(),
                            'current_revision' => (int) $page->current_revision,
                            'page_no' => (int) $page->page_no,
                            'captured' => $page->asset_path !== null,
                            'copy_claim' => $page->blankInstanceClaim === null ? null : [
                                'kind' => $page->blankInstanceClaim->claim_kind,
                                'reproduction_authorization_id' => $page->blankInstanceClaim->reproduction_authorization_id,
                            ],
                            'image_url' => route('scoring.sessions.omr.sheets.pages.image', [
                                'examSession' => $examSession->id,
                                'sheet' => $sheet->id,
                                'page' => (int) $page->page_no,
                            ]),
                            'thumbnail_url' => route('scoring.sessions.omr.sheets.pages.image', [
                                'examSession' => $examSession->id,
                                'sheet' => $sheet->id,
                                'page' => (int) $page->page_no,
                            ]).'?variant=thumbnail',
                            'detected_applicant_number' => is_array($page->proposal_json)
                                ? ($page->proposal_json['applicant_number_proposal'] ?? null)
                                : null,
                            'detected_birthdate' => is_array($page->proposal_json)
                                ? ($page->proposal_json['birthdate_proposal'] ?? null)
                                : null,
                        ])->values()->all(),
                        'created_at' => $sheet->created_at?->toIso8601String(),
                    ];
                })->values()->all(),
            ] : null,
            'workbench_projection' => $workbenchProjection,
            'qr_disabled_issuances' => $qrDisabledIssuances,
            'supplier_stock_allocations' => $supplierStockAllocations,
            'supplier_stock_lots' => $supplierStockLots,
            'run_status' => $batch !== null
                ? $this->runStatusSnapshot->forBatch($batch)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $identityRegions
     */
    private function identityRegionAt(array $identityRegions, int $pageNo = 1): mixed
    {
        $byPage = $identityRegions[(string) $pageNo] ?? null;

        if (is_array($byPage)) {
            return $byPage['applicant_number'] ?? $byPage['name'] ?? null;
        }

        return $identityRegions['name'] ?? $identityRegions['applicant_number'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $identityRegions
     * @param  array<int, array{name: array<string, float>|null}>  $overlayRegions
     * @return array<int, array{applicant_number: array<string, float>|null, name: array<string, float>|null}>
     */
    private function identityRegionsByPage(array $identityRegions, int $pageCount, array $overlayRegions = []): array
    {
        $pages = [];

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $byPage = $identityRegions[(string) $pageNo] ?? null;
            $pages[$pageNo] = [
                'applicant_number' => is_array($byPage) && is_array($byPage['applicant_number'] ?? null)
                    ? $byPage['applicant_number']
                    : null,
                'name' => is_array($byPage) && is_array($byPage['name'] ?? null)
                    ? $byPage['name']
                    : ($overlayRegions[$pageNo]['name'] ?? null),
            ];
        }

        return $pages;
    }
}
