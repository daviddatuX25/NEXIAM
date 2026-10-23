<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentLogisticsSeatAllocation;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionLogisticsPlan;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecordExamSubmission
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        ExamAssignment $assignment,
        User $actor,
        string $idempotencyKey,
        ?string $repairReason = null,
    ): ExamSubmissionRecord {
        $existing = ExamSubmissionRecord::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($assignment, $actor, $idempotencyKey, $repairReason): ExamSubmissionRecord {
            $session = ExamSession::query()->with('gradingSession')->lockForUpdate()->findOrFail($assignment->exam_session_id);
            $assignment = ExamAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ((int) $assignment->exam_session_id !== (int) $session->id) {
                throw new RuntimeException('Assignment session changed; reload before recording submission.');
            }
            $isConductWindow = in_array($session->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true);
            $isGradingRepair = $session->status === ExamSession::STATUS_COMPLETED
                && $session->gradingSession !== null
                && in_array($session->gradingSession->status, [GradingSession::STATUS_OPEN, GradingSession::STATUS_REVIEW], true);
            $repairReason = trim((string) $repairReason);

            if (! $isConductWindow && ! $isGradingRepair) {
                throw new RuntimeException('Submission is not allowed for this session state.');
            }
            if ($isGradingRepair && mb_strlen($repairReason) < 8) {
                throw new RuntimeException('A specific reason is required to repair a missing submission after the exam closed.');
            }
            if ($isConductWindow && now()->gt($session->effective_end_at)) {
                throw new RuntimeException('Submission window has closed.');
            }
            if ($assignment->submissionRecord()->exists()) {
                throw new RuntimeException('Submission already recorded.');
            }

            $logisticsPlan = ExamSessionLogisticsPlan::query()
                ->where('exam_session_id', $session->id)->where('is_current', true)->first();
            if ($logisticsPlan === null && $session->hasLogisticsGeneration()) {
                throw new RuntimeException('Cannot record a submission without the current logistics plan.');
            }
            $legacySnapshot = $logisticsPlan === null
                ? ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->where('is_current', true)->first()
                : null;
            $legacyAllocation = null;
            $logisticsAllocation = null;
            if ($logisticsPlan?->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING) {
                $logisticsAllocation = ExamAssignmentLogisticsSeatAllocation::query()
                    ->where('exam_assignment_id', $assignment->id)->where('exam_session_id', $session->id)
                    ->where('logistics_plan_id', $logisticsPlan->id)->whereNull('effective_to')
                    ->lockForUpdate()->first();
                if ($logisticsAllocation === null) {
                    throw new RuntimeException('Cannot record submission without an active physical seat allocation.');
                }
            } elseif ($legacySnapshot !== null) {
                $legacyAllocation = ExamAssignmentSeatAllocation::query()
                    ->where('exam_assignment_id', $assignment->id)->whereNull('effective_to')
                    ->lockForUpdate()->first();
                if ($legacyAllocation === null) {
                    throw new RuntimeException('Cannot record submission without an active historical seat allocation.');
                }
            }

            $record = ExamSubmissionRecord::query()->create([
                'exam_assignment_id' => $assignment->id,
                'exam_session_id' => $logisticsAllocation?->exam_session_id,
                'seat_allocation_id' => $legacyAllocation?->id,
                'exam_assignment_logistics_seat_allocation_id' => $logisticsAllocation?->id,
                'recorded_by_user_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'submitted_at' => now(),
            ]);

            $this->recordAuditEvent->handle(
                $isGradingRepair ? 'exam.submission_repaired' : 'exam.submission_recorded',
                [
                    'record_id' => $record->id,
                    'assignment_id' => $assignment->id,
                    'seat_allocation_id' => $legacyAllocation?->id,
                    'logistics_seat_allocation_id' => $logisticsAllocation?->id,
                    'repair_reason' => $isGradingRepair ? $repairReason : null,
                ],
                (string) $actor->id,
            );

            return $record;
        });

    }
}
