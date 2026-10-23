<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrScannerContextOutcome;
use App\Assessment\Omr\OmrScannerSessionResolver;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrPendingCapture;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

use function in_array;
use function is_array;
use function trim;

final class RefreshOmrPendingResolution
{
    public function __construct(
        private readonly OmrPendingCaptureAccess $pendingAccess,
        private readonly OmrScannerSessionResolver $sessionResolver,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(OmrPendingCapture $pending, User $actor): OmrPendingCapture
    {
        $this->pendingAccess->assertPending($actor, $pending);
        if ($pending->isTerminal() || trim((string) $pending->detected_applicant_number) === '') {
            return $pending;
        }
        if (! in_array($pending->status, [
            OmrPendingCapture::STATUS_RESOLUTION_REQUIRED,
            OmrPendingCapture::STATUS_NEEDS_RECOVERY,
        ], true)) {
            return $pending;
        }

        $resolution = is_array($pending->resolution_json) ? $pending->resolution_json : [];
        $context = $this->sessionResolver->discover((string) $pending->detected_applicant_number, $actor);
        $sessionId = $context->outcome === OmrScannerContextOutcome::AutoResolved && $context->gradingSession !== null
            ? (int) $context->gradingSession->id
            : null;
        $status = $sessionId === null
            ? OmrPendingCapture::STATUS_RESOLUTION_REQUIRED
            : OmrPendingCapture::STATUS_READY;
        $resolutionCode = $sessionId === null ? $context->resolutionCode : null;
        $nextResolution = [
            'outcome' => $context->outcome->value,
            'code' => $resolutionCode,
            'resolution_code' => $resolutionCode,
            'candidates' => $context->candidates,
            'source' => 'monitoring_recheck',
            ...($sessionId === null ? [] : ['grading_session_id' => $sessionId]),
        ];
        if ($pending->status === $status
            && (int) ($pending->grading_session_id ?? 0) === (int) ($sessionId ?? 0)
            && $this->sameResolution($resolution, $nextResolution)) {
            return $pending;
        }

        try {
            return DB::transaction(function () use ($pending, $actor, $context, $sessionId, $status, $resolutionCode, $nextResolution): OmrPendingCapture {
                $locked = OmrPendingCapture::query()->lockForUpdate()->findOrFail($pending->id);
                $this->pendingAccess->assertPending($actor, $locked);
                if ((int) $locked->version !== (int) $pending->version
                    || ! in_array($locked->status, [
                        OmrPendingCapture::STATUS_RESOLUTION_REQUIRED,
                        OmrPendingCapture::STATUS_NEEDS_RECOVERY,
                    ], true)) {
                    return $locked->fresh();
                }
                if ($locked->expires_at !== null && $locked->expires_at->isPast()) {
                    throw new RuntimeException('omr_pending_capture_expired');
                }
                if ($sessionId !== null && $context->gradingSession !== null) {
                    $this->pendingAccess->assertPending($actor, $locked, $context->gradingSession);
                }
                $locked->forceFill([
                    'status' => $status,
                    'grading_session_id' => $sessionId,
                    'resolution_json' => $nextResolution,
                    'resolved_by_user_id' => $actor->id,
                    'version' => (int) $locked->version + 1,
                ])->save();
                $this->recordAuditEvent->handle('grading.omr_pending_capture_monitoring_recheck', [
                    'pending_capture_id' => $locked->id,
                    'status' => $status,
                    'resolution_code' => $resolutionCode,
                    'grading_session_id' => $sessionId,
                ], (string) $actor->id);

                return $locked->fresh();
            });
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException && $exception->getMessage() === 'omr_pending_capture_expired') {
                throw $exception;
            }

            return $pending;
        }
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $next */
    private function sameResolution(array $current, array $next): bool
    {
        return ($current['resolution_code'] ?? null) === ($next['resolution_code'] ?? null)
            && ($current['grading_session_id'] ?? null) === ($next['grading_session_id'] ?? null)
            && ($current['candidates'] ?? []) === ($next['candidates'] ?? []);
    }
}
