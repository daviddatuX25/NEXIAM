<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Camera;

use App\Assessment\Actions\Omr\Operational\RetakeOmrDiagnosticPage;
use App\Assessment\Actions\Omr\ReplaceOmrSheetPage;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\Operational\OmrExecutionKind;
use App\Assessment\Omr\Operational\OmrWorkbenchProjection;
use App\Assessment\Omr\Queries\OmrCameraCaptureTargetQuery;
use App\Assessment\Support\TestKitMockSession;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrQrDisabledPageClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class ExecuteOmrWorkbenchCaptureAction
{
    public function __construct(
        private readonly OmrCameraFrameFinalization $operations,
        private readonly OmrCameraCaptureTargetQuery $targets,
        private readonly OmrCameraProtocol $protocol,
        private readonly StartOmrPendingCaptureSet $startPendingSet,
        private readonly TestKitMockSession $mockSessions,
        private readonly RetakeOmrDiagnosticPage $retakeDiagnosticPage,
        private readonly ReplaceOmrSheetPage $replaceOmrSheetPage,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @param array<string, string> $urls @return array<string, array<string, string>> */
    public function projectAvailableActions(
        User $actor,
        string $workbenchId,
        OmrWorkbenchProjection $projection,
        array $urls,
    ): array {
        foreach ($projection->sheets as $capture) {
            if (in_array($capture['status'] ?? null, [
                OmrDiagnosticCapture::STATUS_RECEIVED,
                OmrDiagnosticCapture::STATUS_QUEUED,
                OmrDiagnosticCapture::STATUS_PROCESSING,
            ], true)) {
                return [];
            }
        }
        if ($this->operations->hasUnresolvedCaptureOperation($actor, $workbenchId)) {
            return [];
        }
        $latest = $this->operations->findLatestAcceptedOperationReceipt($actor, $workbenchId);
        if (($latest['status'] ?? null) !== 'accepted' || ! is_array($latest['receipt'] ?? null)) {
            return [];
        }

        $receipt = $latest['receipt'];
        $target = $this->canonicalTarget($receipt);
        $accepted = $this->acceptedReceipt((string) ($receipt['operation_id'] ?? ''), $receipt, $target);
        $authorizedTargets = $this->authorizedTransitionTargets(
            $actor,
            $workbenchId,
            $projection,
            $accepted,
        );

        return $this->targets->projectAvailableActions($projection, $actor, $accepted, $urls, $authorizedTargets);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function projectSelectedPageRetakeGrants(
        User $actor,
        OmrWorkbenchProjection $projection,
        string $url,
    ): array {
        if ($projection->executionKind !== OmrExecutionKind::Diagnostic
            || (int) ($projection->context['actor_id'] ?? 0) !== (int) $actor->getAuthIdentifier()) {
            return [];
        }
        foreach ($projection->sheets as $captureRow) {
            if (in_array($captureRow['status'] ?? null, [
                OmrDiagnosticCapture::STATUS_RECEIVED,
                OmrDiagnosticCapture::STATUS_QUEUED,
                OmrDiagnosticCapture::STATUS_PROCESSING,
            ], true)) {
                return [];
            }
        }
        $run = OmrDiagnosticRun::query()->find((int) ($projection->context['run_id'] ?? 0));
        if ($run === null || $run->isTerminal() || $run->sealed_at !== null
            || $this->operations->hasUnresolvedCaptureOperation($actor, $projection->workbenchId)) {
            return [];
        }

        $grants = [];
        foreach ($projection->sheets as $captureRow) {
            $captureId = (int) ($captureRow['id'] ?? 0);
            $capture = OmrDiagnosticCapture::query()
                ->whereKey($captureId)
                ->where('diagnostic_run_id', $run->id)
                ->first();
            $link = is_array($capture?->evidence_json)
                && is_array($capture->evidence_json['pending_page_link'] ?? null)
                ? $capture->evidence_json['pending_page_link']
                : null;
            $setId = (int) ($link['capture_set_id'] ?? 0);
            $pageId = (int) ($link['pending_page_evidence_id'] ?? 0);
            $pageNo = (int) ($link['page_no'] ?? 0);
            if ($capture === null
                || $capture->failure_code === 'discarded_by_operator'
                || in_array($capture->status, [OmrDiagnosticCapture::STATUS_QUEUED, OmrDiagnosticCapture::STATUS_PROCESSING], true)
                || $setId < 1 || $pageId < 1 || $pageNo < 1) {
                continue;
            }

            $set = OmrPendingCaptureSet::query()
                ->whereKey($setId)
                ->where('diagnostic_run_id', $run->id)
                ->where('created_by_user_id', $actor->id)
                ->whereNull('production_batch_id')
                ->whereNull('adopted_sheet_id')
                ->first();
            if ($set === null
                || $set->isExpired()
                || ! in_array($set->status, [
                    OmrPendingCaptureSet::STATUS_PENDING,
                    OmrPendingCaptureSet::STATUS_CAPTURED,
                    OmrPendingCaptureSet::STATUS_READY,
                ], true)) {
                continue;
            }

            $sourceEvidence = OmrPendingCapturePageEvidence::query()
                ->whereKey($pageId)
                ->where('set_id', $set->id)
                ->where('page_no', $pageNo)
                ->first();
            $latestEvidence = OmrPendingCapturePageEvidence::query()
                ->where('set_id', $set->id)
                ->where('page_no', $pageNo)
                ->orderByDesc('id')
                ->first();
            if ($sourceEvidence === null
                || (int) $latestEvidence?->id !== $pageId
                || ! hash_equals((string) ($link['input_hash'] ?? ''), (string) $sourceEvidence->input_hash)) {
                continue;
            }

            $accepted = $this->operations->findAcceptedOperationReceiptForCapture(
                $actor,
                $projection->workbenchId,
                $captureId,
            );
            $receipt = is_array($accepted['receipt'] ?? null) ? $accepted['receipt'] : null;
            if (($accepted['status'] ?? null) !== 'accepted'
                || $receipt === null
                || (int) ($receipt['diagnostic_capture_id'] ?? 0) !== $captureId
                || (int) ($receipt['page_id'] ?? 0) !== $pageId
                || (int) ($receipt['page_no'] ?? 0) !== $pageNo
                || (string) ($receipt['pending_capture_id'] ?? '') !== (string) $set->public_id) {
                continue;
            }

            $acceptedTarget = $this->canonicalTarget($receipt);
            if ((int) $acceptedTarget['pageNo'] !== $pageNo
                || (string) ($acceptedTarget['pending_capture_id'] ?? '') !== (string) $set->public_id) {
                continue;
            }

            $nextSetRevision = (int) $set->revision + 1;
            $authorizationId = hash('sha256', implode('|', [
                (string) $actor->getAuthIdentifier(),
                $projection->workbenchId,
                'capture_retake',
                (string) $projection->revision,
                (string) $captureId,
                (string) $capture->version,
                (string) $pageNo,
                (string) $pageId,
                (string) $set->public_id,
                (string) $set->revision,
                (string) ($receipt['operation_id'] ?? ''),
                (string) $acceptedTarget['targetKey'],
            ]));
            $clientCaptureId = $this->operationUuid($authorizationId);
            $target = $acceptedTarget;
            $target['clientCaptureId'] = $clientCaptureId;
            $target['pageNo'] = $pageNo;
            $target['captureRevision'] = $nextSetRevision;
            $target['targetKey'] = json_encode([
                $projection->workbenchId,
                $clientCaptureId,
                $pageNo,
                $nextSetRevision,
                $acceptedTarget['instanceId'],
                $acceptedTarget['sheetId'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $target['target_key'] = $target['targetKey'];
            $target['pending_capture_revision'] = $nextSetRevision;
            $target['run_revision'] = (int) $projection->revision + 1;
            $target['redo_authorization_id'] = $authorizationId;
            $target['redo_operation_id'] = (string) ($receipt['operation_id'] ?? '');
            $target['redo_source_page_id'] = $pageId;
            $target['redo_source_evidence_id'] = $pageId;
            $target['redo_source_page_revision'] = $nextSetRevision;
            $target['redo_accepted_target_key'] = $acceptedTarget['targetKey'];
            $target['replaces_capture_id'] = $captureId;

            $grants[$captureId] = [
                'url' => $url,
                'authorization_id' => $authorizationId,
                'capture_id' => $captureId,
                'expected_capture_version' => (int) $capture->version,
                'page_no' => $pageNo,
                'source_page_evidence_id' => $pageId,
                'pending_capture_id' => (string) $set->public_id,
                'pending_capture_revision' => (int) $set->revision,
                'expected_revision' => (int) $projection->revision,
                'target' => [
                    'workbench_id' => $projection->workbenchId,
                    'client_capture_id' => $clientCaptureId,
                    'page_no' => $pageNo,
                    'target_revision' => $nextSetRevision,
                    'instance_id' => $target['instanceId'],
                    'sheet_id' => $target['sheetId'],
                    'target_key' => $target['targetKey'],
                ],
            ];
        }

        return $grants;
    }

    public function hasUnresolvedCaptureOperation(User $actor, string $workbenchId): bool
    {
        return $this->operations->hasUnresolvedCaptureOperation($actor, $workbenchId);
    }

    public function persistDiscardRetakeTarget(
        User $actor,
        OmrDiagnosticRun $run,
        OmrPendingCaptureSet $set,
        OmrDiagnosticCapture $capture,
        OmrPendingCapturePageEvidence $sourceEvidence,
        string $reason,
    ): bool {
        $accepted = $this->operations->findAcceptedOperationReceiptForCapture(
            $actor,
            'diagnostic:'.$run->public_id,
            (int) $capture->id,
        );
        $receipt = is_array($accepted['receipt'] ?? null) ? $accepted['receipt'] : null;
        if (($accepted['status'] ?? null) !== 'accepted'
            || $receipt === null
            || (int) ($receipt['diagnostic_capture_id'] ?? 0) !== (int) $capture->id
            || (int) ($receipt['page_id'] ?? 0) !== (int) $sourceEvidence->id
            || (int) ($receipt['page_no'] ?? 0) !== (int) $sourceEvidence->page_no
            || (string) ($receipt['pending_capture_id'] ?? '') !== (string) $set->public_id) {
            return false;
        }

        $sourceTarget = $this->canonicalTarget($receipt);
        $authorizationId = hash('sha256', implode('|', [
            (string) $actor->getAuthIdentifier(),
            'diagnostic:'.$run->public_id,
            'discard_retake',
            (string) $run->revision,
            (string) $capture->id,
            (string) $capture->version,
            (string) $sourceEvidence->id,
            (string) $set->public_id,
            (string) $set->revision,
            (string) ($receipt['operation_id'] ?? ''),
            (string) $sourceTarget['targetKey'],
        ]));
        $target = $this->selectedRetakeTarget(
            $receipt,
            $run,
            $set,
            (int) $sourceEvidence->id,
            (int) $sourceEvidence->page_no,
            (int) $capture->id,
            $authorizationId,
            $reason,
        );
        $this->protocol->persistTargetGrant($actor, 'diagnostic:'.$run->public_id, $target);

        return true;
    }

    /**
     * Consume a server-projected diagnostic redo grant through the ordinary
     * pending-page and diagnostic evidence transaction.
     *
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>|null  $clientQuad
     * @return array<string, mixed>
     */
    public function captureDiagnosticRedo(
        User $actor,
        OmrWorkbenchProjection $projection,
        array $target,
        string $replacementReason,
        string $bytes,
        string $mime,
        string $cropPolicy,
        ?array $clientQuad,
        OmrCameraFrameReservation $reservation,
    ): array {
        $reason = trim($replacementReason);
        $targetJson = $reservation->operation->target_json;
        $sourcePageId = (int) ($target['redo_source_page_id'] ?? 0);
        $sourcePageRevision = (int) ($target['redo_source_page_revision'] ?? 0);
        $sourceOperationId = (string) ($target['redo_operation_id'] ?? '');
        $setPublicId = (string) ($target['pending_capture_id'] ?? '');
        if ($projection->executionKind !== OmrExecutionKind::Diagnostic
            || $sourcePageId < 1 || $sourcePageRevision < 1
            || $sourceOperationId === '' || $setPublicId === ''
            || $reason === '' || ! hash_equals((string) ($target['redo_reason'] ?? ''), $reason)
            || ! hash_equals((string) ($target['targetKey'] ?? ''), (string) ($targetJson['targetKey'] ?? ''))
            || ! hash_equals((string) ($target['redo_authorization_id'] ?? ''), (string) ($targetJson['redo_authorization_id'] ?? ''))
            || ! hash_equals($sourceOperationId, (string) ($targetJson['redo_operation_id'] ?? ''))
            || $sourcePageId !== (int) ($targetJson['redo_source_page_id'] ?? 0)
            || $sourcePageRevision !== (int) ($targetJson['redo_source_page_revision'] ?? 0)
            || ! hash_equals($reason, (string) ($targetJson['redo_reason'] ?? ''))) {
            throw new RuntimeException('omr_camera_capture_action_grant_stale');
        }
        $accepted = $this->operations->findAcceptedOperationReceipt($actor, $projection->workbenchId, $sourceOperationId);
        if (($accepted['status'] ?? null) !== 'accepted' || ! is_array($accepted['receipt'] ?? null)) {
            throw new RuntimeException('omr_camera_accepted_capture_missing');
        }
        $sourceReceipt = $accepted['receipt'];
        if ((int) ($sourceReceipt['page_id'] ?? 0) !== $sourcePageId
            || (string) ($sourceReceipt['pending_capture_id'] ?? '') !== $setPublicId
            || (int) ($sourceReceipt['page_no'] ?? $target['pageNo'] ?? 0) !== (int) ($target['pageNo'] ?? 0)
            || ! hash_equals((string) ($target['redo_accepted_target_key'] ?? ''), (string) ($sourceReceipt['targetKey'] ?? $sourceReceipt['target_key'] ?? ''))) {
            throw new RuntimeException('omr_camera_capture_action_grant_stale');
        }

        $run = OmrDiagnosticRun::query()
            ->whereKey((int) ($target['run_id'] ?? 0))
            ->where('public_id', substr($projection->workbenchId, strlen('diagnostic:')))
            ->where('created_by_user_id', (int) $actor->getAuthIdentifier())
            ->firstOrFail();
        $set = OmrPendingCaptureSet::query()
            ->where('public_id', $setPublicId)
            ->where('diagnostic_run_id', (int) $run->id)
            ->where('created_by_user_id', (int) $actor->getAuthIdentifier())
            ->whereNull('adopted_sheet_id')
            ->whereNull('production_batch_id')
            ->firstOrFail();
        $sourceEvidence = OmrPendingCapturePageEvidence::query()
            ->whereKey($sourcePageId)
            ->where('set_id', (int) $set->id)
            ->where('page_no', (int) $target['pageNo'])
            ->firstOrFail();

        return $this->retakeDiagnosticPage->handleRedo(
            $run,
            $set,
            $sourceEvidence,
            $actor,
            (int) $projection->revision,
            (int) ($target['pending_capture_revision'] ?? 0),
            (int) $target['pageNo'],
            (string) ($target['clientCaptureId'] ?? ''),
            $bytes,
            $mime,
            $reason,
            $clientQuad,
            $cropPolicy,
            $reservation,
        );
    }

    /**
     * @param  array<string, mixed>  $workbench
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $urls
     * @param  array<string, array<string, mixed>>  $authorizedTransitionTargets
     */
    public function handle(
        User $actor,
        string $workbenchId,
        array $workbench,
        OmrWorkbenchProjection $projection,
        array $payload,
        array $urls,
        array $authorizedTransitionTargets = [],
    ): OmrWorkbenchProjection {
        $action = (string) $payload['action'];
        $authorizationId = (string) $payload['authorization_id'];
        $actionOperationId = $this->operationUuid($authorizationId);
        if ($action === 'capture_retake') {
            $this->authorizeSelectedPageRetake($actor, $workbenchId, $projection, $payload, $urls);

            return $projection;
        }
        $replay = $this->operations->findTransitionForReplay($actor, $workbenchId, $actionOperationId, $authorizationId);
        if ($replay !== null) {
            $target = is_array($replay->target_json) ? $replay->target_json : [];
            $reason = $action === 'redo_last' ? trim((string) ($payload['replacement_reason'] ?? '')) : null;
            $fingerprint = $this->operations->fingerprintPayload([
                'action' => $action,
                'accepted_operation_id' => (string) $payload['accepted_operation_id'],
                'accepted_target_key' => (string) $payload['accepted_target_key'],
                'target_key' => (string) ($target['targetKey'] ?? $target['target_key'] ?? ''),
                'replacement_reason' => $reason,
                'expected_revision' => (int) $payload['expected_revision'],
            ]);
            $samePayload = hash_equals((string) ($replay->payload_json['input_fingerprint'] ?? ''), $fingerprint);
            if (($replay->domain_outcome ?? null) === 'accepted' && $samePayload) {
                return $projection;
            }
            throw new RuntimeException(($replay->domain_outcome ?? null) === 'reserved'
                ? 'omr_camera_capture_action_outcome_unknown'
                : 'omr_camera_capture_action_idempotency_conflict');
        }

        if ((int) $payload['expected_revision'] !== $projection->revision) {
            throw new RuntimeException('omr_camera_capture_action_stale');
        }

        $acceptedOperationId = (string) $payload['accepted_operation_id'];
        $acceptedTargetKey = (string) $payload['accepted_target_key'];
        $accepted = $this->operations->findAcceptedOperationReceipt($actor, $workbenchId, $acceptedOperationId);
        if (($accepted['status'] ?? null) !== 'accepted' || ! is_array($accepted['receipt'] ?? null)) {
            throw new RuntimeException('omr_camera_accepted_capture_missing');
        }

        $receipt = $accepted['receipt'];
        $target = $this->canonicalTarget($receipt);
        if (! hash_equals($acceptedTargetKey, (string) $target['targetKey'])) {
            throw new RuntimeException('omr_camera_accepted_target_stale');
        }

        $typedReceipt = $this->acceptedReceipt($acceptedOperationId, $receipt, $target);
        $current = $this->targets->forProjection($projection, $actor);
        $authorizedTransitionTargets = $this->authorizedTransitionTargets(
            $actor,
            $workbenchId,
            $projection,
            $typedReceipt,
        );
        $grants = $this->targets->projectAvailableActions(
            $projection,
            $actor,
            $typedReceipt,
            $urls,
            $authorizedTransitionTargets,
        );
        $action = (string) $payload['action'];
        $grant = $grants[$action] ?? null;
        if (! is_array($grant)
            || ! hash_equals((string) $grant['authorizationId'], (string) $payload['authorization_id'])
            || ! hash_equals($typedReceipt->targetKey, (string) $grant['acceptedTargetKey'])) {
            throw new RuntimeException('omr_camera_capture_action_grant_stale');
        }

        $targetForAction = match ($action) {
            'next_page' => $current,
            'next_set', 'redo_last' => $authorizedTransitionTargets[$action] ?? null,
            default => null,
        };
        if (! is_array($targetForAction) || ! hash_equals((string) $grant['targetKey'], (string) $targetForAction['targetKey'])) {
            throw new RuntimeException('omr_camera_capture_target_stale');
        }

        $actionOperationId = $this->operationUuid((string) $grant['authorizationId']);
        $reason = $action === 'redo_last' ? trim((string) ($payload['replacement_reason'] ?? '')) : null;
        if ($action === 'redo_last' && ($reason === null || $reason === '')) {
            throw new RuntimeException('omr_camera_capture_replacement_reason_required');
        }
        $fingerprint = $this->operations->fingerprintPayload([
            'action' => $action,
            'accepted_operation_id' => $acceptedOperationId,
            'accepted_target_key' => $typedReceipt->targetKey,
            'target_key' => $targetForAction['targetKey'],
            'replacement_reason' => $reason,
            'expected_revision' => $projection->revision,
        ]);

        DB::transaction(function () use ($actor, $workbenchId, $workbench, $targetForAction, $actionOperationId, $grant, $fingerprint, $action, $acceptedOperationId, $typedReceipt, $projection, $reason): void {
            $reservation = $this->operations->reserveOperation(
                $actor,
                hash('sha256', $workbenchId),
                $workbench,
                $targetForAction,
                $actionOperationId,
                (string) $grant['authorizationId'],
                $fingerprint,
                'keep_original',
                null,
            );
            if ($reservation->isReplay()) {
                return;
            }
            if ($this->operations->hasUnresolvedCaptureOperation(
                $actor,
                $workbenchId,
                (string) $reservation->operation->operation_id,
            )) {
                throw new RuntimeException('omr_camera_capture_action_outcome_unknown');
            }
            if ($projection->executionKind === OmrExecutionKind::Diagnostic
                && OmrDiagnosticCapture::query()
                    ->where('diagnostic_run_id', (int) ($projection->context['run_id'] ?? 0))
                    ->whereIn('status', [
                        OmrDiagnosticCapture::STATUS_RECEIVED,
                        OmrDiagnosticCapture::STATUS_QUEUED,
                        OmrDiagnosticCapture::STATUS_PROCESSING,
                    ])
                    ->exists()) {
                throw new RuntimeException('omr_diagnostic_capture_outcome_unknown');
            }

            $resultTarget = $targetForAction;
            if ($action === 'next_set') {
                $resultTarget = $projection->executionKind === OmrExecutionKind::Diagnostic
                    ? $this->startMockNextSet($actor, $projection, $actionOperationId)
                    : $this->startSignedProductionNextSet($actor, $projection, $workbenchId, $targetForAction);
            } elseif ($action === 'redo_last') {
                $resultTarget['redo_authorization_id'] = (string) $grant['authorizationId'];
                $resultTarget['redo_reason'] = $reason;
                $resultTarget['redo_operation_id'] = $typedReceipt->operationId;
                $resultTarget['redo_source_page_id'] = $resultTarget['redo_source_page_id'] ?? $targetForAction['redo_source_page_id'] ?? $typedReceipt->pageId;
                $resultTarget['redo_source_evidence_id'] = $resultTarget['redo_source_evidence_id'] ?? $targetForAction['redo_source_evidence_id'] ?? $typedReceipt->pageId;
                $resultTarget['redo_source_page_revision'] = $resultTarget['redo_source_page_revision'] ?? $targetForAction['redo_source_page_revision'] ?? (isset($targetForAction['pending_capture_id']) ? ($targetForAction['pending_capture_revision'] ?? null) : $typedReceipt->pageId);
                if (isset($targetForAction['pending_capture_id'])) {
                    $set = OmrPendingCaptureSet::query()->where('public_id', $targetForAction['pending_capture_id'])->first();
                    if ($set !== null) {
                        $resultTarget['pending_capture_revision'] = (int) $set->revision;
                    }
                }
                $resultTarget['redo_accepted_target_key'] = $typedReceipt->targetKey;
                $this->protocol->persistTargetGrant($actor, $workbenchId, $resultTarget);
            }

            $this->operations->commitAccepted($reservation, [
                'status' => 'accepted',
                'action' => $action,
                'accepted_operation_id' => $acceptedOperationId,
                'accepted_target_key' => $typedReceipt->targetKey,
                'target_key' => $resultTarget['targetKey'],
                'page_no' => $resultTarget['pageNo'],
                'replacement_reason' => $reason,
            ]);
        });

        return $projection;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $urls
     */
    private function authorizeSelectedPageRetake(
        User $actor,
        string $workbenchId,
        OmrWorkbenchProjection $projection,
        array $payload,
        array $urls,
    ): void {
        $authorizationId = (string) $payload['authorization_id'];
        $captureId = (int) $payload['capture_id'];
        $reason = trim((string) ($payload['replacement_reason'] ?? ''));
        if ($reason === '' || (string) ($payload['idempotency_key'] ?? '') !== $authorizationId) {
            throw new RuntimeException('omr_camera_capture_replacement_reason_required');
        }

        $existingTarget = $this->protocol->findTargetGrantByAuthorization($actor, $workbenchId, $authorizationId);
        if ($this->operations->hasUnresolvedCaptureOperation($actor, $workbenchId)) {
            throw new RuntimeException('omr_camera_capture_action_outcome_unknown');
        }
        if ($existingTarget !== null) {
            $expectedAuthorizationId = hash('sha256', implode('|', [
                (string) $actor->getAuthIdentifier(),
                $workbenchId,
                'capture_retake',
                (string) (int) $payload['expected_revision'],
                (string) $captureId,
                (string) (int) $payload['expected_capture_version'],
                (string) (int) $payload['page_no'],
                (string) (int) $payload['source_page_evidence_id'],
                (string) $payload['pending_capture_id'],
                (string) (int) $payload['pending_capture_revision'],
                (string) ($existingTarget['redo_operation_id'] ?? ''),
                (string) ($existingTarget['redo_accepted_target_key'] ?? ''),
            ]));
            if (! hash_equals($expectedAuthorizationId, $authorizationId)
                || (string) ($existingTarget['redo_reason'] ?? '') !== $reason
                || (int) ($existingTarget['replaces_capture_id'] ?? 0) !== $captureId
                || (int) ($existingTarget['redo_source_page_id'] ?? 0) !== (int) $payload['source_page_evidence_id']
                || ($existingTarget['pending_capture_id'] ?? null) !== ($payload['pending_capture_id'] ?? null)
                || (int) ($existingTarget['pageNo'] ?? 0) !== (int) $payload['page_no']
                || ! $this->matchesSelectedRetakeTarget($payload, $existingTarget)) {
                throw new RuntimeException('omr_camera_capture_action_idempotency_conflict');
            }
            $run = OmrDiagnosticRun::query()->findOrFail((int) ($projection->context['run_id'] ?? 0));
            $capture = OmrDiagnosticCapture::query()->whereKey($captureId)->where('diagnostic_run_id', $run->id)->firstOrFail();
            $set = OmrPendingCaptureSet::query()->where('public_id', $payload['pending_capture_id'])
                ->where('diagnostic_run_id', $run->id)->firstOrFail();
            if ($run->isTerminal() || $run->sealed_at !== null
                || (int) $run->revision !== (int) ($existingTarget['run_revision'] ?? 0)
                || (int) $capture->version !== (int) $payload['expected_capture_version']
                || $capture->failure_code === 'discarded_by_operator'
                || (int) $set->revision !== (int) ($existingTarget['pending_capture_revision'] ?? 0)
                || (int) $set->active_page_no !== (int) $payload['page_no']) {
                throw new RuntimeException('omr_camera_capture_action_grant_stale');
            }
            $link = is_array($capture->evidence_json)
                && is_array($capture->evidence_json['pending_page_link'] ?? null)
                ? $capture->evidence_json['pending_page_link']
                : [];
            $latestEvidence = OmrPendingCapturePageEvidence::query()
                ->where('set_id', $set->id)
                ->where('page_no', (int) $payload['page_no'])
                ->orderByDesc('id')
                ->first();
            if ((int) ($link['pending_page_evidence_id'] ?? 0) !== (int) $payload['source_page_evidence_id']
                || $latestEvidence === null
                || (int) $latestEvidence->id !== (int) $payload['source_page_evidence_id']) {
                throw new RuntimeException('omr_camera_capture_action_grant_stale');
            }

            return;
        }

        $url = (string) ($urls['capture_retake'] ?? '');
        $grants = $this->projectSelectedPageRetakeGrants($actor, $projection, $url);
        $grant = $grants[$captureId] ?? null;
        if (! is_array($grant)
            || ! hash_equals((string) $grant['authorization_id'], $authorizationId)
            || ! $this->matchesSelectedRetakeRequest($payload, $grant)) {
            throw new RuntimeException('omr_camera_capture_action_grant_stale');
        }

        $runId = (int) ($projection->context['run_id'] ?? 0);
        $run = OmrDiagnosticRun::query()->findOrFail($runId);
        $capture = OmrDiagnosticCapture::query()->whereKey($captureId)->where('diagnostic_run_id', $runId)->firstOrFail();
        $set = OmrPendingCaptureSet::query()
            ->where('public_id', $grant['pending_capture_id'])
            ->where('diagnostic_run_id', $runId)
            ->where('created_by_user_id', $actor->id)
            ->firstOrFail();
        $accepted = $this->operations->findAcceptedOperationReceiptForCapture($actor, $workbenchId, $captureId);
        $receipt = is_array($accepted['receipt'] ?? null) ? $accepted['receipt'] : null;
        if (($accepted['status'] ?? null) !== 'accepted' || $receipt === null) {
            throw new RuntimeException('omr_camera_accepted_capture_missing');
        }

        DB::transaction(function () use ($actor, $workbenchId, $run, $capture, $set, $grant, $receipt, $reason): void {
            $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($run->id);
            $lockedCapture = OmrDiagnosticCapture::query()
                ->whereKey($capture->id)
                ->where('diagnostic_run_id', $lockedRun->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSet = OmrPendingCaptureSet::query()
                ->whereKey($set->id)
                ->where('diagnostic_run_id', $lockedRun->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRun->isTerminal() || $lockedRun->sealed_at !== null) {
                throw new RuntimeException('omr_diagnostic_run_is_terminal');
            }
            if ($this->operations->hasUnresolvedCaptureOperation($actor, $workbenchId)) {
                throw new RuntimeException('omr_camera_capture_outcome_unknown');
            }
            $hasInFlightCapture = OmrDiagnosticCapture::query()
                ->where('diagnostic_run_id', $lockedRun->id)
                ->whereIn('status', [
                    OmrDiagnosticCapture::STATUS_RECEIVED,
                    OmrDiagnosticCapture::STATUS_QUEUED,
                    OmrDiagnosticCapture::STATUS_PROCESSING,
                ])
                ->exists();
            if ($hasInFlightCapture) {
                throw new RuntimeException('omr_diagnostic_capture_outcome_unknown');
            }
            if ((int) $lockedRun->revision !== (int) $grant['expected_revision']
                || (int) $lockedCapture->version !== (int) $grant['expected_capture_version']
                || (int) $lockedSet->revision !== (int) $grant['pending_capture_revision']
                || $lockedSet->isExpired()
                || $lockedCapture->failure_code === 'discarded_by_operator') {
                throw new RuntimeException('omr_camera_capture_action_stale');
            }

            $link = is_array($lockedCapture->evidence_json)
                && is_array($lockedCapture->evidence_json['pending_page_link'] ?? null)
                ? $lockedCapture->evidence_json['pending_page_link']
                : [];
            $sourcePageId = (int) $grant['source_page_evidence_id'];
            $pageNo = (int) $grant['page_no'];
            if ((int) ($link['capture_set_id'] ?? 0) !== (int) $lockedSet->id
                || (int) ($link['pending_page_evidence_id'] ?? 0) !== $sourcePageId
                || (int) ($link['page_no'] ?? 0) !== $pageNo) {
                throw new RuntimeException('omr_camera_capture_action_grant_stale');
            }
            $latestEvidence = OmrPendingCapturePageEvidence::query()
                ->where('set_id', $lockedSet->id)
                ->where('page_no', $pageNo)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            if ($latestEvidence === null || (int) $latestEvidence->id !== $sourcePageId) {
                throw new RuntimeException('omr_capture_set_page_superseded');
            }

            $lockedSet->forceFill([
                'active_page_no' => $pageNo,
                'status' => $lockedSet->pairing_code === null
                    ? OmrPendingCaptureSet::STATUS_PENDING
                    : $lockedSet->status,
                'revision' => (int) $lockedSet->revision + 1,
            ])->saveOrFail();
            if ($lockedRun->status === OmrDiagnosticRun::STATUS_PROCESSING) {
                $lockedRun->status = OmrDiagnosticRun::STATUS_OPEN;
                $lockedRun->phase = OmrDiagnosticRun::PHASE_CAPTURE;
                $lockedRun->completed_at = null;
            }
            $lockedRun->revision = (int) $lockedRun->revision + 1;
            $lockedRun->saveOrFail();

            $target = $this->selectedRetakeTarget(
                $receipt,
                $lockedRun,
                $lockedSet,
                $sourcePageId,
                $pageNo,
                (int) $lockedCapture->id,
                (string) $grant['authorization_id'],
                $reason,
            );
            $this->protocol->persistTargetGrant($actor, $workbenchId, $target);
            $this->audit->handle('omr.diagnostic_capture.retake_authorized', [
                'diagnostic_run_id' => (int) $lockedRun->id,
                'capture_id' => (int) $lockedCapture->id,
                'pending_capture_set_id' => (int) $lockedSet->id,
                'pending_page_evidence_id' => $sourcePageId,
                'page_no' => $pageNo,
                'replacement_reason' => $reason,
                'run_revision' => (int) $lockedRun->revision,
                'set_revision' => (int) $lockedSet->revision,
            ], (string) $actor->getAuthIdentifier());
        });
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $grant */
    private function matchesSelectedRetakeRequest(array $payload, array $grant): bool
    {
        foreach ([
            'capture_id' => 'capture_id',
            'expected_capture_version' => 'expected_capture_version',
            'page_no' => 'page_no',
            'source_page_evidence_id' => 'source_page_evidence_id',
            'pending_capture_revision' => 'pending_capture_revision',
            'expected_revision' => 'expected_revision',
        ] as $payloadKey => $grantKey) {
            if ((int) ($payload[$payloadKey] ?? 0) !== (int) ($grant[$grantKey] ?? 0)) {
                return false;
            }
        }

        return ($payload['pending_capture_id'] ?? null) === ($grant['pending_capture_id'] ?? null)
            && hash_equals(
                $this->operations->fingerprintPayload((array) ($payload['target'] ?? [])),
                $this->operations->fingerprintPayload((array) ($grant['target'] ?? [])),
            );
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $target */
    private function matchesSelectedRetakeTarget(array $payload, array $target): bool
    {
        $publicTarget = [
            'workbench_id' => (string) ($target['workbenchId'] ?? ''),
            'client_capture_id' => (string) ($target['clientCaptureId'] ?? ''),
            'page_no' => (int) ($target['pageNo'] ?? 0),
            'target_revision' => (int) ($target['captureRevision'] ?? 0),
            'instance_id' => $target['instanceId'] ?? null,
            'sheet_id' => $target['sheetId'] ?? null,
            'target_key' => (string) ($target['targetKey'] ?? ''),
        ];

        return ($payload['target'] ?? null) === $publicTarget
            && (string) ($target['redo_authorization_id'] ?? '') === (string) ($payload['authorization_id'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $receipt
     * @return array<string, mixed>
     */
    private function selectedRetakeTarget(
        array $receipt,
        OmrDiagnosticRun $run,
        OmrPendingCaptureSet $set,
        int $sourcePageId,
        int $pageNo,
        int $captureId,
        string $authorizationId,
        string $reason,
    ): array {
        $sourceTarget = $this->canonicalTarget($receipt);
        $clientCaptureId = $this->operationUuid($authorizationId);
        $revision = (int) $set->revision;
        $targetKey = json_encode([
            (string) $sourceTarget['workbenchId'],
            $clientCaptureId,
            $pageNo,
            $revision,
            $sourceTarget['instanceId'],
            $sourceTarget['sheetId'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            ...$sourceTarget,
            'clientCaptureId' => $clientCaptureId,
            'pageNo' => $pageNo,
            'captureRevision' => $revision,
            'targetKey' => $targetKey,
            'target_key' => $targetKey,
            'pending_capture_id' => (string) $set->public_id,
            'pending_capture_revision' => $revision,
            'run_revision' => (int) $run->revision,
            'redo_authorization_id' => $authorizationId,
            'redo_reason' => $reason,
            'redo_operation_id' => (string) ($receipt['operation_id'] ?? ''),
            'redo_source_page_id' => $sourcePageId,
            'redo_source_evidence_id' => $sourcePageId,
            'redo_source_page_revision' => $revision,
            'redo_accepted_target_key' => (string) ($sourceTarget['targetKey'] ?? ''),
            'replaces_capture_id' => $captureId,
        ];
    }

    /** @param array<string, mixed> $receipt @param array<string, mixed> $target */
    private function acceptedReceipt(string $operationId, array $receipt, array $target): OmrAcceptedCaptureReceipt
    {
        return new OmrAcceptedCaptureReceipt(
            operationId: $operationId,
            target: $target,
            targetKey: (string) $target['targetKey'],
            pageCount: $this->pageCount($receipt),
            captureId: $this->nullablePositiveInt($receipt['capture_id'] ?? $receipt['diagnostic_capture_id'] ?? null),
            pageId: $this->nullablePositiveInt($receipt['page_id'] ?? null),
            sheetId: $this->nullablePositiveInt($receipt['sheet_id'] ?? $target['sheetId'] ?? null),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function authorizedTransitionTargets(
        User $actor,
        string $workbenchId,
        OmrWorkbenchProjection $projection,
        OmrAcceptedCaptureReceipt $accepted,
    ): array {
        $authorized = [];
        $candidate = $this->targets->nextSetCandidate($projection, $actor);
        if ($candidate !== null
            && ($projection->executionKind === OmrExecutionKind::Diagnostic
                || ($projection->executionKind === OmrExecutionKind::Production
                    && ($candidate['transition_kind'] ?? null) === 'signed_new_group'))) {
            $authorized['next_set'] = $candidate;
        } elseif ($projection->executionKind === OmrExecutionKind::Diagnostic) {
            $candidate = $this->targets->nextSetCandidate($this->withCandidateMockTarget($projection), $actor);
            if ($candidate !== null) {
                $authorized['next_set'] = $candidate;
            }
        }

        if ($this->pendingRedoIsEligible($actor, $accepted)) {
            $authorized['redo_last'] = $this->redoTarget($actor, $workbenchId, $projection, $accepted);
        } elseif ($projection->executionKind === OmrExecutionKind::Production) {
            $productionTarget = $this->productionRedoSource($actor, $projection, $accepted);
            if ($productionTarget !== null) {
                $authorized['redo_last'] = $this->redoTarget($actor, $workbenchId, $projection, $accepted, $productionTarget);
            }
        }

        return $authorized;
    }

    /** @return array<string, mixed> */
    private function redoTarget(
        User $actor,
        string $workbenchId,
        OmrWorkbenchProjection $projection,
        OmrAcceptedCaptureReceipt $accepted,
        ?array $sourceTarget = null,
    ): array {
        $target = $sourceTarget ?? $accepted->target;
        $authorizationId = hash('sha256', implode('|', [
            (string) $actor->getAuthIdentifier(),
            $workbenchId,
            'redo_last',
            $accepted->operationId,
            $accepted->targetKey,
            (string) ($sourceTarget['targetKey'] ?? ''),
            (string) $projection->revision,
        ]));
        if ($sourceTarget === null) {
            $target['clientCaptureId'] = $this->operationUuid($authorizationId);
            $target['targetKey'] = json_encode([
                $target['workbenchId'], $target['clientCaptureId'], $target['pageNo'],
                $target['captureRevision'], $target['instanceId'], $target['sheetId'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $target['redo_authorization_id'] = $authorizationId;
        $target['redo_operation_id'] = $accepted->operationId;
        $target['redo_source_page_id'] = $sourceTarget['source_page_id'] ?? $accepted->pageId;
        $target['redo_source_evidence_id'] = $sourceTarget['source_evidence_id'] ?? $accepted->pageId;
        $target['redo_source_page_revision'] = $sourceTarget['source_revision']
            ?? (isset($target['pending_capture_id'])
                ? ($target['pending_capture_revision'] ?? null)
                : $accepted->pageId);
        $target['redo_accepted_target_key'] = $accepted->targetKey;
        $target['target_key'] = $target['targetKey'];
        $target['client_capture_id'] = $target['clientCaptureId'];

        return $target;
    }

    private function productionSheetForAcceptedSource(
        User $actor,
        OmrWorkbenchProjection $projection,
        OmrAcceptedCaptureReceipt $accepted,
    ): ?OmrCaptureSheet {
        $page = OmrCaptureSheetPage::query()
            ->with('sheet')
            ->whereKey($accepted->pageId)
            ->first();
        if ($page?->sheet instanceof OmrCaptureSheet
            && (int) $page->sheet->batch_id === (int) ($projection->context['run_id'] ?? 0)) {
            return $page->sheet;
        }
        $pendingPublicId = $accepted->target['pending_capture_id'] ?? null;
        if (! is_string($pendingPublicId) || $pendingPublicId === '') {
            return null;
        }
        $set = OmrPendingCaptureSet::query()
            ->where('public_id', $pendingPublicId)
            ->where('production_batch_id', (int) ($projection->context['run_id'] ?? 0))
            ->where('created_by_user_id', (int) $actor->getAuthIdentifier())
            ->first();
        if ($set === null) {
            return null;
        }
        $claim = OmrQrDisabledPageClaim::query()
            ->where('set_id', (int) $set->id)
            ->where('evidence_id', $accepted->pageId)
            ->where('page_no', (int) $accepted->target['pageNo'])
            ->with('captureSheetPage.sheet')
            ->first();
        $sheet = $claim?->captureSheetPage?->sheet;

        return $sheet instanceof OmrCaptureSheet
            && (int) $sheet->batch_id === (int) ($projection->context['run_id'] ?? 0)
                ? $sheet
                : null;
    }

    /** @return array<string, mixed>|null */
    private function productionRedoSource(
        User $actor,
        OmrWorkbenchProjection $projection,
        OmrAcceptedCaptureReceipt $accepted,
    ): ?array {
        if ($accepted->pageId === null) {
            return null;
        }
        $sheet = $accepted->sheetId !== null
            ? OmrCaptureSheet::query()->whereKey($accepted->sheetId)->where('batch_id', (int) ($projection->context['run_id'] ?? 0))->first()
            : $this->productionSheetForAcceptedSource($actor, $projection, $accepted);
        if ($sheet === null) {
            return null;
        }
        $source = $this->replaceOmrSheetPage->redoSource($sheet, (int) $accepted->target['pageNo'], $actor);
        if ($source === null
            || (int) ($source['source_page_id'] ?? 0) < 1
            || (int) ($source['source_evidence_id'] ?? $source['source_page_id'] ?? 0) !== $accepted->pageId
            || ($accepted->sheetId !== null && (int) ($source['sheet_id'] ?? 0) !== $accepted->sheetId)
            || (int) ($source['page_no'] ?? 0) !== (int) $accepted->target['pageNo']) {
            return null;
        }

        return [
            ...$accepted->target,
            ...$source,
            'execution_kind' => OmrExecutionKind::Production->value,
            'batch_id' => (int) ($accepted->target['batch_id'] ?? $projection->context['run_id'] ?? 0),
            'run_id' => (int) ($accepted->target['run_id'] ?? $projection->context['run_id'] ?? 0),
            'redo_source_page_id' => (int) $source['source_page_id'],
            'redo_source_page_revision' => (int) $source['source_revision'],
        ];
    }

    private function withCandidateMockTarget(OmrWorkbenchProjection $projection): OmrWorkbenchProjection
    {
        $run = OmrDiagnosticRun::query()->where('public_id', substr($projection->workbenchId, strlen('diagnostic:')))->firstOrFail();
        if (! $run->hasMockContext()) {
            return $projection;
        }
        $target = $this->mockSessions->captureTarget($run);
        if (! is_array($target)) {
            return $projection;
        }
        $payload = $projection->toSafeArray();
        $payload['context']['capture_target'] = $target;

        return OmrWorkbenchProjection::fromSafeArray($payload);
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>|null  $clientQuad
     */
    public function captureProductionRedo(
        User $actor,
        OmrWorkbenchProjection $projection,
        array $target,
        string $replacementReason,
        string $bytes,
        string $mime,
        string $cropPolicy,
        ?array $clientQuad,
        OmrCameraFrameReservation $reservation,
    ): OmrCaptureSheetPage {
        $reason = trim($replacementReason);
        $targetJson = $reservation->operation->target_json;
        $sourcePageId = (int) ($target['redo_source_page_id'] ?? 0);
        $sourceRevision = (int) ($target['redo_source_page_revision'] ?? 0);
        $sourceEvidenceId = (int) ($target['redo_source_evidence_id'] ?? 0);
        $sourceOperationId = (string) ($target['redo_operation_id'] ?? '');
        $accepted = $this->operations->findAcceptedOperationReceipt($actor, $projection->workbenchId, $sourceOperationId);
        if (($accepted['status'] ?? null) !== 'accepted' || ! is_array($accepted['receipt'] ?? null)
            || ! hash_equals((string) ($target['redo_accepted_target_key'] ?? ''), (string) ($accepted['receipt']['targetKey'] ?? $accepted['receipt']['target_key'] ?? ''))
            || (int) ($accepted['receipt']['page_id'] ?? 0) !== $sourceEvidenceId
            || (is_numeric($accepted['receipt']['sheet_id'] ?? null)
                && (int) $accepted['receipt']['sheet_id'] !== (int) ($target['sheetId'] ?? 0))) {
            throw new RuntimeException('omr_camera_capture_action_grant_stale');
        }
        if ($projection->executionKind !== OmrExecutionKind::Production
            || $sourcePageId < 1 || $sourceRevision < 1 || $sourceEvidenceId < 1 || $reason === ''
            || ! hash_equals((string) ($target['redo_reason'] ?? ''), $reason)
            || ! hash_equals((string) ($target['targetKey'] ?? ''), (string) ($targetJson['targetKey'] ?? ''))
            || ! hash_equals((string) ($target['redo_authorization_id'] ?? ''), (string) ($targetJson['redo_authorization_id'] ?? ''))
            || $sourcePageId !== (int) ($targetJson['redo_source_page_id'] ?? 0)
            || $sourceEvidenceId !== (int) ($targetJson['redo_source_evidence_id'] ?? 0)
            || $sourceRevision !== (int) ($targetJson['redo_source_page_revision'] ?? 0)
            || ! hash_equals($reason, (string) ($targetJson['redo_reason'] ?? ''))) {
            throw new RuntimeException('omr_camera_capture_action_grant_stale');
        }
        $sheet = OmrCaptureSheet::query()
            ->whereKey((int) ($target['sheetId'] ?? 0))
            ->where('batch_id', (int) ($projection->context['run_id'] ?? 0))
            ->firstOrFail();
        $source = $this->replaceOmrSheetPage->redoSource($sheet, (int) $target['pageNo'], $actor);
        if ($source === null
            || (int) ($source['source_page_id'] ?? 0) !== $sourcePageId
            || (int) ($source['source_evidence_id'] ?? $sourcePageId) !== $sourceEvidenceId
            || (int) ($source['source_revision'] ?? 0) !== $sourceRevision
            || ! hash_equals((string) ($source['targetKey'] ?? ''), (string) $target['targetKey'])) {
            throw new RuntimeException('omr_camera_capture_action_grant_stale');
        }

        return $this->replaceOmrSheetPage->handle(
            $sheet,
            (int) $target['pageNo'],
            $sourcePageId,
            $sourceRevision,
            $bytes,
            $mime,
            $actor,
            $reason,
            (string) ($reservation->operation->payload_json['idempotency_key'] ?? ''),
            (string) $target['redo_authorization_id'],
            $clientQuad,
            $cropPolicy,
            $reservation,
        );
    }

    /** @return array<string, mixed> */
    private function startMockNextSet(User $actor, OmrWorkbenchProjection $projection, string $idempotencyKey): array
    {
        $runId = (int) ($projection->context['run_id'] ?? 0);
        $run = OmrDiagnosticRun::query()->findOrFail($runId);
        if (! $run->hasMockContext()) {
            throw new RuntimeException('omr_camera_capture_action_transition_unavailable');
        }

        $rawTarget = $this->targets->nextSetCandidate($projection, $actor)
            ?? $this->targets->nextSetCandidate($this->withCandidateMockTarget($projection), $actor);
        if (! is_array($rawTarget)) {
            throw new RuntimeException('omr_camera_capture_target_stale');
        }
        $instanceNo = (int) ($rawTarget['instance_no'] ?? 0);
        if ($instanceNo < 2 || $instanceNo > (int) ($rawTarget['instance_count'] ?? 0)) {
            throw new RuntimeException('omr_camera_capture_action_transition_unavailable');
        }

        $version = OmrAnswerSheetTemplateVersion::query()->findOrFail((int) $run->template_version_id);
        if ($version->qr_mode === OmrQrMode::Signed) {
            $freshCandidate = $this->targets->nextSetCandidate($projection, $actor)
                ?? $this->targets->nextSetCandidate($this->withCandidateMockTarget($projection), $actor);
            if (! is_array($freshCandidate)
                || ! hash_equals(
                    $this->operations->fingerprintPayload($rawTarget),
                    $this->operations->fingerprintPayload($freshCandidate),
                )) {
                throw new RuntimeException('omr_camera_capture_target_stale');
            }
            $freshTarget = $this->mockSessions->captureTarget($run->fresh());
            if (! is_array($freshTarget)
                || (string) ($freshTarget['instance_id'] ?? '') !== (string) ($freshCandidate['instanceId'] ?? '')
                || (int) ($freshTarget['page_no'] ?? 0) !== 1
                || ! empty($freshTarget['pending_capture_id'])) {
                throw new RuntimeException('omr_camera_capture_target_stale');
            }
            $this->protocol->persistTargetGrant($actor, $projection->workbenchId, $freshCandidate);

            return $freshCandidate;
        }
        $instanceKey = sprintf('instance-%03d', $instanceNo);
        $set = $this->startPendingSet->forDiagnostic($run, $actor, $idempotencyKey, $instanceKey);
        $freshTarget = $this->mockSessions->captureTarget($run->fresh());
        if (! is_array($freshTarget)
            || (string) ($freshTarget['instance_id'] ?? '') !== (string) ($rawTarget['instanceId'] ?? '')
            || (int) ($freshTarget['page_no'] ?? 0) !== 1
            || (string) ($freshTarget['pending_capture_id'] ?? '') !== (string) $set->public_id) {
            throw new RuntimeException('omr_camera_capture_target_stale');
        }

        $candidate = $this->targets->nextSetCandidate($this->withCandidateMockTarget($projection), $actor);
        if (! is_array($candidate)
            || ($candidate['pending_capture_id'] ?? null) !== (string) $set->public_id
            || (int) ($candidate['pending_capture_revision'] ?? 0) !== (int) $set->revision
            || ($candidate['clientCaptureId'] ?? null) !== (string) $freshTarget['client_capture_id']) {
            throw new RuntimeException('omr_camera_capture_target_stale');
        }
        $this->protocol->persistTargetGrant($actor, $projection->workbenchId, $candidate);

        return $candidate;
    }

    /** @param array<string, mixed> $authorizedTarget @return array<string, mixed> */
    private function startSignedProductionNextSet(
        User $actor,
        OmrWorkbenchProjection $projection,
        string $workbenchId,
        array $authorizedTarget,
    ): array {
        $batchId = (int) ($projection->context['run_id'] ?? 0);
        $sessionId = (int) ($projection->context['session_id'] ?? 0);
        if (($authorizedTarget['transition_kind'] ?? null) !== 'signed_new_group'
            || $projection->executionKind !== OmrExecutionKind::Production
            || $workbenchId !== sprintf('production:%d:%d', $sessionId, $batchId)) {
            throw new RuntimeException('omr_camera_capture_action_transition_unavailable');
        }

        $batch = OmrCaptureBatch::query()
            ->whereKey($batchId)
            ->where('grading_session_id', $sessionId)
            ->lockForUpdate()
            ->firstOrFail();
        if ($batch->status !== OmrCaptureBatch::STATUS_OPEN) {
            throw new RuntimeException('omr_camera_capture_action_transition_unavailable');
        }

        $freshCandidate = $this->targets->nextSetCandidate($projection, $actor);
        if (! is_array($freshCandidate)
            || ! hash_equals(
                $this->operations->fingerprintPayload($authorizedTarget),
                $this->operations->fingerprintPayload($freshCandidate),
            )) {
            throw new RuntimeException('omr_camera_capture_target_stale');
        }
        $this->protocol->persistTargetGrant($actor, $workbenchId, $freshCandidate);

        return $freshCandidate;
    }

    /** @param array<string, mixed> $receipt @return array<string, mixed> */
    private function canonicalTarget(array $receipt): array
    {
        $binding = is_array($receipt['target'] ?? null) ? $receipt['target'] : $receipt;
        $target = [
            ...$receipt,
            'workbenchId' => $binding['workbenchId'] ?? $binding['workbench_id'] ?? null,
            'clientCaptureId' => $binding['clientCaptureId'] ?? $binding['client_capture_id'] ?? null,
            'pageNo' => $binding['pageNo'] ?? $binding['page_no'] ?? null,
            'captureRevision' => $binding['captureRevision'] ?? $binding['capture_revision'] ?? null,
            'instanceId' => array_key_exists('instanceId', $binding) ? $binding['instanceId'] : ($binding['instance_id'] ?? null),
            'sheetId' => array_key_exists('sheetId', $binding) ? $binding['sheetId'] : ($binding['sheet_id'] ?? null),
            'material_identity' => $receipt['material_identity'] ?? null,
            'batch_id' => $receipt['batch_id'] ?? null,
            'run_id' => $receipt['run_id'] ?? null,
            'pending_capture_id' => $receipt['pending_capture_id'] ?? null,
            'pending_capture_revision' => $receipt['pending_capture_revision'] ?? null,
            'execution_kind' => $receipt['execution_kind'] ?? null,
            'session_id' => $receipt['session_id'] ?? null,
            'page_count' => $receipt['page_count'] ?? $receipt['expected_page_count'] ?? null,
            'qr_mode' => $receipt['qr_mode'] ?? null,
            'registration_mode' => $receipt['registration_mode'] ?? null,
            'diagnostic_purpose' => $receipt['diagnostic_purpose'] ?? null,
            'identity_mode' => $receipt['identity_mode'] ?? null,
            'supplier_stock_allocation_id' => $receipt['supplier_stock_allocation_id'] ?? null,
        ];
        if (! is_string($target['workbenchId']) || ! is_string($target['clientCaptureId'])
            || ! is_numeric($target['pageNo']) || ! is_numeric($target['captureRevision'])
            || ! is_string($target['targetKey']) || ! is_array($target['material_identity'])) {
            throw new RuntimeException('omr_camera_accepted_capture_receipt_invalid');
        }

        return $target;
    }

    /** @param array<string, mixed> $receipt */
    private function pageCount(array $receipt): int
    {
        $pageCount = $receipt['page_count'] ?? $receipt['expected_page_count'] ?? null;
        if (is_numeric($pageCount) && (int) $pageCount > 0) {
            return (int) $pageCount;
        }

        $pageId = $receipt['page_id'] ?? null;
        if (is_numeric($pageId)) {
            $page = OmrCaptureSheetPage::query()->with('sheet')->find((int) $pageId);
            if ($page !== null) {
                return (int) $page->sheet?->expected_page_count;
            }
        }

        $pendingId = $receipt['pending_capture_id'] ?? null;
        if (is_string($pendingId)) {
            $set = OmrPendingCaptureSet::query()->where('public_id', $pendingId)->first();
            if ($set !== null) {
                return (int) $set->page_count;
            }
        }

        throw new RuntimeException('omr_camera_accepted_capture_receipt_invalid');
    }

    private function positiveInt(mixed $value): int
    {
        if (! is_numeric($value) || (int) $value < 1) {
            throw new RuntimeException('omr_camera_accepted_capture_receipt_invalid');
        }

        return (int) $value;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        return $value === null ? null : $this->positiveInt($value);
    }

    private function operationUuid(string $authorizationId): string
    {
        $hex = substr(hash('sha256', 'omr-capture-action|'.$authorizationId), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'
            .substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }

    private function pendingRedoIsEligible(User $actor, OmrAcceptedCaptureReceipt $accepted): bool
    {
        $setId = $accepted->target['pending_capture_id'] ?? null;
        if (! is_string($setId) || $setId === '' || $accepted->pageId === null) {
            return false;
        }

        $set = OmrPendingCaptureSet::query()
            ->where('public_id', $setId)
            ->where('created_by_user_id', $actor->id)
            ->whereNull('adopted_sheet_id')
            ->whereIn('status', [OmrPendingCaptureSet::STATUS_PENDING, OmrPendingCaptureSet::STATUS_READY, OmrPendingCaptureSet::STATUS_CAPTURED])
            ->where('expires_at', '>', now())
            ->first();
        if ($set === null) {
            return false;
        }

        return DB::table('omr_pending_capture_page_evidence')
            ->where('set_id', $set->id)
            ->where('page_no', (int) $accepted->target['pageNo'])
            ->where('id', $accepted->pageId)
            ->exists();
    }
}
