<?php

namespace App\Examination\Actions;

use App\Assessment\Support\EnsureGradingSessionForCompletedExam;
use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentLogisticsSeatAllocation;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionLogisticsPlan;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CloseExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly EnsureGradingSessionForCompletedExam $ensureGradingSession,
    ) {}

    public function handle(ExamSession $session, User $actor, int $expectedVersion): ExamSession
    {
        $created = collect();

        $closed = DB::transaction(function () use ($session, $actor, $expectedVersion, &$created): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if (in_array($session->status, [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_CANCELLED], true)) {
                return $session;
            }

            if (! $session->attendanceIsOpen()) {
                throw new RuntimeException('A session must be started before it can be closed. Use cancellation to cancel an un-started session.');
            }

            // Attendance for every active assignment keeps the grading roster deterministic.
            $missingAttendance = ExamAssignment::query()
                ->where('exam_session_id', $session->id)
                ->active()
                ->whereDoesntHave('attendanceRecord')
                ->count();

            if ($missingAttendance > 0) {
                throw new RuntimeException(
                    "Attendance is not recorded for {$missingAttendance} assigned applicant(s)."
                );
            }

            $totalPresentCount = ExamAssignment::query()
                ->where('exam_session_id', $session->id)
                ->active()
                ->whereHas('attendanceRecord', fn ($query) => $query->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT))
                ->count();

            if ($totalPresentCount === 0) {
                $applicantRecipients = NotificationRecipients::assignedApplicantsFor($session);
                $staffRecipients = NotificationRecipients::testAdministratorUsersFor($session, $this->authorizer);
                $noticeAssignmentIds = ExamAssignment::query()->active()->where('exam_session_id', $session->id)->pluck('id')->all();

                $activeAssignments = ExamAssignment::query()
                    ->where('exam_session_id', $session->id)
                    ->active()
                    ->with('attendanceRecord')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $endedAt = now()->floorSecond();
                $reason = "Zero examinee attendance present at session closure ({$session->code}).";

                foreach ($activeAssignments as $assignment) {
                    $outcome = $assignment->attendanceRecord?->outcome;
                    $endReason = $outcome === ExamAttendanceRecord::OUTCOME_VOID
                        ? "Voided sitting in {$session->code}; session closed with zero present examinees."
                        : "Absent in {$session->code}; session closed with zero present examinees.";

                    $assignment->update([
                        'status' => ExamAssignment::STATUS_CANCELLED,
                        'ended_at' => $endedAt,
                        'end_reason' => $endReason,
                        'version' => $assignment->version + 1,
                    ]);

                    $this->supersedeUnusedAdministrations((int) $assignment->id);
                    $this->closeActiveSeatAllocations((int) $assignment->id, $endedAt, $endReason);
                }

                $session->update([
                    'status' => ExamSession::STATUS_CANCELLED,
                    'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                    'reservation_closed_at' => $session->isOpenForReservation() ? now() : $session->reservation_closed_at,
                    'closed_at' => now(),
                    'version' => $session->version + 1,
                ]);

                $this->recordAuditEvent->handle('exam.session_cancelled', [
                    'exam_session_id' => $session->id,
                    'reason' => $reason,
                ], (string) $actor->id);

                $this->recordAuditEvent->handle('exam.session_closed', [
                    'exam_session_id' => $session->id,
                    'outcome' => ExamSession::STATUS_CANCELLED,
                    'present_count' => 0,
                ], (string) $actor->id);

                if ($applicantRecipients->isNotEmpty()) {
                    $applicantIntent = NotificationIntent::v1(
                        eventType: 'exam.session_cancelled.v1',
                        templateKey: 'exam.session_cancelled',
                        payload: [
                            ...AdmissionExaminationNoticeContext::examinationPayload($session, 'session-cancelled'),
                            'notice_assignment_ids' => $noticeAssignmentIds,
                            'subject' => "Exam session {$session->code} cancelled",
                            'body' => "Your scheduled exam session {$session->code} was cancelled ({$reason}). The admissions office will arrange your reassignment.",
                            'action_label' => 'View schedule',
                            'action_url' => AdmissionExaminationNoticeContext::url('applicant.schedule'),
                            'session_id' => $session->id,
                            'code' => $session->code,
                        ],
                        channels: [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL],
                    );

                    $created = $created->concat($this->dispatcher->dispatch(
                        $applicantIntent,
                        hash('sha256', "exam.session_cancelled:applicants:{$session->id}:v{$session->version}"),
                        $applicantRecipients,
                        $applicantIntent->channels,
                    ));
                }

                if ($staffRecipients->isNotEmpty()) {
                    $staffIntent = NotificationIntent::v1(
                        eventType: 'exam.session_cancelled.v1',
                        templateKey: 'exam.session_cancelled.staff',
                        payload: [
                            'subject' => "Exam session {$session->code} cancelled",
                            'body' => "Session {$session->code} was cancelled ({$reason}).",
                            'action_label' => 'View exam sessions',
                            'action_url' => route('exams.sessions.index'),
                            'session_id' => $session->id,
                            'code' => $session->code,
                        ],
                        channels: [NotificationDelivery::CHANNEL_IN_APP],
                    );

                    $created = $created->concat($this->dispatcher->dispatch(
                        $staffIntent,
                        hash('sha256', "exam.session_cancelled:staff:{$session->id}:v{$session->version}"),
                        $staffRecipients,
                        $staffIntent->channels,
                    ));
                }

                return $session->fresh();
            }

            $logisticsPlan = ExamSessionLogisticsPlan::query()
                ->where('exam_session_id', $session->id)->where('is_current', true)->first();
            if ($logisticsPlan === null && $session->hasLogisticsGeneration()) {
                throw new RuntimeException('Cannot close a session without its current logistics plan.');
            }
            $legacySnapshot = $logisticsPlan === null
                ? ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->where('is_current', true)->first()
                : null;
            $presentAssignments = ExamAssignment::query()
                ->where('exam_session_id', $session->id)
                ->active()
                ->whereHas('attendanceRecord', fn ($query) => $query->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT))
                ->whereDoesntHave('submissionRecord')
                ->with(['activeSeatAllocation', 'activeLogisticsSeatAllocation', 'admissionAttempt'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($presentAssignments as $assignment) {
                $legacyAllocation = $assignment->activeSeatAllocation;
                $logisticsAllocation = $assignment->activeLogisticsSeatAllocation;
                if ($logisticsPlan?->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING && $logisticsAllocation === null) {
                    throw new RuntimeException("Cannot close session: examinee {$assignment->admissionAttempt?->reference_code} has no active physical seat allocation.");
                }
                if ($legacySnapshot !== null && $legacyAllocation === null) {
                    throw new RuntimeException("Cannot close session: examinee {$assignment->admissionAttempt?->reference_code} has no active historical seat allocation.");
                }
                ExamSubmissionRecord::query()->create([
                    'exam_assignment_id' => $assignment->id,
                    'exam_session_id' => $logisticsAllocation?->exam_session_id,
                    'seat_allocation_id' => $legacyAllocation?->id,
                    'exam_assignment_logistics_seat_allocation_id' => $logisticsAllocation?->id,
                    'recorded_by_user_id' => $actor->id,
                    'idempotency_key' => hash('sha256', "close-session-submission:{$session->id}:{$assignment->id}"),
                    'submitted_at' => now(),
                ]);
            }

            $nonPresentAssignments = ExamAssignment::query()
                ->where('exam_session_id', $session->id)
                ->active()
                ->whereHas('attendanceRecord', fn ($query) => $query->whereIn('outcome', [
                    ExamAttendanceRecord::OUTCOME_ABSENT,
                    ExamAttendanceRecord::OUTCOME_VOID,
                ]))
                ->with('attendanceRecord')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $endedAt = now()->floorSecond();
            foreach ($nonPresentAssignments as $nonPresent) {
                $outcome = $nonPresent->attendanceRecord?->outcome;
                $endReason = $outcome === ExamAttendanceRecord::OUTCOME_VOID
                    ? "Voided sitting on session close ({$session->code})"
                    : "Absent on session close ({$session->code})";

                $nonPresent->update([
                    'status' => ExamAssignment::STATUS_REMOVED,
                    'ended_at' => $endedAt,
                    'end_reason' => $endReason,
                    'version' => $nonPresent->version + 1,
                ]);

                $this->supersedeUnusedAdministrations((int) $nonPresent->id);
                $this->closeActiveSeatAllocations((int) $nonPresent->id, $endedAt, $endReason);
            }

            $this->recordAuditEvent->handle('exam.submissions_auto_reconciled', [
                'exam_session_id' => $session->id,
                'count' => $presentAssignments->count(),
            ], (string) $actor->id);

            $session->update([
                'status' => ExamSession::STATUS_COMPLETED,
                'closed_at' => now(),
                'version' => $session->version + 1,
            ]);

            $this->ensureGradingSession->handle($session, $actor, 'close');

            $this->recordAuditEvent->handle('exam.session_closed', [
                'exam_session_id' => $session->id,
                'outcome' => ExamSession::STATUS_COMPLETED,
                'present_count' => $totalPresentCount,
                'released_non_present_count' => $nonPresentAssignments->count(),
            ], (string) $actor->id);
            // Completion notices go to authorized Test Administrators in this institution.
            $intent = NotificationIntent::v1(
                eventType: 'exam.session_completed.v1',
                templateKey: 'exam.session_completed',
                payload: [
                    ...AdmissionExaminationNoticeContext::examinationPayload($session, 'session-completed'),
                    'action_label' => 'Open grading',
                    'action_url' => AdmissionExaminationNoticeContext::url('grading.index'),
                    'exam_session_id' => $session->id,
                    'code' => $session->code,
                ],
            );

            $recipients = NotificationRecipients::testAdministratorUsersFor($session, $this->authorizer);

            $created = $this->dispatcher->dispatch(
                $intent,
                hash('sha256', "exam.session_completed:{$session->id}:v{$session->version}"),
                $recipients,
                $intent->channels,
            );

            return $session->fresh();
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $closed;
    }

    private function supersedeUnusedAdministrations(int $assignmentId): void
    {
        AssessmentAdministration::query()
            ->where('exam_assignment_id', $assignmentId)
            ->whereNull('superseded_at')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('assessment_scoring_runs')->whereColumn('administration_id', 'assessment_administrations.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('assessment_raw_result_revisions')->whereColumn('administration_id', 'assessment_administrations.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('assessment_conversion_revisions')->whereColumn('administration_id', 'assessment_administrations.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('assessment_result_sets')->whereColumn('administration_id', 'assessment_administrations.id'))
            ->update(['superseded_at' => now()]);
    }

    private function closeActiveSeatAllocations(int $assignmentId, Carbon $endedAt, string $reason): void
    {
        $allocations = ExamAssignmentLogisticsSeatAllocation::query()
            ->where('exam_assignment_id', $assignmentId)
            ->whereNull('effective_to')
            ->lockForUpdate()
            ->get()
            ->concat(
                ExamAssignmentSeatAllocation::query()
                    ->where('exam_assignment_id', $assignmentId)
                    ->whereNull('effective_to')
                    ->lockForUpdate()
                    ->get()
            );

        foreach ($allocations as $allocation) {
            $effectiveFrom = $allocation->effective_from?->copy()->floorSecond();
            $allocation->update([
                'effective_to' => $effectiveFrom !== null && $effectiveFrom->gte($endedAt)
                    ? $effectiveFrom->copy()->addSecond()
                    : $endedAt,
                'reason' => $reason,
            ]);
        }
    }
}
