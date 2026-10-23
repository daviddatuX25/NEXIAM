<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\Camera\OmrCameraFrameReservation;
use App\Assessment\Omr\Canonical\OmrBlankPrintContract;
use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;
use App\Assessment\Omr\Operational\OmrDiagnosticRunStatusSnapshot;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CaptureOmrPendingPage
{
    public function __construct(
        private readonly OmrPendingCaptureAccess $access,
        private readonly OmrDiagnosticRunStatusSnapshot $diagnosticSnapshot,
        private readonly AnalyzeOmrPendingPage $analyze,
        private readonly SensitiveAssetStore $assets,
        private readonly RecordAuditEvent $audit,
        private readonly OmrCameraFrameFinalization $cameraFrameFinalization,
    ) {}

    public function handle(
        OmrPendingCaptureSet $set,
        User $actor,
        int $expectedRevision,
        int $pageNo,
        string $clientCaptureId,
        string $bytes,
        string $mime,
        ?string $replacementReason = null,
        ?array $clientQuad = null,
        string $cropPolicy = 'auto_document_quad',
        ?OmrCameraFrameReservation $cameraReservation = null,
        ?int $expectedSupersedesId = null,
    ): OmrPendingCaptureSet {
        if ($expectedSupersedesId !== null && $cameraReservation === null) {
            throw new RuntimeException('omr_redo_camera_reservation_required');
        }
        $mime = strtolower(trim($mime));
        $replacementReason = $replacementReason === null ? null : trim($replacementReason);
        if (! in_array($cropPolicy, ['auto_document_quad', 'keep_original'], true)) {
            throw new RuntimeException('omr_capture_set_crop_policy_invalid');
        }
        if ($cropPolicy === 'keep_original'
            && $set->registration_mode !== OmrRegistrationMode::DocumentBound->value) {
            throw new RuntimeException('omr_capture_set_crop_policy_not_allowed');
        }
        if (! Str::isUuid($clientCaptureId) || $bytes === '' || strlen($bytes) > 25 * 1024 * 1024
            || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || ($replacementReason !== null && (strlen($replacementReason) > 500 || $replacementReason === ''))) {
            throw new RuntimeException('omr_capture_set_input_invalid');
        }

        try {
            $sourceImage = OmrSourceImage::fromBytes($bytes);
        } catch (RuntimeException) {
            throw new RuntimeException('omr_capture_set_input_invalid');
        }
        $hash = $sourceImage->inputHash;
        if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $mime) {
            throw new RuntimeException('omr_capture_set_input_invalid');
        }
        $validatedClientQuad = null;
        if ($clientQuad !== null) {
            if ($set->registration_mode !== OmrRegistrationMode::DocumentBound->value) {
                throw new RuntimeException('omr_capture_set_client_quad_not_allowed');
            }
            try {
                $validatedClientQuad = OmrClientQuad::fromArray($clientQuad);
                if (! $validatedClientQuad->isBoundTo($sourceImage, $pageNo)) {
                    throw new RuntimeException('omr_capture_set_client_quad_source_mismatch');
                }
            } catch (Throwable $exception) {
                throw new RuntimeException('omr_capture_set_client_quad_invalid', previous: $exception);
            }
        }
        $clientQuadJson = $validatedClientQuad?->toArray();
        $storedPath = null;
        $plannedAssetPath = $cameraReservation === null
            ? null
            : 'omr/pending-pages/camera-'.(int) $cameraReservation->operation->id.'.bin';
        if ($cameraReservation !== null && $plannedAssetPath !== null) {
            $this->cameraFrameFinalization->recordAssetIntent(
                $cameraReservation,
                $plannedAssetPath,
                $this->assets->hmac($bytes),
            );
        }
        $isReplay = false;
        try {
            $captured = DB::transaction(function () use ($set, $actor, $expectedRevision, $pageNo, $clientCaptureId, $bytes, $mime, $replacementReason, $hash, $clientQuadJson, $cropPolicy, $cameraReservation, $expectedSupersedesId, $plannedAssetPath, &$storedPath, &$isReplay): OmrPendingCaptureSet {
                $this->assertContext($set, $actor);
                $locked = OmrPendingCaptureSet::query()->lockForUpdate()->findOrFail($set->id);
                if ($cropPolicy === 'keep_original'
                    && $locked->registration_mode !== OmrRegistrationMode::DocumentBound->value) {
                    throw new RuntimeException('omr_capture_set_crop_policy_not_allowed');
                }
                if ($clientQuadJson !== null
                    && $locked->registration_mode !== OmrRegistrationMode::DocumentBound->value) {
                    throw new RuntimeException('omr_capture_set_client_quad_not_allowed');
                }
                if ($locked->isExpired()) {
                    throw new RuntimeException('omr_capture_set_expired');
                }
                if ($pageNo < 1 || $pageNo > $locked->page_count) {
                    throw new RuntimeException('omr_capture_set_page_target_mismatch');
                }

                $replay = OmrPendingCapturePageEvidence::query()
                    ->where('set_id', $locked->id)
                    ->where('client_capture_id', $clientCaptureId)
                    ->first();
                if ($replay !== null) {
                    $replayQuadJson = null;
                    if ($replay->client_quad_json !== null) {
                        try {
                            $replayQuadJson = OmrClientQuad::fromArray($replay->client_quad_json)->toArray();
                        } catch (Throwable) {
                            throw new RuntimeException('omr_capture_set_idempotency_conflict');
                        }
                    }
                    if ($replay->page_no !== $pageNo || ! hash_equals($replay->input_hash, $hash)
                        || $replay->mime_type !== $mime || $replay->replacement_reason !== $replacementReason
                        || $replay->crop_policy !== $cropPolicy || $replayQuadJson !== $clientQuadJson
                        || ($expectedSupersedesId !== null && (int) $replay->supersedes_id !== $expectedSupersedesId)) {
                        throw new RuntimeException('omr_capture_set_idempotency_conflict');
                    }

                    $isReplay = true;
                    if ($cameraReservation !== null && $locked->production_batch_id !== null) {
                        $this->audit->handle('omr.capture_page_camera_replay_accepted', [
                            'capture_set_id' => (int) $locked->id,
                            'evidence_id' => (int) $replay->id,
                            'page_no' => $pageNo,
                            'input_hash' => $hash,
                        ], (string) $actor->id);
                        $this->cameraFrameFinalization->commitAccepted($cameraReservation, [
                            'operation_id' => (string) $cameraReservation->operation->operation_id,
                            'target_key' => (string) ($cameraReservation->operation->target_json['targetKey'] ?? $cameraReservation->operation->target_json['target_key'] ?? ''),
                            'status' => 'accepted',
                            'capture_id' => (int) $locked->id,
                            'page_id' => (int) $replay->id,
                            'page_no' => $pageNo,
                            'client_capture_id' => $clientCaptureId,
                            'revision' => (int) $locked->revision,
                        ]);
                    }

                    return $locked;
                }
                if ($expectedRevision !== $locked->revision) {
                    throw new RuntimeException('omr_capture_set_revision_stale');
                }
                if ($locked->adopted_sheet_id !== null) {
                    throw new RuntimeException('omr_capture_set_already_adopted');
                }
                if (! in_array($locked->status, [OmrPendingCaptureSet::STATUS_PENDING, OmrPendingCaptureSet::STATUS_CAPTURED, OmrPendingCaptureSet::STATUS_READY], true)) {
                    throw new RuntimeException('omr_capture_set_context_closed');
                }

                $selected = OmrPendingCapturePageEvidence::query()
                    ->where('set_id', $locked->id)
                    ->where('page_no', $pageNo)
                    ->orderByDesc('id')
                    ->first();
                if ($expectedSupersedesId !== null
                    && ($selected === null || (int) $selected->id !== $expectedSupersedesId)) {
                    throw new RuntimeException('omr_capture_set_page_superseded');
                }
                if ($selected === null && ($replacementReason !== null || $locked->status !== OmrPendingCaptureSet::STATUS_PENDING
                    || $pageNo !== $locked->active_page_no)) {
                    throw new RuntimeException('omr_capture_set_page_target_mismatch');
                }
                if ($selected !== null && $replacementReason === null) {
                    throw new RuntimeException('omr_capture_set_replacement_reason_required');
                }

                $assetPath = $plannedAssetPath ?? 'omr/pending-pages/'.Str::uuid().'.bin';
                $storedPath = $this->assets->store($bytes, $assetPath);
                if (! Storage::disk('sensitive')->exists($storedPath)) {
                    throw new RuntimeException('omr_capture_set_asset_store_failed');
                }
                $evidence = OmrPendingCapturePageEvidence::query()->create([
                    'set_id' => $locked->id,
                    'page_no' => $pageNo,
                    'client_capture_id' => $clientCaptureId,
                    'input_hash' => $hash,
                    'asset_path' => $storedPath,
                    'mime_type' => $mime,
                    'byte_count' => strlen($bytes),
                    'captured_by_user_id' => $actor->id,
                    'replacement_reason' => $replacementReason,
                    'supersedes_id' => $selected?->id,
                    'client_quad_json' => $clientQuadJson,
                    'crop_policy' => $cropPolicy,
                ]);
                if ($selected === null) {
                    if ($pageNo === $locked->page_count) {
                        $locked->status = OmrPendingCaptureSet::STATUS_CAPTURED;
                    } else {
                        $locked->active_page_no = $pageNo + 1;
                    }
                }
                if ($locked->status === OmrPendingCaptureSet::STATUS_READY) {
                    $locked->status = OmrPendingCaptureSet::STATUS_CAPTURED;
                }
                $locked->pairing_code = null;
                $locked->resolution_code = null;
                $locked->revision++;
                $locked->save();
                $this->audit->handle($locked->production_batch_id === null
                    ? 'omr.diagnostic.capture_page_recorded'
                    : 'omr.production.capture_page_recorded', [
                        'capture_set_id' => $locked->id,
                        'evidence_id' => $evidence->id,
                        'page_no' => $pageNo,
                        'supersedes_id' => $selected?->id,
                        'replacement_reason' => $replacementReason,
                        'input_hash' => $hash,
                        'client_quad_attached' => $clientQuadJson !== null,
                    ], (string) $actor->id);
                if ($expectedSupersedesId !== null && $cameraReservation !== null) {
                    $this->consumeRedoGrant(
                        $locked,
                        $actor,
                        $cameraReservation,
                        $expectedSupersedesId,
                        $pageNo,
                        (int) $evidence->id,
                        (string) $replacementReason,
                    );
                }
                if ($cameraReservation !== null && $locked->production_batch_id !== null) {
                    $this->cameraFrameFinalization->commitAccepted($cameraReservation, [
                        'operation_id' => (string) $cameraReservation->operation->operation_id,
                        'target_key' => (string) ($cameraReservation->operation->target_json['targetKey'] ?? $cameraReservation->operation->target_json['target_key'] ?? ''),
                        'status' => 'accepted',
                        'capture_id' => (int) $locked->id,
                        'page_id' => (int) $evidence->id,
                        'pending_capture_id' => (string) $locked->public_id,
                        'page_no' => $pageNo,
                        'client_capture_id' => $clientCaptureId,
                        'revision' => (int) $locked->revision,
                    ]);
                }

                return $locked;
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null && $cameraReservation === null) {
                $this->assets->delete($storedPath);
            }
            throw $exception;
        }

        return $isReplay ? $captured : $this->analyze->handle($captured, $actor, $clientCaptureId);
    }

    private function consumeRedoGrant(
        OmrPendingCaptureSet $set,
        User $actor,
        OmrCameraFrameReservation $reservation,
        int $expectedSourcePageId,
        int $pageNo,
        int $replacementPageEvidenceId,
        string $reason,
    ): void {
        $captureTarget = (array) $reservation->operation->target_json;
        $authorizationId = $captureTarget['redo_authorization_id'] ?? null;
        $targetKey = (string) ($captureTarget['targetKey'] ?? $captureTarget['target_key'] ?? '');
        $workbenchKey = (string) $reservation->operation->workbench_key;
        if (! is_string($authorizationId) || $targetKey === '') {
            throw new RuntimeException('omr_redo_authorization_missing');
        }
        $grant = OmrCameraCommand::query()
            ->where('actor_id', $actor->id)
            ->where('workbench_key', $workbenchKey)
            ->where('kind', 'CAPTURE_TARGET_GRANT')
            ->where('domain_outcome', 'granted')
            ->where('target_json->redo_authorization_id', $authorizationId)
            ->lockForUpdate()
            ->first();
        if ($grant === null) {
            throw new RuntimeException('omr_redo_authorization_consumed');
        }
        $grantedTarget = is_array($grant->target_json) ? $grant->target_json : [];
        if (($grantedTarget['targetKey'] ?? $grantedTarget['target_key'] ?? null) !== $targetKey
            || (int) ($grantedTarget['redo_source_page_id'] ?? 0) !== $expectedSourcePageId
            || (int) ($grantedTarget['pageNo'] ?? $grantedTarget['page_no'] ?? 0) !== $pageNo
            || ($grantedTarget['pending_capture_id'] ?? null) !== (string) $set->public_id
            || (int) ($grantedTarget['pending_capture_revision'] ?? 0) !== ((int) $set->revision - 1)
            || ($grantedTarget['redo_reason'] ?? null) !== $reason) {
            throw new RuntimeException('omr_redo_authorization_stale');
        }
        $this->audit->handle('omr.capture_page_redo_authorized', [
            'capture_set_id' => (int) $set->id,
            'source_page_evidence_id' => $expectedSourcePageId,
            'replacement_page_evidence_id' => $replacementPageEvidenceId,
            'authorization_id' => $authorizationId,
            'target_key' => $targetKey,
            'reason' => $reason,
        ], (string) $actor->id);
        $grant->forceFill([
            'domain_outcome' => 'consumed',
            'domain_receipt_json' => [
                'status' => 'accepted',
                'action' => 'redo_last',
                'operation_id' => (string) $reservation->operation->operation_id,
                'accepted_operation_id' => (string) ($grantedTarget['redo_operation_id'] ?? ''),
                'target_key' => $targetKey,
                'page_no' => $pageNo,
                'source_page_id' => $expectedSourcePageId,
                'page_id' => $replacementPageEvidenceId,
                'revision' => (int) $set->revision,
                'replacement_reason' => $reason,
            ],
            'completed_at' => now(),
        ])->saveOrFail();
    }

    private function assertContext(OmrPendingCaptureSet $set, User $actor): void
    {
        $scope = $this->access->actorScope($actor);
        if ((int) $set->created_by_user_id !== (int) $actor->id
            || (int) $set->institution_id !== $scope['institution_id']
            || (int) $set->academic_cycle_id !== $scope['academic_cycle_id']) {
            throw new RuntimeException('omr_capture_set_scope_mismatch');
        }
        $version = OmrAnswerSheetTemplateVersion::query()->findOrFail($set->template_version_id);
        $contract = OmrBlankPrintContract::fromVersion($version);
        if ($contract->qrMode !== OmrQrMode::Disabled
            || $contract->registrationMode->value !== $set->registration_mode
            || $contract->compiledPages !== $set->expected_pages_json
            || ! hash_equals($contract->nativeBundleHash, $set->native_bundle_hash)
            || ! hash_equals($contract->artifactHash, $set->artifact_hash)) {
            throw new RuntimeException('omr_capture_set_material_mismatch');
        }
        if ($set->production_batch_id !== null) {
            $batch = OmrCaptureBatch::query()->lockForUpdate()->findOrFail($set->production_batch_id);
            $session = GradingSession::query()->findOrFail($batch->grading_session_id);
            if ($batch->status !== OmrCaptureBatch::STATUS_OPEN
                || $session->status === GradingSession::STATUS_FINALIZED
                || (int) $batch->template_version_id !== (int) $set->template_version_id) {
                throw new RuntimeException('omr_capture_set_context_closed');
            }
        } else {
            $run = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($set->diagnostic_run_id);
            $this->diagnosticSnapshot->assertCanProject($run, $actor);
            if ($run->status !== OmrDiagnosticRun::STATUS_OPEN
                || $run->phase !== OmrDiagnosticRun::PHASE_CAPTURE
                || (int) $run->template_version_id !== (int) $set->template_version_id) {
                throw new RuntimeException('omr_capture_set_context_closed');
            }
        }
    }
}
