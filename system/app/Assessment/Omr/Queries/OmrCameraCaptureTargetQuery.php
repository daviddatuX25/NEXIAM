<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Queries;

use App\Assessment\Actions\Omr\Camera\OmrAcceptedCaptureReceipt;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\Operational\OmrExecutionKind;
use App\Assessment\Omr\Operational\OmrWorkbenchProjection;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrCaptureSheetPageRevision;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrQrDisabledPageClaim;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/** Resolves targets from server-owned projections and pinned domain records. */
final readonly class OmrCameraCaptureTargetQuery
{
    public function __construct(private CapabilityAuthorizer $authorizer) {}

    /** @return array<string, mixed>|null */
    public function forProjection(
        OmrWorkbenchProjection $projection,
        User $actor,
        ?int $captureSheetId = null,
        ?int $diagnosticPageNo = null,
    ): ?array {
        if (isset($projection->context['actor_id'])
            && (int) $projection->context['actor_id'] !== (int) $actor->getAuthIdentifier()) {
            return null;
        }

        if ($projection->executionKind === OmrExecutionKind::Production || $projection->executionKind->value === 'production') {
            return $this->productionTarget($projection, $actor, $captureSheetId);
        }
        if ($captureSheetId !== null
            || $projection->phase !== OmrWorkbenchProjection::PHASE_CAPTURE
            || (isset($projection->context['status'])
                && $projection->context['status'] !== OmrDiagnosticRun::STATUS_OPEN)) {
            return null;
        }
        $authorizedPendingRedo = $this->pendingRedoTargetWithoutProjection($projection, $actor);
        if ($authorizedPendingRedo !== null
            && ($diagnosticPageNo === null || (int) $authorizedPendingRedo['pageNo'] === $diagnosticPageNo)) {
            return $authorizedPendingRedo;
        }

        $projected = $projection->context['capture_target'] ?? null;
        if (is_array($projected)) {
            $material = $this->diagnosticTargetMaterial($projection, $projected);
            $target = $this->normalize($projection, $projected, $material);
            if ($target === null || ($diagnosticPageNo !== null && (int) $target['pageNo'] !== $diagnosticPageNo)) {
                return null;
            }

            $pending = $projection->context['pending_capture'] ?? null;
            if (is_array($pending) && ($target['pending_capture_id'] ?? null) === ($pending['id'] ?? null)) {
                $pendingMaterial = [
                    ...$material,
                    'pending_capture_id' => (string) $pending['id'],
                    'pending_capture_revision' => (int) $pending['revision'],
                    'page_count' => (int) $pending['page_count'],
                    'supplier_stock_allocation_id' => $pending['supplier_stock_allocation_id'] ?? null,
                    'identity_mode' => $pending['identity_mode'] ?? null,
                ];
                $redoTarget = $this->pendingRedoTarget($projection, $actor, $pending, $pendingMaterial);
                if ($redoTarget !== null) {
                    return $redoTarget;
                }
                $target = $this->normalize($projection, $projected, $pendingMaterial);
                if ($target === null) {
                    return null;
                }
            }

            return $this->diagnosticTargetFollowsAcceptedCapture($projection, $actor, $target)
                ? $target
                : null;
        }
        $pending = $projection->context['pending_capture'] ?? null;
        if (! is_array($pending)) {
            return $this->nonMockDiagnosticTarget($projection, $actor, $diagnosticPageNo);
        }

        $target = $this->pendingTarget(
            $projection,
            $actor,
            $pending,
            $this->diagnosticTargetMaterial($projection, $pending),
        );
        if ($target === null || ($diagnosticPageNo !== null && (int) $target['pageNo'] !== $diagnosticPageNo)) {
            return null;
        }

        return $this->diagnosticTargetFollowsAcceptedCapture($projection, $actor, $target)
            ? $target
            : null;
    }

    private function pendingRedoTargetWithoutProjection(
        OmrWorkbenchProjection $projection,
        User $actor,
    ): ?array {
        if ($projection->executionKind !== OmrExecutionKind::Diagnostic) {
            return null;
        }
        $grant = OmrCameraCommand::query()
            ->where('workbench_key', hash('sha256', $projection->workbenchId))
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('kind', 'CAPTURE_TARGET_GRANT')
            ->where('domain_outcome', 'granted')
            ->latest('id')
            ->first();
        $target = is_array($grant?->target_json) ? $grant->target_json : [];
        $pendingCaptureId = $target['pending_capture_id'] ?? null;
        if (! is_string($pendingCaptureId) || $pendingCaptureId === '') {
            return null;
        }
        $material = $this->freshObservationMaterial($projection, $actor, $target);
        if ($material === null) {
            return null;
        }
        $setQuery = OmrPendingCaptureSet::query()
            ->where('public_id', $pendingCaptureId)
            ->where('diagnostic_run_id', (int) $material['run_id'])
            ->where('created_by_user_id', (int) $actor->getAuthIdentifier());
        if (is_string($target['instanceId'] ?? null) && $target['instanceId'] !== '') {
            $setQuery->where('mock_instance_key', $target['instanceId']);
        }
        $set = $setQuery->whereNull('adopted_sheet_id')
            ->where('expires_at', '>', now())
            ->whereIn('status', [
                OmrPendingCaptureSet::STATUS_PENDING,
                OmrPendingCaptureSet::STATUS_CAPTURED,
                OmrPendingCaptureSet::STATUS_READY,
            ])
            ->with('templateVersion')
            ->first();
        if ($set === null || $set->templateVersion === null) {
            return null;
        }
        if ((int) $set->template_version_id !== (int) ($material['template_version_id'] ?? 0)) {
            return null;
        }
        if ((int) ($target['pending_capture_revision'] ?? 0) !== (int) $set->revision) {
            return null;
        }
        $latestByPage = OmrPendingCapturePageEvidence::query()
            ->where('set_id', $set->id)
            ->orderBy('id')
            ->get(['id', 'page_no'])
            ->groupBy('page_no');
        $pages = [];
        foreach ($set->expected_pages_json as $expectedPage) {
            $pageNo = (int) $expectedPage['page_no'];
            $evidence = $latestByPage->get($pageNo)?->last();
            $pages[] = [
                'page_no' => $pageNo,
                'active' => false,
                'capture_count' => $evidence === null ? 0 : 1,
                'capture_id' => $evidence === null ? null : (int) $evidence->id,
            ];
        }

        $pending = [
            'id' => (string) $set->public_id,
            'revision' => (int) $set->revision,
            'status' => (string) $set->status,
            'active_page_no' => (int) $set->active_page_no,
            'page_count' => (int) $set->page_count,
            'adopted_sheet_id' => $set->adopted_sheet_id,
            'supplier_stock_allocation_id' => $set->supplier_stock_allocation_id,
            'identity_mode' => $set->identity_mode,
            'pages' => $pages,
        ];
        $targetMaterial = [
            ...$this->diagnosticTargetMaterial($projection, $target),
            'pending_capture_id' => (string) $set->public_id,
            'pending_capture_revision' => (int) $set->revision,
            'page_count' => (int) $set->page_count,
            'supplier_stock_allocation_id' => $set->supplier_stock_allocation_id,
            'identity_mode' => $set->identity_mode,
        ];

        return $this->pendingRedoTarget($projection, $actor, $pending, $targetMaterial);
    }

    private function nonMockDiagnosticTarget(
        OmrWorkbenchProjection $projection,
        User $actor,
        ?int $diagnosticPageNo,
    ): ?array {
        $material = $this->freshObservationMaterial($projection, $actor, []);
        $pageNo = $diagnosticPageNo ?? 1;
        if ($material === null
            || ($material['diagnostic_purpose'] ?? null) === OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL
            || (int) ($material['run_revision'] ?? 0) !== $projection->revision
            || $pageNo < 1 || $pageNo > (int) ($material['page_count'] ?? 0)) {
            return null;
        }

        $clientCaptureId = $this->stableUuid(implode('|', [
            'nexiam:omr:diagnostic-image',
            (string) $actor->getAuthIdentifier(),
            (string) $material['run_id'],
            (string) $material['run_revision'],
            (string) $pageNo,
            (string) $material['template_version_id'],
            (string) $material['booklet_version_id'],
        ]));

        return $this->normalize($projection, [
            'client_capture_id' => $clientCaptureId,
            'page_no' => $pageNo,
            'capture_revision' => (int) $material['run_revision'],
        ], $material);
    }

    /** @param array<string, mixed> $projected @return array<string, mixed> */
    private function diagnosticTargetMaterial(OmrWorkbenchProjection $projection, array $projected): array
    {
        $templateVersionId = $projection->context['template_version_id'] ?? $projected['template_version_id'] ?? null;
        $templateVersion = OmrAnswerSheetTemplateVersion::query()->find($templateVersionId);
        $pendingId = $projected['pending_capture_id'] ?? $projected['id'] ?? null;
        $pending = is_string($pendingId) ? OmrPendingCaptureSet::query()
            ->where('public_id', $pendingId)
            ->where('diagnostic_run_id', (int) ($projection->context['run_id'] ?? 0))
            ->where('created_by_user_id', (int) ($projection->context['actor_id'] ?? 0))
            ->first() : null;

        return [
            'execution_kind' => OmrExecutionKind::Diagnostic->value,
            'run_id' => $projection->context['run_id'] ?? null,
            'diagnostic_purpose' => $projection->context['diagnostic_purpose'] ?? null,
            'run_revision' => $projection->revision,
            'pending_capture_id' => $projected['pending_capture_id'] ?? null,
            'pending_capture_revision' => $projected['pending_capture_revision'] ?? null,
            'page_count' => $projected['page_count'] ?? null,
            'instance_no' => $projected['instance_no'] ?? null,
            'instance_count' => $projected['instance_count'] ?? null,
            'template_version_id' => $templateVersionId,
            'booklet_version_id' => $projection->context['booklet_version_id'] ?? null,
            'material_identity' => [
                'template_version_id' => $templateVersionId,
                'booklet_version_id' => $projection->context['booklet_version_id'] ?? null,
                'mock_manifest_sha256' => $projection->context['mock_manifest_sha256'] ?? null,
            ],
            'identity_mode' => $pending?->identity_mode ?? $projected['identity_mode'] ?? $projection->context['identity_mode'] ?? null,
            'supplier_stock_allocation_id' => $pending?->supplier_stock_allocation_id ?? $projected['supplier_stock_allocation_id'] ?? $projection->context['supplier_stock_allocation_id'] ?? null,
            'registration_mode' => $templateVersion?->registration_mode?->value,
            'qr_mode' => $templateVersion?->qr_mode?->value,
        ];
    }

    /** @return array<string, mixed>|null */
    private function latestAcceptedCaptureTarget(OmrWorkbenchProjection $projection, User $actor): ?array
    {
        $command = OmrCameraCommand::query()
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('workbench_key', hash('sha256', $projection->workbenchId))
            ->where('kind', 'CAPTURE_RECEIPT')
            ->where('domain_outcome', 'accepted')
            ->whereNull('domain_receipt_json->action')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        return is_array($command?->target_json) ? $command->target_json : null;
    }

    private function hasTargetGrant(User $actor, string $workbenchId, string $targetKey): bool
    {
        return OmrCameraCommand::query()
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('workbench_key', hash('sha256', $workbenchId))
            ->where('kind', 'CAPTURE_TARGET_GRANT')
            ->where('domain_outcome', 'granted')
            ->where('target_json->targetKey', $targetKey)
            ->exists();
    }

    /** @param array<string, mixed> $target */
    private function diagnosticTargetFollowsAcceptedCapture(
        OmrWorkbenchProjection $projection,
        User $actor,
        array $target,
    ): bool {
        $accepted = $this->latestAcceptedCaptureTarget($projection, $actor);
        if ($accepted === null) {
            return true;
        }
        if (! $this->isCanonicalTransitionTarget($projection, $accepted, $accepted)
            || ($accepted['material_identity'] ?? null) !== ($target['material_identity'] ?? null)) {
            return false;
        }

        if ($this->samePhysicalGroup($accepted, $target)) {
            return (int) ($target['pageNo'] ?? 0) === (int) ($accepted['pageNo'] ?? 0) + 1;
        }

        return (int) ($target['pageNo'] ?? 0) === 1
            && $this->hasTargetGrant($actor, $projection->workbenchId, (string) $target['targetKey']);
    }

    /**
     * Read-only server candidate for the explicit Mock Next Set transition.
     * This candidate is never a capture grant by itself.
     *
     * @return array<string, mixed>|null
     */
    public function nextSetCandidate(OmrWorkbenchProjection $projection, User $actor): ?array
    {
        if (isset($projection->context['actor_id'])
            && (int) $projection->context['actor_id'] !== (int) $actor->getAuthIdentifier()) {
            return null;
        }
        if ($projection->executionKind === OmrExecutionKind::Production) {
            return in_array($projection->phase, [
                OmrWorkbenchProjection::PHASE_CAPTURE,
                OmrWorkbenchProjection::PHASE_PROCESSING,
                OmrWorkbenchProjection::PHASE_REVIEW,
                OmrWorkbenchProjection::PHASE_CONTEXT,
            ], true) ? $this->signedProductionNextSetCandidate($projection, $actor) : null;
        }
        if ($projection->phase !== OmrWorkbenchProjection::PHASE_CAPTURE) {
            return null;
        }
        if ($projection->executionKind !== OmrExecutionKind::Diagnostic
            || ($projection->context['diagnostic_purpose'] ?? null) !== OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL) {
            return null;
        }

        $projected = $projection->context['capture_target'] ?? null;
        if (! is_array($projected)
            || (int) ($projected['instance_no'] ?? 0) < 2
            || (int) ($projected['instance_no'] ?? 0) > (int) ($projected['instance_count'] ?? 0)
            || (int) ($projected['page_no'] ?? 0) !== 1) {
            return null;
        }

        $runId = (int) ($projection->context['run_id'] ?? 0);
        $runPublicId = str_starts_with($projection->workbenchId, 'diagnostic:')
            ? substr($projection->workbenchId, strlen('diagnostic:'))
            : '';
        $run = OmrDiagnosticRun::query()
            ->whereKey($runId)
            ->where('public_id', $runPublicId)
            ->where('created_by_user_id', (int) $actor->getAuthIdentifier())
            ->where('status', OmrDiagnosticRun::STATUS_OPEN)
            ->first();
        $material = $this->freshObservationMaterial($projection, $actor, $projected);
        $target = $this->normalize($projection, $projected, $this->diagnosticTargetMaterial($projection, $projected));
        $accepted = $this->latestAcceptedCaptureTarget($projection, $actor);
        if ($run === null || ! $run->hasMockContext() || $material === null || $target === null
            || $accepted === null || ! $this->isCanonicalTransitionTarget($projection, $accepted, $accepted)
            || ($accepted['material_identity'] ?? null) !== ($target['material_identity'] ?? null)
            || ($target['material_identity'] ?? null) !== ($material['material_identity'] ?? null)
            || ($accepted['run_id'] ?? null) !== ($target['run_id'] ?? null)
            || (int) ($accepted['pageNo'] ?? 0) !== (int) ($accepted['page_count'] ?? 0)
            || $this->samePhysicalGroup($accepted, $target)) {
            return null;
        }

        if ($this->hasTargetGrant($actor, $projection->workbenchId, (string) $target['targetKey'])) {
            return null;
        }

        return $target;
    }

    private function signedProductionNextSetCandidate(OmrWorkbenchProjection $projection, User $actor): ?array
    {
        $material = $this->freshObservationMaterial($projection, $actor, []);
        if ($material === null || ($material['qr_mode'] ?? null) !== OmrQrMode::Signed->value) {
            return null;
        }

        $batchId = (int) ($material['batch_id'] ?? 0);
        $pageCount = (int) ($material['page_count'] ?? 0);
        $command = OmrCameraCommand::query()
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('workbench_key', hash('sha256', $projection->workbenchId))
            ->where('kind', 'CAPTURE_RECEIPT')
            ->where('domain_outcome', 'accepted')
            ->whereNull('domain_receipt_json->action')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
        $accepted = is_array($command?->target_json) ? $command->target_json : [];
        $receipt = is_array($command?->domain_receipt_json) ? $command->domain_receipt_json : [];
        $sourceSheetId = (int) ($receipt['sheet_id'] ?? 0);
        $sourcePageId = (int) ($receipt['page_id'] ?? 0);
        if ($command === null || $batchId < 1 || $pageCount < 1 || $pageCount > 4
            || ! $this->isCanonicalTransitionTarget($projection, $accepted, $accepted)
            || ($accepted['material_identity'] ?? null) !== $material['material_identity']
            || (int) ($accepted['pageNo'] ?? 0) !== $pageCount
            || $sourceSheetId < 1 || $sourcePageId < 1) {
            return null;
        }

        $sheet = OmrCaptureSheet::query()
            ->whereKey($sourceSheetId)
            ->where('batch_id', $batchId)
            ->where('client_capture_id', (string) ($accepted['clientCaptureId'] ?? ''))
            ->where('expected_page_count', $pageCount)
            ->whereIn('status', [
                OmrCaptureSheet::STATUS_QUEUED,
                OmrCaptureSheet::STATUS_PROCESSING,
                OmrCaptureSheet::STATUS_NEEDS_REVIEW,
                OmrCaptureSheet::STATUS_REVIEWED,
            ])
            ->with(['pages:id,sheet_id,page_no,asset_path'])
            ->first();
        if ($sheet === null || $sheet->pages->count() !== $pageCount
            || $sheet->pages->contains(static fn (OmrCaptureSheetPage $page): bool => $page->asset_path === null)
            || ! $sheet->pages->contains(static fn (OmrCaptureSheetPage $page): bool => (int) $page->id === $sourcePageId && (int) $page->page_no === $pageCount)) {
            return null;
        }
        if (OmrCaptureSheet::query()->where('batch_id', $batchId)->whereIn('status', [
            OmrCaptureSheet::STATUS_PENDING_PAGES,
            OmrCaptureSheet::STATUS_RECEIVED,
        ])->exists()) {
            return null;
        }
        if (OmrPendingCaptureSet::query()->where('production_batch_id', $batchId)
            ->whereNull('adopted_sheet_id')
            ->whereIn('status', [
                OmrPendingCaptureSet::STATUS_PENDING,
                OmrPendingCaptureSet::STATUS_CAPTURED,
                OmrPendingCaptureSet::STATUS_READY,
            ])
            ->where('expires_at', '>', now())
            ->exists()) {
            return null;
        }

        $target = $this->normalize($projection, [
            'client_capture_id' => $this->stableUuid('nexiam:omr:signed-next-set:'.$batchId.':'.$command->operation_id),
            'page_no' => 1,
            'capture_revision' => max(1, (int) ($material['batch_version'] ?? 1)),
        ], [
            ...$material,
            'transition_kind' => 'signed_new_group',
            'source_operation_id' => (string) $command->operation_id,
        ]);

        if ($this->hasTargetGrant($actor, $projection->workbenchId, (string) $target['targetKey'])) {
            return null;
        }

        return $target;
    }

    public function forObservationTarget(
        OmrWorkbenchProjection $projection,
        User $actor,
        ?int $diagnosticPageNo = null,
    ): ?array {
        $current = $this->forProjection($projection, $actor, null, $diagnosticPageNo);
        if ($current !== null) {
            return $current;
        }

        if (isset($projection->context['actor_id'])
            && (int) $projection->context['actor_id'] !== (int) $actor->getAuthIdentifier()) {
            return null;
        }

        $command = OmrCameraCommand::query()
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('workbench_key', hash('sha256', $projection->workbenchId))
            ->where('kind', 'CAPTURE_RECEIPT')
            ->where('domain_outcome', 'accepted')
            ->whereNull('domain_receipt_json->action')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
        if ($command === null || ! is_array($command->target_json)) {
            return null;
        }

        $target = $command->target_json;
        $material = $this->freshObservationMaterial($projection, $actor, $target);
        if ($projection->executionKind === OmrExecutionKind::Diagnostic
            && $diagnosticPageNo !== null
            && (int) ($target['pageNo'] ?? 0) !== $diagnosticPageNo) {
            return null;
        }
        if ($material === null || ! $this->isCanonicalTransitionTarget($projection, $target, $target)
            || (int) ($target['pageNo'] ?? 0) > (int) ($material['page_count'] ?? 0)) {
            return null;
        }

        foreach ([
            'execution_kind', 'session_id', 'batch_id', 'run_id', 'template_version_id',
            'booklet_version_id', 'batch_version', 'registration_mode', 'qr_mode', 'page_count',
            'diagnostic_purpose', 'material_identity',
        ] as $field) {
            if (($target[$field] ?? null) !== ($material[$field] ?? null)) {
                return null;
            }
        }

        $pendingCaptureId = $target['pending_capture_id'] ?? null;
        if (is_string($pendingCaptureId)) {
            $pendingQuery = OmrPendingCaptureSet::query()
                ->where('public_id', $pendingCaptureId)
                ->where('created_by_user_id', (int) $actor->getAuthIdentifier())
                ->whereNull('adopted_sheet_id')
                ->where('expires_at', '>', now())
                ->whereIn('status', [
                    OmrPendingCaptureSet::STATUS_PENDING,
                    OmrPendingCaptureSet::STATUS_CAPTURED,
                    OmrPendingCaptureSet::STATUS_READY,
                ]);
            if ($projection->executionKind === OmrExecutionKind::Production) {
                $pendingQuery->where('production_batch_id', (int) $material['batch_id']);
            } else {
                $pendingQuery->where('diagnostic_run_id', (int) $material['run_id']);
            }
            $set = $pendingQuery->first();
            if ($set === null || (int) $set->template_version_id !== (int) $material['template_version_id']
                || (int) $target['pageNo'] > (int) $set->page_count
                || (int) ($target['page_count'] ?? 0) !== (int) $set->page_count
                || (int) ($target['pending_capture_revision'] ?? 0) < 1
                || (int) $target['pending_capture_revision'] > (int) $set->revision
                || ($target['supplier_stock_allocation_id'] ?? null) !== $set->supplier_stock_allocation_id
                || ($target['identity_mode'] ?? null) !== $set->identity_mode
                || (($target['instanceId'] ?? null) !== null && $target['instanceId'] !== $set->mock_instance_key)) {
                return null;
            }
            $material = [
                ...$material,
                'pending_capture_id' => (string) $set->public_id,
                'pending_capture_revision' => (int) $target['pending_capture_revision'],
                'page_count' => (int) $set->page_count,
                'supplier_stock_allocation_id' => $set->supplier_stock_allocation_id === null
                    ? null
                    : (int) $set->supplier_stock_allocation_id,
                'identity_mode' => $set->identity_mode,
            ];
        }

        $normalized = $this->normalize($projection, $target, $material);
        if ($normalized === null || ! hash_equals((string) ($target['targetKey'] ?? ''), $normalized['targetKey'])) {
            return null;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $target @return array<string, mixed>|null */
    private function freshObservationMaterial(
        OmrWorkbenchProjection $projection,
        User $actor,
        array $target,
    ): ?array {
        if ($projection->executionKind === OmrExecutionKind::Production) {
            $context = $projection->context;
            $batchId = (int) ($context['run_id'] ?? 0);
            $sessionId = (int) ($context['session_id'] ?? 0);
            if ($batchId < 1 || $sessionId < 1
                || $projection->workbenchId !== sprintf('production:%d:%d', $sessionId, $batchId)) {
                return null;
            }
            $batch = OmrCaptureBatch::query()
                ->whereKey($batchId)
                ->where('grading_session_id', $sessionId)
                ->with(['gradingSession.examSession.academicCycle', 'templateVersion', 'bookletVersion'])
                ->first();
            if ($batch === null || $batch->status !== OmrCaptureBatch::STATUS_OPEN
                || $batch->gradingSession === null || $batch->gradingSession->status === GradingSession::STATUS_FINALIZED
                || $batch->templateVersion === null || $batch->bookletVersion === null) {
            }
            $cycle = $batch->gradingSession?->examSession?->academicCycle;
            $cycleCode = trim((string) ($cycle?->code ?? ''));
            if ($cycle === null || $cycleCode === '' || ! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, [
                'institution_id' => (int) $cycle->institution_id,
                'cycle' => $cycleCode,
                'office' => 'guidance',
            ])) {
                return null;
            }

            return $this->pinnedMaterial($batch);
        }

        $runId = (int) ($projection->context['run_id'] ?? 0);
        $publicId = str_starts_with($projection->workbenchId, 'diagnostic:')
            ? substr($projection->workbenchId, strlen('diagnostic:'))
            : '';
        if ($runId < 1 || $publicId === '') {
            return null;
        }
        $run = OmrDiagnosticRun::query()
            ->whereKey($runId)
            ->where('public_id', $publicId)
            ->where('created_by_user_id', (int) $actor->getAuthIdentifier())
            ->where('status', OmrDiagnosticRun::STATUS_OPEN)
            ->with(['academicCycle', 'templateVersion', 'bookletVersion'])
            ->first();
        if ($run === null || $run->academicCycle === null || $run->templateVersion === null || $run->bookletVersion === null) {
            return null;
        }
        $cycle = $run->academicCycle;
        $cycleCode = trim((string) ($cycle->code ?? ''));
        if ($cycleCode === '' || (int) $cycle->institution_id !== (int) $run->institution_id
            || (int) $cycle->id !== (int) $run->academic_cycle_id
            || ! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, [
                'institution_id' => (int) $run->institution_id,
                'cycle' => $cycleCode,
                'office' => 'guidance',
            ])) {
            return null;
        }

        $version = $run->templateVersion;
        $booklet = $run->bookletVersion;

        return [
            'execution_kind' => OmrExecutionKind::Diagnostic->value,
            'run_id' => (int) $run->id,
            'diagnostic_purpose' => (string) $run->diagnostic_purpose,
            'run_revision' => (int) $run->revision,
            'template_version_id' => (int) $version->id,
            'booklet_version_id' => (int) $booklet->id,
            'registration_mode' => $version->registration_mode->value,
            'qr_mode' => $version->qr_mode->value,
            'page_count' => (int) $version->page_count,
            'material_identity' => [
                'template_version_id' => (int) $version->id,
                'booklet_version_id' => (int) $booklet->id,
                'mock_manifest_sha256' => $run->mock_manifest_sha256,
            ],
        ];
    }

    /** @param array<string, mixed> $assertion @return array<string, mixed> */
    public function assertCurrentTarget(OmrWorkbenchProjection $projection, User $actor, array $assertion): array
    {
        $captureSheetId = is_numeric($assertion['sheetId'] ?? null) ? (int) $assertion['sheetId'] : null;
        $diagnosticPageNo = $projection->executionKind === OmrExecutionKind::Diagnostic
            && ! is_array($projection->context['capture_target'] ?? null)
            && ! is_array($projection->context['pending_capture'] ?? null)
                ? (is_numeric($assertion['pageNo'] ?? null) ? (int) $assertion['pageNo'] : 1)
                : null;
        $target = $this->forProjection($projection, $actor, $captureSheetId, $diagnosticPageNo);
        if ($target === null || ! is_string($assertion['targetKey'] ?? null)
            || ! hash_equals((string) $target['targetKey'], $assertion['targetKey'])) {
            throw new RuntimeException('omr_camera_capture_target_stale');
        }

        foreach (['workbenchId', 'clientCaptureId', 'pageNo', 'captureRevision', 'instanceId', 'sheetId'] as $field) {
            if (($assertion[$field] ?? null) !== $target[$field]) {
                throw new RuntimeException('omr_camera_capture_target_mismatch');
            }
        }
        foreach ([
            'execution_kind', 'session_id', 'batch_id', 'run_id', 'template_version_id', 'booklet_version_id',
            'batch_version', 'registration_mode', 'qr_mode', 'page_count', 'material_identity', 'pending_capture_id',
            'pending_capture_revision', 'supplier_stock_allocation_id', 'identity_mode', 'redo_authorization_id',
            'diagnostic_purpose', 'run_revision',
            'redo_reason', 'redo_operation_id', 'redo_source_page_id', 'redo_source_page_revision',
            'redo_source_evidence_id', 'redo_accepted_target_key', 'replaces_capture_id',
        ] as $field) {
            if (array_key_exists($field, $assertion) && ($assertion[$field] ?? null) !== ($target[$field] ?? null)) {
                throw new RuntimeException('omr_camera_capture_material_mismatch');
            }
        }

        return $target;
    }

    /**
     * @param  array<string, string>  $urls
     * @param  array<string, array<string, mixed>>  $authorizedTransitionTargets
     * @return array<string, array{authorizationId: string, targetKey: string, acceptedTargetKey: string, url: string}>
     */
    public function projectAvailableActions(
        OmrWorkbenchProjection $projection,
        User $actor,
        OmrAcceptedCaptureReceipt $accepted,
        array $urls,
        array $authorizedTransitionTargets = [],
    ): array {
        if (isset($projection->context['actor_id'])
            && (int) $projection->context['actor_id'] !== (int) $actor->getAuthIdentifier()) {
            return [];
        }

        $acceptedTarget = $accepted->target;
        if ($accepted->operationId === '' || $accepted->pageCount < 1 || $accepted->pageCount > 4
            || $accepted->targetKey !== ($acceptedTarget['targetKey'] ?? null)
            || ! $this->isCanonicalTransitionTarget($projection, $acceptedTarget, $acceptedTarget)) {
            return [];
        }

        $grants = [];
        $current = $this->forProjection($projection, $actor);
        $acceptedPageNo = (int) ($acceptedTarget['pageNo'] ?? 0);
        $url = $urls['next_page'] ?? null;
        if ($current !== null && $acceptedPageNo > 0 && $acceptedPageNo < $accepted->pageCount
            && $this->samePhysicalGroup($acceptedTarget, $current)
            && (int) $current['pageNo'] === $acceptedPageNo + 1
            && $current['material_identity'] === ($acceptedTarget['material_identity'] ?? null)
            && ($accepted->sheetId === null || (int) ($current['sheetId'] ?? 0) === $accepted->sheetId)
            && is_string($url) && $url !== '') {
            $grants['next_page'] = $this->actionGrant(
                $projection,
                $actor,
                'next_page',
                $accepted,
                $current,
                $url,
            );
        }

        foreach (['next_set', 'redo_last'] as $action) {
            $target = $authorizedTransitionTargets[$action] ?? null;
            $url = $urls[$action] ?? null;
            if (! is_array($target) || ! is_string($url) || $url === ''
                || ! $this->isCanonicalTransitionTarget($projection, $acceptedTarget, $target)) {
                continue;
            }

            $targetPageNo = (int) ($target['pageNo'] ?? 0);
            $validTransition = $action === 'next_set'
                ? $acceptedPageNo === $accepted->pageCount && $targetPageNo === 1
                    && ! $this->samePhysicalGroup($acceptedTarget, $target)
                : $targetPageNo === $acceptedPageNo && $this->samePhysicalGroup($acceptedTarget, $target);
            if ($validTransition) {
                $grants[$action] = $this->actionGrant($projection, $actor, $action, $accepted, $target, $url);
            }
        }

        return $grants;
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function samePhysicalGroup(array $left, array $right): bool
    {
        if (($left['pending_capture_id'] ?? null) !== null) {
            return $left['pending_capture_id'] === ($right['pending_capture_id'] ?? null);
        }
        if (($left['instanceId'] ?? null) !== null || ($right['instanceId'] ?? null) !== null) {
            return ($left['instanceId'] ?? null) === ($right['instanceId'] ?? null)
                && ($left['run_id'] ?? null) === ($right['run_id'] ?? null)
                && ($left['clientCaptureId'] ?? null) === ($right['clientCaptureId'] ?? null);
        }

        return is_string($left['clientCaptureId'] ?? null)
            && $left['clientCaptureId'] === ($right['clientCaptureId'] ?? null);
    }

    /** @param array<string, mixed> $acceptedTarget @param array<string, mixed> $target */
    private function isCanonicalTransitionTarget(
        OmrWorkbenchProjection $projection,
        array $acceptedTarget,
        array $target,
    ): bool {
        if (($target['workbenchId'] ?? null) !== $projection->workbenchId
            || ! is_string($target['clientCaptureId'] ?? null)
            || ! is_numeric($target['pageNo'] ?? null) || (int) $target['pageNo'] < 1 || (int) $target['pageNo'] > 4
            || ! is_numeric($target['captureRevision'] ?? null) || (int) $target['captureRevision'] < 1
            || ! array_key_exists('instanceId', $target)
            || ! array_key_exists('sheetId', $target)
            || ! is_string($target['targetKey'] ?? null)
            || ! is_array($target['material_identity'] ?? null)
            || $target['material_identity'] !== ($acceptedTarget['material_identity'] ?? null)) {
            return false;
        }

        foreach (['execution_kind', 'diagnostic_purpose', 'session_id', 'batch_id', 'run_id', 'template_version_id', 'booklet_version_id', 'batch_version', 'registration_mode', 'qr_mode', 'page_count'] as $field) {
            if (($target[$field] ?? null) !== ($acceptedTarget[$field] ?? null)) {
                return false;
            }
        }

        $canonicalKey = json_encode([
            $target['workbenchId'], $target['clientCaptureId'], (int) $target['pageNo'],
            (int) $target['captureRevision'], $target['instanceId'], $target['sheetId'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash_equals($canonicalKey, $target['targetKey']);
    }

    /** @param array<string, mixed> $target @return array{authorizationId: string, targetKey: string, acceptedTargetKey: string, url: string} */
    private function actionGrant(
        OmrWorkbenchProjection $projection,
        User $actor,
        string $action,
        OmrAcceptedCaptureReceipt $accepted,
        array $target,
        string $url,
    ): array {
        return [
            'authorizationId' => hash('sha256', implode('|', [
                (string) $actor->getAuthIdentifier(), $projection->workbenchId, $action,
                $accepted->operationId, $target['targetKey'], (string) $projection->revision,
            ])),
            'targetKey' => $target['targetKey'],
            'acceptedTargetKey' => $accepted->targetKey,
            'url' => $url,
        ];
    }

    /** @param array<string, mixed> $projected @param array<string, mixed> $material */
    private function normalize(OmrWorkbenchProjection $projection, array $projected, array $material): ?array
    {
        $clientCaptureId = $projected['clientCaptureId'] ?? $projected['client_capture_id'] ?? $projected['id'] ?? null;
        $pageNo = $projected['pageNo'] ?? $projected['page_no'] ?? $projected['active_page_no'] ?? null;
        $captureRevision = $projected['captureRevision'] ?? $projected['capture_revision']
            ?? $projected['target_revision'] ?? $projected['pending_capture_revision'] ?? $projected['revision'] ?? null;
        if (! is_string($clientCaptureId) || trim($clientCaptureId) === '' || ! is_numeric($pageNo)
            || (int) $pageNo < 1 || ! is_numeric($captureRevision) || (int) $captureRevision < 1) {
            return null;
        }

        $target = [
            'workbenchId' => $projection->workbenchId,
            'clientCaptureId' => $clientCaptureId,
            'pageNo' => (int) $pageNo,
            'captureRevision' => (int) $captureRevision,
            'instanceId' => $projected['instanceId'] ?? $projected['instance_id'] ?? null,
            'sheetId' => $projected['sheetId'] ?? $projected['sheet_id'] ?? null,
            ...$material,
        ];
        $target['targetKey'] = json_encode([
            $target['workbenchId'], $target['clientCaptureId'], $target['pageNo'], $target['captureRevision'],
            $target['instanceId'], $target['sheetId'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $target['target_key'] = $target['targetKey'];
        $target['client_capture_id'] = $target['clientCaptureId'];
        $target['page_no'] = $target['pageNo'];
        $target['capture_revision'] = $target['captureRevision'];
        $target['instance_id'] = $target['instanceId'];
        $target['sheet_id'] = $target['sheetId'];

        return $target;
    }

    /** @param array<string, mixed> $pending @param array<string, mixed> $material */
    private function pendingTarget(
        OmrWorkbenchProjection $projection,
        User $actor,
        array $pending,
        array $material,
    ): ?array {
        $setId = $pending['id'] ?? null;
        $pageNo = (int) ($pending['active_page_no'] ?? $pending['page_no'] ?? 0);
        $pageCount = (int) ($pending['page_count'] ?? 0);
        $revision = (int) ($pending['revision'] ?? $pending['pending_capture_revision'] ?? 0);
        $status = $pending['status'] ?? null;
        if (! is_string($setId) || $setId === '' || $pageCount < 1 || $pageCount > 4 || $revision < 1
            || ! in_array($status, [
                OmrPendingCaptureSet::STATUS_PENDING,
                OmrPendingCaptureSet::STATUS_CAPTURED,
                OmrPendingCaptureSet::STATUS_READY,
            ], true) || ($pending['adopted_sheet_id'] ?? null) !== null) {
            return null;
        }

        $targetMaterial = [
            ...$material,
            'pending_capture_id' => $setId,
            'pending_capture_revision' => $revision,
            'page_count' => $pageCount,
            'supplier_stock_allocation_id' => $pending['supplier_stock_allocation_id'] ?? null,
            'identity_mode' => $pending['identity_mode'] ?? null,
        ];
        $redoTarget = $this->pendingRedoTarget($projection, $actor, $pending, $targetMaterial);
        if ($redoTarget !== null) {
            return $redoTarget;
        }

        if ($status !== OmrPendingCaptureSet::STATUS_PENDING || $pageNo < 1 || $pageNo > $pageCount) {
            return null;
        }

        $pageTarget = null;
        foreach ((array) ($pending['pages'] ?? []) as $page) {
            if (is_array($page) && (int) ($page['page_no'] ?? 0) === $pageNo) {
                $pageTarget = $page;
                break;
            }
        }
        if ($pageTarget === null || ($pageTarget['active'] ?? false) !== true
            || (int) ($pageTarget['capture_count'] ?? 0) > 0) {
            return null;
        }

        $clientCaptureId = $pageTarget['client_capture_id'] ?? $pending['client_capture_id'] ?? null;
        if (! is_string($clientCaptureId) || $clientCaptureId === '') {
            $clientCaptureId = $this->stableUuid('nexiam:omr:pending-page:'.$setId.':'.$pageNo);
        }

        return $this->normalize($projection, [
            'client_capture_id' => $clientCaptureId,
            'page_no' => $pageNo,
            'capture_revision' => $revision,
            'instance_id' => $pending['instance_id'] ?? null,
            'sheet_id' => $pending['adopted_sheet_id'] ?? null,
        ], $targetMaterial);
    }

    /** @param array<string, mixed> $pending @param array<string, mixed> $material */
    private function pendingRedoTarget(
        OmrWorkbenchProjection $projection,
        User $actor,
        array $pending,
        array $material,
    ): ?array {
        $grant = OmrCameraCommand::query()
            ->where('workbench_key', hash('sha256', $projection->workbenchId))
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('kind', 'CAPTURE_TARGET_GRANT')
            ->latest('id')
            ->first();
        $target = is_array($grant?->target_json) ? $grant->target_json : [];
        $setId = $pending['id'] ?? null;
        $revision = (int) ($pending['revision'] ?? 0);
        $pageNo = (int) ($target['pageNo'] ?? $target['page_no'] ?? 0);
        $sourcePageId = (int) ($target['redo_source_page_id'] ?? 0);
        if ($grant === null || $grant->domain_outcome !== 'granted') {
            return null;
        }
        if (! is_string($target['redo_authorization_id'] ?? null) || $target['redo_authorization_id'] === ''
            || ! is_string($target['redo_reason'] ?? null) || trim($target['redo_reason']) === ''
            || ! Str::isUuid((string) ($target['redo_operation_id'] ?? ''))
            || $sourcePageId !== (int) ($target['redo_source_evidence_id'] ?? 0)
            || (int) ($target['redo_source_page_revision'] ?? 0) < 1
            || (int) ($target['redo_source_page_revision'] ?? 0) > $revision
            || ! is_string($target['redo_accepted_target_key'] ?? null)
            || $target['redo_accepted_target_key'] === ''
            || ($target['pending_capture_id'] ?? null) !== $setId
            || (int) ($target['pending_capture_revision'] ?? 0) !== $revision
            || $pageNo < 1 || $pageNo > (int) ($pending['page_count'] ?? 0)) {
            return null;
        }
        $sourceIsCurrent = false;
        foreach ((array) ($pending['pages'] ?? []) as $page) {
            if (is_array($page) && (int) ($page['page_no'] ?? 0) === $pageNo
                && (int) ($page['capture_id'] ?? 0) === $sourcePageId) {
                $sourceIsCurrent = true;
                break;
            }
        }
        if (! $sourceIsCurrent) {
            return null;
        }

        $operation = OmrCameraCommand::query()
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('workbench_key', hash('sha256', $projection->workbenchId))
            ->where('operation_id', (string) $target['redo_operation_id'])
            ->where('kind', 'CAPTURE_RECEIPT')
            ->where('domain_outcome', 'accepted')
            ->first();
        $receipt = is_array($operation?->domain_receipt_json) ? $operation->domain_receipt_json : [];
        $sourceTarget = is_array($operation?->target_json) ? $operation->target_json : [];
        if ($operation !== null) {
            if ((int) ($receipt['page_id'] ?? 0) !== $sourcePageId
                || (isset($receipt['pending_capture_id']) && $receipt['pending_capture_id'] !== $setId)
                || (int) ($sourceTarget['pageNo'] ?? $sourceTarget['page_no'] ?? 0) !== $pageNo
                || ($sourceTarget['pending_capture_id'] ?? null) !== $setId
                || (isset($target['redo_accepted_target_key']) && is_string($sourceTarget['targetKey'] ?? $sourceTarget['target_key'] ?? null)
                    && ! hash_equals($target['redo_accepted_target_key'], (string) ($sourceTarget['targetKey'] ?? $sourceTarget['target_key'])))) {
                return null;
            }
        }

        foreach ([
            'execution_kind', 'session_id', 'batch_id', 'run_id', 'template_version_id',
            'booklet_version_id', 'batch_version', 'page_count',
            'material_identity', 'supplier_stock_allocation_id',
        ] as $field) {
            if (($target[$field] ?? null) !== ($material[$field] ?? null)) {
                return null;
            }
        }
        if (isset($target['identity_mode']) && $target['identity_mode'] !== null && ($material['identity_mode'] ?? null) !== null && $target['identity_mode'] !== $material['identity_mode']) {
            return null;
        }

        $normalized = $this->normalize($projection, $target, $material);
        if ($normalized === null || ! is_string($target['targetKey'] ?? null)
            || ! hash_equals((string) $normalized['targetKey'], $target['targetKey'])) {
            return null;
        }
        foreach ([
            'redo_authorization_id', 'redo_reason', 'redo_operation_id', 'redo_source_page_id',
            'redo_source_page_revision', 'redo_source_evidence_id', 'redo_accepted_target_key',
            'replaces_capture_id',
        ] as $field) {
            $normalized[$field] = $target[$field] ?? null;
        }

        return $normalized;
    }

    private function productionTarget(OmrWorkbenchProjection $projection, User $actor, ?int $captureSheetId): ?array
    {
        $context = $projection->context;
        $batchId = (int) ($context['run_id'] ?? 0);
        $sessionId = (int) ($context['session_id'] ?? 0);
        if ($batchId < 1 || $sessionId < 1
            || $projection->workbenchId !== sprintf('production:%d:%d', $sessionId, $batchId)) {
            return null;
        }

        $batch = OmrCaptureBatch::query()
            ->whereKey($batchId)
            ->where('grading_session_id', $sessionId)
            ->with(['gradingSession.examSession.academicCycle', 'templateVersion', 'bookletVersion'])
            ->first();
        if ($batch === null || $batch->status !== OmrCaptureBatch::STATUS_OPEN
            || $batch->templateVersion === null || $batch->bookletVersion === null) {
            return null;
        }

        $cycle = $batch->gradingSession?->examSession?->academicCycle;
        $cycleCode = trim((string) ($cycle?->code ?? ''));
        if ($cycle === null || $cycleCode === '' || ! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, [
            'institution_id' => (int) $cycle->institution_id,
            'cycle' => $cycleCode,
            'office' => 'guidance',
        ])) {
            return null;
        }

        $pending = $context['pending_capture'] ?? null;
        if (is_array($pending)) {
            return $captureSheetId === null
                ? $this->pendingTarget($projection, $actor, $pending, $this->pinnedMaterial($batch))
                : null;
        }
        $redoTarget = $this->signedRedoTarget($projection, $actor, $batch);
        if ($redoTarget !== null
            && ($captureSheetId === null || (int) ($redoTarget['sheetId'] ?? 0) === $captureSheetId)) {
            return $redoTarget;
        }
        if ($projection->phase !== OmrWorkbenchProjection::PHASE_CAPTURE) {
            return null;
        }

        return $this->signedProductionTarget($projection, $actor, $batch, $captureSheetId);
    }

    /** @return array<string, mixed> */
    private function pinnedMaterial(OmrCaptureBatch $batch): array
    {
        $version = $batch->templateVersion;
        $booklet = $batch->bookletVersion;

        return [
            'execution_kind' => OmrExecutionKind::Production->value,
            'session_id' => (int) $batch->grading_session_id,
            'batch_id' => (int) $batch->id,
            'run_id' => (int) $batch->id,
            'template_version_id' => (int) $version->id,
            'booklet_version_id' => (int) $booklet->id,
            'batch_version' => (int) $batch->version,
            'registration_mode' => $version->registration_mode->value,
            'qr_mode' => $version->qr_mode->value,
            'page_count' => (int) $version->page_count,
            'material_identity' => [
                'template_version_id' => (int) $version->id,
                'template_version' => (int) $version->version,
                'template_bundle_hash' => $version->native_bundle_hash,
                'template_json_hash' => $version->template_json_hash,
                'booklet_version_id' => (int) $booklet->id,
                'booklet_version' => (int) $booklet->version,
            ],
        ];
    }

    private function signedProductionTarget(OmrWorkbenchProjection $projection, User $actor, OmrCaptureBatch $batch, ?int $captureSheetId): ?array
    {
        $pageCount = (int) $batch->templateVersion->page_count;
        if ($pageCount < 1 || $pageCount > 4) {
            return null;
        }

        $redoTarget = $this->signedRedoTarget($projection, $actor, $batch);
        if ($redoTarget !== null
            && ($captureSheetId === null || (int) ($redoTarget['sheetId'] ?? 0) === $captureSheetId)) {
            return $redoTarget;
        }

        $incompleteQuery = OmrCaptureSheet::query()
            ->where('batch_id', $batch->id)
            ->where('expected_page_count', $pageCount)
            ->whereIn('status', [OmrCaptureSheet::STATUS_PENDING_PAGES, OmrCaptureSheet::STATUS_RECEIVED])
            ->with(['pages:id,sheet_id,page_no,asset_path'])
            ->orderBy('id');
        if ($captureSheetId !== null) {
            $incompleteQuery->whereKey($captureSheetId);
        }
        $incomplete = $incompleteQuery
            ->get(['id', 'batch_id', 'client_capture_id', 'expected_page_count', 'status', 'version']);
        if (($captureSheetId !== null && $incomplete->isEmpty())
            || ($captureSheetId === null && $incomplete->count() > 1)) {
            return null;
        }

        $sheet = $incomplete->first();
        if ($sheet !== null) {
            $pages = $sheet->pages->keyBy('page_no');
            $pageNo = null;
            for ($candidate = 1; $candidate <= $pageCount; $candidate++) {
                $page = $pages->get($candidate);
                if ($page === null) {
                    $pageNo = $candidate;
                    break;
                }
                if ($page->asset_path === null) {
                    return null;
                }
            }
            if ($pageNo === null) {
                return null;
            }

            return $this->normalize($projection, [
                'client_capture_id' => (string) $sheet->client_capture_id,
                'page_no' => $pageNo,
                'capture_revision' => max(1, (int) $batch->version),
                'sheet_id' => (int) $sheet->id,
            ], $this->pinnedMaterial($batch));
        }
        if ($captureSheetId !== null) {
            return null;
        }

        $grant = OmrCameraCommand::query()
            ->where('workbench_key', hash('sha256', $projection->workbenchId))
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('kind', 'CAPTURE_TARGET_GRANT')
            ->latest('id')
            ->first();
        if ($grant !== null) {
            $granted = is_array($grant->target_json) ? $grant->target_json : [];
            $grantedGroupId = $granted['client_capture_id'] ?? $granted['clientCaptureId'] ?? null;
            if (($granted['batch_id'] ?? null) !== (int) $batch->id
                || ($granted['page_no'] ?? $granted['pageNo'] ?? null) !== 1
                || ! is_string($grantedGroupId)
                || OmrCaptureSheet::query()->where('batch_id', $batch->id)->where('client_capture_id', $grantedGroupId)->exists()) {
                return null;
            }

            return $this->normalize($projection, $granted, $this->pinnedMaterial($batch));
        }
        if ($batch->sheets()->exists()) {
            return null;
        }

        return $this->normalize($projection, [
            'client_capture_id' => $this->stableUuid('nexiam:omr:camera-target:'.$batch->id.':'.$actor->getAuthIdentifier()),
            'page_no' => 1,
            'capture_revision' => max(1, (int) $batch->version),
        ], $this->pinnedMaterial($batch));
    }

    private function signedRedoTarget(
        OmrWorkbenchProjection $projection,
        User $actor,
        OmrCaptureBatch $batch,
    ): ?array {
        $grant = OmrCameraCommand::query()
            ->where('workbench_key', hash('sha256', $projection->workbenchId))
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('kind', 'CAPTURE_TARGET_GRANT')
            ->latest('id')
            ->first();
        $target = is_array($grant?->target_json) ? $grant->target_json : [];
        $sourcePageId = (int) ($target['redo_source_page_id'] ?? 0);
        $sourcePageRevision = (int) ($target['redo_source_page_revision'] ?? 0);
        $sourceEvidenceId = (int) ($target['redo_source_evidence_id'] ?? 0);
        $sheetId = (int) ($target['sheetId'] ?? 0);
        $pageNo = (int) ($target['pageNo'] ?? 0);
        $pendingCaptureId = $target['pending_capture_id'] ?? null;
        if ($grant?->domain_outcome !== 'granted'
            || ! $this->isCanonicalTransitionTarget($projection, $target, $target)
            || ! is_string($target['redo_authorization_id'] ?? null) || $target['redo_authorization_id'] === ''
            || ! is_string($target['redo_reason'] ?? null) || trim($target['redo_reason']) === ''
            || ! Str::isUuid((string) ($target['redo_operation_id'] ?? ''))
            || $sourcePageId < 1 || $sourcePageRevision < 1 || $sheetId < 1 || $pageNo < 1
            || (is_string($pendingCaptureId) && $sourceEvidenceId < 1)
            || ! is_string($target['redo_accepted_target_key'] ?? null)
            || $target['redo_accepted_target_key'] === '') {
            return null;
        }

        $sheet = OmrCaptureSheet::query()
            ->whereKey($sheetId)
            ->where('batch_id', $batch->id)
            ->whereIn('status', [
                OmrCaptureSheet::STATUS_PENDING_PAGES,
                OmrCaptureSheet::STATUS_RECEIVED,
                OmrCaptureSheet::STATUS_QUEUED,
                OmrCaptureSheet::STATUS_PROCESSING,
                OmrCaptureSheet::STATUS_NEEDS_REVIEW,
                OmrCaptureSheet::STATUS_REVIEWED,
            ])
            ->with(['pages:id,sheet_id,page_no,asset_path,current_revision'])
            ->first();
        $page = $sheet?->pages->firstWhere('page_no', $pageNo);
        if ($batch->gradingSession === null || $batch->gradingSession->status === GradingSession::STATUS_FINALIZED
            || $sheet === null || (int) $sheet->expected_page_count !== (int) $batch->templateVersion->page_count
            || $pageNo > (int) $sheet->expected_page_count || $page === null || $page->asset_path === null
            || (int) $page->id !== $sourcePageId || (int) $page->current_revision !== $sourcePageRevision
            || (int) ($target['captureRevision'] ?? 0) !== $sourcePageRevision + 1
            || ! $this->pageRevisionLineageMatches($page, $sourcePageRevision)) {
            return null;
        }

        $operation = OmrCameraCommand::query()
            ->where('actor_id', (int) $actor->getAuthIdentifier())
            ->where('workbench_key', hash('sha256', $projection->workbenchId))
            ->where('operation_id', (string) $target['redo_operation_id'])
            ->where('kind', 'CAPTURE_RECEIPT')
            ->where('domain_outcome', 'accepted')
            ->whereNull('domain_receipt_json->action')
            ->first();
        $receipt = is_array($operation?->domain_receipt_json) ? $operation->domain_receipt_json : [];
        $sourceTarget = is_array($operation?->target_json) ? $operation->target_json : [];
        if ($operation === null
            || ! is_string($sourceTarget['targetKey'] ?? null)
            || ! hash_equals((string) $target['redo_accepted_target_key'], $sourceTarget['targetKey'])
            || (int) ($sourceTarget['pageNo'] ?? $sourceTarget['page_no'] ?? 0) !== $pageNo) {
            return null;
        }

        $material = $this->pinnedMaterial($batch);
        if (is_string($pendingCaptureId)) {
            $material = $this->adoptedPendingRedoMaterial(
                $batch,
                $sheet,
                $page,
                $pendingCaptureId,
                $target,
                $sourceTarget,
                $receipt,
                $material,
            );
            if ($material === null) {
                return null;
            }
        } elseif (
            (int) ($receipt['page_id'] ?? 0) !== $sourcePageId
            || (int) ($receipt['sheet_id'] ?? 0) !== $sheetId
            || ($target['clientCaptureId'] ?? null) !== $sheet->client_capture_id
            || ($sourceTarget['clientCaptureId'] ?? $sourceTarget['client_capture_id'] ?? null) !== $sheet->client_capture_id
        ) {
            return null;
        }

        foreach ([
            'execution_kind', 'session_id', 'batch_id', 'run_id', 'template_version_id',
            'booklet_version_id', 'batch_version', 'registration_mode', 'qr_mode', 'page_count',
            'material_identity', 'pending_capture_id', 'pending_capture_revision',
            'supplier_stock_allocation_id', 'identity_mode',
        ] as $field) {
            if (($target[$field] ?? null) !== ($material[$field] ?? null)) {
                return null;
            }
        }

        $normalized = $this->normalize($projection, $target, $material);
        if ($normalized === null || ! is_string($target['targetKey'] ?? null)
            || ! hash_equals((string) $normalized['targetKey'], $target['targetKey'])) {
            return null;
        }
        foreach ([
            'redo_authorization_id', 'redo_reason', 'redo_operation_id', 'redo_source_page_id',
            'redo_source_page_revision', 'redo_source_evidence_id', 'redo_accepted_target_key',
        ] as $field) {
            if (array_key_exists($field, $target)) {
                $normalized[$field] = $target[$field];
            }
        }

        return $normalized;
    }

    private function pageRevisionLineageMatches(OmrCaptureSheetPage $page, int $expectedRevision): bool
    {
        if ($expectedRevision < 1 || (int) $page->current_revision !== $expectedRevision) {
            return false;
        }

        $currentRevisionId = (int) ($page->current_revision_id ?? 0);
        if ($currentRevisionId < 1) {
            return $expectedRevision === 1;
        }

        $revisions = OmrCaptureSheetPageRevision::query()
            ->where('page_id', $page->id)
            ->get(['id', 'page_id', 'revision', 'revision_kind', 'source_revision_id'])
            ->keyBy('id');
        $current = $revisions->get($currentRevisionId);
        if ($current === null || (int) $current->revision !== $expectedRevision) {
            return false;
        }

        $visited = [];
        for ($step = 0; $step < $revisions->count(); $step++) {
            if ($current->revision_kind === OmrCaptureSheetPageRevision::KIND_SOURCE) {
                return (int) $current->revision === 1 && $current->source_revision_id === null;
            }

            $currentId = (int) $current->getKey();
            if (isset($visited[$currentId])) {
                return false;
            }
            $visited[$currentId] = true;

            $sourceRevisionId = (int) ($current->source_revision_id ?? 0);
            $source = $revisions->get($sourceRevisionId);
            if ($source === null || (int) $source->page_id !== (int) $page->id) {
                return false;
            }

            $currentRevision = (int) $current->revision;
            $sourceRevision = (int) $source->revision;
            $expectedSourceRevision = $current->revision_kind === OmrCaptureSheetPageRevision::KIND_REPLACEMENT
                ? $currentRevision - 1
                : $currentRevision;
            if (! in_array($current->revision_kind, [
                OmrCaptureSheetPageRevision::KIND_REPLACEMENT,
                OmrCaptureSheetPageRevision::KIND_ANALYSIS,
                OmrCaptureSheetPageRevision::KIND_SNAPSHOT,
            ], true) || $sourceRevision !== $expectedSourceRevision) {
                return false;
            }

            $current = $source;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $sourceTarget
     * @param  array<string, mixed>  $receipt
     * @param  array<string, mixed>  $material
     * @return array<string, mixed>|null
     */
    private function adoptedPendingRedoMaterial(
        OmrCaptureBatch $batch,
        OmrCaptureSheet $sheet,
        OmrCaptureSheetPage $page,
        string $pendingCaptureId,
        array $target,
        array $sourceTarget,
        array $receipt,
        array $material,
    ): ?array {
        $evidenceId = (int) ($target['redo_source_evidence_id'] ?? 0);
        $receiptPageId = (int) ($receipt['page_id'] ?? 0);
        if (($sourceTarget['pending_capture_id'] ?? null) !== $pendingCaptureId
            || (isset($receipt['pending_capture_id']) && $receipt['pending_capture_id'] !== $pendingCaptureId)
            || (int) ($sourceTarget['pageNo'] ?? $sourceTarget['page_no'] ?? 0) !== (int) $page->page_no
            || $evidenceId < 1 || ! in_array($receiptPageId, [$evidenceId, (int) $page->id], true)) {
            return null;
        }

        $set = OmrPendingCaptureSet::query()
            ->where('public_id', $pendingCaptureId)
            ->where('production_batch_id', $batch->id)
            ->where('adopted_sheet_id', $sheet->id)
            ->where('status', OmrPendingCaptureSet::STATUS_READY)
            ->where('template_version_id', $batch->template_version_id)
            ->first();
        if ($set === null || (int) ($target['pending_capture_revision'] ?? 0) !== (int) $set->revision
            || (int) $set->page_count !== (int) $sheet->expected_page_count
            || (string) $sheet->client_capture_id !== $pendingCaptureId
            || ($target['supplier_stock_allocation_id'] ?? null) !== $set->supplier_stock_allocation_id
            || ($target['identity_mode'] ?? null) !== $set->identity_mode) {
            return null;
        }

        $evidence = OmrPendingCapturePageEvidence::query()
            ->whereKey($evidenceId)
            ->where('set_id', $set->id)
            ->where('page_no', (int) $page->page_no)
            ->first();
        if ($evidence === null) {
            return null;
        }

        $claim = OmrQrDisabledPageClaim::query()
            ->where('set_id', $set->id)
            ->where('evidence_id', $evidence->id)
            ->where('capture_sheet_page_id', $page->id)
            ->where('page_no', (int) $page->page_no)
            ->first();
        if ($claim === null || ! hash_equals((string) $claim->input_hash, (string) $evidence->input_hash)
            || (int) ($claim->supplier_stock_allocation_id ?? 0) !== (int) ($set->supplier_stock_allocation_id ?? 0)
            || (int) ($claim->issuance_id ?? 0) !== (int) ($set->issuance_id ?? 0)) {
            return null;
        }

        return [
            ...$material,
            'pending_capture_id' => $pendingCaptureId,
            'pending_capture_revision' => (int) $set->revision,
            'page_count' => (int) $set->page_count,
            'supplier_stock_allocation_id' => $set->supplier_stock_allocation_id === null
                ? null
                : (int) $set->supplier_stock_allocation_id,
            'identity_mode' => $set->identity_mode,
        ];
    }

    private function stableUuid(string $seed): string
    {
        $bytes = substr(hash('sha256', $seed, true), 0, 16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return strtolower(sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12)));
    }
}
