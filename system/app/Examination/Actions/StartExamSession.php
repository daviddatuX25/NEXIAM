<?php

namespace App\Examination\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StartExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    public function handle(
        ExamSession $session,
        User $actor,
        int $expectedVersion,
        ?int $durationMinutes = null,
    ): ExamSession {
        $this->authorizeActor($actor, $session);

        $created = collect();

        $started = DB::transaction(function () use ($session, $actor, $expectedVersion, $durationMinutes, &$created): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
            $session->loadMissing(['academicCycle', 'room']);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if ($session->status === ExamSession::STATUS_IN_PROGRESS) {
                return $session;
            }

            if ($session->status !== ExamSession::STATUS_PUBLISHED || $session->closed_at !== null) {
                throw new RuntimeException('Only published, unclosed sessions may be started.');
            }

            $doorLeadMinutes = (int) config('examination.thresholds.door_checkin_lead_minutes', 30);
            $windowOpensAt = $session->starts_at->copy()->subMinutes($doorLeadMinutes);
            $hasScheduleOverride = $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, [
                'institution_id' => (int) $session->academicCycle?->institution_id,
                'academic_cycle_id' => (int) $session->academic_cycle_id,
                'office' => 'guidance',
            ]);

            if (! $hasScheduleOverride && (now()->lt($windowOpensAt) || now()->gt($session->effective_end_at))) {
                throw new RuntimeException('Session can only be started within the conduct window.');
            }

            $startedAt = now();
            $effectiveEndAt = $durationMinutes !== null
                ? $startedAt->copy()->addMinutes(max(15, $durationMinutes))
                : $session->effective_end_at;

            $session->update([
                'status' => ExamSession::STATUS_IN_PROGRESS,
                'started_at' => $startedAt,
                'effective_end_at' => $effectiveEndAt,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_started', [
                'exam_session_id' => $session->id,
                'started_at' => $session->started_at?->toIso8601String(),
                'effective_end_at' => $session->effective_end_at?->toIso8601String(),
            ], (string) $actor->id);

            $intent = NotificationIntent::v1(
                eventType: 'exam.session_started.v1',
                templateKey: 'exam.session_started',
                payload: [
                    'session_id' => $session->id,
                    'session_code' => $session->code,
                    'room_name' => $session->room?->name,
                    'starts_at' => $session->starts_at?->toIso8601String(),
                    'started_by' => $actor->name,
                    'subject' => 'Exam Session Started',
                    'body' => "Session {$session->code} has been started by {$actor->name}. Attendance is now open.",
                    'action_label' => 'Open Exam Monitoring',
                    'action_url' => AdmissionExaminationNoticeContext::url('exams.sessions.monitoring', $session),
                ],
            );
            if ($startedAt->lt($session->starts_at)) {
                $applicantIntent = NotificationIntent::v1(
                    eventType: 'exam.session_started_early.v1',
                    templateKey: 'exam.session_started',
                    payload: [
                        'subject' => "Exam session {$session->code} started early",
                        'body' => "Exam session {$session->code} started early. Started at ".self::campusInstant($session->started_at).'; expected end '.self::campusInstant($session->effective_end_at).'.',
                        'action_label' => 'View schedule',
                        'action_url' => AdmissionExaminationNoticeContext::url('applicant.schedule'),
                        'session_id' => (int) $session->id,
                        'session_version' => (int) $session->version,
                        'academic_cycle_id' => (int) $session->academic_cycle_id,
                        'institution_id' => (int) $session->academicCycle?->institution_id,
                        'started_at' => $session->started_at?->toIso8601String(),
                        'effective_end_at' => $session->effective_end_at?->toIso8601String(),
                    ],
                    channels: [NotificationDelivery::CHANNEL_IN_APP],
                );

                $created = $created->merge($this->dispatcher->dispatch(
                    $applicantIntent,
                    hash('sha256', "exam.session_started_early:{$session->id}:v{$session->version}"),
                    NotificationRecipients::assignedApplicantsFor($session),
                    $applicantIntent->channels,
                ));
            }

            $recipients = NotificationRecipients::testAdministratorUsersFor($session, $this->authorizer);

            $created = $created->merge($this->dispatcher->dispatch(
                $intent,
                hash('sha256', "exam.session_started:{$session->id}:v{$session->version}"),
                $recipients,
                $intent->channels,
            ));

            return $session->fresh();
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $started;
    }

    private function authorizeActor(User $actor, ExamSession $session): void
    {
        $session->loadMissing(['academicCycle', 'room']);

        if ($this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, [
            'institution_id' => (int) $session->academicCycle?->institution_id,
            'office' => 'guidance',
        ])) {
            return;
        }

        throw new RuntimeException('You are not authorized to start this session.');
    }

    private static function campusInstant(?Carbon $instant): string
    {
        return $instant?->copy()->setTimezone('Asia/Manila')->format('F j, Y g:i A T') ?? 'Not recorded';
    }
}
