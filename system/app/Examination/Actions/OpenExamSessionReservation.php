<?php

namespace App\Examination\Actions;

use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Support\ActionExecutionReceiptClaim;
use App\Examination\Support\ActionExecutionReceiptStore;
use App\Examination\Support\ActionExecutionResult;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

final class OpenExamSessionReservation
{
    private const ACTION_KEY = 'exams.session.reservation.open@1';

    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly ActionExecutionReceiptStore $receipts,
    ) {}

    public function handle(ExamSession $session, User $actor, int $expectedVersion, ?string $reason = null): ExamSession
    {
        $result = $this->execute($session, $actor, $expectedVersion, $reason, null);
        if ($result instanceof ActionExecutionResult) {
            throw new LogicException('Human exam reservation opening returned an Xiam receipt.');
        }

        return $result;
    }

    public function handleConfirmed(
        ExamSession $session,
        User $actor,
        int $expectedVersion,
        ?string $reason,
        ActionExecutionIdentity $identity,
    ): ActionExecutionResult {
        $result = $this->execute($session, $actor, $expectedVersion, $reason, $identity);
        if (! $result instanceof ActionExecutionResult) {
            throw new LogicException('Confirmed exam reservation opening did not return an Action receipt.');
        }

        return $result;
    }

    public function replayConfirmed(
        ExamSession $session,
        User $actor,
        int $expectedVersion,
        ?string $reason,
        ActionExecutionIdentity $identity,
    ): ?ActionExecutionResult {
        $claim = $this->receipts->replay(
            identity: $identity,
            actor: $actor,
            actionKey: self::ACTION_KEY,
            target: ['exam_session_id' => (int) $session->id],
            command: ['expected_version' => $expectedVersion, 'reason' => $reason],
        );

        return $claim === null ? null : new ActionExecutionResult($claim->safeResult, replayed: true);
    }

    private function execute(
        ExamSession $session,
        User $actor,
        int $expectedVersion,
        ?string $reason,
        ?ActionExecutionIdentity $identity,
    ): ExamSession|ActionExecutionResult {
        return DB::transaction(function () use ($session, $actor, $expectedVersion, $reason, $identity): ExamSession|ActionExecutionResult {
            $claim = $identity === null
                ? null
                : $this->receipts->claim(
                    identity: $identity,
                    actor: $actor,
                    actionKey: self::ACTION_KEY,
                    target: ['exam_session_id' => (int) $session->id],
                    command: ['expected_version' => $expectedVersion, 'reason' => $reason],
                );

            if ($claim?->replayed) {
                return new ActionExecutionResult($claim->safeResult, replayed: true);
            }

            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if ($session->status !== ExamSession::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'session' => 'Only draft examination sessions can be opened for applicant reservation.',
                ]);
            }
            if ($session->isOpenForReservation()) {
                return $this->completeReceipt($session, $claim);
            }

            if ($session->room_id === null || $session->starts_at === null || $session->effective_end_at === null) {
                throw new RuntimeException('Room and schedule times are required before opening reservation.');
            }

            $session->update([
                'booking_mode' => ExamSession::BOOKING_OPEN_FOR_RESERVATION,
                'auto_assign_enabled' => false,
                'reservation_opened_at' => now(),
                'reservation_closed_at' => null,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_reservation_opened', [
                'exam_session_id' => $session->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $this->completeReceipt($session->fresh(['room', 'academicCycle']), $claim);
        });
    }

    private function completeReceipt(
        ExamSession $session,
        ?ActionExecutionReceiptClaim $claim,
    ): ExamSession|ActionExecutionResult {
        if ($claim === null) {
            return $session;
        }

        $safeResult = [
            'session_id' => $session->id,
            'booking_mode' => $session->booking_mode,
            'version' => $session->version,
            'status' => $session->status,
        ];
        $this->receipts->complete($claim, $safeResult);

        return new ActionExecutionResult($safeResult, replayed: false);
    }
}
