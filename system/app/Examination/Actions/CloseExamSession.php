<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CloseExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {
    }

    public function handle(ExamSession $session, User $actor, int $expectedVersion): ExamSession
    {
        $created = collect();

        $closed = DB::transaction(function () use ($session, $actor, $expectedVersion, &$created): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if ($session->status === ExamSession::STATUS_COMPLETED) {
                return $session;
            }

            if (! in_array($session->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true)) {
                throw new RuntimeException('Session cannot be closed from current state.');
            }

            // GRE-3 — closing must not happen before proctors record who attended,
            // otherwise the grading roster cannot be derived deterministically.
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

            $session->update([
                'status' => ExamSession::STATUS_COMPLETED,
                'closed_at' => now(),
                'version' => $session->version + 1,
            ]);

            GradingSession::query()->firstOrCreate(
                ['exam_session_id' => $session->id],
                [
                    'status' => GradingSession::STATUS_OPEN,
                    'opened_at' => now(),
                ],
            );

            $this->recordAuditEvent->handle('exam.session_closed', [
                'exam_session_id' => $session->id,
            ], (string) $actor->id);

            // NT-8 — completion lands in the exam-ops staff bell (proctors + TA set).
            $intent = NotificationIntent::v1(
                eventType: 'exam.session_completed.v1',
                templateKey: 'exam.session_completed',
                payload: [
                    'subject' => 'Exam session completed',
                    'body' => "Exam session {$session->code} has been completed and grading is open.",
                    'action_label' => 'Open grading',
                    'action_url' => route('grading.index'),
                    'exam_session_id' => $session->id,
                    'code' => $session->code,
                ],
            );

            $recipients = NotificationRecipients::proctorsFor($session)
                ->merge(NotificationRecipients::testAdministratorUsers())
                ->unique('id')
                ->values();

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
}
