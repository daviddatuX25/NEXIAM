<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PublishExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {
    }

    public function handle(ExamSession $session, User $actor, int $expectedVersion): ExamSession
    {
        $created = collect();

        $published = DB::transaction(function () use ($session, $actor, $expectedVersion, &$created): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if ($session->status !== ExamSession::STATUS_DRAFT) {
                throw new RuntimeException('Only draft sessions may be published.');
            }

            $assignments = ExamAssignment::query()
                ->active()
                ->where('exam_session_id', $session->id)
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty()) {
                throw new RuntimeException('Assign at least one applicant before publishing.');
            }

            $session->update([
                'status' => ExamSession::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => $session->version + 1,
            ]);

            // NT-8 — one delivery row per recipient × channel, deduped by event
            // occurrence + version (re-publishing a later version is a NEW event).
            $intent = NotificationIntent::v1(
                eventType: 'exam.session_published.v1',
                templateKey: 'exam.session_published',
                payload: [
                    'subject' => 'Your exam session has been scheduled',
                    'body' => "Your exam session {$session->code} has been published. See the NEXIAM portal for the schedule.",
                    'action_label' => 'View schedule',
                    'action_url' => route('applicant.schedule'),
                    'exam_session_id' => $session->id,
                    'code' => $session->code,
                    'starts_at' => $session->starts_at?->toIso8601String(),
                ],
                channels: [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL],
            );

            $eventId = hash('sha256', "exam.session_published:{$session->id}:v{$session->version}");

            $created = $this->dispatcher->dispatch(
                $intent,
                $eventId,
                NotificationRecipients::assignedApplicantsFor($session),
                $intent->channels,
            );

            $this->recordAuditEvent->handle('exam.session_published', [
                'exam_session_id' => $session->id,
                'assignment_count' => $assignments->count(),
                'delivery_count' => $created->count(),
            ], (string) $actor->id);

            return $session->fresh();
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $published;
    }
}
