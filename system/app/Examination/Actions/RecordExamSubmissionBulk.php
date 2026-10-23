<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionLogisticsPlan;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecordExamSubmissionBulk
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * Submit all present examinees who haven't been submitted yet.
     *
     * @return Collection<int, ExamSubmissionRecord>
     */
    public function handle(ExamSession $session, User $actor, string $batchKey): Collection
    {
        return DB::transaction(function () use ($session, $actor, $batchKey): Collection {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! in_array($session->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true)) {
                throw new RuntimeException('Bulk submission is not allowed for this session state.');
            }

            if (now()->gt($session->effective_end_at)) {
                throw new RuntimeException('Submission window has closed.');
            }

            $logisticsPlan = ExamSessionLogisticsPlan::query()
                ->where('exam_session_id', $session->id)->where('is_current', true)->first();
            if ($logisticsPlan === null && $session->hasLogisticsGeneration()) {
                throw new RuntimeException('Bulk submission requires the current logistics plan.');
            }
            $legacySnapshot = $logisticsPlan === null
                ? ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->where('is_current', true)->first()
                : null;
            $assignments = ExamAssignment::query()
                ->where('exam_session_id', $session->id)
                ->active()
                ->whereHas('attendanceRecord', fn ($q) => $q->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT))
                ->whereDoesntHave('submissionRecord')
                ->with(['activeSeatAllocation', 'activeLogisticsSeatAllocation'])
                ->orderBy('id')->lockForUpdate()->get();

            $records = collect();
            foreach ($assignments as $assignment) {
                $childKey = hash('sha256', "{$batchKey}:{$assignment->id}");
                $existing = ExamSubmissionRecord::query()->where('idempotency_key', $childKey)->first();
                if ($existing !== null) {
                    $records->push($existing);

                    continue;
                }
                $logisticsAllocation = $assignment->activeLogisticsSeatAllocation;
                $legacyAllocation = $assignment->activeSeatAllocation;
                if ($logisticsPlan?->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
                    && ($logisticsAllocation === null || (int) $logisticsAllocation->logistics_plan_id !== (int) $logisticsPlan->id)) {
                    throw new RuntimeException('Bulk submission requires each physical examinee to have a current logistics seat.');
                }
                if ($legacySnapshot !== null && $legacyAllocation === null) {
                    throw new RuntimeException('Bulk submission requires each historical material snapshot seat.');
                }

                $records->push(ExamSubmissionRecord::query()->create([
                    'exam_assignment_id' => $assignment->id,
                    'exam_session_id' => $logisticsAllocation?->exam_session_id,
                    'seat_allocation_id' => $legacyAllocation?->id,
                    'exam_assignment_logistics_seat_allocation_id' => $logisticsAllocation?->id,
                    'recorded_by_user_id' => $actor->id,
                    'idempotency_key' => $childKey,
                    'submitted_at' => now(),
                ]));
            }

            if ($records->isNotEmpty()) {
                $this->recordAuditEvent->handle('exam.submission_bulk_recorded', [
                    'exam_session_id' => $session->id,
                    'batch_key' => $batchKey,
                    'count' => $records->count(),
                ], (string) $actor->id);
            }

            return $records;
        });
    }
}
