<?php

namespace App\Examination\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Support\EligibleProctorCandidates;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssignProctorDuty
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly EligibleProctorCandidates $eligible,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    public function handle(ExamSession $session, User $proctor, User $actor, string $idempotencyKey): ProctorDuty
    {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN)) {
            throw new RuntimeException('proctor_assign_denied');
        }

        if (! $this->eligible->isEligible($proctor)) {
            throw new RuntimeException('proctor_assignee_ineligible');
        }

        $existing = ProctorDuty::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $duplicate = ProctorDuty::query()
            ->where('exam_session_id', $session->id)
            ->where('user_id', $proctor->id)
            ->where('status', ProctorDuty::STATUS_ACTIVE)
            ->first();
        if ($duplicate !== null) {
            return $duplicate;
        }

        $created = collect();

        $duty = DB::transaction(function () use ($session, $proctor, $actor, $idempotencyKey, &$created): ProctorDuty {
            $duty = ProctorDuty::query()->create([
                'exam_session_id' => $session->id,
                'user_id' => $proctor->id,
                'status' => ProctorDuty::STATUS_ACTIVE,
                'idempotency_key' => $idempotencyKey,
                'assigned_at' => now(),
            ]);

            $this->recordAuditEvent->handle('exam.proctor_duty_assigned', [
                'duty_id' => $duty->id,
                'exam_session_id' => $session->id,
                'proctor_user_id' => $proctor->id,
            ], (string) $actor->id);

            $intent = NotificationIntent::v1(
                eventType: 'exam.proctor_duty_assigned.v1',
                templateKey: 'exam.proctor_duty_assigned',
                payload: [
                    'subject' => 'You have been assigned proctor duty',
                    'body' => "You are assigned as proctor for exam session {$session->code}. Open Exam Monitoring to record attendance and submission.",
                    'action_label' => 'Open exam monitoring',
                    'action_url' => route('exams.sessions.monitoring', $session),
                    'exam_session_id' => $session->id,
                    'duty_id' => $duty->id,
                    'code' => $session->code,
                    'starts_at' => $session->starts_at?->toIso8601String(),
                ],
            );

            $created = $this->dispatcher->dispatch(
                $intent,
                hash('sha256', "exam.proctor_duty_assigned:{$duty->id}:v1"),
                [$proctor],
                $intent->channels,
            );

            return $duty;
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $duty;
    }
}
