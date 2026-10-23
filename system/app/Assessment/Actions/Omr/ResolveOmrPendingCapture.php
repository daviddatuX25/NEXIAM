<?php

namespace App\Assessment\Actions\Omr;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Actions\Omr\Canonical\ResolveOmrBlankInstanceManually;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrScannerContext;
use App\Assessment\Omr\OmrScannerContextOutcome;
use App\Assessment\Omr\OmrScannerSessionResolver;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBlankInstancePage;
use App\Models\Assessment\OmrPendingCapture;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function substr;
use function trim;

final class ResolveOmrPendingCapture
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly CommandIdempotency $idempotency,
        private readonly OmrPendingCaptureAccess $pendingAccess,
        private readonly OmrScannerSessionResolver $sessionResolver,
        private readonly ResolveOmrBlankInstanceManually $manualBlankResolver,
        private readonly CaptureGenericOmrImage $capture,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function resolveApplicantNumber(
        OmrPendingCapture $pending,
        User $actor,
        string $applicantNumber,
        int $expectedVersion,
        string $idempotencyKey,
    ): OmrPendingCapture {
        $this->assertCapability($actor);
        $applicantNumber = trim($applicantNumber);
        $idempotencyKey = trim($idempotencyKey);
        if ($expectedVersion < 0 || $idempotencyKey === '') {
            throw new RuntimeException('omr_pending_capture_version_required');
        }
        if (preg_match('/^[A-Za-z0-9._-]+$/', $applicantNumber) !== 1) {
            throw new RuntimeException('applicant_number_invalid');
        }
        $this->pendingAccess->assertPending($actor, $pending);
        $operation = $this->claimOperation($actor, 'resolve_applicant', $pending, [
            'applicant_number' => $applicantNumber,
            'expected_version' => $expectedVersion,
        ], $idempotencyKey);
        $replay = $this->replayOperation($operation, $actor);
        if ($replay !== null) {
            return $replay;
        }
        $operation = $this->beginOperation($operation);

        try {
            $locked = $this->lockPending($pending, $actor, $expectedVersion);
            $this->assertIdentityRecoveryAllowed($locked);
            $context = $this->sessionResolver->discover($applicantNumber, $actor);
            $result = $this->applyContext($locked, $actor, $context, $applicantNumber, 'manual', $expectedVersion);
            $this->completeOperation($operation, $result->id);

            return $result;
        } catch (Throwable $exception) {
            $this->failOperation($operation, $exception->getMessage());

            throw $exception;
        }
    }

    public function selectSession(
        OmrPendingCapture $pending,
        User $actor,
        int $gradingSessionId,
        int $expectedVersion,
        string $idempotencyKey,
    ): OmrPendingCapture {
        $this->assertCapability($actor);
        $idempotencyKey = trim($idempotencyKey);
        if ($gradingSessionId < 1 || $expectedVersion < 0 || $idempotencyKey === '') {
            throw new RuntimeException('omr_pending_capture_version_required');
        }
        $this->pendingAccess->assertPending($actor, $pending);
        $operation = $this->claimOperation($actor, 'select_session', $pending, [
            'grading_session_id' => $gradingSessionId,
            'expected_version' => $expectedVersion,
        ], $idempotencyKey);
        $replay = $this->replayOperation($operation, $actor);
        if ($replay !== null) {
            return $replay;
        }
        $operation = $this->beginOperation($operation);

        try {
            $locked = $this->lockPending($pending, $actor, $expectedVersion);
            $applicantNumber = trim((string) $locked->detected_applicant_number);
            if ($applicantNumber === '' || preg_match('/^[A-Za-z0-9._-]+$/', $applicantNumber) !== 1) {
                throw new RuntimeException('applicant_number_required');
            }
            $context = $this->sessionResolver->discover($applicantNumber, $actor);
            $candidateIds = array_values(array_map(
                static fn (array $candidate): int => (int) ($candidate['id'] ?? 0),
                $context->candidates,
            ));
            if (! in_array($gradingSessionId, $candidateIds, true)) {
                throw new RuntimeException('grading_session_not_a_candidate');
            }

            $result = $this->applyContext($locked, $actor, $context, $applicantNumber, 'server_candidate', $expectedVersion, $gradingSessionId);
            $this->completeOperation($operation, $result->id);

            return $result;
        } catch (Throwable $exception) {
            $this->failOperation($operation, $exception->getMessage());

            throw $exception;
        }
    }

    public function resolveDisplayCode(
        OmrPendingCapture $pending,
        User $actor,
        string $displayCode,
        bool $confirmed,
        int $expectedVersion,
        string $idempotencyKey,
    ): OmrPendingCapture {
        $this->assertCapability($actor);
        $displayCode = trim($displayCode);
        $idempotencyKey = trim($idempotencyKey);
        if ($expectedVersion < 0 || $idempotencyKey === '') {
            throw new RuntimeException('omr_pending_capture_version_required');
        }
        if ($displayCode === '' || ! $confirmed) {
            throw new RuntimeException('manual_confirmation_required');
        }
        $this->pendingAccess->assertPending($actor, $pending);
        $operation = $this->claimOperation($actor, 'resolve_display_code', $pending, [
            'display_code' => $displayCode,
            'expected_version' => $expectedVersion,
        ], $idempotencyKey);
        $replay = $this->replayOperation($operation, $actor);
        if ($replay !== null) {
            return $replay;
        }
        $operation = $this->beginOperation($operation);

        try {
            $locked = $this->lockPending($pending, $actor, $expectedVersion);
            if ($locked->qr_state === 'present_invalid' || $locked->analysis_code === 'qr_invalid') {
                throw new RuntimeException('qr_invalid_manual_bypass_forbidden');
            }
            if (! in_array($locked->qr_state, ['absent', 'undecodable'], true)) {
                throw new RuntimeException('manual_recovery_state_invalid');
            }
            $resolved = $this->manualBlankResolver->handle(
                $actor,
                $locked->qr_state,
                $displayCode,
                true,
                (int) $locked->institution_id,
                $locked->academicCycle?->code,
            );
            $instance = $resolved['instance'];
            $instance->loadMissing(['templateVersion.template', 'pages']);
            $page = $instance->pages->firstWhere('page_no', (int) $locked->page_no);
            if (! $page instanceof OmrBlankInstancePage) {
                throw new RuntimeException('qr_page_invalid');
            }
            if ($instance->isTerminal() && ! $instance->isReusableMaster()) {
                throw new RuntimeException('qr_lifecycle_invalid');
            }
            if ($page->status === OmrBlankInstancePage::STATUS_VOID) {
                throw new RuntimeException('qr_lifecycle_invalid');
            }
            if ($instance->templateVersion === null) {
                throw new RuntimeException('qr_instance_missing');
            }

            $updated = DB::transaction(function () use ($locked, $actor, $instance, $page, $expectedVersion): OmrPendingCapture {
                $current = OmrPendingCapture::query()->lockForUpdate()->with('academicCycle')->findOrFail($locked->id);
                $this->pendingAccess->assertPending($actor, $current);
                if ((int) $current->version !== $expectedVersion || ! in_array($current->status, [
                    OmrPendingCapture::STATUS_RESOLUTION_REQUIRED,
                    OmrPendingCapture::STATUS_NEEDS_RECOVERY,
                ], true)) {
                    throw new RuntimeException('omr_pending_capture_stale_version');
                }
                $current->forceFill([
                    'status' => OmrPendingCapture::STATUS_ANALYZING,
                    'qr_state' => 'manual_resolved',
                    'qr_payload' => null,
                    'qr_payload_hash' => null,
                    'template_version_id' => $instance->template_version_id,
                    'blank_instance_id' => $instance->id,
                    'blank_instance_page_id' => $page->id,
                    'page_count' => (int) $instance->page_count,
                    'analysis_code' => null,
                    'analysis_detail' => null,
                    'resolved_by_user_id' => $actor->id,
                    'version' => (int) $current->version + 1,
                ])->save();
                $this->recordAuditEvent->handle('grading.omr_pending_capture_display_code_resolved', [
                    'pending_capture_id' => $current->id,
                    'blank_instance_id' => $instance->id,
                    'blank_instance_page_id' => $page->id,
                    'qr_state' => $locked->qr_state,
                ], (string) $actor->id);

                return $current->fresh(['templateVersion.template', 'academicCycle']);
            });

            $result = $this->capture->retry($updated, $actor, (int) $updated->version, $idempotencyKey);
            $this->completeOperation($operation, $result->id);

            return $result;
        } catch (Throwable $exception) {
            $this->failOperation($operation, $exception->getMessage());

            throw $exception;
        }
    }

    private function assertCapability(User $actor): void
    {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR)) {
            throw new AuthorizationException('OMR scanner is not available.');
        }
    }

    private function lockPending(OmrPendingCapture $pending, User $actor, int $expectedVersion): OmrPendingCapture
    {
        return DB::transaction(function () use ($pending, $actor, $expectedVersion): OmrPendingCapture {
            $locked = OmrPendingCapture::query()->lockForUpdate()->with('academicCycle')->findOrFail($pending->id);
            $this->pendingAccess->assertPending($actor, $locked);
            if ((int) $locked->version !== $expectedVersion) {
                throw new RuntimeException('omr_pending_capture_stale_version');
            }
            if ($locked->expires_at !== null && $locked->expires_at->isPast()) {
                throw new RuntimeException('omr_pending_capture_expired');
            }
            if (! in_array($locked->status, [
                OmrPendingCapture::STATUS_RESOLUTION_REQUIRED,
                OmrPendingCapture::STATUS_NEEDS_RECOVERY,
            ], true)) {
                throw new RuntimeException('omr_pending_capture_not_recoverable');
            }

            return $locked;
        });
    }

    private function assertIdentityRecoveryAllowed(OmrPendingCapture $pending): void
    {
        $resolution = is_array($pending->resolution_json) ? $pending->resolution_json : [];
        $code = is_string($resolution['resolution_code'] ?? null)
            ? $resolution['resolution_code']
            : $pending->analysis_code;
        if (! in_array($code, [
            'applicant_unresolved',
            'identity_unresolved',
            'identity_conflict',
            'unreadable',
        ], true)) {
            throw new RuntimeException('applicant_number_recovery_unavailable');
        }
    }

    private function applyContext(
        OmrPendingCapture $pending,
        User $actor,
        OmrScannerContext $context,
        string $applicantNumber,
        string $source,
        int $expectedVersion,
        ?int $selectedSessionId = null,
    ): OmrPendingCapture {
        $sessionId = null;
        $status = OmrPendingCapture::STATUS_RESOLUTION_REQUIRED;
        $resolutionCode = $context->resolutionCode;
        if ($context->outcome === OmrScannerContextOutcome::AutoResolved && $context->gradingSession !== null) {
            $sessionId = (int) $context->gradingSession->id;
            $status = OmrPendingCapture::STATUS_READY;
            $resolutionCode = null;
        } elseif ($selectedSessionId !== null && $context->resolutionCode === 'session_ambiguous') {
            $sessionId = $selectedSessionId;
            $status = OmrPendingCapture::STATUS_READY;
        }

        return DB::transaction(function () use (
            $pending,
            $actor,
            $applicantNumber,
            $source,
            $sessionId,
            $status,
            $resolutionCode,
            $context,
            $expectedVersion,
        ): OmrPendingCapture {
            $locked = OmrPendingCapture::query()->lockForUpdate()->findOrFail($pending->id);
            $this->pendingAccess->assertPending($actor, $locked);
            if ((int) $locked->version !== $expectedVersion || ! in_array($locked->status, [
                OmrPendingCapture::STATUS_RESOLUTION_REQUIRED,
                OmrPendingCapture::STATUS_NEEDS_RECOVERY,
            ], true)) {
                throw new RuntimeException('omr_pending_capture_stale_version');
            }
            if ($locked->expires_at !== null && $locked->expires_at->isPast()) {
                throw new RuntimeException('omr_pending_capture_expired');
            }
            if ($sessionId !== null) {
                $session = $context->gradingSession;
                if ($session === null || (int) $session->id !== $sessionId) {
                    $session = null;
                }
                if ($session !== null) {
                    $this->pendingAccess->assertPending($actor, $locked, $session);
                }
            }
            $locked->forceFill([
                'status' => $status,
                'detected_applicant_number' => $applicantNumber,
                'detected_applicant_confidence' => $source === 'manual' ? null : $locked->detected_applicant_confidence,
                'grading_session_id' => $sessionId,
                'resolution_json' => [
                    'outcome' => $context->outcome->value,
                    'code' => $resolutionCode,
                    'resolution_code' => $resolutionCode,
                    'candidates' => $context->candidates,
                    'source' => $source,
                    'selected_session_id' => $sessionId,
                ],
                'resolved_by_user_id' => $actor->id,
                'version' => (int) $locked->version + 1,
            ])->save();
            $this->recordAuditEvent->handle('grading.omr_pending_capture_identity_resolved', [
                'pending_capture_id' => $locked->id,
                'source' => $source,
                'status' => $status,
                'resolution_code' => $resolutionCode,
                'grading_session_id' => $sessionId,
            ], (string) $actor->id);

            return $locked->fresh();
        });
    }

    /** @param array<string, mixed> $payload */
    private function claimOperation(User $actor, string $command, OmrPendingCapture $pending, array $payload, string $idempotencyKey): Operation
    {
        return $this->idempotency->claim(
            $actor,
            'grading.omr.'.$command,
            (string) $pending->id,
            ['pending_capture_id' => (int) $pending->id, ...$payload],
            $idempotencyKey,
        );
    }

    private function replayOperation(Operation $operation, User $actor): ?OmrPendingCapture
    {
        $pendingId = is_array($operation->payload['result'] ?? null)
            ? (int) ($operation->payload['result']['pending_capture_id'] ?? 0)
            : 0;
        if ($pendingId > 0) {
            $pending = OmrPendingCapture::query()->findOrFail($pendingId);
            $this->pendingAccess->assertPending($actor, $pending);

            return $pending;
        }
        if (! $operation->wasRecentlyCreated
            && $operation->status === Operation::STATUS_PROCESSING
            && $operation->updated_at !== null
            && $operation->updated_at->isAfter(now()->subMinutes(5))) {
            throw new RuntimeException('omr_pending_operation_in_progress');
        }

        return null;
    }

    private function beginOperation(Operation $operation): Operation
    {
        $operation->forceFill([
            'status' => Operation::STATUS_PROCESSING,
            'attempts' => (int) $operation->attempts + 1,
            'finished_at' => null,
            'last_error' => null,
        ])->save();

        return $operation;
    }

    private function completeOperation(Operation $operation, int $pendingId): void
    {
        $operation->forceFill([
            'status' => Operation::STATUS_SUCCEEDED,
            'payload' => [
                ...(array) $operation->payload,
                'result' => ['pending_capture_id' => $pendingId],
            ],
            'finished_at' => now(),
            'last_error' => null,
        ])->save();
    }

    private function failOperation(Operation $operation, string $message): void
    {
        $operation->forceFill([
            'status' => Operation::STATUS_FAILED,
            'last_error' => trim(substr($message, 0, 500)),
            'finished_at' => now(),
        ])->save();
    }
}
