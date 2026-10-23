<?php

namespace App\Scheduling\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentLogisticsSeatAllocation;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\Log;

final class ReconcileReconsideredAttempt
{
    public function __construct(
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @return array{
     *     closed_exam_assignments_count: int,
     *     closed_consultation_assignments_count: int,
     * }
     */
    public function handle(AdmissionAttempt $attempt, User $actor, string $reason): array
    {
        $reconciledExam = $this->planningMutex->run(
            AssignmentInvariant::DOMAIN_EXAM,
            (int) $attempt->academic_cycle_id,
            function () use ($attempt, $actor, $reason): int {
                $activeAssignments = ExamAssignment::query()
                    ->active()
                    ->where('admission_attempt_id', $attempt->id)
                    ->whereIn('origin', [ExamAssignment::ORIGIN_BATCH, ExamAssignment::ORIGIN_CONTINUOUS])
                    ->get();

                if ($activeAssignments->isEmpty()) {
                    return 0;
                }

                $sessionIds = $activeAssignments->pluck('exam_session_id')->unique()->sort()->values()->all();
                $sessions = ExamSession::query()
                    ->whereIn('id', $sessionIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lockedAttempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                $now = now();
                $closedCount = 0;
                foreach ($activeAssignments as $assignment) {
                    $session = $sessions->get($assignment->exam_session_id);
                    if ($session === null || $session->status !== ExamSession::STATUS_DRAFT) {
                        Log::notice('Operational notice: Retaining automated exam assignment for reconsidered attempt on non-draft session.', [
                            'assignment_id' => $assignment->id,
                            'exam_session_id' => $assignment->exam_session_id,
                            'session_status' => $session?->status,
                            'attempt_id' => $lockedAttempt->id,
                            'origin' => $assignment->origin,
                            'reason' => $reason,
                        ]);

                        continue;
                    }

                    $assignment->update([
                        'status' => ExamAssignment::STATUS_CANCELLED,
                        'ended_at' => $now,
                        'end_reason' => 'Reconsidered: '.$reason,
                        'version' => (int) $assignment->version + 1,
                    ]);

                    $logisticsAllocations = ExamAssignmentLogisticsSeatAllocation::query()
                        ->where('exam_assignment_id', $assignment->id)
                        ->whereNull('effective_to')
                        ->lockForUpdate()
                        ->get();
                    foreach ($logisticsAllocations as $allocation) {
                        $from = $allocation->effective_from?->copy()->floorSecond();
                        $closedAt = $from !== null && $from->gte($now)
                            ? $from->copy()->addSecond()
                            : $now;
                        $allocation->update(['effective_to' => $closedAt]);
                    }
                    ExamAssignmentSeatAllocation::query()
                        ->where('exam_assignment_id', $assignment->id)
                        ->whereNull('effective_to')
                        ->update(['effective_to' => $now]);

                    $session->increment('version');

                    $this->recordAuditEvent->handle('exam.automated_assignment_reconciled', [
                        'assignment_id' => $assignment->id,
                        'exam_session_id' => $assignment->exam_session_id,
                        'attempt_id' => $lockedAttempt->id,
                        'origin' => $assignment->origin,
                        'reason' => $reason,
                    ], (string) $actor->id);

                    $closedCount++;
                }

                return $closedCount;
            }
        );

        $reconciledConsultation = $this->planningMutex->run(
            AssignmentInvariant::DOMAIN_CONSULTATION,
            (int) $attempt->academic_cycle_id,
            function () use ($attempt, $actor, $reason): int {
                $activeAssignments = ConsultationAssignment::query()
                    ->active()
                    ->where('admission_attempt_id', $attempt->id)
                    ->whereIn('origin', [ConsultationAssignment::ORIGIN_BATCH, ConsultationAssignment::ORIGIN_CONTINUOUS])
                    ->get();

                if ($activeAssignments->isEmpty()) {
                    return 0;
                }

                $sessionIds = $activeAssignments->pluck('consultation_session_id')->unique()->sort()->values()->all();
                $sessions = ConsultationSession::query()
                    ->whereIn('id', $sessionIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lockedAttempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                $now = now();
                $closedCount = 0;
                foreach ($activeAssignments as $assignment) {
                    $session = $sessions->get($assignment->consultation_session_id);
                    if ($session === null || $session->status !== ConsultationSession::STATUS_DRAFT) {
                        Log::notice('Operational notice: Retaining automated consultation assignment for reconsidered attempt on non-draft session.', [
                            'assignment_id' => $assignment->id,
                            'consultation_session_id' => $assignment->consultation_session_id,
                            'session_status' => $session?->status,
                            'attempt_id' => $lockedAttempt->id,
                            'origin' => $assignment->origin,
                            'reason' => $reason,
                        ]);

                        continue;
                    }

                    $assignment->update([
                        'status' => ConsultationAssignment::STATUS_CANCELLED,
                        'cancelled_at' => $now,
                        'cancel_reason' => 'Reconsidered: '.$reason,
                        'version' => (int) $assignment->version + 1,
                    ]);

                    $session->increment('version');

                    $this->recordAuditEvent->handle('consultation.automated_assignment_reconciled', [
                        'assignment_id' => $assignment->id,
                        'consultation_session_id' => $assignment->consultation_session_id,
                        'attempt_id' => $lockedAttempt->id,
                        'origin' => $assignment->origin,
                        'reason' => $reason,
                    ], (string) $actor->id);

                    $closedCount++;
                }

                return $closedCount;
            }
        );

        return [
            'closed_exam_assignments_count' => $reconciledExam,
            'closed_consultation_assignments_count' => $reconciledConsultation,
        ];
    }
}
