<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\User;
use App\Notifications\ExamSessionCompleted;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ExamSessionAutoCloser
{
    public function run(): void
    {
        $overdue = ExamSession::whereIn('status', [
            ExamSession::STATUS_PUBLISHED,
            ExamSession::STATUS_IN_PROGRESS,
        ])
            ->whereNotNull('end_time')
            ->get()
            ->filter(fn ($s) => $s->isEffectiveEndTimePast());

        foreach ($overdue as $session) {
            DB::transaction(function () use ($session) {
                // Lock the session to prevent race conditions from concurrent cron runs
                $lockedSession = ExamSession::lockForUpdate()->find($session->id);

                // Check status again after lock
                if (! in_array($lockedSession->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS])) {
                    return;
                }

                $this->evaluate($lockedSession);
            });
        }
    }

    private function evaluate(ExamSession $session): void
    {
        $stats = $this->getApplicantsStats($session->id);

        $total = $stats['total'];
        $pending = $stats['pending'];
        $submitted = $stats['submitted'];
        $present_pending_submission = $stats['present_pending_submission'];

        $hasStragglers = $present_pending_submission > 0;

        // If no one showed up and the session was never started, just cancel it.
        if ($session->status === ExamSession::STATUS_PUBLISHED && $pending === $total) {
            $this->closeSession($session, ExamSession::STATUS_CANCELLED, 'Auto-cancelled: Session expired without any applicant attendance.');

            return;
        }

        // If no stragglers (everyone who came has submitted, or no one came)
        if (! $hasStragglers) {
            $this->closeSession($session, ExamSession::STATUS_COMPLETED, 'Auto-completed: Session expired and all present applicants had submissions logged.');

            return;
        }

        // Stragglers exist: calculate effective grace deadline.
        // If the proctor explicitly extended the session, use that as the deadline.
        // Otherwise fall back to end_time + 30 minutes.
        $tz = config('app.timezone', 'UTC');
        $sessionDate = Carbon::parse($session->date)->tz($tz);

        $graceDeadline = $session->extended_end_time
            ? $sessionDate->copy()->setTimeFromTimeString($session->extended_end_time)
            : $sessionDate->copy()->setTimeFromTimeString($session->end_time)->addMinutes(30);

        $now = Carbon::now($tz);

        if ($now->gt($graceDeadline)) {
            // Grace period exceeded — force close.
            $this->closeSession($session, ExamSession::STATUS_COMPLETED, "Auto-closed after grace period. {$present_pending_submission} applicant(s) had not submitted.");
        }
    }

    private function closeSession(ExamSession $session, string $status, string $notes): void
    {
        $session->update([
            'status' => $status,
            'closed_at' => now(),
            'system_notes' => $notes,
        ]);

        if ($status === ExamSession::STATUS_COMPLETED) {
            $session->load('proctors');
            $recipients = $session->proctors;
            $testAdmins = User::whereHas('roles', fn ($q) => $q->where('name', 'test_administrator'))->get();
            Notification::send(
                $recipients->merge($testAdmins)->unique('id'),
                new ExamSessionCompleted($session)
            );
        }
    }

    private function getApplicantsStats(int $sessionId): array
    {
        $pivots = DB::table('exam_session_applicant')->where('exam_session_id', $sessionId)->get();

        $present = $pivots->where('attendance_status', 'present')->count();
        $absent = $pivots->where('attendance_status', 'absent')->count();
        $pending = $pivots->where('attendance_status', 'pending')->count();
        $submitted = $pivots->where('submission_status', 'submitted')->count();
        $present_pending_submission = $pivots
            ->where('attendance_status', 'present')
            ->where('submission_status', 'pending')
            ->count();

        return [
            'total' => $pivots->count(),
            'present' => $present,
            'absent' => $absent,
            'pending' => $pending,
            'submitted' => $submitted,
            'present_pending_submission' => $present_pending_submission,
        ];
    }
}
