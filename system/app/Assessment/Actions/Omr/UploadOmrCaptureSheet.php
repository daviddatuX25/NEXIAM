<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\Camera\OmrCameraFrameReservation;
use App\Assessment\Actions\Omr\Canonical\RegisterOmrBlankInstanceCapture;
use App\Assessment\Actions\Omr\Canonical\ResolveOmrBlankInstanceManually;
use App\Assessment\Omr\Canonical\OmrBlankInstanceException;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\Canonical\OmrQrReader;
use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;
use App\Assessment\Omr\Queries\OmrRunStatusSnapshot;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\AnalyzeOmrCaptureSheetJob;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function hash;
use function in_array;
use function str_starts_with;
use function strlen;
use function trim;

/**
 * Page-aware OMR upload coordinator.
 *
 * The first page creates the logical sheet; later pages attach by
 * (batch, client_capture_id) in any arrival order. Analysis dispatches only
 * after every page of the set has landed. Single-page bundles keep the exact
 * legacy behavior (asset mirrored on the sheet row).
 */
final class UploadOmrCaptureSheet
{
    public function __construct(
        private readonly SensitiveAssetStore $assetStore,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly RegisterOmrBlankInstanceCapture $registerBlankInstance,
        private readonly ResolveOmrBlankInstanceManually $resolveManually,
        private readonly OmrBlankInstanceService $instances,
        private readonly OmrQrReader $qrReader,
        private readonly OmrPendingCaptureAccess $pendingCaptureAccess,
        private readonly OmrCameraFrameFinalization $cameraFrameFinalization,
    ) {}

    public function handle(
        OmrCaptureBatch $batch,
        string $imageBytes,
        string $mime,
        string $captureMode,
        string $clientCaptureId,
        User $actor,
        ?int $admissionAttemptId = null,
        int $pageNo = 1,
        ?string $qrPayload = null,
        ?string $qrState = null,
        ?string $displayCode = null,
        bool $manualConfirmed = false,
        ?string $observedSetCode = null,
        ?int $expectedRevision = null,
        ?array $clientQuad = null,
        ?string $cropPolicy = 'auto_document_quad',
        ?OmrCameraFrameReservation $cameraReservation = null,
    ): OmrCaptureSheet {
        $cropPolicy = $cropPolicy ?? 'auto_document_quad';
        if (! in_array($cropPolicy, ['auto_document_quad', 'keep_original'], true)) {
            throw new RuntimeException('Unsupported OMR crop policy.');
        }
        if (! in_array($captureMode, [OmrCaptureSheet::MODE_MANUAL, OmrCaptureSheet::MODE_AUTO], true)) {
            throw new RuntimeException('Unsupported OMR capture mode.');
        }

        // Determine if canonical authoring batch before opening the DB transaction.
        // Extraction is performed ahead of the transaction so that no DB lock is held across HTTP.
        $templateVersion = $batch->templateVersion
            ?? OmrAnswerSheetTemplateVersion::query()->find($batch->template_version_id);
        $isCanonical = $templateVersion !== null
            && ($templateVersion->authoring_manifest !== null || $templateVersion->blankInstances()->exists());
        $registrationMode = $templateVersion?->registration_mode;
        if ($templateVersion === null || ! $registrationMode instanceof OmrRegistrationMode) {
            throw new RuntimeException('The capture batch has no explicit immutable registration mode.');
        }
        if ($cropPolicy === 'keep_original' && $registrationMode !== OmrRegistrationMode::DocumentBound) {
            throw new RuntimeException('Keeping the original image is supported only for document-bound capture.');
        }
        $validatedClientQuad = null;
        if ($clientQuad !== null) {
            if ($registrationMode !== OmrRegistrationMode::DocumentBound) {
                throw new RuntimeException('Client corners are accepted only for document-bound capture.');
            }
            try {
                $validatedClientQuad = OmrClientQuad::fromArray($clientQuad);
                if (! $validatedClientQuad->isBoundTo(OmrSourceImage::fromBytes($imageBytes), $pageNo)) {
                    throw new RuntimeException('The advisory corners do not match this image and page.');
                }
            } catch (Throwable $exception) {
                throw new RuntimeException('The advisory corners are invalid or stale.', previous: $exception);
            }
        }

        $extractedQr = $isCanonical
            ? $this->qrReader->inspect($imageBytes, $mime)->payload
            : null;

        $storedPath = null;
        $plannedAssetPath = $cameraReservation === null
            ? null
            : 'omr/camera-originals/receipt-'.(string) $cameraReservation->operation->id.'.bin';
        if ($cameraReservation !== null && $plannedAssetPath !== null) {
            $this->cameraFrameFinalization->recordAssetIntent(
                $cameraReservation,
                $plannedAssetPath,
                $this->assetStore->hmac($imageBytes),
            );
        }
        try {
            return DB::transaction(function () use (
                $batch,
                $imageBytes,
                $mime,
                $captureMode,
                $clientCaptureId,
                $actor,
                $pageNo,
                $extractedQr,
                $qrState,
                $displayCode,
                $manualConfirmed,
                $observedSetCode,
                $validatedClientQuad,
                $cropPolicy,
                $expectedRevision,
                $registrationMode,
                $cameraReservation,
                $plannedAssetPath,
            ): OmrCaptureSheet {
                $batch = OmrCaptureBatch::query()
                    ->with(['templateVersion', 'bookletVersion', 'gradingSession.examSession'])
                    ->lockForUpdate()
                    ->findOrFail($batch->id);
                $this->assertExpectedRevision($batch, $expectedRevision);

                if ($batch->status !== OmrCaptureBatch::STATUS_OPEN) {
                    throw new RuntimeException('Sheets can only be uploaded while the OMR batch is open.');
                }

                if ($batch->templateVersion === null || $batch->bookletVersion === null) {
                    throw new RuntimeException('The OMR batch is missing its immutable template or booklet pin.');
                }

                if ($batch->templateVersion->qr_mode === OmrQrMode::Disabled) {
                    throw new OmrBlankInstanceException(
                        'qr_disabled_capture_requires_pending_set',
                        'QR-disabled paper cannot be claimed through the signed-QR upload path; a route-bound pending capture set is required.',
                    );
                }

                $pageCount = (int) $batch->templateVersion->pageCount();
                if ($pageNo < 1 || $pageNo > min(4, $pageCount)) {
                    throw new RuntimeException("Page {$pageNo} is outside this sheet's supported page count.");
                }
                $this->assertUploadScope($batch, $actor);
                if ($batch->templateVersion->registration_mode !== $registrationMode) {
                    throw new RuntimeException('The capture registration mode changed before the upload was committed.');
                }

                $sheet = OmrCaptureSheet::query()
                    ->where('batch_id', $batch->id)
                    ->where('client_capture_id', $clientCaptureId)
                    ->lockForUpdate()
                    ->first();

                $existingPage = $sheet?->pages()->where('page_no', $pageNo)->first();
                if ($existingPage !== null && $existingPage->asset_path !== null) {
                    if ($existingPage->crop_policy !== $cropPolicy) {
                        throw new RuntimeException('omr_capture_idempotency_conflict');
                    }
                    throw new RuntimeException("Page {$pageNo} of this sheet set was already uploaded.");
                }

                // Canonical blank-instance identity validation runs BEFORE asset storage.
                // A tampered or unresolvable sheet fails closed with no orphaned asset file.
                $blankInstance = $this->resolveBlankInstanceIdentity(
                    $batch,
                    $actor,
                    $pageNo,
                    $extractedQr,
                    $qrState,
                    $displayCode,
                    $manualConfirmed,
                );
                $this->assertBlankInstanceScope($blankInstance, $batch, $pageNo);

                $assetPath = $plannedAssetPath ?? 'omr/assets/'.Str::uuid()->toString().'.bin';
                $storedPath = $this->assetStore->store($imageBytes, $assetPath);
                $path = $storedPath;

                $pageFields = [
                    'sheet_id' => $sheet->id ?? null,
                    'page_no' => $pageNo,
                    'status' => OmrCaptureSheetPage::STATUS_QUEUED,
                    'asset_path' => $path,
                    'asset_hmac' => $this->assetStore->hmac($imageBytes),
                    'asset_hmac_key_id' => (string) config('omr.keyring.active_key_id'),
                    'asset_format_version' => 'v1',
                    'asset_key_id' => (string) config('omr.keyring.active_key_id'),
                    'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                    'original_mime' => $mime,
                    'original_bytes' => strlen($imageBytes),
                    'crop_policy' => $cropPolicy,
                    'client_quad_json' => $validatedClientQuad?->toArray(),
                ];

                if ($sheet === null) {
                    $sheetFields = [
                        'batch_id' => $batch->id,
                        'admission_attempt_id' => null,
                        'booklet_set_id' => null,
                        'set_resolution_reason' => null,
                        'observed_set_code' => $observedSetCode === null ? null : strtoupper(trim($observedSetCode)),
                        'status' => $pageCount === 1
                            ? OmrCaptureSheet::STATUS_QUEUED
                            : OmrCaptureSheet::STATUS_PENDING_PAGES,
                        'capture_mode' => $captureMode,
                        'version' => 0,
                        'expected_page_count' => $pageCount,
                        'client_capture_id' => $clientCaptureId,
                        'captured_by_user_id' => $actor->id,
                        'captured_at' => now(),
                    ];

                    // Single-page sheets must carry the asset mirror at insert
                    // time: `omr_capture_sheets_asset_check` requires
                    // `asset_path IS NOT NULL` unless `expected_page_count > 1`.
                    if ($pageCount === 1) {
                        $sheetFields += [
                            'asset_path' => $path,
                            'asset_hmac' => $pageFields['asset_hmac'],
                            'asset_hmac_key_id' => $pageFields['asset_hmac_key_id'],
                            'asset_format_version' => $pageFields['asset_format_version'],
                            'asset_key_id' => $pageFields['asset_key_id'],
                            'asset_state' => $pageFields['asset_state'],
                            'original_mime' => $mime,
                            'original_bytes' => strlen($imageBytes),
                        ];
                    }

                    $sheet = OmrCaptureSheet::query()->create($sheetFields);
                }

                if ($existingPage !== null) {
                    $existingPage->update($pageFields);
                    $page = $existingPage;
                } else {
                    $page = $sheet->pages()->create($pageFields);
                }

                if ($blankInstance !== null) {
                    $this->instances->registerCapture(
                        $blankInstance,
                        $pageNo,
                        $page,
                        $actor,
                        $batch->gradingSession?->exam_session_id === null
                            ? null
                            : (int) $batch->gradingSession->exam_session_id,
                    );
                    $claim = $page->blankInstanceClaim()->first();
                    $this->recordAuditEvent->handle('omr.blank_instance_capture_registered', [
                        'instance_id' => $blankInstance->id,
                        'page_no' => $pageNo,
                        'template_version_id' => $batch->template_version_id,
                        'qr_payload_hash' => hash('sha256', (string) ($extractedQr ?? '')),
                        'copy_policy' => $blankInstance->copy_policy,
                        'claim_kind' => $claim?->claim_kind,
                        'reproduction_authorization_id' => $claim?->reproduction_authorization_id,
                    ], (string) $actor->id);
                }

                // Single-page legacy mirror: keep the asset on the sheet row too.
                if ($pageCount === 1) {
                    $sheet->update([
                        'original_mime' => $mime,
                        'original_bytes' => strlen($imageBytes),
                        'asset_path' => $path,
                        'asset_hmac' => $pageFields['asset_hmac'],
                        'asset_hmac_key_id' => $pageFields['asset_hmac_key_id'],
                        'asset_format_version' => $pageFields['asset_format_version'],
                        'asset_key_id' => $pageFields['asset_key_id'],
                        'asset_state' => $pageFields['asset_state'],
                    ]);
                }

                $runIds = [];
                if ($sheet->pages()->count() >= $pageCount) {
                    $sheet->update(['status' => OmrCaptureSheet::STATUS_QUEUED]);

                    $driver = (string) config('omr.provider.driver', 'fake');
                    $providerName = $driver === 'fake'
                        ? (string) config('omr.provider.name', 'nexiam.fake')
                        : (string) $batch->templateVersion->engine_name;
                    $providerVersion = $driver === 'fake'
                        ? (string) config('omr.provider.version', '1')
                        : (string) ($batch->templateVersion->engine_commit ?: 'unknown');
                    $providerCommit = $driver === 'fake'
                        ? (config('omr.provider.commit') !== null ? (string) config('omr.provider.commit') : null)
                        : ($batch->templateVersion->engine_commit !== null ? (string) $batch->templateVersion->engine_commit : null);

                    foreach ($sheet->pages()->orderBy('page_no')->get() as $pageRow) {
                        $pageBytes = $pageRow->id === $page->id
                            ? $imageBytes
                            : $this->assetStore->read((string) $pageRow->asset_path, (string) $pageRow->asset_hmac);

                        $run = $sheet->processingRuns()->create([
                            'actor_id' => $actor->id,
                            'run_no' => ((int) $sheet->processingRuns()->max('run_no')) + 1,
                            'run_uuid' => Str::uuid()->toString(),
                            'status' => OmrProcessingRun::STATUS_PENDING,
                            'page_revision' => max(1, (int) $pageRow->current_revision),
                            'provider_name' => $providerName,
                            'provider_version' => $providerVersion,
                            'provider_commit' => $providerCommit,
                            'request_version' => OmrDetectionRequest::ENVELOPE_VERSION,
                            'expected_sheet_version' => (int) $sheet->version,
                            'input_hash' => hash('sha256', $pageBytes),
                            'template_hash' => (string) $batch->templateVersion->native_bundle_hash,
                            'booklet_hash' => (string) $batch->bookletVersion->answer_key_hash,
                            'native_bundle_hash' => (string) $batch->templateVersion->native_bundle_hash,
                            'manifest_hash' => (string) $batch->templateVersion->review_manifest_hash,
                            'config_hash' => (string) $batch->templateVersion->config_hash,
                            'patch_identity' => (string) $batch->templateVersion->patch_identity,
                            'attempts' => 0,
                        ]);

                        $pageRow->forceFill(['current_processing_run_id' => $run->id])->save();
                        if ($pageCount === 1) {
                            $sheet->forceFill(['current_processing_run_id' => $run->id])->save();
                        }
                        $runIds[] = (int) $run->id;
                    }
                }

                $this->recordAuditEvent->handle('grading.omr_sheet_received', [
                    'sheet_id' => $sheet->id,
                    'batch_id' => $batch->id,
                    'capture_mode' => $captureMode,
                    'client_capture_id' => $clientCaptureId,
                    'page_no' => $pageNo,
                    'client_quad_attached' => $validatedClientQuad !== null,
                    'page_count' => $pageCount,
                    'processing_run_ids' => $runIds,
                ], (string) $actor->id);
                if ($cameraReservation !== null) {
                    $this->cameraFrameFinalization->commitAccepted($cameraReservation, [
                        'operation_id' => (string) $cameraReservation->operation->operation_id,
                        'target_key' => (string) ($cameraReservation->operation->target_json['target_key'] ?? ''),
                        'status' => 'accepted',
                        'capture_id' => (int) $sheet->id,
                        'sheet_id' => (int) $sheet->id,
                        'page_id' => (int) $page->id,
                        'page_no' => $pageNo,
                        'client_capture_id' => $clientCaptureId,
                        'revision' => $expectedRevision,
                    ]);
                }

                foreach ($runIds as $runId) {
                    AnalyzeOmrCaptureSheetJob::dispatch($runId)->afterCommit();
                }

                return $sheet;
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null && $cameraReservation === null) {
                $this->assetStore->delete($storedPath);
            }

            throw $exception;
        }
    }

    private function assertExpectedRevision(OmrCaptureBatch $batch, ?int $expectedRevision): void
    {
        if ($expectedRevision === null) {
            return;
        }

        $revision = app(OmrRunStatusSnapshot::class)
            ->forBatch($batch->load(['sheets.pages', 'sheets.issues', 'sheets.processingRuns']))['revision'];

        if ($revision !== $expectedRevision) {
            throw new RuntimeException('The OMR run changed after validation. Refresh the current status before continuing.');
        }
    }

    private function assertUploadScope(OmrCaptureBatch $batch, User $actor): void
    {
        $actorScope = $this->pendingCaptureAccess->actorScope($actor);
        $session = $batch->gradingSession;
        $session?->loadMissing('examSession.academicCycle');
        $cycle = $session?->examSession?->academicCycle;

        if ($cycle === null
            || (int) $cycle->id !== $actorScope['academic_cycle_id']
            || (int) $cycle->institution_id !== $actorScope['institution_id']) {
            throw new RuntimeException('The OMR batch is outside the active staff institution and cycle scope.');
        }
    }

    private function assertBlankInstanceScope(?OmrBlankInstance $instance, OmrCaptureBatch $batch, int $pageNo): void
    {
        if ($instance === null) {
            return;
        }

        $cycle = $batch->gradingSession?->examSession?->academicCycle;
        if ($cycle === null
            || (int) $instance->template_version_id !== (int) $batch->template_version_id
            || (int) $instance->page_count < $pageNo
            || $instance->institution_id === null
            || $instance->academic_cycle_id === null
            || (int) $instance->institution_id !== (int) $cycle->institution_id
            || (int) $instance->academic_cycle_id !== (int) $cycle->id) {
            throw new OmrBlankInstanceException(
                'qr_scope_mismatch',
                'The issued blank-instance identity is outside the selected batch, institution, cycle, or page scope.',
            );
        }
    }

    /**
     * Canonical blank-instance gate (OpenSpec 35.31, AD-8) — runs BEFORE the
     * analysis request is constructed. A canonical (compiled) template batch
     * must resolve its signed blank-instance QR against the pinned template
     * version/page before recognition may start:
     *
     *  - readable QR: must be a BLANK-instance payload (`nexiam:blank:*`);
     *    a calibration-purpose QR or any other purpose is a hard rejection,
     *    and a present-but-invalid/mismatched signed payload is hard-rejected
     *    (manual entry can never override a bad signature).
     *  - absent/unreadable QR: audited manual recovery (display code +
     *    staff confirmation) must be recorded BEFORE recognition; otherwise
     *    recognition stays blocked with a typed error.
     *
     * Legacy (non-canonical) batches are untouched.
     */
    private function resolveBlankInstanceIdentity(
        OmrCaptureBatch $batch,
        User $actor,
        int $pageNo,
        ?string $qrPayload,
        ?string $qrState,
        ?string $displayCode,
        bool $manualConfirmed,
    ): ?OmrBlankInstance {
        if ($batch->templateVersion === null
            || ($batch->templateVersion->authoring_manifest === null && $batch->templateVersion->blankInstances()->doesntExist())) {
            return null; // legacy scan/solver batches with no blank instances: no canonical QR contract
        }
        $templateVersion = $batch->templateVersion;

        if ($qrPayload !== null && trim($qrPayload) !== '') {
            $payload = trim($qrPayload);
            if (str_starts_with($payload, 'nexiam:calibration:')) {
                throw new OmrBlankInstanceException('qr_purpose_mismatch', 'A calibration challenge QR cannot register an applicant blank-instance capture.');
            }
            if (! str_starts_with($payload, 'nexiam:blank:')) {
                throw new OmrBlankInstanceException('qr_signature_invalid', 'The blank-instance QR payload format is invalid.');
            }

            return $this->registerBlankInstance->verifyPayload($payload, $templateVersion, $pageNo);
        }

        if ($qrState === 'present_invalid') {
            // The resolver hard-fails: manual entry must never override a
            // present-but-invalid payload.
            $this->resolveManually->handle($actor, 'present_invalid', (string) $displayCode, $manualConfirmed);

            return null;
        }

        if (in_array($qrState, ['absent', 'undecodable'], true)
            && $displayCode !== null
            && trim($displayCode) !== ''
            && $manualConfirmed) {
            $resolved = $this->resolveManually->handle($actor, $qrState, $displayCode, true);
            if ((int) $resolved['instance']->template_version_id !== (int) $templateVersion->id) {
                throw new OmrBlankInstanceException('template_mismatch', 'The manually resolved blank instance belongs to another template version.');
            }
            if ((int) $resolved['instance']->page_count < $pageNo) {
                throw new OmrBlankInstanceException('page_missing_from_set', 'The manually resolved blank instance does not cover this page.');
            }

            return $resolved['instance'];
        }

        throw new OmrBlankInstanceException(
            'qr_undecodable',
            'The blank-instance QR is absent or unreadable; use the audited manual recovery (display code + confirmation) before recognition.',
        );
    }
}
