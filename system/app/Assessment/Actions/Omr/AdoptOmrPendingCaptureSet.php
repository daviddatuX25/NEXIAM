<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Canonical\OmrBlankPrintContract;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrPinnedSnapshot;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrResolutionRequest;
use App\Assessment\Support\KitCompositionContext;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrPendingCapturePageAnalysis;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrQrDisabledPageClaim;
use App\Models\Assessment\OmrSupplierStockAllocation;
use App\Models\Assessment\OmrSupplierStockLot;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Converts a complete, paired, printed QR-free set into one reviewable production sheet. */
final class AdoptOmrPendingCaptureSet
{
    public function __construct(
        private readonly OmrPendingCaptureAccess $access,
        private readonly OmrCaptureResolver $resolver,
        private readonly SensitiveAssetStore $assets,
        private readonly RecordAuditEvent $audit,
        private readonly ?RecordOmrSupplierStockAllocationUse $recordSupplierUse = null,
    ) {}

    public function handle(OmrPendingCaptureSet $set, User $actor, int $expectedRevision): OmrCaptureSheet
    {
        $scope = $this->access->actorScope($actor);

        try {
            return DB::transaction(function () use ($set, $actor, $scope, $expectedRevision): OmrCaptureSheet {
                $batch = OmrCaptureBatch::query()->lockForUpdate()->with([
                    'templateVersion.areas.aptitudeArea', 'bookletVersion.questions.aptitudeArea',
                    'gradingSession.examSession.currentMaterialSnapshot',
                ])->findOrFail($set->production_batch_id);
                $locked = OmrPendingCaptureSet::query()->lockForUpdate()->findOrFail($set->id);
                if ($locked->production_batch_id === null || (int) $locked->production_batch_id !== (int) $batch->id
                    || (int) $locked->created_by_user_id !== (int) $actor->id
                    || (int) $locked->institution_id !== $scope['institution_id']
                    || (int) $locked->academic_cycle_id !== $scope['academic_cycle_id']) {
                    throw new RuntimeException('omr_capture_set_scope_mismatch');
                }
                if ($locked->adopted_sheet_id !== null) {
                    return OmrCaptureSheet::query()->findOrFail($locked->adopted_sheet_id);
                }
                if ($locked->revision !== $expectedRevision) {
                    throw new RuntimeException('omr_capture_set_revision_stale');
                }
                if ($locked->status !== OmrPendingCaptureSet::STATUS_READY || $locked->pairing_code !== null || $locked->isExpired()) {
                    throw new RuntimeException('omr_capture_set_pairing_required');
                }
                $session = $batch->gradingSession;
                $exam = $session?->examSession;
                $snapshot = $exam?->currentMaterialSnapshot;
                if ($batch->status !== OmrCaptureBatch::STATUS_OPEN || $session === null
                    || $session->status === $session::STATUS_FINALIZED || $exam === null
                    || ! $snapshot instanceof ExamSessionMaterialSnapshot
                    || (int) $snapshot->template_version_id !== (int) $locked->template_version_id
                    || (int) $snapshot->booklet_version_id !== (int) $batch->booklet_version_id
                    || (int) $batch->template_version_id !== (int) $locked->template_version_id) {
                    throw new RuntimeException('omr_capture_set_context_closed');
                }
                $version = $batch->templateVersion;
                $contract = $version === null ? null : OmrBlankPrintContract::fromVersion($version);
                if ($contract === null || $contract->qrMode !== OmrQrMode::Disabled
                    || $contract->compiledPages !== $locked->expected_pages_json
                    || ! hash_equals($contract->nativeBundleHash, $locked->native_bundle_hash)
                    || ! hash_equals($contract->artifactHash, $locked->artifact_hash)) {
                    throw new RuntimeException($locked->copy_policy === OmrSupplierStockAllocation::COPY_POLICY_SUPPLIER_STOCK ? 'supplier_stock_invalid' : 'qr_disabled_issuance_invalid');
                }

                $isSupplierStock = $locked->copy_policy === OmrSupplierStockAllocation::COPY_POLICY_SUPPLIER_STOCK;
                $issuance = null;
                $allocation = null;
                if ($isSupplierStock) {
                    if ($locked->supplier_stock_allocation_id === null
                        || $locked->issuance_id !== null
                        || OmrSourcePolicy::sourceKindForVersion($version) !== OmrSourcePolicy::UPLOADED_ORIGINAL) {
                        throw new RuntimeException('supplier_stock_invalid');
                    }
                    $allocationRef = OmrSupplierStockAllocation::query()->find($locked->supplier_stock_allocation_id);
                    $lot = $allocationRef === null ? null : OmrSupplierStockLot::query()->lockForUpdate()->find($allocationRef->lot_id);
                    $allocation = $allocationRef === null ? null : OmrSupplierStockAllocation::query()->lockForUpdate()->find($allocationRef->id);
                    if ($lot === null || $allocation === null
                        || $lot->status !== OmrSupplierStockLot::STATUS_AVAILABLE
                        || $allocation->status !== OmrSupplierStockAllocation::STATUS_ACTIVE
                        || (int) $allocation->lot_id !== (int) $lot->id
                        || (int) $allocation->batch_id !== (int) $batch->id
                        || (int) $allocation->grading_session_id !== (int) $session->id
                        || (int) $allocation->template_version_id !== (int) $version->id
                        || (int) $lot->template_version_id !== (int) $version->id
                        || (int) $allocation->institution_id !== $scope['institution_id']
                        || (int) $allocation->academic_cycle_id !== $scope['academic_cycle_id']
                        || (int) $lot->institution_id !== $scope['institution_id']
                        || (int) $lot->academic_cycle_id !== $scope['academic_cycle_id']
                        || (int) $allocation->page_count !== (int) $locked->page_count
                        || (int) $lot->page_count !== (int) $locked->page_count) {
                        throw new RuntimeException('supplier_stock_invalid');
                    }
                    if ($allocation->remainingQuantity() < 1) {
                        throw new RuntimeException('supplier_stock_exhausted');
                    }
                } else {
                    if ($locked->issuance_id === null
                        || $locked->supplier_stock_allocation_id !== null
                        || OmrSourcePolicy::sourceKindForVersion($version) === OmrSourcePolicy::UPLOADED_ORIGINAL) {
                        throw new RuntimeException('qr_disabled_issuance_invalid');
                    }
                    $issuance = OmrQrDisabledIssuance::query()->lockForUpdate()->find($locked->issuance_id);
                    if ($issuance === null
                        || $issuance->status !== OmrQrDisabledIssuance::STATUS_PRINTED
                        || (int) $issuance->template_version_id !== (int) $version->id
                        || (int) $issuance->institution_id !== $scope['institution_id']
                        || (int) $issuance->academic_cycle_id !== $scope['academic_cycle_id']
                        || (int) $issuance->id !== (int) $locked->issuance_id
                        || (int) $issuance->page_count !== (int) $locked->page_count
                        || $issuance->copy_policy !== $locked->copy_policy) {
                        throw new RuntimeException('qr_disabled_issuance_invalid');
                    }
                    if ($locked->copy_policy === OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE
                        && OmrQrDisabledPageClaim::query()->where('issuance_id', $issuance->id)
                            ->where('page_no', 1)->count() >= (int) $issuance->intended_copy_count) {
                        throw new RuntimeException('qr_disabled_stock_exhausted');
                    }
                }

                $manifest = is_array($version->authoring_manifest) ? $version->authoring_manifest : [];
                $identityMethod = is_array($manifest['identity_method'] ?? null) ? $manifest['identity_method'] : [];
                $isWriteInManual = ($identityMethod['method'] ?? null) === 'write_in_manual' || $locked->identity_mode === 'write_in_manual';
                $manualPages = [];
                if ($isWriteInManual && is_array($locked->manual_identity_json['pages'] ?? null)) {
                    foreach ($locked->manual_identity_json['pages'] as $entry) {
                        if (is_array($entry) && isset($entry['page_no'])) {
                            $manualPages[(int) $entry['page_no']] = $entry;
                        }
                    }
                }
                $selected = [];
                $number = null;
                foreach ($locked->expected_pages_json as $expected) {
                    $pageNo = (int) $expected['page_no'];
                    $evidence = OmrPendingCapturePageEvidence::query()->where('set_id', $locked->id)
                        ->where('page_no', $pageNo)->orderByDesc('id')->first();
                    $analysis = $evidence === null ? null : OmrPendingCapturePageAnalysis::query()
                        ->where('evidence_id', $evidence->id)->first();
                    $payload = $analysis?->evidence_json;
                    if ($analysis === null || $analysis->recognition_code !== null
                        || ! is_array($payload) || ($payload['outcome'] ?? null) !== 'succeeded'
                        || ($payload['page_no'] ?? null) !== $pageNo
                        || ! hash_equals((string) $evidence->input_hash, (string) ($payload['input_hash'] ?? ''))) {
                        throw new RuntimeException('omr_capture_set_pairing_required');
                    }
                    if ($isWriteInManual) {
                        $manualEntry = $manualPages[$pageNo] ?? null;
                        $pageNumber = is_array($manualEntry) ? trim((string) ($manualEntry['applicant_number'] ?? '')) : '';
                        if (! is_array($manualEntry)
                            || (int) ($manualEntry['evidence_id'] ?? 0) !== (int) $evidence->id
                            || ! hash_equals((string) $evidence->input_hash, (string) ($manualEntry['input_hash'] ?? ''))
                            || $pageNumber === ''
                            || ($number !== null && $number !== $pageNumber)) {
                            throw new RuntimeException('omr_capture_set_pairing_required');
                        }
                        $number = $pageNumber;
                    } else {
                        if (! is_string($analysis->detected_applicant_number)
                            || $analysis->detected_applicant_number === ''
                            || ($number !== null && $number !== $analysis->detected_applicant_number)) {
                            throw new RuntimeException('omr_capture_set_pairing_required');
                        }
                        $number = $analysis->detected_applicant_number;
                    }
                    $bytes = $this->assets->read($evidence->asset_path);
                    if (! hash_equals($evidence->input_hash, hash('sha256', $bytes))) {
                        throw new RuntimeException('analysis_evidence_invalid');
                    }
                    $selected[$pageNo] = [$evidence, $payload, $bytes];
                }
                if (count($selected) !== (int) $locked->page_count || $number === null) {
                    throw new RuntimeException('omr_capture_set_pairing_required');
                }

                $resolution = $this->resolver->resolve(new OmrResolutionRequest($session, $number));
                if (! $resolution->isResolved() || $resolution->materialSet === null || $resolution->attempt === null) {
                    throw new RuntimeException($resolution->failureCode() ?? 'applicant_unresolved');
                }
                $bookletSet = $resolution->materialSet->bookletSet()->firstOrFail();
                $kitContext = null;
                if ($snapshot->test_kit_version_id !== null) {
                    $kitVersion = TestKitVersion::query()->findOrFail((int) $snapshot->test_kit_version_id);
                    $kitContext = app(KitCompositionContext::class)->resolveVersion($kitVersion);
                    if ((int) $kitContext['test_kit_version_id'] !== (int) $snapshot->test_kit_version_id
                        || (string) $kitContext['manifest_sha256'] !== (string) $snapshot->manifest_sha256
                        || (int) $kitContext['template_version_id'] !== (int) $version->id
                        || (int) $kitContext['booklet_version_id'] !== (int) $batch->booklet_version_id
                        || (int) $kitContext['booklet_set_id'] !== (int) $bookletSet->id
                        || (int) $kitContext['test_instrument_version_id'] !== (int) $snapshot->test_instrument_version_id
                        || (int) ($kitContext['version']->selected_acceptance_receipt_id ?? 0) !== (int) $snapshot->acceptance_receipt_id
                        || (int) $resolution->materialSet->booklet_set_id !== (int) $bookletSet->id) {
                        throw new RuntimeException('omr_capture_set_context_closed');
                    }
                }
                $pin = OmrPinnedSnapshot::pin($version, $batch->bookletVersion, $bookletSet, $kitContext);
                $first = $selected[1][0];
                $sheet = $batch->sheets()->create([
                    'admission_attempt_id' => null,
                    'booklet_set_id' => $resolution->materialSet->booklet_set_id,
                    'set_resolution_reason' => 'submission_frozen_seat',
                    'set_resolved_by_user_id' => $actor->id,
                    'set_resolved_at' => now(),
                    'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
                    'expected_page_count' => $locked->page_count,
                    'capture_mode' => OmrCaptureSheet::MODE_MANUAL,
                    'version' => 0,
                    'client_capture_id' => $locked->public_id,
                    'detected_applicant_number' => $number,
                    'proposal_json' => $proposal,
                    'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                    'captured_by_user_id' => $actor->id,
                    'captured_at' => $first->created_at,
                    ...($locked->page_count === 1 ? [
                        'asset_path' => $first->asset_path,
                        'asset_hmac' => $this->assets->hmac($selected[1][2]),
                        'original_mime' => $first->mime_type,
                        'original_bytes' => $first->byte_count,
                    ] : []),
                ]);
                $recordedIssues = [];
                foreach ($selected as $pageNo => [$evidence, $pageProposal, $bytes]) {
                    $page = $sheet->pages()->create([
                        'page_no' => $pageNo,
                        'status' => OmrCaptureSheetPage::STATUS_SUCCEEDED,
                        'asset_path' => $evidence->asset_path,
                        'asset_hmac' => $this->assets->hmac($bytes),
                        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                        'original_mime' => $evidence->mime_type,
                        'original_bytes' => $evidence->byte_count,
                        'crop_policy' => $evidence->crop_policy ?? 'auto_document_quad',
                        'client_quad_json' => $evidence->client_quad_json,
                        'proposal_json' => $pageProposal,
                    ]);
                    OmrQrDisabledPageClaim::query()->create([
                        'issuance_id' => $issuance?->id,
                        'supplier_stock_allocation_id' => $allocation?->id,
                        'set_id' => $locked->id,
                        'evidence_id' => $evidence->id,
                        'capture_sheet_page_id' => $page->id,
                        'page_no' => $pageNo,
                        'input_hash' => $evidence->input_hash,
                        'created_by_user_id' => $actor->id,
                    ]);
                    foreach ((array) ($pageProposal['issues'] ?? []) as $issue) {
                        if (! is_array($issue) || ! is_string($issue['code'] ?? null) || $issue['code'] === ''
                            || isset($recordedIssues[$issue['code']])) {
                            continue;
                        }
                        $recordedIssues[$issue['code']] = true;
                        $sheet->issues()->create([
                            'page_no' => $pageNo,
                            'issue_code' => $issue['code'],
                            'severity' => in_array($issue['severity'] ?? null, ['warning', 'required', 'terminal'], true) ? $issue['severity'] : 'required',
                            'state' => 'open',
                            'evidence_json' => ['capture_set_id' => $locked->id, 'page_evidence_id' => $evidence->id],
                            'version' => 1,
                        ]);
                    }
                }
                if ($isSupplierStock) {
                    ($this->recordSupplierUse ?? app(RecordOmrSupplierStockAllocationUse::class))->handle(
                        $locked,
                        $actor,
                        (int) $sheet->id,
                        (string) $locked->idempotency_key,
                    );
                }
                $locked->adopted_sheet_id = $sheet->id;
                $locked->resolution_code = null;
                $locked->revision++;
                $locked->save();
                $sheet->setRelation('batch', $batch);
                $resolved = $this->resolver->resolveSheet($sheet);
                if ($resolved->failureCode() === 'applicant_duplicate'
                    && ($resolved->failure?->context['duplicate_prior_batch'] ?? false) === true) {
                    throw new RuntimeException('applicant_duplicate_prior_batch');
                }
                if (! $resolved->isResolved()) {
                    $code = $resolved->failureCode();
                    if ($code !== 'applicant_duplicate') {
                        throw new RuntimeException($code ?? 'qr_disabled_claim_invalid');
                    }
                    $sheet->issues()->create([
                        'page_no' => 0, 'issue_code' => $code, 'severity' => 'required',
                        'state' => 'open', 'evidence_json' => $resolved->failure?->context ?? [], 'version' => 1,
                    ]);
                }
                $this->audit->handle('grading.omr_qr_disabled_set_adopted', [
                    'capture_set_id' => $locked->id, 'sheet_id' => $sheet->id,
                    'batch_id' => $batch->id, 'issuance_id' => $issuance?->id,
                    'supplier_stock_allocation_id' => $allocation?->id,
                    'copy_policy' => $locked->copy_policy,
                    'duplicate_code' => $resolved->failureCode(),
                    'pages' => array_map(static fn (array $entry): array => [
                        'evidence_id' => $entry[0]->id, 'page_no' => $entry[0]->page_no,
                        'input_hash' => $entry[0]->input_hash,
                    ], array_values($selected)),
                ], (string) $actor->id);

                return $sheet;
            });
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage();
            if (in_array($code, [
                'qr_disabled_stock_exhausted', 'qr_disabled_issuance_invalid',
                'supplier_stock_exhausted', 'supplier_stock_invalid',
                'applicant_unresolved', 'applicant_conflict', 'applicant_missing',
                'submission_missing', 'seat_missing', 'seat_snapshot_missing',
                'key_missing', 'observed_set_mismatch', 'analysis_evidence_invalid',
                'qr_disabled_claim_invalid', 'qr_disabled_claim_missing',
                'qr_disabled_claim_stale', 'qr_disabled_claim_mixed',
                'qr_disabled_stock_invalid', 'qr_disabled_provenance_invalid',
                'applicant_duplicate_prior_batch',
            ], true)) {
                DB::transaction(function () use ($set, $actor, $expectedRevision, $code): void {
                    $locked = OmrPendingCaptureSet::query()->lockForUpdate()->findOrFail($set->id);
                    if ($locked->status !== OmrPendingCaptureSet::STATUS_READY
                        || $locked->adopted_sheet_id !== null || $locked->revision !== $expectedRevision) {
                        return;
                    }
                    $locked->resolution_code = $code;
                    $locked->revision++;
                    $locked->save();
                    $this->audit->handle('grading.omr_qr_disabled_adoption_blocked', [
                        'capture_set_id' => $locked->id, 'issuance_id' => $locked->issuance_id,
                        'supplier_stock_allocation_id' => $locked->supplier_stock_allocation_id,
                        'batch_id' => $locked->production_batch_id, 'code' => $code,
                    ], (string) $actor->id);
                });
            }

            throw $exception;
        }
    }

    /** @param array<int, array{OmrPendingCapturePageEvidence, array<string,mixed>, string}> $selected */
    private function proposal(array $selected, OmrPinnedSnapshot $pin, OmrPendingCaptureSet $set, string $number): array
    {
        $geometryByPosition = [];
        foreach ($pin->items as $geometry) {
            $geometryByPosition[$geometry->position] = $geometry;
        }
        $items = [];
        foreach ($selected as $pageNo => [$evidence, $payload]) {
            foreach ((array) ($payload['items'] ?? []) as $detected) {
                $position = (int) ($detected['position'] ?? 0);
                $geometry = $geometryByPosition[$position] ?? null;
                if (! is_array($detected) || $geometry === null || $geometry->pageNo !== $pageNo
                    || (int) ($detected['page_no'] ?? 0) !== $pageNo
                    || (string) ($detected['area_code'] ?? '') !== $geometry->areaCode
                    || (int) ($detected['question_no'] ?? 0) !== $geometry->questionNo
                    || isset($items[$position])) {
                    throw new RuntimeException('analysis_evidence_invalid');
                }
                $machine = is_array($detected['detected'] ?? null) ? $detected['detected'] : [];
                $selectedOption = $machine['selected_option'] ?? null;
                $items[$position] = [
                    'position' => $position, 'page_no' => $pageNo,
                    'area_code' => $geometry->areaCode,
                    'area_id' => $pin->formalSubtestIdsByPosition[$position] ?? $pin->areaIdsByCode[$geometry->areaCode],
                    'question_no' => $geometry->questionNo,
                    'options' => $geometry->optionCodes(),
                    'machine' => [
                        'state' => $machine['state'] ?? 'unreadable',
                        'selected_option' => $selectedOption,
                        'observed_options' => $machine['observed_options'] ?? ($selectedOption === null ? [] : [$selectedOption]),
                        'option_observations' => $machine['option_observations'] ?? [],
                        'confidence' => $machine['confidence'] ?? null,
                        'issue_codes' => $machine['issue_codes'] ?? [],
                    ],
                    'state' => $machine['state'] ?? 'unreadable',
                    'detected_answer' => $selectedOption,
                    'confidence' => $machine['confidence'] ?? null,
                    'evidence_hash' => hash('sha256', json_encode($detected, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                    'region' => $detected['region'] ?? $geometry->region->toArray(),
                ];
            }
        }
        if ($items === [] || count($items) !== count($geometryByPosition)
            || array_diff(array_keys($geometryByPosition), array_keys($items)) !== []) {
            throw new RuntimeException('analysis_evidence_invalid');
        }
        ksort($items);

        return [
            'envelope' => 'nexiam.omr.detection.v2',
            'native_bundle_hash' => $set->native_bundle_hash,
            'applicant_number_proposal' => $number,
            'items' => array_values($items),
            'qr_disabled' => [
                'capture_set_id' => $set->id,
                'issuance_id' => $set->issuance_id,
                'supplier_stock_allocation_id' => $set->supplier_stock_allocation_id,
                'copy_policy' => $set->copy_policy,
            ],
        ];
    }
}
