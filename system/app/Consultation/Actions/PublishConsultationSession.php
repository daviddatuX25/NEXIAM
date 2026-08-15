<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PublishConsultationSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    public function handle(ConsultationSession $session, User $actor, int $expectedVersion): ConsultationSession
    {
        $created = collect();

        $published = DB::transaction(function () use ($session, $actor, $expectedVersion, &$created): ConsultationSession {
            $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale consultation session version.');
            }

            if ($session->status !== ConsultationSession::STATUS_DRAFT) {
                throw new RuntimeException('Only draft sessions may be published.');
            }

            $assignments = ConsultationAssignment::query()
                ->active()
                ->where('consultation_session_id', $session->id)
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty()) {
                throw new RuntimeException('Assign at least one applicant before publishing.');
            }

            $session->update([
                'status' => ConsultationSession::STATUS_PUBLISHED,
                'published_by_user_id' => $actor->id,
                'published_at' => now(),
                'version' => $session->version + 1,
            ]);

            $intent = NotificationIntent::v1(
                eventType: 'consultation.session_published.v1',
                templateKey: 'consultation.session_published',
                payload: [
                    'subject' => 'Your consultation session has been scheduled',
                    'body' => "Your consultation session {$session->code} has been published. See the NEXIAM portal for the schedule.",
                    'action_label' => 'View consultation schedule',
                    'action_url' => route('applicant.consultation'),
                    'consultation_session_id' => $session->id,
                    'code' => $session->code,
                    'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                ],
                channels: [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL],
            );

            $eventId = hash('sha256', "consultation.session_published:{$session->id}:v{$session->version}");

            $created = $this->dispatcher->dispatch(
                $intent,
                $eventId,
                NotificationRecipients::assignedConsultationApplicantsFor($session),
                $intent->channels,
            );

            $this->recordAuditEvent->handle('consultation.session_published', [
                'consultation_session_id' => $session->id,
                'assignment_count' => $assignments->count(),
                'delivery_count' => $created->count(),
            ], (string) $actor->id);

            return $session->fresh();
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $published;
    }
}
