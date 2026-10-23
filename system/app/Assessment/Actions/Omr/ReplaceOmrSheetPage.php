<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\Camera\OmrCameraFrameReservation;
use App\Assessment\Actions\Omr\Canonical\RegisterOmrBlankInstanceCapture;
use App\Assessment\Omr\Canonical\OmrQrReader;
use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\AnalyzeOmrCaptureSheetJob;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrBlankInstancePage;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrCaptureSheetPageRevision;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\ScoreRecord;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function hash;
use function hash_equals;
use function in_array;
use function json_encode;
use function preg_match;
use function strlen;
use function trim;

/**
 * Creates an immutable, reasoned production page revision and queues analysis
 * against only the newly accepted original bytes.
 */
final class ReplaceOmrSheetPage
{
    private const REPLACEABLE_SHEET_STATUSES = [
        OmrCaptureSheet::STATUS_RECEIVED,
        OmrCaptureSheet::STATUS_PENDING_PAGES,
        OmrCaptureSheet::STATUS_QUEUED,
        OmrCaptureSheet::STATUS_PROCESSING,
        OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        OmrCaptureSheet::STATUS_REVIEWED,
        OmrCaptureSheet::STATUS_FAILED_TERMINAL,
    ];

    private const REPLACEABLE_PAGE_STATUSES = [
        OmrCaptureSheetPage::STATUS_QUEUED,
        OmrCaptureSheetPage::STATUS_PROCESSING,
        OmrCaptureSheetPage::STATUS_SUCCEEDED,
        OmrCaptureSheetPage::STATUS_FAILED_TERMINAL,
    ];

    public function __construct(
        private readonly SensitiveAssetStore $assetStore,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly OmrPendingCaptureAccess $pendingCaptureAccess,
        private readonly OmrQrReader $qrReader,
        private readonly RegisterOmrBlankInstanceCapture $registerBlankInstance,
        private readonly OmrCameraFrameFinalization $cameraFrameFinalization,
    ) {}

    /**
     * @param  array<string, mixed>|null  $clientQuad
     */
    public function handle(
        OmrCaptureSheet $sheet,
        int $pageNo,
        int $expectedSourcePageId,
        int $expectedRevision,
        string $imageBytes,
        string $mime,
        User $actor,
        string $replacementReason,
        string $idempotencyKey,
        ?string $redoAuthorizationId = null,
        ?array $clientQuad = null,
        string $cropPolicy = 'auto_document_quad',
        ?OmrCameraFrameReservation $cameraReservation = null,
    ): OmrCaptureSheetPage {
        $reason = trim($replacementReason);
        $redoAuthorizationId = trim((string) $redoAuthorizationId);
        $idempotencyKey = trim($idempotencyKey);
        if ($reason === '' || strlen($reason) > 2000
            || ($cameraReservation !== null && $redoAuthorizationId === '')
            || strlen($redoAuthorizationId) > 255
            || $idempotencyKey === '' || strlen($idempotencyKey) > 255) {
            throw new RuntimeException('omr_page_replacement_reason_or_idempotency_invalid');
        }
        if (! in_array($cropPolicy, ['auto_document_quad', 'keep_original'], true)) {
            throw new RuntimeException('omr_page_replacement_crop_policy_invalid');
        }

        $sheetReference = OmrCaptureSheet::query()
            ->with(['batch.gradingSession.examSession.academicCycle', 'batch.templateVersion', 'batch.bookletVersion'])
            ->findOrFail($sheet->id);
        $batchReference = $sheetReference->batch;
        if ($batchReference === null) {
            throw new RuntimeException('omr_page_replacement_batch_missing');
        }
        $this->assertActorScope($batchReference, $actor);

        $pageReference = OmrCaptureSheetPage::query()
            ->with(['blankInstanceClaim.blankInstancePage.instance', 'qrDisabledClaim.captureSet', 'currentRevision'])
            ->where('sheet_id', $sheetReference->id)
            ->where('page_no', $pageNo)
            ->first();
        if ($pageReference === null) {
            throw new RuntimeException("Page {$pageNo} has not been captured yet.");
        }

        $operationId = $cameraReservation === null
            ? null
            : (string) $cameraReservation->operation->operation_id;
        $cameraTargetKey = $cameraReservation === null
            ? null
            : (string) ($cameraReservation->operation->target_json['targetKey']
                ?? $cameraReservation->operation->target_json['target_key']
                ?? '');
        if ($cameraReservation !== null && $cameraTargetKey === '') {
            throw new RuntimeException('omr_camera_capture_target_missing');
        }

        $inputHash = hash('sha256', $imageBytes);
        $fingerprint = $this->fingerprint(
            (int) $actor->getAuthIdentifier(),
            (int) $sheetReference->id,
            $pageNo,
            $expectedSourcePageId,
            $expectedRevision,
            $reason,
            $redoAuthorizationId,
            $idempotencyKey,
            $inputHash,
            $mime,
            $clientQuad,
            $operationId,
            $cameraTargetKey,
        );

        $replay = $this->findReplay(
            $pageReference,
            $idempotencyKey,
            $fingerprint,
            (int) $actor->getAuthIdentifier(),
            $expectedSourcePageId,
            $expectedRevision,
        );
        if ($replay !== null) {
            return $replay;
        }

        $target = $this->redoSource($sheetReference, $pageNo, $actor);
        if ($target === null
            || (int) $target['source_page_id'] !== $expectedSourcePageId
            || (int) $target['source_revision'] !== $expectedRevision) {
            throw new RuntimeException('omr_page_replacement_source_stale');
        }
        if ($cameraReservation !== null
            && ! hash_equals((string) $target['targetKey'], (string) $cameraTargetKey)) {
            throw new RuntimeException('omr_camera_capture_target_stale');
        }

        if ($cameraReservation !== null) {
            $this->assertCameraReservationTarget(
                $cameraReservation,
                $target,
                $redoAuthorizationId,
                $idempotencyKey,
                $reason,
                $expectedSourcePageId,
                $expectedRevision,
            );
        }

        $version = $batchReference->templateVersion;
        $registrationMode = $version?->registration_mode;
        if ($version === null || ! $registrationMode instanceof OmrRegistrationMode) {
            throw new RuntimeException('omr_page_replacement_template_policy_missing');
        }
        if ($cropPolicy === 'keep_original' && $registrationMode !== OmrRegistrationMode::DocumentBound) {
            throw new RuntimeException('Keeping the original image is supported only for document-bound capture.');
        }
        $sourceImage = OmrSourceImage::fromBytes($imageBytes);
        $validatedQuad = null;
        if ($clientQuad !== null) {
            if ($registrationMode !== OmrRegistrationMode::DocumentBound) {
                throw new RuntimeException('Client corners are accepted only for document-bound capture.');
            }
            $validatedQuad = OmrClientQuad::fromArray($clientQuad);
            if (! $validatedQuad->isBoundTo($sourceImage, $pageNo)) {
                throw new RuntimeException('The advisory corners do not match this image and page.');
            }
        }
        $this->assertReplacementPhysicalIdentity($pageReference, $batchReference, $pageNo, $imageBytes, $mime);

        $candidatePath = 'omr/assets/'.Str::uuid()->toString().'.bin';
        $candidateHmac = $this->assetStore->hmac($imageBytes);
        if ($cameraReservation !== null) {
            $this->cameraFrameFinalization->recordAssetIntent($cameraReservation, $candidatePath, $candidateHmac);
        }

        try {
            $storedPath = $this->assetStore->store($imageBytes, $candidatePath);
        } catch (Throwable $exception) {
            if ($cameraReservation === null) {
                $this->assetStore->delete($candidatePath);
            }
            throw $exception;
        }

        $created = false;
        try {
            $result = DB::transaction(function () use (
                $sheetReference,
                $pageNo,
                $expectedSourcePageId,
                $expectedRevision,
                $imageBytes,
                $inputHash,
                $mime,
                $actor,
                $reason,
                $redoAuthorizationId,
                $idempotencyKey,
                $target,
                $fingerprint,
                $validatedQuad,
                $cropPolicy,
                $storedPath,
                $candidateHmac,
                $operationId,
                $cameraTargetKey,
                $cameraReservation,
                &$created,
            ): OmrCaptureSheetPage {
                $batchReference = OmrCaptureBatch::query()->findOrFail($sheetReference->batch_id);
                $gradingSession = GradingSession::query()->lockForUpdate()->findOrFail($batchReference->grading_session_id);
                $batch = OmrCaptureBatch::query()
                    ->with(['templateVersion', 'bookletVersion', 'gradingSession.examSession.academicCycle'])
                    ->lockForUpdate()
                    ->findOrFail($batchReference->id);

                $activeRuns = OmrProcessingRun::query()
                    ->where('sheet_id', $sheetReference->id)
                    ->whereIn('status', [
                        OmrProcessingRun::STATUS_PENDING,
                        OmrProcessingRun::STATUS_CLAIMED,
                        OmrProcessingRun::STATUS_RETRYABLE_FAILED,
                    ])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lockedSheet = OmrCaptureSheet::query()->lockForUpdate()->findOrFail($sheetReference->id);
                $lockedSheet->setRelation('batch', $batch);
                $pages = $lockedSheet->pages()->orderBy('page_no')->lockForUpdate()->get();
                $page = $pages->firstWhere('page_no', $pageNo);
                if (! $page instanceof OmrCaptureSheetPage) {
                    throw new RuntimeException("Page {$pageNo} has not been captured yet.");
                }

                $this->assertActorScope($batch, $actor);

                $replay = $this->findReplay(
                    $page,
                    $idempotencyKey,
                    $fingerprint,
                    (int) $actor->getAuthIdentifier(),
                    $expectedSourcePageId,
                    $expectedRevision,
                );
                if ($replay !== null) {
                    return $replay;
                }

                if (! $this->isEligible($lockedSheet, $page, $batch, $gradingSession)
                    || (int) $page->getKey() !== $expectedSourcePageId
                    || (int) $page->current_revision !== $expectedRevision) {
                    throw new RuntimeException('omr_page_replacement_source_stale');
                }

                $nextRevision = $expectedRevision + 1;
                $grantOperationId = $redoAuthorizationId === ''
                    ? null
                    : $this->consumeRedoGrant(
                        $actor,
                        $batch,
                        $lockedSheet,
                        $page,
                        $target,
                        $redoAuthorizationId,
                        $idempotencyKey,
                        $reason,
                        $expectedSourcePageId,
                        $expectedRevision,
                        $cameraReservation,
                    );
                $revisionOperationId = $operationId ?? $grantOperationId;

                $page->setRelation('sheet', $lockedSheet);

                $sourceRevisionId = $page->current_revision_id === null
                    ? null
                    : (int) $page->current_revision_id;
                if ($sourceRevisionId === null) {
                    if ((int) $page->current_revision !== 1) {
                        throw new RuntimeException('omr_page_revision_history_missing');
                    }
                    $sourceRevision = OmrCaptureSheetPageRevision::capture(
                        $page,
                        OmrCaptureSheetPageRevision::KIND_SOURCE,
                        actorUserId: $lockedSheet->captured_by_user_id === null
                            ? null
                            : (int) $lockedSheet->captured_by_user_id,
                    );
                    $sourceRevisionId = (int) $sourceRevision->getKey();
                } else {
                    $currentRevision = OmrCaptureSheetPageRevision::query()
                        ->whereKey($sourceRevisionId)
                        ->where('page_id', $page->getKey())
                        ->where('revision', (int) $page->current_revision)
                        ->first();
                    if ($currentRevision === null) {
                        throw new RuntimeException('omr_page_revision_pointer_stale');
                    }

                    $snapshot = OmrCaptureSheetPageRevision::capture(
                        $page,
                        OmrCaptureSheetPageRevision::KIND_SNAPSHOT,
                        sourceRevisionId: $sourceRevisionId,
                        actorUserId: (int) $actor->getAuthIdentifier(),
                    );
                    $sourceRevisionId = (int) $snapshot->getKey();
                    $page->forceFill(['current_revision_id' => $sourceRevisionId])->save();
                }

                $nextSheetVersion = (int) $lockedSheet->version + 1;
                foreach ($activeRuns as $run) {
                    if ((int) $run->page_no === $pageNo) {
                        $run->forceFill([
                            'status' => OmrProcessingRun::STATUS_SUPERSEDED,
                            'diagnostic_code' => 'superseded_by_page_revision',
                            'claim_token' => null,
                            'claim_expires_at' => null,
                            'next_retry_at' => null,
                        ])->save();

                        continue;
                    }

                    $otherPage = $pages->firstWhere('page_no', (int) $run->page_no);
                    if (! $otherPage instanceof OmrCaptureSheetPage
                        || (int) $otherPage->current_processing_run_id !== (int) $run->id
                        || (int) $run->page_revision !== (int) $otherPage->current_revision) {
                        throw new RuntimeException('omr_sibling_processing_source_stale');
                    }

                    $run->forceFill(['expected_sheet_version' => $nextSheetVersion])->save();
                }

                $expectedPageCount = max(1, (int) $lockedSheet->expected_page_count);
                $hasAllPages = $pages->count() >= $expectedPageCount;
                $otherPageProcessing = $pages->contains(function (OmrCaptureSheetPage $otherPage) use ($pageNo, $activeRuns): bool {
                    if ((int) $otherPage->page_no === $pageNo) {
                        return false;
                    }

                    $run = $activeRuns->get((int) $otherPage->current_processing_run_id);

                    return $otherPage->status === OmrCaptureSheetPage::STATUS_PROCESSING
                        || $run?->status === OmrProcessingRun::STATUS_CLAIMED;
                });

                $page->forceFill([
                    'status' => OmrCaptureSheetPage::STATUS_QUEUED,
                    'asset_path' => $storedPath,
                    'asset_hmac' => $candidateHmac,
                    'asset_hmac_key_id' => (string) config('omr.keyring.active_key_id'),
                    'asset_key_id' => (string) config('omr.keyring.active_key_id'),
                    'asset_format_version' => 'v1',
                    'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                    'original_mime' => $mime,
                    'original_bytes' => strlen($imageBytes),
                    'client_quad_json' => $validatedQuad?->toArray(),
                    'crop_policy' => $cropPolicy,
                    'aligned_mime' => null,
                    'aligned_bytes' => null,
                    'aligned_width' => null,
                    'aligned_height' => null,
                    'aligned_hash' => null,
                    'aligned_asset_path' => null,
                    'aligned_asset_hmac' => null,
                    'aligned_asset_hmac_key_id' => null,
                    'aligned_asset_key_id' => null,
                    'proposal_json' => null,
                    'current_processing_run_id' => null,
                    'current_revision' => $nextRevision,
                ])->save();

                $newRun = null;
                if ($hasAllPages) {
                    $templateVersion = $batch->templateVersion;
                    $bookletVersion = $batch->bookletVersion;
                    if ($templateVersion === null || $bookletVersion === null) {
                        throw new RuntimeException('omr_page_replacement_material_pin_missing');
                    }

                    $driver = (string) config('omr.provider.driver', 'fake');
                    $providerName = $driver === 'fake'
                        ? (string) config('omr.provider.name', 'nexiam.fake')
                        : (string) $templateVersion->engine_name;
                    $providerVersion = $driver === 'fake'
                        ? (string) config('omr.provider.version', '1')
                        : (string) ($templateVersion->engine_commit ?: 'unknown');
                    $providerCommit = $driver === 'fake'
                        ? (config('omr.provider.commit') !== null ? (string) config('omr.provider.commit') : null)
                        : ($templateVersion->engine_commit !== null ? (string) $templateVersion->engine_commit : null);

                    $newRun = $lockedSheet->processingRuns()->create([
                        'actor_id' => $actor->id,
                        'run_no' => ((int) $lockedSheet->processingRuns()->max('run_no')) + 1,
                        'run_uuid' => Str::uuid()->toString(),
                        'status' => OmrProcessingRun::STATUS_PENDING,
                        'page_no' => $pageNo,
                        'page_revision' => $nextRevision,
                        'provider_name' => $providerName,
                        'provider_version' => $providerVersion,
                        'provider_commit' => $providerCommit,
                        'request_version' => OmrDetectionRequest::ENVELOPE_VERSION,
                        'expected_sheet_version' => $nextSheetVersion,
                        'input_hash' => $inputHash,
                        'template_hash' => (string) $templateVersion->native_bundle_hash,
                        'booklet_hash' => (string) $bookletVersion->answer_key_hash,
                        'native_bundle_hash' => (string) $templateVersion->native_bundle_hash,
                        'manifest_hash' => (string) $templateVersion->review_manifest_hash,
                        'config_hash' => (string) $templateVersion->config_hash,
                        'patch_identity' => (string) $templateVersion->patch_identity,
                        'attempts' => 0,
                    ]);
                    $page->forceFill(['current_processing_run_id' => $newRun->id])->save();
                }

                $nextSheetStatus = ! $hasAllPages
                    ? OmrCaptureSheet::STATUS_PENDING_PAGES
                    : ($otherPageProcessing ? OmrCaptureSheet::STATUS_PROCESSING : OmrCaptureSheet::STATUS_QUEUED);
                $lockedSheet->forceFill([
                    'status' => $nextSheetStatus,
                    'version' => $nextSheetVersion,
                    'sheet_confidence' => null,
                    'proposal_json' => null,
                    'review_draft_json' => null,
                    'reviewed_json' => null,
                    'reviewed_by_user_id' => null,
                    'reviewed_at' => null,
                    'detected_applicant_number' => null,
                    'detected_applicant_confidence' => null,
                    'detected_birthdate' => null,
                ]);
                if ($expectedPageCount === 1) {
                    $lockedSheet->forceFill([
                        'asset_path' => $storedPath,
                        'asset_hmac' => $candidateHmac,
                        'asset_hmac_key_id' => (string) config('omr.keyring.active_key_id'),
                        'asset_key_id' => (string) config('omr.keyring.active_key_id'),
                        'asset_format_version' => 'v1',
                        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                        'original_mime' => $mime,
                        'original_bytes' => strlen($imageBytes),
                        'aligned_mime' => null,
                        'aligned_bytes' => null,
                        'aligned_width' => null,
                        'aligned_height' => null,
                        'aligned_hash' => null,
                        'aligned_asset_path' => null,
                        'aligned_asset_hmac' => null,
                        'aligned_asset_hmac_key_id' => null,
                        'aligned_asset_key_id' => null,
                        'current_processing_run_id' => $newRun?->id,
                    ]);
                }
                $lockedSheet->save();

                $page->setRelation('sheet', $lockedSheet);
                $revision = OmrCaptureSheetPageRevision::capture(
                    $page,
                    OmrCaptureSheetPageRevision::KIND_REPLACEMENT,
                    sourceRevisionId: $sourceRevisionId,
                    actorUserId: (int) $actor->getAuthIdentifier(),
                    operationId: $revisionOperationId,
                    idempotencyKey: $idempotencyKey,
                    payloadFingerprint: $fingerprint,
                    replacementReason: $reason,
                );
                $page->forceFill(['current_revision_id' => $revision->getKey()])->save();

                $this->recordAuditEvent->handle('grading.omr_sheet_page_replaced', [
                    'sheet_id' => (int) $lockedSheet->id,
                    'batch_id' => (int) $lockedSheet->batch_id,
                    'page_id' => (int) $page->id,
                    'page_no' => $pageNo,
                    'source_revision' => $expectedRevision,
                    'revision' => $nextRevision,
                    'operation_id' => $revisionOperationId,
                    'idempotency_key' => $idempotencyKey,
                    'replacement_reason' => $reason,
                    'new_input_hash' => $inputHash,
                ], (string) $actor->id);

                if ($cameraReservation !== null) {
                    $this->cameraFrameFinalization->commitAccepted($cameraReservation, [
                        'operation_id' => $operationId,
                        'target_key' => $cameraTargetKey,
                        'status' => 'accepted',
                        'capture_id' => (int) $lockedSheet->id,
                        'page_id' => (int) $page->id,
                        'page_no' => $pageNo,
                        'client_capture_id' => (string) $lockedSheet->client_capture_id,
                        'revision' => $nextRevision,
                    ]);
                }

                if ($newRun !== null) {
                    AnalyzeOmrCaptureSheetJob::dispatch((int) $newRun->id)->afterCommit();
                }

                $created = true;

                return $page->fresh(['sheet.batch', 'currentRevision']) ?? $page;
            }, 3);
        } catch (Throwable $exception) {
            if ($cameraReservation === null
                && ! $this->candidatePathIsReferenced((int) $pageReference->getKey(), $storedPath)) {
                $this->assetStore->delete($storedPath);
            }

            throw $exception;
        }

        if ($cameraReservation === null
            && ! $created
            && ! $this->candidatePathIsReferenced((int) $pageReference->getKey(), $storedPath)) {
            $this->assetStore->delete($storedPath);
        }

        return $result;
    }

    /**
     * @return array{
     *     sheet_id:int,
     *     page_no:int,
     *     source_page_id:int,
     *     source_revision:int,
     *     workbenchId:string,
     *     clientCaptureId:string,
     *     pageNo:int,
     *     captureRevision:int,
     *     instanceId:?int,
     *     sheetId:int,
     *     targetKey:string,
     *     redo_source_page_id:int,
     *     redo_source_page_revision:int,
     *     source_evidence_id:int
     * }|null
     */
    public function redoSource(OmrCaptureSheet $sheet, int $pageNo, User $actor): ?array
    {
        $lockedSheet = OmrCaptureSheet::query()
            ->with(['batch.gradingSession.examSession.academicCycle', 'batch.templateVersion', 'batch.bookletVersion'])
            ->find($sheet->id);
        if ($lockedSheet === null || $lockedSheet->batch === null) {
            return null;
        }

        $batch = $lockedSheet->batch;
        $this->assertActorScope($batch, $actor);
        $page = OmrCaptureSheetPage::query()
            ->with([
                'blankInstanceClaim.blankInstancePage.instance',
                'qrDisabledClaim.captureSet',
                'qrDisabledClaim.pageEvidence',
                'currentRevision',
            ])
            ->where('sheet_id', $lockedSheet->id)
            ->where('page_no', $pageNo)
            ->first();
        if ($page === null || ! $this->isEligible($lockedSheet, $page, $batch, $batch->gradingSession)) {
            return null;
        }

        $revision = (int) $page->current_revision;
        if (($page->current_revision_id === null && $revision !== 1)
            || ($page->current_revision_id !== null
                && ((int) ($page->currentRevision?->page_id ?? 0) !== (int) $page->getKey()
                    || (int) ($page->currentRevision?->revision ?? 0) !== $revision))) {
            return null;
        }

        $instanceId = $page->blankInstanceClaim?->blankInstancePage?->instance_id;
        $instanceId = is_numeric($instanceId) ? (int) $instanceId : null;
        $workbenchId = sprintf('production:%d:%d', (int) $batch->grading_session_id, (int) $batch->id);
        $clientCaptureId = (string) $lockedSheet->client_capture_id;
        $captureRevision = $revision + 1;
        $targetKey = json_encode([
            $workbenchId,
            $clientCaptureId,
            $pageNo,
            $captureRevision,
            $instanceId,
            (int) $lockedSheet->id,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'sheet_id' => (int) $lockedSheet->id,
            'page_no' => $pageNo,
            'source_page_id' => (int) $page->getKey(),
            'source_evidence_id' => (int) ($page->qrDisabledClaim?->evidence_id ?? $page->getKey()),
            'source_revision' => $revision,
            'workbenchId' => $workbenchId,
            'clientCaptureId' => $clientCaptureId,
            'pageNo' => $pageNo,
            'captureRevision' => $captureRevision,
            'instanceId' => $instanceId,
            'sheetId' => (int) $lockedSheet->id,
            'targetKey' => $targetKey,
            'redo_source_page_id' => (int) $page->getKey(),
            'redo_source_page_revision' => $revision,
        ];
    }

    private function isEligible(
        OmrCaptureSheet $sheet,
        OmrCaptureSheetPage $page,
        OmrCaptureBatch $batch,
        ?GradingSession $gradingSession,
    ): bool {
        $version = $batch->templateVersion;
        if ($batch->status !== OmrCaptureBatch::STATUS_OPEN
            || $gradingSession === null
            || $gradingSession->status === GradingSession::STATUS_FINALIZED
            || ! in_array($sheet->status, self::REPLACEABLE_SHEET_STATUSES, true)
            || $sheet->wasAbstained()
            || (int) $page->sheet_id !== (int) $sheet->getKey()
            || (int) $page->page_no < 1
            || (int) $page->page_no > max(1, (int) $sheet->expected_page_count)
            || ! in_array($page->status, self::REPLACEABLE_PAGE_STATUSES, true)
            || ! is_string($page->asset_path)
            || trim($page->asset_path) === ''
            || ! is_string($page->asset_hmac)
            || preg_match('/^[a-f0-9]{64}$/i', $page->asset_hmac) !== 1
            || (int) $page->current_revision < 1
            || $version === null
            || ScoreRecord::query()->where('capture_sheet_id', $sheet->id)->exists()) {
            return false;
        }

        if ($sheet->upload_claim_token !== null
            && $sheet->upload_claim_expires_at !== null
            && $sheet->upload_claim_expires_at->isFuture()) {
            return false;
        }

        $blankClaim = $page->blankInstanceClaim;
        $qrDisabledClaim = $page->qrDisabledClaim;
        if ($blankClaim !== null && $qrDisabledClaim !== null) {
            return false;
        }

        if ($qrDisabledClaim !== null) {
            $set = $qrDisabledClaim->captureSet;
            $evidence = $qrDisabledClaim->pageEvidence;

            return $version->qr_mode === OmrQrMode::Disabled
                && (int) $qrDisabledClaim->capture_sheet_page_id === (int) $page->getKey()
                && (int) $qrDisabledClaim->page_no === (int) $page->page_no
                && $set !== null
                && $set->status === OmrPendingCaptureSet::STATUS_READY
                && (int) $set->adopted_sheet_id === (int) $sheet->getKey()
                && (int) $set->production_batch_id === (int) $batch->getKey()
                && $set->diagnostic_run_id === null
                && (int) $set->page_count === (int) $sheet->expected_page_count
                && $evidence !== null
                && (int) $evidence->set_id === (int) $set->id
                && (int) $evidence->page_no === (int) $page->page_no
                && is_string($qrDisabledClaim->input_hash)
                && is_string($evidence->input_hash)
                && hash_equals($qrDisabledClaim->input_hash, $evidence->input_hash);
        }

        if ($blankClaim !== null) {
            $blankPage = $blankClaim->blankInstancePage;
            $instance = $blankPage?->instance;
            $cycle = $gradingSession->examSession?->academicCycle;

            return $version->qr_mode === OmrQrMode::Signed
                && $blankPage !== null
                && $instance !== null
                && $cycle !== null
                && (int) $blankClaim->capture_sheet_page_id === (int) $page->getKey()
                && (int) $blankPage->page_no === (int) $page->page_no
                && (int) $instance->template_version_id === (int) $batch->template_version_id
                && (int) $instance->institution_id === (int) $cycle->institution_id
                && (int) $instance->academic_cycle_id === (int) $cycle->id
                && $blankPage->status !== OmrBlankInstancePage::STATUS_VOID
                && ! in_array($instance->status, [OmrBlankInstance::STATUS_VOID, OmrBlankInstance::STATUS_SUPERSEDED], true);
        }

        $isCanonical = $version->authoring_manifest !== null || $version->blankInstances()->exists();

        return $version->qr_mode === OmrQrMode::Signed && ! $isCanonical;
    }

    private function assertActorScope(OmrCaptureBatch $batch, User $actor): void
    {
        $scope = $this->pendingCaptureAccess->actorScope($actor);
        $batch->loadMissing('gradingSession.examSession.academicCycle');
        $cycle = $batch->gradingSession?->examSession?->academicCycle;
        if ($cycle === null
            || (int) $cycle->id !== $scope['academic_cycle_id']
            || (int) $cycle->institution_id !== $scope['institution_id']) {
            throw new AuthorizationException('The OMR batch is outside the active staff institution and cycle scope.');
        }
    }

    private function assertReplacementPhysicalIdentity(
        OmrCaptureSheetPage $page,
        OmrCaptureBatch $batch,
        int $pageNo,
        string $imageBytes,
        string $mime,
    ): void {
        $version = $batch->templateVersion;
        if ($version === null || $version->qr_mode !== OmrQrMode::Signed) {
            return;
        }

        $isCanonical = $version->authoring_manifest !== null || $version->blankInstances()->exists();
        if (! $isCanonical) {
            return;
        }

        $claim = $page->blankInstanceClaim;
        $blankPage = $claim?->blankInstancePage;
        if ($claim === null || $blankPage === null) {
            throw new RuntimeException('omr_page_replacement_signed_claim_missing');
        }

        $observation = $this->qrReader->inspect($imageBytes, $mime);
        $payload = is_string($observation->payload) ? trim($observation->payload) : '';
        if ($payload === '') {
            return;
        }

        $replacementInstance = $this->registerBlankInstance->verifyPayload($payload, $version, $pageNo);
        if ((int) $replacementInstance->getKey() !== (int) $blankPage->instance_id) {
            throw new RuntimeException('omr_page_replacement_signed_identity_mismatch');
        }
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function assertCameraReservationTarget(
        OmrCameraFrameReservation $reservation,
        array $target,
        string $authorizationId,
        string $idempotencyKey,
        string $reason,
        int $sourcePageId,
        int $sourceRevision,
    ): void {
        $cameraTarget = is_array($reservation->operation->target_json)
            ? $reservation->operation->target_json
            : [];
        $operationPayload = is_array($reservation->operation->payload_json)
            ? $reservation->operation->payload_json
            : [];
        $operationIdempotencyKey = (string) ($operationPayload['idempotency_key'] ?? $operationPayload['idempotencyKey'] ?? '');
        if ((int) $reservation->operation->actor_id < 1
            || ! hash_equals((string) ($cameraTarget['targetKey'] ?? $cameraTarget['target_key'] ?? ''), (string) $target['targetKey'])
            || ! hash_equals((string) ($cameraTarget['redo_authorization_id'] ?? ''), $authorizationId)
            || ! hash_equals($operationIdempotencyKey, $idempotencyKey)
            || ! hash_equals((string) ($cameraTarget['redo_reason'] ?? ''), $reason)
            || (int) ($cameraTarget['redo_source_page_id'] ?? 0) !== $sourcePageId
            || (int) ($cameraTarget['redo_source_page_revision'] ?? 0) !== $sourceRevision
            || (int) ($cameraTarget['redo_source_evidence_id'] ?? 0) !== (int) $target['source_evidence_id']
            || (int) ($cameraTarget['pageNo'] ?? $cameraTarget['page_no'] ?? 0) !== (int) $target['pageNo']
            || (int) ($cameraTarget['sheetId'] ?? $cameraTarget['sheet_id'] ?? 0) !== (int) $target['sheetId']
            || (int) ($cameraTarget['captureRevision'] ?? $cameraTarget['capture_revision'] ?? 0) !== (int) $target['captureRevision']
            || ($cameraTarget['clientCaptureId'] ?? $cameraTarget['client_capture_id'] ?? null) !== $target['clientCaptureId']) {
            throw new RuntimeException('omr_camera_redo_target_stale');
        }
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function consumeRedoGrant(
        User $actor,
        OmrCaptureBatch $batch,
        OmrCaptureSheet $sheet,
        OmrCaptureSheetPage $page,
        array $target,
        string $authorizationId,
        string $idempotencyKey,
        string $reason,
        int $sourcePageId,
        int $sourceRevision,
        ?OmrCameraFrameReservation $cameraReservation,
    ): string {
        $workbenchId = (string) $target['workbenchId'];
        $workbenchKey = hash('sha256', $workbenchId);
        $grant = OmrCameraCommand::query()
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('workbench_key', $workbenchKey)
            ->where('kind', 'CAPTURE_TARGET_GRANT')
            ->where('domain_outcome', 'granted')
            ->where('target_json->redo_authorization_id', $authorizationId)
            ->lockForUpdate()
            ->first();
        if ($grant === null) {
            throw new RuntimeException('omr_redo_authorization_missing_or_consumed');
        }

        $grantedTarget = is_array($grant->target_json) ? $grant->target_json : [];
        $version = $batch->templateVersion;
        $booklet = $batch->bookletVersion;
        if ($version === null || $booklet === null) {
            throw new RuntimeException('omr_redo_material_pin_missing');
        }
        $materialIdentity = [
            'template_version_id' => (int) $version->id,
            'template_version' => (int) $version->version,
            'template_bundle_hash' => $version->native_bundle_hash,
            'template_json_hash' => $version->template_json_hash,
            'booklet_version_id' => (int) $booklet->id,
            'booklet_version' => (int) $booklet->version,
        ];
        $targetMatches = (string) ($grantedTarget['workbenchId'] ?? '') === $workbenchId
            && (string) ($grantedTarget['clientCaptureId'] ?? '') === (string) $sheet->client_capture_id
            && (int) ($grantedTarget['pageNo'] ?? $grantedTarget['page_no'] ?? 0) === (int) $page->page_no
            && (int) ($grantedTarget['captureRevision'] ?? $grantedTarget['capture_revision'] ?? 0) === (int) $target['captureRevision']
            && ($grantedTarget['instanceId'] ?? null) === $target['instanceId']
            && (int) ($grantedTarget['sheetId'] ?? $grantedTarget['sheet_id'] ?? 0) === (int) $sheet->getKey()
            && hash_equals((string) ($grantedTarget['targetKey'] ?? $grantedTarget['target_key'] ?? ''), (string) $target['targetKey'])
            && hash_equals((string) ($grantedTarget['redo_authorization_id'] ?? ''), $authorizationId)
            && hash_equals((string) ($grantedTarget['redo_reason'] ?? ''), $reason)
            && (int) ($grantedTarget['redo_source_page_id'] ?? 0) === $sourcePageId
            && (int) ($grantedTarget['redo_source_page_revision'] ?? 0) === $sourceRevision
            && (int) ($grantedTarget['redo_source_evidence_id'] ?? 0) === (int) $target['source_evidence_id']
            && (int) ($grantedTarget['batch_id'] ?? 0) === (int) $batch->id
            && (int) ($grantedTarget['session_id'] ?? 0) === (int) $batch->grading_session_id
            && (int) ($grantedTarget['template_version_id'] ?? 0) === (int) $version->id
            && (int) ($grantedTarget['booklet_version_id'] ?? 0) === (int) $booklet->id
            && ($grantedTarget['execution_kind'] ?? null) === 'production'
            && ($grantedTarget['registration_mode'] ?? null) === $version->registration_mode->value
            && ($grantedTarget['qr_mode'] ?? null) === $version->qr_mode->value
            && (int) ($grantedTarget['page_count'] ?? 0) === (int) $sheet->expected_page_count
            && ($grantedTarget['material_identity'] ?? null) === $materialIdentity;
        if (! $targetMatches) {
            throw new RuntimeException('omr_redo_authorization_stale');
        }

        $sourceOperationId = (string) ($grantedTarget['redo_operation_id'] ?? '');
        if (! Str::isUuid($sourceOperationId)) {
            throw new RuntimeException('omr_redo_source_operation_invalid');
        }
        $sourceOperation = OmrCameraCommand::query()
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('workbench_key', $workbenchKey)
            ->where('operation_id', $sourceOperationId)
            ->where('kind', 'CAPTURE_RECEIPT')
            ->where('domain_outcome', 'accepted')
            ->whereNull('domain_receipt_json->action')
            ->first();
        $sourceReceipt = is_array($sourceOperation?->domain_receipt_json)
            ? $sourceOperation->domain_receipt_json
            : [];
        $sourceTarget = is_array($sourceOperation?->target_json)
            ? $sourceOperation->target_json
            : [];
        $sourceReceiptTargetKey = (string) ($sourceReceipt['target_key'] ?? $sourceReceipt['targetKey'] ?? '');
        $sourceOperationTargetKey = (string) ($sourceTarget['targetKey'] ?? $sourceTarget['target_key'] ?? '');
        $grantedAcceptedTargetKey = (string) ($grantedTarget['redo_accepted_target_key']
            ?? $grantedTarget['acceptedTargetKey']
            ?? '');
        $qrDisabledClaim = $page->qrDisabledClaim;
        $sourcePageEvidenceId = $qrDisabledClaim === null
            ? (int) $page->getKey()
            : (int) $qrDisabledClaim->evidence_id;
        $sourceCaptureId = $qrDisabledClaim === null
            ? (int) $sheet->getKey()
            : (int) $qrDisabledClaim->set_id;
        $sourceCaptureClientId = $sourceTarget['clientCaptureId']
            ?? $sourceTarget['client_capture_id']
            ?? null;
        $receiptClientId = $sourceReceipt['client_capture_id'] ?? null;
        $isAdoptedReplacementReceipt = $qrDisabledClaim !== null
            && isset($sourceTarget['redo_authorization_id'])
            && (int) ($sourceTarget['captureRevision'] ?? $sourceTarget['capture_revision'] ?? 0) === $sourceRevision;
        $sourcePageEvidenceId = $isAdoptedReplacementReceipt
            ? (int) $page->getKey()
            : ($qrDisabledClaim === null ? (int) $page->getKey() : (int) $qrDisabledClaim->evidence_id);
        $sourceCaptureId = $isAdoptedReplacementReceipt || $qrDisabledClaim === null
            ? (int) $sheet->getKey()
            : (int) $qrDisabledClaim->set_id;
        $sourceIdentityMatches = $qrDisabledClaim === null || $isAdoptedReplacementReceipt
            ? $receiptClientId === (string) $sheet->client_capture_id
                && $sourceCaptureClientId === $sheet->client_capture_id
            : $qrDisabledClaim->captureSet !== null
                && (string) ($sourceTarget['pending_capture_id'] ?? '') === (string) $qrDisabledClaim->captureSet->public_id
                && is_string($receiptClientId)
                && is_string($sourceCaptureClientId)
                && $receiptClientId === $sourceCaptureClientId;
        if ($sourceOperation === null
            || (int) ($sourceReceipt['page_id'] ?? 0) !== $sourcePageEvidenceId
            || (int) ($sourceReceipt['capture_id'] ?? $sourceReceipt['sheet_id'] ?? 0) !== $sourceCaptureId
            || (int) ($sourceReceipt['page_no'] ?? 0) !== (int) $page->page_no
            || ! $sourceIdentityMatches
            || (int) ($sourceTarget['pageNo'] ?? $sourceTarget['page_no'] ?? 0) !== (int) $page->page_no
            || (($sourceTarget['sheetId'] ?? $sourceTarget['sheet_id'] ?? null) !== null
                && $qrDisabledClaim === null
                && (int) ($sourceTarget['sheetId'] ?? $sourceTarget['sheet_id']) !== (int) $sheet->getKey())
            || ($sourceReceiptTargetKey !== '' && $sourceOperationTargetKey !== ''
                && ! hash_equals($sourceReceiptTargetKey, $sourceOperationTargetKey))
            || ($grantedAcceptedTargetKey !== ''
                && ! hash_equals($grantedAcceptedTargetKey, $sourceOperationTargetKey))) {
            throw new RuntimeException('omr_redo_source_receipt_stale');
        }

        if ($cameraReservation !== null) {
            $this->assertCameraReservationTarget(
                $cameraReservation,
                $target,
                $authorizationId,
                $idempotencyKey,
                $reason,
                $sourcePageId,
                $sourceRevision,
            );
        }

        $operationId = $cameraReservation === null
            ? (string) $grant->operation_id
            : (string) $cameraReservation->operation->operation_id;
        $this->recordAuditEvent->handle('omr.capture_page_redo_authorized', [
            'batch_id' => (int) $batch->id,
            'sheet_id' => (int) $sheet->id,
            'source_page_id' => $sourcePageId,
            'source_evidence_id' => $sourcePageEvidenceId,
            'replacement_page_id' => (int) $page->id,
            'page_no' => (int) $page->page_no,
            'authorization_id' => $authorizationId,
            'target_key' => (string) $target['targetKey'],
            'reason' => $reason,
        ], (string) $actor->id);
        $grant->forceFill([
            'domain_outcome' => 'consumed',
            'domain_receipt_json' => [
                'status' => 'accepted',
                'action' => 'redo_last',
                'operation_id' => $operationId,
                'accepted_operation_id' => $sourceOperationId,
                'target_key' => (string) $target['targetKey'],
                'page_no' => (int) $page->page_no,
                'source_page_id' => $sourcePageId,
                'source_evidence_id' => $sourcePageEvidenceId,
                'capture_id' => (int) $sheet->id,
                'page_id' => (int) $page->id,
                'revision' => $sourceRevision + 1,
                'replacement_reason' => $reason,
            ],
            'completed_at' => now(),
        ])->saveOrFail();

        return $operationId;
    }

    /**
     * @param  array<string, mixed>|null  $clientQuad
     */
    private function fingerprint(
        int $actorId,
        int $sheetId,
        int $pageNo,
        int $sourcePageId,
        int $sourceRevision,
        string $reason,
        string $redoAuthorizationId,
        string $idempotencyKey,
        string $inputHash,
        string $mime,
        ?array $clientQuad,
        string $cropPolicy,
        ?string $operationId,
        ?string $targetKey,
    ): string {
        return hash('sha256', json_encode([
            'actor_id' => $actorId,
            'sheet_id' => $sheetId,
            'page_no' => $pageNo,
            'source_page_id' => $sourcePageId,
            'source_revision' => $sourceRevision,
            'reason' => $reason,
            'redo_authorization_id' => $redoAuthorizationId,
            'idempotency_key' => $idempotencyKey,
            'input_hash' => $inputHash,
            'mime' => $mime,
            'client_quad' => $clientQuad,
            'crop_policy' => $cropPolicy,
            'operation_id' => $operationId,
            'target_key' => $targetKey,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function findReplay(
        OmrCaptureSheetPage $page,
        string $idempotencyKey,
        string $fingerprint,
        int $actorId,
        int $expectedSourcePageId,
        int $expectedRevision,
    ): ?OmrCaptureSheetPage {
        $revision = OmrCaptureSheetPageRevision::query()
            ->where('page_id', $page->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($revision === null) {
            return null;
        }

        if ((int) $revision->actor_user_id !== $actorId
            || (int) $revision->page_id !== $expectedSourcePageId
            || (int) $revision->revision !== $expectedRevision + 1
            || ! is_string($revision->payload_fingerprint)
            || ! hash_equals($revision->payload_fingerprint, $fingerprint)) {
            throw new RuntimeException('omr_page_replacement_idempotency_conflict');
        }

        return $page->fresh(['sheet.batch', 'currentRevision']) ?? $page;
    }

    private function candidatePathIsReferenced(int $pageId, string $path): bool
    {
        try {
            return OmrCaptureSheetPage::query()
                ->whereKey($pageId)
                ->where(function ($query) use ($path): void {
                    $query->where('asset_path', $path)->orWhere('aligned_asset_path', $path);
                })
                ->exists()
                || OmrCaptureSheetPageRevision::query()
                    ->where('page_id', $pageId)
                    ->where(function ($query) use ($path): void {
                        $query->where('asset_path', $path)->orWhere('aligned_asset_path', $path);
                    })
                    ->exists();
        } catch (Throwable) {
            return true;
        }
    }
}
