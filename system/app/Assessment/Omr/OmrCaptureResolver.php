<?php

declare(strict_types=1);

namespace App\Assessment\Omr;

use App\Assessment\Omr\Canonical\OmrBlankPrintContract;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrBlankInstancePage;
use App\Models\Assessment\OmrBlankInstancePageClaim;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrCaptureSheetPageRevision;
use App\Models\Assessment\OmrPendingCapturePageAnalysis;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrQrDisabledPageClaim;
use App\Models\Assessment\OmrSupplierStockAllocation;
use App\Models\Assessment\OmrSupplierStockAllocationUse;
use App\Models\Assessment\OmrSupplierStockLot;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSet;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

use function array_unique;
use function count;
use function hash_equals;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_match;
use function strtoupper;
use function trim;

/**
 * Resolves one captured page against one selected grading/exam session.
 *
 * Detection and QR observations are proposals. This resolver is the only
 * server-side join from those observations to an eligible assignment and the
 * submission-frozen material chain. It never uses names, birthdates, seats,
 * legacy assignment Set columns, or browser-supplied Set values as authority.
 */
final class OmrCaptureResolver
{
    public function __construct(private readonly Canonical\OmrQrPayload $qr) {}

    public function resolve(OmrResolutionRequest $request): OmrCaptureResolution
    {
        [$gradingSession, $examSession] = $this->sessions($request->session);
        $snapshot = $examSession?->currentMaterialSnapshot;
        $applicantNumber = $request->normalizedApplicantNumber();
        $observedSetCode = $request->normalizedObservedSetCode();

        $qrDisabledEvidence = $this->qrDisabledPageEvidence($request);
        if ($qrDisabledEvidence instanceof OmrCaptureResolution) {
            return $qrDisabledEvidence;
        }

        $provenance = $qrDisabledEvidence === null
            ? $this->provenance($request, $snapshot)
            : [null, null];
        if ($provenance instanceof OmrCaptureResolution) {
            return $provenance;
        }
        [$blankInstance, $blankPage] = $provenance;
        $qrDisabledEvidence ??= [];

        if ($gradingSession === null || $examSession === null) {
            return $this->failure(
                OmrResolutionOutcome::ApplicantUnresolved,
                'session_missing',
                'The selected grading session is unavailable.',
                applicantNumber: $applicantNumber,
                blankInstance: $blankInstance,
                blankPage: $blankPage,
                observedSetCode: $observedSetCode,
            );
        }

        $assignmentQuery = $this->eligibleAssignments($examSession, $applicantNumber);
        $assignments = $assignmentQuery->get();

        if ($request->sheet?->set_resolution_reason === 'identity_exception'
            && $request->sheet->admission_attempt_id !== null) {
            $assignments = $this->eligibleAssignments($examSession, null)
                ->where('admission_attempt_id', (int) $request->sheet->admission_attempt_id)
                ->get();
        } elseif ($assignments->isEmpty() && $applicantNumber === null && $request->sheet?->admission_attempt_id !== null) {
            $assignments = $this->eligibleAssignments($examSession, null)
                ->where('admission_attempt_id', (int) $request->sheet->admission_attempt_id)
                ->get();
        }

        if ($assignments->isEmpty()) {
            return $this->failure(
                OmrResolutionOutcome::ApplicantUnresolved,
                'applicant_unresolved',
                'The detected Applicant Number is not assigned to an eligible examinee in this session.',
                applicantNumber: $applicantNumber,
                blankInstance: $blankInstance,
                blankPage: $blankPage,
                observedSetCode: $observedSetCode,
                context: ['exam_session_id' => $examSession->id],
            );
        }

        if ($assignments->count() !== 1) {
            return $this->failure(
                OmrResolutionOutcome::ApplicantConflict,
                'applicant_conflict',
                'The detected Applicant Number matches more than one eligible assignment in this session.',
                applicantNumber: $applicantNumber,
                blankInstance: $blankInstance,
                blankPage: $blankPage,
                observedSetCode: $observedSetCode,
                context: ['assignment_ids' => $assignments->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()],
            );
        }

        /** @var ExamAssignment $assignment */
        $assignment = $assignments->first();
        $attempt = $assignment->admissionAttempt;
        if ($attempt === null) {
            return $this->failure(
                OmrResolutionOutcome::ApplicantUnresolved,
                'applicant_missing',
                'The eligible assignment has no admission attempt.',
                applicantNumber: $applicantNumber,
                blankInstance: $blankInstance,
                blankPage: $blankPage,
                observedSetCode: $observedSetCode,
            );
        }

        $duplicate = $this->duplicateApplicant($request->sheet, (int) $attempt->id, $applicantNumber);
        if ($duplicate !== null) {
            return $this->failure(
                OmrResolutionOutcome::ApplicantDuplicate,
                'applicant_duplicate',
                'Another capture in this grading session already uses this applicant.',
                applicantNumber: $applicantNumber,
                blankInstance: $blankInstance,
                blankPage: $blankPage,
                observedSetCode: $observedSetCode,
                context: [
                    'duplicate_sheet_id' => $duplicate['sheet_id'],
                    'duplicate_batch_id' => $duplicate['batch_id'],
                    'duplicate_prior_batch' => $duplicate['prior_batch'],
                ],
            );
        }

        $submission = $assignment->submissionRecord;
        if ($submission === null) {
            return $this->failure(
                OmrResolutionOutcome::SubmissionMissing,
                'submission_missing',
                'The applicant has no valid submission for this grading session.',
                applicantNumber: $applicantNumber,
                blankInstance: $blankInstance,
                blankPage: $blankPage,
                observedSetCode: $observedSetCode,
            );
        }

        $seatAllocation = $submission->seatAllocation;
        if ($seatAllocation === null || (int) $seatAllocation->exam_assignment_id !== (int) $assignment->id) {
            return $this->failure(
                OmrResolutionOutcome::SeatMissing,
                'seat_missing',
                'The submission does not point to one frozen session-seat allocation.',
                applicantNumber: $applicantNumber,
                blankInstance: $blankInstance,
                blankPage: $blankPage,
                observedSetCode: $observedSetCode,
            );
        }

        $sessionSeat = $seatAllocation->sessionSeat;
        if ($sessionSeat === null) {
            return $this->failure(
                OmrResolutionOutcome::SeatMissing,
                'seat_missing',
                'The submission seat allocation has no session seat.',
                applicantNumber: $applicantNumber,
                blankInstance: $blankInstance,
                blankPage: $blankPage,
                observedSetCode: $observedSetCode,
            );
        }

        $frozenSnapshot = $sessionSeat->materialSnapshot;
        if ($frozenSnapshot === null || (int) $frozenSnapshot->exam_session_id !== (int) $examSession->id) {
            return $this->failure(
                OmrResolutionOutcome::SeatMissing,
                'seat_snapshot_missing',
                'The submitted session seat is not part of the selected exam-session snapshot.',
                applicantNumber: $applicantNumber,
                blankInstance: $blankInstance,
                blankPage: $blankPage,
                observedSetCode: $observedSetCode,
            );
        }

        $materialSet = $this->materialSet($frozenSnapshot, $sessionSeat->booklet_set_id);
        if ($materialSet === null || ! is_array($materialSet->answer_key_json) || $materialSet->answer_key_json === []) {
            return $this->failure(
                OmrResolutionOutcome::KeyMissing,
                'key_missing',
                'The frozen session seat has no immutable answer key.',
                applicantNumber: $applicantNumber,
                blankInstance: $blankInstance,
                blankPage: $blankPage,
                observedSetCode: $observedSetCode,
                context: ['snapshot_id' => $frozenSnapshot->id, 'session_seat_id' => $sessionSeat->id],
            );
        }

        $expectedSetCode = strtoupper(trim((string) $materialSet->set_code));
        if ($observedSetCode !== null && $observedSetCode !== $expectedSetCode) {
            return $this->failure(
                OmrResolutionOutcome::ObservedSetMismatch,
                'observed_set_mismatch',
                'The observed Set is retained as evidence but does not match the submission-frozen seat.',
                applicantNumber: $applicantNumber,
                blankInstance: $blankInstance,
                blankPage: $blankPage,
                observedSetCode: $observedSetCode,
                context: ['expected_set_code' => $expectedSetCode],
            );
        }

        return OmrCaptureResolution::resolved(
            applicantNumber: $applicantNumber,
            attempt: $attempt,
            assignment: $assignment,
            submission: $submission,
            seatAllocation: $seatAllocation,
            sessionSeat: $sessionSeat,
            snapshot: $frozenSnapshot,
            materialSet: $materialSet,
            blankInstance: $blankInstance,
            blankPage: $blankPage,
            observedSetCode: $observedSetCode,
            evidence: [
                'grading_session_id' => $gradingSession->id,
                'exam_session_id' => $examSession->id,
                'snapshot_revision' => $frozenSnapshot->revision,
                'page_no' => $request->pageNo,
                'legacy_binding' => $applicantNumber === null,
                ...$qrDisabledEvidence,
            ],
        );
    }

    public function resolveSheet(OmrCaptureSheet|int $sheet, ?array $kitContext = null): OmrCaptureResolution
    {
        $modelId = $sheet instanceof OmrCaptureSheet ? $sheet->id : $sheet;
        $model = OmrCaptureSheet::query()
            ->with([
                'batch.gradingSession.examSession.currentMaterialSnapshot',
                'batch.templateVersion',
            ])
            ->findOrFail($modelId);

        $capturePages = $model->pages()
            ->with('blankInstanceClaim.blankInstancePage.instance')
            ->orderBy('page_no')
            ->get();
        if ($model->batch->templateVersion?->qr_mode === OmrQrMode::Disabled) {
            $resolution = $this->resolve(new OmrResolutionRequest(
                session: $model->batch->gradingSession,
                applicantNumber: $model->detected_applicant_number,
                pageNo: (int) ($capturePages->first()?->page_no ?? 1),
                observedSetCode: $model->observed_set_code,
                sheet: $model,
            ));
        } else {
            $pageNo = $capturePages->first()?->page_no ?? 1;
            $blankPages = $capturePages->map(function ($capturePage): ?OmrBlankInstancePage {
                return $capturePage->blankInstanceClaim?->blankInstancePage
                    ?? OmrBlankInstancePage::query()
                        ->with('instance')
                        ->where('captured_page_id', $capturePage->id)
                        ->first();
            });
            $claimedPages = $blankPages->filter();
            if ($claimedPages->count() !== 0 && $claimedPages->count() !== $capturePages->count()) {
                return $this->failure(OmrResolutionOutcome::PageInvalid, 'mixed_page_provenance', 'Every page in the capture must resolve to the same issued Answer Sheet instance.');
            }
            if ($claimedPages->pluck('instance_id')->unique()->count() > 1
                || $claimedPages->contains(
                    fn (OmrBlankInstancePage $page, int $index): bool => (int) $page->page_no !== (int) $capturePages->values()->get($index)?->page_no,
                )) {
                return $this->failure(OmrResolutionOutcome::PageInvalid, 'mixed_page_provenance', 'Every page in the capture must resolve to the same issued Answer Sheet instance and page.');
            }
            $blankPage = $claimedPages->first();

            $resolution = $this->resolve(new OmrResolutionRequest(
                session: $model->batch->gradingSession,
                applicantNumber: $model->detected_applicant_number,
                pageNo: (int) $pageNo,
                blankInstance: $blankPage?->instance,
                blankPage: $blankPage,
                observedSetCode: $model->observed_set_code,
                sheet: $model,
            ));
        }

        if ($kitContext !== null && $resolution->isResolved()) {
            $kitVersionId = (int) ($kitContext['test_kit_version_id'] ?? 0);
            $snapshot = $resolution->snapshot;
            if ($kitVersionId < 1
                || (int) ($snapshot?->test_kit_version_id ?? 0) !== $kitVersionId
                || (string) ($kitContext['manifest_sha256'] ?? '') !== (string) ($snapshot?->manifest_sha256 ?? '')
                || (int) ($kitContext['template_version_id'] ?? 0) !== (int) $model->batch->template_version_id
                || (int) ($kitContext['booklet_version_id'] ?? 0) !== (int) $model->batch->booklet_version_id
                || (int) ($kitContext['test_instrument_version_id'] ?? 0) !== (int) ($snapshot?->test_instrument_version_id ?? 0)
                || (int) ($kitContext['version']->selected_acceptance_receipt_id ?? 0) !== (int) ($snapshot?->acceptance_receipt_id ?? 0)
                || (int) ($kitContext['booklet_set_id'] ?? 0) !== (int) ($resolution->materialSet?->booklet_set_id ?? 0)) {
                return $this->failure(
                    OmrResolutionOutcome::TemplateMismatch,
                    'kit_composition_mismatch',
                    'The exact frozen Kit composition does not match this capture and resolved sitting.',
                );
            }
        }

        return $resolution;
    }

    /**
     * @return array{
     *     qr_disabled_issuance_id: int,
     *     qr_disabled_set_id: int,
     *     qr_disabled_copy_policy: string,
     *     qr_disabled_template_version_id: int,
     *     qr_disabled_page_count: int
     * }|OmrCaptureResolution|null
     */
    private function qrDisabledPageEvidence(OmrResolutionRequest $request): array|OmrCaptureResolution|null
    {
        if ($request->sheet === null) {
            return null;
        }

        $sheet = OmrCaptureSheet::query()->find($request->sheet->id);
        if ($sheet === null) {
            $requestBatch = $request->sheet->batch_id === null
                ? null
                : OmrCaptureBatch::query()->with('templateVersion')->find($request->sheet->batch_id);
            if ($requestBatch?->templateVersion?->qr_mode !== OmrQrMode::Disabled) {
                return null;
            }

            return $this->failure(
                OmrResolutionOutcome::LifecycleInvalid,
                'qr_disabled_stock_invalid',
                'The selected QR-disabled capture sheet is no longer persisted.',
            );
        }

        $batch = OmrCaptureBatch::query()
            ->with([
                'templateVersion',
                'gradingSession.examSession.academicCycle',
                'gradingSession.examSession.currentMaterialSnapshot',
            ])
            ->find($sheet->batch_id);
        if ($batch?->templateVersion?->qr_mode !== OmrQrMode::Disabled) {
            return null;
        }

        if (trim((string) $request->qrPayload) !== ''
            || $request->blankInstance !== null
            || $request->blankPage !== null) {
            return $this->failure(
                OmrResolutionOutcome::PageInvalid,
                'qr_disabled_claim_mixed',
                'QR-disabled paper cannot use signed QR or blank-instance provenance.',
            );
        }

        $templateVersion = $batch->templateVersion;
        $gradingSession = $batch->gradingSession;
        $examSession = $gradingSession?->examSession;
        $academicCycle = $examSession?->academicCycle;
        $snapshot = $examSession?->currentMaterialSnapshot;
        $requestedGradingSessionId = $request->session instanceof GradingSession
            ? (int) $request->session->id
            : $request->session->gradingSession()->value('id');

        if ($templateVersion === null
            || $gradingSession === null
            || $examSession === null
            || $academicCycle === null
            || $snapshot === null
            || ! is_numeric($requestedGradingSessionId)
            || (int) $batch->grading_session_id !== (int) $requestedGradingSessionId
            || (int) $snapshot->template_version_id !== (int) $batch->template_version_id) {
            return $this->failure(
                OmrResolutionOutcome::LifecycleInvalid,
                'qr_disabled_stock_invalid',
                'The QR-disabled capture does not match the selected production batch and frozen session scope.',
            );
        }

        try {
            $printContract = OmrBlankPrintContract::fromVersion($templateVersion);
        } catch (Throwable) {
            return $this->failure(
                OmrResolutionOutcome::LifecycleInvalid,
                'qr_disabled_stock_invalid',
                'The QR-disabled batch template no longer has a valid immutable print contract.',
            );
        }

        $pageCount = count($printContract->compiledPages);
        if ($pageCount < 1
            || $pageCount > 4
            || (int) $templateVersion->id !== (int) $batch->template_version_id
            || $printContract->qrMode !== OmrQrMode::Disabled) {
            return $this->failure(
                OmrResolutionOutcome::LifecycleInvalid,
                'qr_disabled_stock_invalid',
                'The QR-disabled batch template has an invalid page or QR policy.',
            );
        }

        $pages = $sheet->pages()->orderBy('page_no')->get();
        if ((int) $sheet->batch_id !== (int) $batch->id
            || (int) $sheet->expected_page_count !== $pageCount
            || $pages->count() !== $pageCount) {
            return $this->failure(
                OmrResolutionOutcome::PageInvalid,
                'qr_disabled_claim_missing',
                'Every expected page must be captured and claimed before material resolution.',
            );
        }

        foreach ($pages->values() as $index => $page) {
            if ((int) $page->page_no !== $index + 1
                || $page->status !== OmrCaptureSheetPage::STATUS_SUCCEEDED) {
                return $this->failure(
                    OmrResolutionOutcome::PageInvalid,
                    'qr_disabled_claim_missing',
                    'The QR-disabled capture does not contain every ordered, successfully captured page.',
                );
            }
        }

        $capturePageIds = $pages->pluck('id')->all();
        if (OmrBlankInstancePageClaim::query()->whereIn('capture_sheet_page_id', $capturePageIds)->exists()
            || OmrBlankInstancePage::query()->whereIn('captured_page_id', $capturePageIds)->exists()) {
            return $this->failure(
                OmrResolutionOutcome::PageInvalid,
                'qr_disabled_claim_mixed',
                'QR-disabled pages cannot also carry signed blank-instance claims.',
            );
        }

        if ((int) $request->pageNo !== (int) $pages->first()->page_no) {
            return $this->failure(
                OmrResolutionOutcome::PageInvalid,
                'qr_disabled_claim_stale',
                'The requested page does not match the first ordered page in the claimed capture set.',
            );
        }

        $claims = [];
        foreach ($pages as $page) {
            $claim = $page->qrDisabledClaim()->first();
            if ($claim === null) {
                return $this->failure(
                    OmrResolutionOutcome::PageInvalid,
                    'qr_disabled_claim_missing',
                    'Every expected page must have a QR-disabled page claim.',
                );
            }
            $claims[] = $claim;
        }

        $setId = $claims[0]->set_id;
        $issuanceId = $claims[0]->issuance_id;
        $supplierAllocationId = $claims[0]->supplier_stock_allocation_id;
        $hasIssuance = is_numeric($issuanceId) && $supplierAllocationId === null;
        $hasSupplierAllocation = is_numeric($supplierAllocationId) && $issuanceId === null;
        if (! is_numeric($setId) || (! $hasIssuance && ! $hasSupplierAllocation)) {
            return $this->failure(
                OmrResolutionOutcome::LifecycleInvalid,
                'qr_disabled_stock_invalid',
                'The QR-disabled page claims do not identify a capture set and single stock source.',
            );
        }
        foreach ($claims as $index => $claim) {
            $page = $pages->values()->get($index);
            if ((int) $claim->set_id !== (int) $setId
                || ($hasIssuance && ((int) $claim->issuance_id !== (int) $issuanceId || $claim->supplier_stock_allocation_id !== null))
                || ($hasSupplierAllocation && ((int) $claim->supplier_stock_allocation_id !== (int) $supplierAllocationId || $claim->issuance_id !== null))) {
                return $this->failure(
                    OmrResolutionOutcome::PageInvalid,
                    'qr_disabled_claim_mixed',
                    'Every QR-disabled page claim must belong to the same capture set and stock source.',
                );
            }
            if ((int) $claim->capture_sheet_page_id !== (int) $page->id
                || (int) $claim->page_no !== (int) $page->page_no) {
                return $this->failure(
                    OmrResolutionOutcome::PageInvalid,
                    'qr_disabled_claim_stale',
                    'A QR-disabled claim no longer matches its captured page.',
                );
            }
        }

        $set = $claims[0]->captureSet()->first();
        if ($set === null) {
            return $this->failure(
                OmrResolutionOutcome::LifecycleInvalid,
                'qr_disabled_stock_invalid',
                'The QR-disabled capture set is unavailable.',
            );
        }

        $setClaims = $set->pageClaims()->orderBy('page_no')->get();
        if ($setClaims->count() < $pageCount) {
            return $this->failure(
                OmrResolutionOutcome::PageInvalid,
                'qr_disabled_claim_missing',
                'The QR-disabled capture set does not contain one claim for every expected page.',
            );
        }
        if ($setClaims->count() > $pageCount) {
            return $this->failure(
                OmrResolutionOutcome::PageInvalid,
                'qr_disabled_claim_mixed',
                'The QR-disabled capture set contains claims outside its pinned page list.',
            );
        }

        foreach ($setClaims->values() as $index => $setClaim) {
            $page = $pages->values()->get($index);
            $pageClaim = $claims[$index];
            if ((int) $setClaim->id !== (int) $pageClaim->id
                || (int) $setClaim->page_no !== $index + 1
                || (int) $setClaim->capture_sheet_page_id !== (int) $page->id) {
                return $this->failure(
                    OmrResolutionOutcome::PageInvalid,
                    'qr_disabled_claim_mixed',
                    'The QR-disabled capture set claims do not match the captured page order.',
                );
            }
        }

        $adoptedSheet = $set->adoptedSheet()->first();
        if ($adoptedSheet !== null && (int) $adoptedSheet->id !== (int) $sheet->id) {
            return $this->failure(
                OmrResolutionOutcome::PageInvalid,
                'qr_disabled_claim_mixed',
                'The QR-disabled capture set is already adopted by another sheet.',
            );
        }

        if ((int) $set->production_batch_id !== (int) $batch->id
            || $set->diagnostic_run_id !== null
            || (int) $set->template_version_id !== (int) $templateVersion->id
            || ($hasIssuance && ((int) $set->issuance_id !== (int) $issuanceId || $set->supplier_stock_allocation_id !== null))
            || ($hasSupplierAllocation && ((int) $set->supplier_stock_allocation_id !== (int) $supplierAllocationId || $set->issuance_id !== null))
            || (int) $set->institution_id !== (int) $academicCycle->institution_id
            || (int) $set->academic_cycle_id !== (int) $examSession->academic_cycle_id
            || (int) $set->adopted_sheet_id !== (int) $sheet->id
            || $set->status !== OmrPendingCaptureSet::STATUS_READY
            || $set->pairing_code !== null
            || (int) $set->page_count !== $pageCount
            || $set->registration_mode !== $printContract->registrationMode->value
            || $set->expected_pages_json !== $printContract->compiledPages
            || ! is_string($set->native_bundle_hash)
            || ! hash_equals($printContract->nativeBundleHash, $set->native_bundle_hash)
            || ! is_string($set->artifact_hash)
            || ! hash_equals($printContract->artifactHash, $set->artifact_hash)) {
            if ($set->pairing_code === 'pairing_missing') {
                return $this->failure(
                    OmrResolutionOutcome::ApplicantUnresolved,
                    'pairing_missing',
                    'Every QR-disabled page must have the same non-null Applicant Number.',
                );
            }
            if ($set->pairing_code === 'pairing_conflict') {
                return $this->failure(
                    OmrResolutionOutcome::ApplicantConflict,
                    'pairing_conflict',
                    'The QR-disabled pages contain different Applicant Numbers.',
                );
            }

            return $this->failure(
                OmrResolutionOutcome::LifecycleInvalid,
                'qr_disabled_stock_invalid',
                'The QR-disabled capture set does not match its adopted production batch, template, or ready lifecycle.',
            );
        }

        $issuance = null;
        $allocation = null;
        if ($hasSupplierAllocation) {
            $allocation = $claims[0]->supplierStockAllocation()->first();
            $setAllocation = $set->supplierStockAllocation()->first();
            $lot = $allocation?->lot()->first();
            $use = OmrSupplierStockAllocationUse::query()
                ->where('set_id', $set->id)
                ->where('capture_sheet_id', $sheet->id)
                ->first();
            if ($allocation === null
                || $setAllocation === null
                || $lot === null
                || $use === null
                || (int) $allocation->id !== (int) $supplierAllocationId
                || (int) $setAllocation->id !== (int) $supplierAllocationId
                || (int) $use->allocation_id !== (int) $allocation->id
                || (int) $allocation->lot_id !== (int) $lot->id
                || (int) $allocation->batch_id !== (int) $batch->id
                || (int) $allocation->template_version_id !== (int) $templateVersion->id
                || (int) $lot->template_version_id !== (int) $templateVersion->id
                || (int) $allocation->institution_id !== (int) $academicCycle->institution_id
                || (int) $allocation->academic_cycle_id !== (int) $examSession->academic_cycle_id
                || (int) $lot->institution_id !== (int) $academicCycle->institution_id
                || (int) $lot->academic_cycle_id !== (int) $examSession->academic_cycle_id
                || $lot->status !== OmrSupplierStockLot::STATUS_AVAILABLE
                || ! in_array($allocation->status, [
                    OmrSupplierStockAllocation::STATUS_ACTIVE,
                    OmrSupplierStockAllocation::STATUS_EXHAUSTED,
                ], true)
                || (int) $allocation->page_count !== $pageCount
                || (int) $lot->page_count !== $pageCount
                || $set->copy_policy !== OmrSupplierStockAllocation::COPY_POLICY_SUPPLIER_STOCK
                || $allocation->copy_policy !== OmrSupplierStockAllocation::COPY_POLICY_SUPPLIER_STOCK
                || OmrSourcePolicy::sourceKindForVersion($templateVersion) !== OmrSourcePolicy::UPLOADED_ORIGINAL) {
                return $this->failure(
                    OmrResolutionOutcome::LifecycleInvalid,
                    'supplier_stock_invalid',
                    'The supplier stock allocation is unavailable or does not match its pinned lot, batch, template, or scope.',
                );
            }
        } else {
            $issuance = $claims[0]->issuance()->first();
            $setIssuance = $set->issuance()->first();
            if ($issuance === null
                || $setIssuance === null
                || (int) $issuance->id !== (int) $issuanceId
                || (int) $setIssuance->id !== (int) $issuanceId
                || (int) $issuance->template_version_id !== (int) $templateVersion->id
                || (int) $issuance->institution_id !== (int) $academicCycle->institution_id
                || (int) $issuance->academic_cycle_id !== (int) $examSession->academic_cycle_id
                || $issuance->status !== OmrQrDisabledIssuance::STATUS_PRINTED
                || (int) $issuance->page_count !== $pageCount
                || $issuance->copy_policy !== $set->copy_policy
                || ! in_array($issuance->copy_policy, [
                    OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE,
                    OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER,
                ], true)
                || (int) $issuance->intended_copy_count < 1
                || ! is_string($issuance->artifact_manifest_hash)
                || preg_match('/^[a-f0-9]{64}$/i', $issuance->artifact_manifest_hash) !== 1
                || OmrSourcePolicy::sourceKindForVersion($templateVersion) === OmrSourcePolicy::UPLOADED_ORIGINAL) {
                return $this->failure(
                    OmrResolutionOutcome::LifecycleInvalid,
                    'qr_disabled_issuance_invalid',
                    'The QR-disabled issuance is not printed or does not match its pinned template, scope, or copy policy.',
                );
            }

            $issuanceTemplate = $issuance->templateVersion()->first();
            if ($issuanceTemplate === null
                || (int) $issuanceTemplate->id !== (int) $templateVersion->id
                || $issuanceTemplate->qr_mode !== OmrQrMode::Disabled) {
                return $this->failure(
                    OmrResolutionOutcome::LifecycleInvalid,
                    'qr_disabled_issuance_invalid',
                    'The QR-disabled issuance is not tied to a QR-disabled immutable template version.',
                );
            }
        }

        $manifest = is_array($templateVersion->authoring_manifest) ? $templateVersion->authoring_manifest : [];
        $identityMethod = is_array($manifest['identity_method'] ?? null) ? $manifest['identity_method'] : [];
        $isWriteInManual = ($identityMethod['method'] ?? null) === 'write_in_manual' || $set->identity_mode === 'write_in_manual';
        $manualPages = [];
        if ($isWriteInManual && is_array($set->manual_identity_json['pages'] ?? null)) {
            foreach ($set->manual_identity_json['pages'] as $entry) {
                if (is_array($entry) && isset($entry['page_no'])) {
                    $manualPages[(int) $entry['page_no']] = $entry;
                }
            }
        }
        $identityNumbers = [];
        foreach ($claims as $index => $claim) {
            $capturePage = $pages->values()->get($index);
            $pageProposal = $capturePage->proposal_json;
            $evidence = $claim->pageEvidence()->first();
            if ($evidence === null
                || (int) $evidence->set_id !== (int) $set->id
                || (int) $evidence->page_no !== (int) $claim->page_no
                || ! is_string($claim->input_hash)
                || ! is_string($evidence->input_hash)
                || preg_match('/^[a-f0-9]{64}$/i', $claim->input_hash) !== 1
                || preg_match('/^[a-f0-9]{64}$/i', $evidence->input_hash) !== 1
                || ! hash_equals($claim->input_hash, $evidence->input_hash)
                || ! is_array($pageProposal)
                || (int) ($pageProposal['page_no'] ?? 0) !== (int) $claim->page_no
                || ! $this->matchesClaimedPageRevision($capturePage, $pageProposal, $claim, $evidence)) {
                return $this->failure(
                    OmrResolutionOutcome::PageInvalid,
                    'qr_disabled_claim_stale',
                    'A QR-disabled claim does not match its selected evidence hash, asset, or captured page.',
                );
            }

            $selectedEvidence = OmrPendingCapturePageEvidence::query()
                ->where('set_id', $set->id)
                ->where('page_no', $claim->page_no)
                ->orderByDesc('id')
                ->first();
            if ($selectedEvidence === null || (int) $selectedEvidence->id !== (int) $evidence->id) {
                return $this->failure(
                    OmrResolutionOutcome::PageInvalid,
                    'qr_disabled_claim_stale',
                    'The claimed QR-disabled page is no longer the selected pending-page evidence.',
                );
            }

            $analysis = OmrPendingCapturePageAnalysis::query()
                ->where('evidence_id', $evidence->id)
                ->first();
            $analysisEnvelope = $analysis?->evidence_json;
            if ($analysis === null
                || ! is_array($analysisEnvelope)
                || ! is_string($analysisEnvelope['input_hash'] ?? null)
                || ! hash_equals($evidence->input_hash, $analysisEnvelope['input_hash'])
                || (int) ($analysisEnvelope['page_no'] ?? 0) !== (int) $claim->page_no) {
                return $this->failure(
                    OmrResolutionOutcome::PageInvalid,
                    'qr_disabled_claim_stale',
                    'The QR-disabled page analysis no longer matches its selected input hash and page.',
                );
            }

            if ($isWriteInManual) {
                $manualEntry = $manualPages[(int) $claim->page_no] ?? null;
                $number = is_array($manualEntry) ? trim((string) ($manualEntry['applicant_number'] ?? '')) : '';
                if (! is_array($manualEntry)
                    || (int) ($manualEntry['evidence_id'] ?? 0) !== (int) $evidence->id
                    || ! hash_equals($evidence->input_hash, (string) ($manualEntry['input_hash'] ?? ''))
                    || $number === '') {
                    return $this->failure(
                        OmrResolutionOutcome::ApplicantUnresolved,
                        'identity_review_required',
                        'Every write-in page must have a confirmed manual identity bound to its selected evidence.',
                    );
                }
                $identityNumbers[] = $number;
            } else {
                $number = trim((string) $analysis->detected_applicant_number);
                if ($number === '') {
                    return $this->failure(
                        OmrResolutionOutcome::ApplicantUnresolved,
                        'pairing_missing',
                        'Every QR-disabled page must have the same non-null Applicant Number.',
                    );
                }
                $identityNumbers[] = $number;
            }
        }

        $applicantNumbers = array_unique($identityNumbers);
        if (count($applicantNumbers) !== 1) {
            return $this->failure(
                OmrResolutionOutcome::ApplicantConflict,
                'pairing_conflict',
                'The QR-disabled pages contain different Applicant Numbers.',
            );
        }

        $applicantNumber = $request->normalizedApplicantNumber();
        $sheetApplicantNumber = trim((string) $sheet->detected_applicant_number);
        if ($applicantNumber === null || $sheetApplicantNumber === '') {
            return $this->failure(
                OmrResolutionOutcome::ApplicantUnresolved,
                'pairing_missing',
                'Every QR-disabled page must have the same non-null Applicant Number.',
            );
        }
        if ($applicantNumber !== $identityNumbers[0] || $sheetApplicantNumber !== $identityNumbers[0]) {
            return $this->failure(
                OmrResolutionOutcome::ApplicantConflict,
                'pairing_conflict',
                'The QR-disabled page claims, capture sheet, and Applicant Number do not agree.',
            );
        }

        return [
            'qr_disabled_issuance_id' => $issuance !== null ? (int) $issuance->id : null,
            'supplier_stock_allocation_id' => $allocation !== null ? (int) $allocation->id : null,
            'qr_disabled_set_id' => (int) $set->id,
            'qr_disabled_copy_policy' => (string) $set->copy_policy,
            'qr_disabled_template_version_id' => (int) $templateVersion->id,
            'qr_disabled_page_count' => $pageCount,
        ];
    }

    /** @return array{0: ?GradingSession, 1: ?ExamSession} */
    private function sessions(GradingSession|ExamSession $session): array
    {
        if ($session instanceof GradingSession) {
            $session->loadMissing('examSession.currentMaterialSnapshot');

            return [$session, $session->examSession];
        }

        $session->loadMissing('currentMaterialSnapshot', 'gradingSession');

        return [$session->gradingSession, $session];
    }

    /**
     * A production redo changes the active scan, not the immutable QR-disabled
     * stock claim or the pending evidence that established the examinee.
     *
     * @param  array<string, mixed>  $proposal
     */
    private function matchesClaimedPageRevision(
        OmrCaptureSheetPage $page,
        array $proposal,
        OmrQrDisabledPageClaim $claim,
        OmrPendingCapturePageEvidence $evidence,
    ): bool {
        if (! is_string($proposal['input_hash'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/i', (string) $proposal['input_hash']) !== 1) {
            return false;
        }

        if ($page->current_revision_id === null) {
            return (int) $page->current_revision === 1
                && $page->asset_path === $evidence->asset_path
                && hash_equals((string) $claim->input_hash, (string) $proposal['input_hash']);
        }

        $current = $page->currentRevision()->first();
        if ($current === null
            || (int) $current->page_id !== (int) $page->getKey()
            || (int) $current->revision !== (int) $page->current_revision
            || $current->status !== $page->status
            || $current->asset_path !== $page->asset_path
            || $current->asset_hmac !== $page->asset_hmac
            || $current->aligned_asset_path !== $page->aligned_asset_path
            || $current->aligned_hash !== $page->aligned_hash
            || $current->crop_policy !== $page->crop_policy
            || $current->client_quad_json !== $page->client_quad_json
            || $current->proposal_json !== $page->proposal_json
            || (int) $current->current_processing_run_id !== (int) $page->current_processing_run_id
            || ! hash_equals((string) ($page->proposal_json['input_hash'] ?? ''), (string) $proposal['input_hash'])) {
            return false;
        }

        $run = $page->currentProcessingRun()->first();
        if ($run === null
            || $run->status !== OmrProcessingRun::STATUS_SUCCEEDED
            || (int) $run->page_no !== (int) $page->page_no
            || (int) $run->page_revision !== (int) $page->current_revision
            || ! hash_equals((string) $run->input_hash, (string) $proposal['input_hash'])) {
            return false;
        }

        $seen = [];
        while (true) {
            $revisionId = (int) $current->getKey();
            if (isset($seen[$revisionId]) || (int) $current->page_id !== (int) $page->getKey()) {
                return false;
            }
            $seen[$revisionId] = true;

            if ($current->source_revision_id === null) {
                return $current->revision_kind === OmrCaptureSheetPageRevision::KIND_SOURCE
                    && (int) $current->revision === 1
                    && $current->asset_path === $evidence->asset_path
                    && is_array($current->proposal_json)
                    && hash_equals(
                        (string) $claim->input_hash,
                        (string) ($current->proposal_json['input_hash'] ?? ''),
                    );
            }

            $source = OmrCaptureSheetPageRevision::query()->find($current->source_revision_id);
            if ($source === null || (int) $source->page_id !== (int) $page->getKey()) {
                return false;
            }
            if (($current->revision_kind === OmrCaptureSheetPageRevision::KIND_REPLACEMENT
                    && (int) $current->revision !== (int) $source->revision + 1)
                || (in_array($current->revision_kind, [
                    OmrCaptureSheetPageRevision::KIND_ANALYSIS,
                    OmrCaptureSheetPageRevision::KIND_SNAPSHOT,
                ], true) && (int) $current->revision !== (int) $source->revision)
                || ! in_array($current->revision_kind, [
                    OmrCaptureSheetPageRevision::KIND_REPLACEMENT,
                    OmrCaptureSheetPageRevision::KIND_ANALYSIS,
                    OmrCaptureSheetPageRevision::KIND_SNAPSHOT,
                ], true)) {
                return false;
            }

            $current = $source;
        }
    }

    /** @return Builder<ExamAssignment> */
    private function eligibleAssignments(ExamSession $session, ?string $applicantNumber): Builder
    {
        $query = ExamAssignment::query()
            ->with([
                'admissionAttempt',
                'attendanceRecord',
                'submissionRecord.seatAllocation.sessionSeat.materialSnapshot',
                'submissionRecord.seatAllocation.sessionSeat.bookletSet',
            ])
            ->where('exam_session_id', $session->id)
            ->active()
            ->whereHas('attendanceRecord', fn (Builder $query): Builder => $query->where('outcome', 'present'));

        if ($applicantNumber !== null) {
            $query->whereHas('admissionAttempt', fn (Builder $query): Builder => $query->where('reference_code', $applicantNumber));
        }

        return $query->orderBy('id');
    }

    private function materialSet(ExamSessionMaterialSnapshot $snapshot, mixed $bookletSetId): ?ExamSessionMaterialSet
    {
        if (! is_numeric($bookletSetId)) {
            return null;
        }

        return $snapshot->sets()->where('booklet_set_id', (int) $bookletSetId)->first();
    }

    /** @return array{sheet_id: int, batch_id: int, prior_batch: bool}|null */
    private function duplicateApplicant(?OmrCaptureSheet $sheet, int $attemptId, ?string $applicantNumber): ?array
    {
        if ($sheet === null) {
            return null;
        }

        $currentSheet = OmrCaptureSheet::query()->with('batch')->find($sheet->id);
        $gradingSessionId = $currentSheet?->batch?->grading_session_id;
        if ($currentSheet === null || $gradingSessionId === null) {
            return null;
        }

        $duplicate = OmrCaptureSheet::query()
            ->whereHas('batch', fn (Builder $query): Builder => $query->where('grading_session_id', $gradingSessionId))
            ->whereKeyNot($currentSheet->id)
            ->whereNotIn('status', [OmrCaptureSheet::STATUS_FAILED_TERMINAL])
            ->where(function (Builder $query) use ($attemptId, $applicantNumber): void {
                $query->where('admission_attempt_id', $attemptId);
                if ($applicantNumber !== null) {
                    $query->orWhere('detected_applicant_number', $applicantNumber);
                }
            })
            ->orderByRaw('CASE WHEN batch_id = ? THEN 1 ELSE 0 END', [$currentSheet->batch_id])
            ->orderBy('id')
            ->select(['id', 'batch_id'])
            ->first();

        return $duplicate === null ? null : [
            'sheet_id' => (int) $duplicate->id,
            'batch_id' => (int) $duplicate->batch_id,
            'prior_batch' => (int) $duplicate->batch_id !== (int) $currentSheet->batch_id,
        ];
    }

    /**
     * @return array{0: ?OmrBlankInstance, 1: ?OmrBlankInstancePage}|OmrCaptureResolution
     */
    private function provenance(OmrResolutionRequest $request, ?ExamSessionMaterialSnapshot $snapshot): array|OmrCaptureResolution
    {
        $blankInstance = $request->blankInstance;
        $blankPage = $request->blankPage;
        $payload = trim((string) $request->qrPayload);

        if ($payload !== '') {
            try {
                $verified = $this->qr->verify($payload);
            } catch (Throwable $exception) {
                return $this->failure(
                    OmrResolutionOutcome::QrInvalid,
                    'qr_invalid',
                    'The Answer Sheet QR signature is invalid.',
                    context: ['reason' => $exception->getMessage()],
                );
            }

            $verifiedInstance = OmrBlankInstance::query()
                ->where('instance_code', $verified['instance_code'])
                ->first();
            if ($verifiedInstance === null) {
                return $this->failure(OmrResolutionOutcome::QrInvalid, 'qr_instance_missing', 'The signed Answer Sheet instance is not registered.');
            }

            if ($blankInstance !== null && (int) $blankInstance->id !== (int) $verifiedInstance->id) {
                return $this->failure(OmrResolutionOutcome::QrInvalid, 'qr_provenance_conflict', 'The signed QR does not match the registered blank instance.');
            }

            $blankInstance = $verifiedInstance;
            if ((int) $verified['page_no'] !== $request->pageNo || (int) $verified['page_count'] !== (int) $blankInstance->page_count) {
                return $this->failure(OmrResolutionOutcome::PageInvalid, 'qr_page_invalid', 'The signed QR page does not match the blank-instance page.');
            }
        }

        $requiresQr = $payload !== '' || $blankInstance !== null || $blankPage !== null || $this->sheetRequiresQr($request->sheet);
        if ($requiresQr && $blankInstance === null) {
            return $this->failure(OmrResolutionOutcome::QrInvalid, 'qr_missing', 'A valid issued Answer Sheet QR/page provenance is required.');
        }

        if ($blankInstance !== null) {
            $blankInstance->loadMissing('pages');
            if (in_array($blankInstance->status, [OmrBlankInstance::STATUS_VOID, OmrBlankInstance::STATUS_SUPERSEDED], true)) {
                return $this->failure(
                    OmrResolutionOutcome::LifecycleInvalid,
                    'qr_lifecycle_invalid',
                    'The issued Answer Sheet instance is void or superseded.',
                    blankInstance: $blankInstance,
                );
            }
            if ((int) $blankInstance->page_count < $request->pageNo) {
                return $this->failure(
                    OmrResolutionOutcome::PageInvalid,
                    'page_missing_from_set',
                    'The issued Answer Sheet instance does not contain this page.',
                    blankInstance: $blankInstance,
                );
            }
            $blankPage ??= $blankInstance->pages->firstWhere('page_no', $request->pageNo);
            if ($blankPage === null) {
                return $this->failure(
                    OmrResolutionOutcome::PageInvalid,
                    'page_missing_from_set',
                    'The issued Answer Sheet page is not registered.',
                    blankInstance: $blankInstance,
                );
            }
            if ($blankPage->status === OmrBlankInstancePage::STATUS_VOID) {
                return $this->failure(
                    OmrResolutionOutcome::LifecycleInvalid,
                    'qr_page_void',
                    'The issued Answer Sheet page is void.',
                    blankInstance: $blankInstance,
                    blankPage: $blankPage,
                );
            }

            $currentCapturePageId = $request->sheet?->pages()->where('page_no', $request->pageNo)->value('id');
            $currentCaptureOwnsClaim = $currentCapturePageId !== null
                && OmrBlankInstancePageClaim::query()
                    ->where('blank_instance_page_id', $blankPage->id)
                    ->where('capture_sheet_page_id', $currentCapturePageId)
                    ->exists();
            if ($blankPage->isCaptured()
                && (int) $blankPage->captured_page_id !== (int) $currentCapturePageId
                && ! $currentCaptureOwnsClaim) {
                return $this->failure(
                    OmrResolutionOutcome::QrDuplicate,
                    'qr_duplicate',
                    'This issued Answer Sheet page has already been claimed by another capture.',
                    blankInstance: $blankInstance,
                    blankPage: $blankPage,
                );
            }

            $expectedTemplateId = $request->sheet?->batch?->template_version_id ?? $snapshot?->template_version_id;
            if ($expectedTemplateId !== null && (int) $blankInstance->template_version_id !== (int) $expectedTemplateId) {
                return $this->failure(
                    OmrResolutionOutcome::TemplateMismatch,
                    'template_mismatch',
                    'The issued Answer Sheet belongs to another immutable template version.',
                    blankInstance: $blankInstance,
                    blankPage: $blankPage,
                );
            }
        }

        return [$blankInstance, $blankPage];
    }

    private function sheetRequiresQr(?OmrCaptureSheet $sheet): bool
    {
        $template = $sheet?->batch?->templateVersion;
        if ($template === null) {
            return false;
        }

        return $template->authoring_manifest !== null || $template->blankInstances()->exists();
    }

    private function failure(
        OmrResolutionOutcome $outcome,
        string $code,
        string $message,
        ?string $applicantNumber = null,
        ?OmrBlankInstance $blankInstance = null,
        ?OmrBlankInstancePage $blankPage = null,
        ?string $observedSetCode = null,
        array $context = [],
    ): OmrCaptureResolution {
        return OmrCaptureResolution::failed(
            outcome: $outcome,
            code: $code,
            message: $message,
            context: $context,
            applicantNumber: $applicantNumber,
            blankInstance: $blankInstance,
            blankPage: $blankPage,
            observedSetCode: $observedSetCode,
        );
    }
}
