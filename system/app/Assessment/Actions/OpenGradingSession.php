<?php

namespace App\Assessment\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OpenGradingSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    /**
     * Open (or return the existing) grading session for an exam session.
     *
     * Idempotent: `grading_sessions.exam_session_id` is unique, so a second
     * open for the same exam session returns the existing session untouched.
     *
     * Preconditions (GF-06 §6): the exam session must already be completed
     * (grading starts after close, never on draft/published/in-progress/
     * cancelled sessions), and the completed session must still have at least
     * one active assignment to grade — an empty roster would produce a
     * permanently unfinalizable session.
     */
    public function handle(ExamSession $session, User $actor): GradingSession
    {
        return DB::transaction(function () use ($session, $actor): GradingSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            $existing = GradingSession::query()->where('exam_session_id', $session->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            if ($session->status !== ExamSession::STATUS_COMPLETED) {
                throw new RuntimeException('Grading can only be opened for a completed exam session.');
            }

            $hasActiveRoster = ExamAssignment::query()
                ->where('exam_session_id', $session->id)
                ->active()
                ->exists();

            if (! $hasActiveRoster) {
                throw new RuntimeException('No applicants are assigned to this exam session; there is nothing to grade.');
            }

            $gradingSession = GradingSession::query()->create([
                'exam_session_id' => $session->id,
                'status' => GradingSession::STATUS_OPEN,
                'opened_at' => now(),
                'opened_by_user_id' => $actor->id,
            ]);

            $this->recordAuditEvent->handle('grading.session_opened', [
                'grading_session_id' => $gradingSession->id,
                'exam_session_id' => $session->id,
            ], (string) $actor->id);

            return $gradingSession;
        });
    }
}
