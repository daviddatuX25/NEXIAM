<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Single effective deadline (REQ-VS-020). */
class ExtendExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    public function handle(ExamSession $session, User $actor, int $expectedVersion, CarbonInterface $effectiveEndAt): ExamSession
    {
        $created = collect();
        $extended = DB::transaction(function () use ($session, $actor, $expectedVersion, $effectiveEndAt, &$created): ExamSession {
            $session->loadMissing('academicCycle', 'room');
            $institutionId = (int) ($session->academicCycle?->institution_id ?? $session->room?->institution_id);
            $session = ExamSession::query()
                ->forWorkspace()
                ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if (! in_array($session->status, [
                ExamSession::STATUS_PUBLISHED,
                ExamSession::STATUS_IN_PROGRESS,
            ], true)) {
                throw new RuntimeException('Session cannot be extended from current state.');
            }

            if ($effectiveEndAt->lte($session->effective_end_at)) {
                throw new RuntimeException('New effective end must be after the current effective end.');
            }

            $session->update([
                'effective_end_at' => $effectiveEndAt,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_extended', [
                'exam_session_id' => $session->id,
                'effective_end_at' => $effectiveEndAt->toIso8601String(),
            ], (string) $actor->id);

            $timeLabel = $session->status === ExamSession::STATUS_IN_PROGRESS ? 'expected end' : 'scheduled end';
            $endLabel = $session->status === ExamSession::STATUS_IN_PROGRESS
                ? self::campusInstant($session->effective_end_at)
                : ($session->effective_end_at?->format('F j, Y g:i A').' (campus time)');
            $intent = NotificationIntent::v1(
                eventType: 'exam.session_expected_end_changed.v1',
                templateKey: 'exam.session_expected_end_changed',
                payload: [
                    'subject' => "Exam session {$session->code} time update",
                    'body' => "The {$timeLabel} for exam session {$session->code} has changed to {$endLabel}. Please check your schedule for the current appointment information.",
                    'action_label' => 'View schedule',
                    'action_url' => AdmissionExaminationNoticeContext::url('applicant.schedule'),
                    'session_id' => (int) $session->id,
                    'session_version' => (int) $session->version,
                    'academic_cycle_id' => (int) $session->academic_cycle_id,
                    'institution_id' => $institutionId,
                    'effective_end_at' => $session->effective_end_at?->toIso8601String(),
                ],
                channels: [NotificationDelivery::CHANNEL_IN_APP],
            );
            $created = $this->dispatcher->dispatch(
                $intent,
                hash('sha256', "exam.session_expected_end_changed:{$session->id}:v{$session->version}"),
                NotificationRecipients::assignedApplicantsFor($session),
                $intent->channels,
            );

            return $session->fresh();
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $extended;
    }

    private static function campusInstant(?CarbonInterface $instant): string
    {
        return $instant?->toImmutable()->setTimezone('Asia/Manila')->format('F j, Y g:i A T') ?? 'Not recorded';
    }
}
