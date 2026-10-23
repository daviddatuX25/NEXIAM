<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Operational;

use App\Assessment\Actions\Omr\Camera\ExecuteOmrWorkbenchCaptureAction;
use App\Assessment\Omr\Operational\OmrDiagnosticRunStatusSnapshot;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DiscardOmrDiagnosticCapture
{
    public function __construct(
        private readonly OmrDiagnosticRunStatusSnapshot $snapshot,
        private readonly RecordAuditEvent $audit,
        private readonly ExecuteOmrWorkbenchCaptureAction $captureActions,
    ) {}

    public function handle(
        OmrDiagnosticRun $run,
        OmrDiagnosticCapture $capture,
        User $actor,
        int $expectedRunRevision,
        int $expectedCaptureVersion,
        ?string $reason = null,
    ): OmrDiagnosticRun {
        if ((int) $capture->diagnostic_run_id !== (int) $run->id) {
            throw new RuntimeException('omr_diagnostic_capture_run_mismatch');
        }

        return DB::transaction(function () use ($run, $capture, $actor, $expectedRunRevision, $expectedCaptureVersion, $reason): OmrDiagnosticRun {
            $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($run->id);
            $lockedCapture = OmrDiagnosticCapture::query()
                ->whereKey($capture->id)
                ->where('diagnostic_run_id', $lockedRun->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->snapshot->assertCanProject($lockedRun, $actor);
            if ($lockedRun->status === OmrDiagnosticRun::STATUS_CANCELLED) {
                throw new RuntimeException('omr_diagnostic_run_is_terminal');
            }
            if ($lockedCapture->failure_code === 'discarded_by_operator') {
                return $lockedRun;
            }
            if ($lockedRun->isTerminal() || $lockedRun->sealed_at !== null) {
                throw new RuntimeException('omr_diagnostic_run_is_terminal');
            }
            if ((int) $lockedRun->revision !== $expectedRunRevision) {
                throw new RuntimeException('omr_diagnostic_run_revision_stale');
            }
            if ((int) $lockedCapture->version !== $expectedCaptureVersion) {
                throw new RuntimeException('omr_diagnostic_capture_version_stale');
            }
            if (in_array($lockedCapture->status, [
                OmrDiagnosticCapture::STATUS_QUEUED,
                OmrDiagnosticCapture::STATUS_PROCESSING,
            ], true)) {
                throw new RuntimeException('omr_diagnostic_capture_outcome_unknown');
            }
            $otherInFlight = OmrDiagnosticCapture::query()
                ->where('diagnostic_run_id', $lockedRun->id)
                ->where('id', '!=', $lockedCapture->id)
                ->whereIn('status', [
                    OmrDiagnosticCapture::STATUS_QUEUED,
                    OmrDiagnosticCapture::STATUS_PROCESSING,
                ])
                ->exists();
            if ($otherInFlight) {
                throw new RuntimeException('omr_diagnostic_capture_outcome_unknown');
            }
            if ($this->captureActions->hasUnresolvedCaptureOperation($actor, 'diagnostic:'.$lockedRun->public_id)) {
                throw new RuntimeException('omr_diagnostic_capture_outcome_unknown');
            }

            $discardReason = trim((string) ($reason ?: 'Discarded by operator'));
            $priorEvidence = is_array($lockedCapture->evidence_json) ? $lockedCapture->evidence_json : [];
            $priorEvidence['operator_disposition'] = [
                'kind' => 'discarded',
                'reason' => $discardReason,
                'actor_id' => (int) $actor->getAuthIdentifier(),
                'discarded_at' => now()->toIso8601String(),
                'prior_recognition' => [
                    'status' => (string) $lockedCapture->status,
                    'failure_code' => $lockedCapture->failure_code,
                    'failure_detail' => $lockedCapture->failure_detail,
                    'completed_at' => $lockedCapture->completed_at?->toIso8601String(),
                ],
            ];
            $priorEvidence['operator_disposition']['prior_recognition']['evidence_hash'] = $lockedCapture->evidence_hash;
            $evidenceJson = json_encode($priorEvidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $evidenceHash = hash('sha256', $evidenceJson);
            $lockedCapture->forceFill([
                'status' => OmrDiagnosticCapture::STATUS_FAILED,
                'failure_code' => 'discarded_by_operator',
                'failure_detail' => $discardReason,
                'evidence_json' => $priorEvidence,
                'evidence_hash' => $evidenceHash,
                'completed_at' => now(),
                'version' => (int) $lockedCapture->version + 1,
            ])->saveOrFail();

            $link = is_array($priorEvidence['pending_page_link'] ?? null)
                ? $priorEvidence['pending_page_link']
                : null;
            $setId = (int) ($link['capture_set_id'] ?? 0);
            $sourceEvidenceId = (int) ($link['pending_page_evidence_id'] ?? 0);
            $pageNo = (int) ($link['page_no'] ?? 0);
            $retakeSet = null;
            $retakeEvidence = null;
            if ($setId > 0 && $sourceEvidenceId > 0 && $pageNo > 0) {
                $set = OmrPendingCaptureSet::query()
                    ->whereKey($setId)
                    ->where('diagnostic_run_id', $lockedRun->id)
                    ->whereNull('production_batch_id')
                    ->lockForUpdate()
                    ->first();
                $sourceEvidence = OmrPendingCapturePageEvidence::query()
                    ->whereKey($sourceEvidenceId)
                    ->where('set_id', $setId)
                    ->where('page_no', $pageNo)
                    ->first();
                $latestEvidence = OmrPendingCapturePageEvidence::query()
                    ->where('set_id', $setId)
                    ->where('page_no', $pageNo)
                    ->orderByDesc('id')
                    ->first();

                if ($set !== null
                    && $sourceEvidence !== null
                    && $latestEvidence !== null
                    && (int) $latestEvidence->id === $sourceEvidenceId
                    && hash_equals((string) ($link['input_hash'] ?? ''), (string) $sourceEvidence->input_hash)
                    && $set->adopted_sheet_id === null
                    && ! $set->isExpired()
                    && in_array($set->status, [
                        OmrPendingCaptureSet::STATUS_PENDING,
                        OmrPendingCaptureSet::STATUS_CAPTURED,
                        OmrPendingCaptureSet::STATUS_READY,
                    ], true)) {
                    $set->forceFill([
                        'active_page_no' => $pageNo,
                        'status' => $set->pairing_code === null
                            ? OmrPendingCaptureSet::STATUS_PENDING
                            : $set->status,
                        'revision' => (int) $set->revision + 1,
                    ])->saveOrFail();
                    $retakeSet = $set;
                    $retakeEvidence = $sourceEvidence;
                }
            }

            $lockedRun->forceFill([
                'status' => OmrDiagnosticRun::STATUS_OPEN,
                'phase' => OmrDiagnosticRun::PHASE_CAPTURE,
                'completed_at' => null,
                'revision' => (int) $lockedRun->revision + 1,
            ])->saveOrFail();

            if ($retakeSet !== null && $retakeEvidence !== null
                && ! $this->captureActions->persistDiscardRetakeTarget(
                    $actor,
                    $lockedRun,
                    $retakeSet->fresh(),
                    $lockedCapture->fresh(),
                    $retakeEvidence,
                    $discardReason,
                )) {
                throw new RuntimeException('omr_diagnostic_capture_outcome_unknown');
            }

            $this->audit->handle('omr.diagnostic_capture.discarded', [
                'diagnostic_run_id' => $lockedRun->id,
                'capture_id' => $lockedCapture->id,
                'client_capture_id' => $lockedCapture->client_capture_id,
                'reason' => $discardReason,
                'prior_recognition_status' => $priorEvidence['operator_disposition']['prior_recognition']['status'],
                'prior_recognition_failure_code' => $priorEvidence['operator_disposition']['prior_recognition']['failure_code'],
            ], (string) $actor->getAuthIdentifier());

            return $lockedRun;
        });
    }
}
