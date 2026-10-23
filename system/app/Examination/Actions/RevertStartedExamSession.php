<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RevertStartedExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    public function handle(ExamSession $session, User $actor, int $expectedVersion): ExamSession
    {
        $created = collect();
        $reverted = DB::transaction(function () use ($session, $actor, $expectedVersion, &$created): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
            $session->loadMissing('academicCycle');

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if ($session->status === ExamSession::STATUS_PUBLISHED) {
                return $session;
            }

            if ($session->status !== ExamSession::STATUS_IN_PROGRESS || $session->closed_at !== null) {
                throw new RuntimeException('Only an in-progress session can be reverted to published.');
            }

            $hasConductEvidence = ExamAssignment::query()
                ->where('exam_session_id', $session->id)
                ->where(function ($query): void {
                    $query->whereHas('attendanceRecord')
                        ->orWhereHas('submissionRecord');
                })
                ->exists();

            if ($hasConductEvidence) {
                throw new RuntimeException('Cannot revert session start after attendance or submission has been recorded.');
            }

            $earlyStart = $session->started_at !== null && $session->starts_at !== null
                && $session->started_at->lt($session->starts_at);
            $earlyStartEventIds = $earlyStart
                ? NotificationDelivery::query()
                    ->where('event_type', 'exam.session_started_early.v1')
                    ->where('recipient_type', ApplicantIdentity::class)
                    ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
                    ->where('payload->session_id', (int) $session->id)
                    ->where('payload->started_at', $session->started_at->toIso8601String())
                    ->distinct()->pluck('event_id')->all()
                : [];
            $affectedIdentities = $earlyStart
                ? NotificationRecipients::assignedApplicantsFor($session)
                : collect();

            $durationMinutes = 90;
            if ($session->started_at !== null && $session->effective_end_at !== null) {
                $diff = (int) $session->started_at->diffInMinutes($session->effective_end_at, false);
                $durationMinutes = $diff >= 15 && $diff <= 480 ? $diff : $session->scheduledDurationMinutes();
            } else {
                $durationMinutes = $session->scheduledDurationMinutes();
            }

            $restoredEffectiveEnd = $session->starts_at !== null
                ? $session->starts_at->copy()->addMinutes($durationMinutes)
                : $session->effective_end_at;

            $session->update([
                'status' => ExamSession::STATUS_PUBLISHED,
                'started_at' => null,
                'closed_at' => null,
                'effective_end_at' => $restoredEffectiveEnd,
                'version' => $session->version + 1,
            ]);

            if ($earlyStart) {
                NotificationDelivery::query()
                    ->whereIn('event_id', $earlyStartEventIds)
                    ->whereIn('status', [
                        NotificationDelivery::STATUS_PENDING,
                        NotificationDelivery::STATUS_RETRYING,
                        NotificationDelivery::STATUS_PROCESSING,
                    ])
                    ->update([
                        'status' => NotificationDelivery::STATUS_SUPERSEDED,
                        'claim_token' => null,
                        'lease_expires_at' => null,
                        'next_attempt_at' => null,
                        'updated_at' => now(),
                    ]);

                if ($affectedIdentities->isNotEmpty()) {
                    $correction = NotificationIntent::v1(
                        eventType: 'exam.session_start_reverted.v1',
                        templateKey: 'exam.session_start_reverted',
                        payload: [
                            'subject' => "Exam session {$session->code} returned to its published schedule",
                            'body' => "Exam session {$session->code} has returned to its published appointment. Please follow the scheduled date and time shown in your schedule.",
                            'action_label' => 'View schedule',
                            'action_url' => AdmissionExaminationNoticeContext::url('applicant.schedule'),
                            'session_id' => (int) $session->id,
                            'session_version' => (int) $session->version,
                            'academic_cycle_id' => (int) $session->academic_cycle_id,
                            'institution_id' => (int) $session->academicCycle?->institution_id,
                        ],
                        channels: [NotificationDelivery::CHANNEL_IN_APP],
                    );
                    $created = $this->dispatcher->dispatch(
                        $correction,
                        hash('sha256', "exam.session_start_reverted:{$session->id}:v{$session->version}"),
                        $affectedIdentities,
                        $correction->channels,
                    );
                }
            }

            $this->recordAuditEvent->handle('exam.session_start_reverted', [
                'exam_session_id' => $session->id,
                'code' => $session->code,
                'effective_end_at' => $restoredEffectiveEnd?->toIso8601String(),
            ], (string) $actor->id);

            return $session->fresh();
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $reverted;
    }
}
