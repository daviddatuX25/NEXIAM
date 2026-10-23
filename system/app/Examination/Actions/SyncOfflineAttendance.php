<?php

namespace App\Examination\Actions;

use App\Application\Actions\ActionExecutionIdentity;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Support\ActionExecutionReceiptStore;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\StaffProfile;
use App\Models\User;
use App\Testing\PracticeWorkspace;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SyncOfflineAttendance
{
    private const ACTION_KEY = 'exams.attendance.offline_sync@1';

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ActionExecutionReceiptStore $receipts,
        private readonly RecordExamAttendance $attendance,
        private readonly PracticeWorkspace $workspace,
    ) {}

    /**
     * @param  array{expected_actor_id: int, assignment_id: int, idempotency_key: string, outcome: string, expected_outcome: ?string, expected_attendance_id: ?int, expected_recorded_at: ?string, expected_started_at: string}  $command
     * @return array<string, mixed>
     */
    public function handle(ExamSession $session, User $actor, array $command): array
    {
        return DB::transaction(function () use ($session, $actor, $command): array {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
            $session->loadMissing('academicCycle', 'room');
            $institutionId = (int) ($session->academicCycle?->institution_id ?? $session->room?->institution_id);
            $principal = User::query()->with('staffProfile')->findOrFail($actor->id);
            $profile = $principal->staffProfile;
            if ((int) ($command['expected_actor_id'] ?? 0) !== (int) $principal->id) {
                throw new AuthorizationException('This pending attendance belongs to another staff account.');
            }
            if ($profile === null || $profile->status !== StaffProfile::STATUS_ACTIVE || $profile->deactivated_at !== null
                || ! $this->authorizer->allows($principal, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, ['institution_id' => $institutionId, 'office' => 'guidance'])) {
                throw new AuthorizationException('Test Administrator access is required.');
            }
            abort_unless((string) ($session->practice_fixture_set_id ?? '') === (string) ($this->workspace->currentId() ?? ''), 404);
            $this->workspace->assertWritable();
            $assignment = ExamAssignment::query()->where('exam_session_id', $session->id)->lockForUpdate()->findOrFail($command['assignment_id']);
            $target = ['exam_session_id' => (int) $session->id, 'exam_assignment_id' => (int) $assignment->id];
            $identity = new ActionExecutionIdentity($command['idempotency_key'], self::ACTION_KEY, hash('sha256', json_encode($command, JSON_THROW_ON_ERROR)));
            try {
                $claim = $this->receipts->claim($identity, $principal, self::ACTION_KEY, $target, $command);
            } catch (RuntimeException $exception) {
                if (! str_starts_with($exception->getMessage(), 'action_execution_receipt_')) {
                    throw $exception;
                }
                throw new DomainException('This attendance key conflicts with an earlier command. Review the pending outcome.', previous: $exception);
            }
            if ($claim->replayed) {
                return [...$claim->safeResult, 'status' => 'already_applied'];
            }
            if (! $session->attendanceIsOpen() || ! $assignment->isActive()
                || $session->started_at?->toIso8601String() !== $command['expected_started_at']) {
                throw new DomainException('The session has closed or changed. Review this pending attendance with the testing office.');
            }
            $record = $assignment->attendanceRecord()->lockForUpdate()->first();
            if ($record?->id !== $command['expected_attendance_id']
                || $record?->outcome !== $command['expected_outcome']
                || $record?->recorded_at?->toIso8601String() !== $command['expected_recorded_at']) {
                throw new DomainException('Attendance changed on another device. Review the pending outcome before recording again.');
            }
            $record = $this->attendance->handle($assignment, $principal, $command['outcome'], $command['idempotency_key']);
            if ((int) $record->exam_assignment_id !== (int) $assignment->id || $record->outcome !== $command['outcome']) {
                throw new DomainException('This attendance key belongs to another command. Review the pending outcome.');
            }
            $result = ['status' => 'applied', 'attendance' => ['id' => $record->id, 'outcome' => $record->outcome, 'recorded_at' => $record->recorded_at?->toIso8601String()]];
            $this->receipts->complete($claim, $result);

            return $result;
        });
    }
}
