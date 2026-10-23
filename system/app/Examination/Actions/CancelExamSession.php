<?php

namespace App\Examination\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentLogisticsSeatAllocation;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    public function handle(ExamSession $session, User $actor, int $expectedVersion, string $reason): ExamSession
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Cancel requires a reason.');
        }

        $created = collect();
        $cancelled = DB::transaction(function () use ($session, $actor, $expectedVersion, $reason, &$created): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if (in_array($session->status, [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_CANCELLED], true)) {
                throw new RuntimeException('Session is already terminal.');
            }

            if ($session->status === ExamSession::STATUS_IN_PROGRESS) {
                throw new RuntimeException('In-progress sessions must be closed, not cancelled via CancelExamSession.');
            }

            $applicantRecipients = NotificationRecipients::assignedApplicantsFor($session);
            $staffRecipients = NotificationRecipients::testAdministratorUsersFor($session, $this->authorizer);
            $noticeAssignmentIds = ExamAssignment::query()->active()->where('exam_session_id', $session->id)->pluck('id')->all();

            $assignments = ExamAssignment::query()
                ->active()->where('exam_session_id', $session->id)
                ->orderBy('id')->lockForUpdate()->get();
            $endedAt = now()->floorSecond();
            foreach ($assignments as $assignment) {
                $assignment->update([
                    'status' => ExamAssignment::STATUS_CANCELLED,
                    'ended_at' => $endedAt,
                    'end_reason' => $reason,
                    'version' => $assignment->version + 1,
                ]);
                $allocations = ExamAssignmentLogisticsSeatAllocation::query()
                    ->where('exam_assignment_id', $assignment->id)->whereNull('effective_to')->lockForUpdate()->get()
                    ->concat(ExamAssignmentSeatAllocation::query()
                        ->where('exam_assignment_id', $assignment->id)->whereNull('effective_to')->lockForUpdate()->get());
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
                        'body' => "Exam session {$session->code} was cancelled ({$reason}).",
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
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $cancelled;
    }
}
