<?php

namespace App\Examination\Actions;

use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Support\ActionExecutionReceiptStore;
use App\Examination\Support\ActionExecutionResult;
use App\Examination\Support\ExamSessionPublishPreflight;
use App\Examination\Support\FreezeExamSessionLogisticsPlan;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

use function hash;
use function hash_equals;

final class PublishExamSession
{
    private const ACTION_KEY = 'exams.session.publish@1';

    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly ExamSessionPublishPreflight $preflight,
        private readonly ActionExecutionReceiptStore $receipts,
        private readonly FreezeExamSessionLogisticsPlan $freezeLogisticsPlan,
    ) {}

    public function handle(
        ExamSession $session,
        User $actor,
        int $expectedVersion,
        ?string $expectedFingerprint = null,
        ?int $testKitVersionId = null,
    ): ExamSession {
        $result = $this->execute($session, $actor, $expectedVersion, $expectedFingerprint, null, $testKitVersionId);
        if ($result instanceof ActionExecutionResult) {
            throw new LogicException('Human exam publication returned a Xiam receipt.');
        }

        return $result;
    }

    public function handleConfirmed(
        ExamSession $session,
        User $actor,
        int $expectedVersion,
        ?string $expectedFingerprint,
        ActionExecutionIdentity $identity,
        ?int $testKitVersionId = null,
    ): ActionExecutionResult {
        $result = $this->execute($session, $actor, $expectedVersion, $expectedFingerprint, $identity, $testKitVersionId);
        if (! $result instanceof ActionExecutionResult) {
            throw new LogicException('Confirmed exam publication did not return an Action receipt.');
        }

        return $result;
    }

    public function replayConfirmed(
        ExamSession $session,
        User $actor,
        int $expectedVersion,
        ?string $expectedFingerprint,
        ActionExecutionIdentity $identity,
        ?int $testKitVersionId = null,
    ): ?ActionExecutionResult {
        $claim = $this->receipts->replay(
            identity: $identity,
            actor: $actor,
            actionKey: self::ACTION_KEY,
            target: ['exam_session_id' => (int) $session->id],
            command: ['expected_version' => $expectedVersion, 'expected_fingerprint' => $expectedFingerprint],
        );

        return $claim === null ? null : new ActionExecutionResult($claim->safeResult, replayed: true);
    }

    private function execute(
        ExamSession $session,
        User $actor,
        int $expectedVersion,
        ?string $expectedFingerprint,
        ?ActionExecutionIdentity $identity,
        ?int $testKitVersionId,
    ): ExamSession|ActionExecutionResult {
        $created = collect();
        $cycleId = (int) $session->academic_cycle_id;
        $published = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_EXAM,
            $cycleId,
            function () use ($session, $actor, $expectedVersion, $expectedFingerprint, $identity, $cycleId, &$created): ExamSession|ActionExecutionResult {
                return DB::transaction(function () use ($session, $actor, $expectedVersion, $expectedFingerprint, $identity, $cycleId, &$created): ExamSession|ActionExecutionResult {
                    $institution = Institution::query()->lockForUpdate()->findOrFail((int) Institution::current()->id);
                    $claim = $identity === null ? null : $this->receipts->claim(
                        identity: $identity,
                        actor: $actor,
                        actionKey: self::ACTION_KEY,
                        target: ['exam_session_id' => (int) $session->id],
                        command: ['expected_version' => $expectedVersion, 'expected_fingerprint' => $expectedFingerprint],
                    );
                    if ($claim?->replayed) {
                        return new ActionExecutionResult($claim->safeResult, replayed: true);
                    }

                    $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
                    if ((int) $session->academic_cycle_id !== $cycleId) {
                        throw new RuntimeException('Exam cycle changed; reload the session before publishing.');
                    }
                    if ((int) $session->version !== $expectedVersion) {
                        throw new RuntimeException('Stale exam session version.');
                    }
                    if ($session->status !== ExamSession::STATUS_DRAFT) {
                        throw new RuntimeException('Only draft sessions may be published.');
                    }
                    if ($session->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
                        && $session->seating_arrangement_id !== null
                    ) {
                        $arrangement = SeatingArrangement::query()
                            ->lockForUpdate()
                            ->find($session->seating_arrangement_id);
                        if ($arrangement === null) {
                            throw new RuntimeException('The selected seating arrangement is unavailable.');
                        }
                        $session->setRelation('seatingArrangement', $arrangement);
                    }
                    $room = $session->room_id === null ? null : Room::query()->lockForUpdate()->find($session->room_id);
                    if ($room === null || ! $room->is_active || ! $room->isExamRoom()) {
                        throw new RuntimeException('Select an active examination room before publish.');
                    }
                    $assignments = ExamAssignment::query()
                        ->active()->where('exam_session_id', $session->id)
                        ->orderBy('admission_attempt_id')->orderBy('id')->lockForUpdate()->get();
                    $session->setRelation('room', $room);
                    $session->setRelation('academicCycle', $session->academicCycle()->first());
                    $preflight = $this->preflight->evaluate($session, $assignments->count());
                    if (! $preflight['ok']) {
                        throw new RuntimeException((string) ($preflight['reason'] ?? 'Exam session is no longer publishable.'));
                    }
                    $fingerprint = $preflight['roster_material_fingerprint'];
                    if (! is_string($fingerprint) || ($expectedFingerprint !== null && ! hash_equals($expectedFingerprint, $fingerprint))) {
                        throw new RuntimeException('Stale exam session roster or logistics state.');
                    }

                    $plan = $this->freezeLogisticsPlan->handle($session, $room, $assignments, $actor);
                    $effectivePolicy = ExamNotificationPolicy::fromSnapshot($session->notification_policy_snapshot)
                        ?? ExamNotificationPolicy::fromStorage($session->academicCycle?->exam_notification_policy)
                            ->withSessionOverride($session->notification_policy);
                    $policySnapshot = $effectivePolicy->snapshot($session->version + 1);
                    $reservationWasOpen = $session->booking_mode === ExamSession::BOOKING_OPEN_FOR_RESERVATION;
                    $session->update([
                        'status' => ExamSession::STATUS_PUBLISHED,
                        'published_at' => now(),
                        'version' => $session->version + 1,
                        'notification_policy_snapshot' => $policySnapshot,
                        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                        'auto_assign_enabled' => false,
                        ...($reservationWasOpen ? ['reservation_closed_at' => now()] : []),
                    ]);

                    $channels = [NotificationDelivery::CHANNEL_IN_APP];
                    if ($effectivePolicy->publicationEmailEnabled) {
                        $channels[] = NotificationDelivery::CHANNEL_MAIL;
                    }
                    $intent = NotificationIntent::v1(
                        eventType: 'exam.session_published.v1',
                        templateKey: 'exam.session_published',
                        payload: [
                            ...AdmissionExaminationNoticeContext::examinationPayload($session, 'session-published'),
                            'subject' => 'Your exam session has been scheduled',
                            'body' => "Your exam session {$session->code} has been published. See the NEXIAM portal for the schedule.",
                            'action_label' => 'View schedule',
                            'action_url' => AdmissionExaminationNoticeContext::url('applicant.schedule'),
                            'exam_session_id' => $session->id,
                            'code' => $session->code,
                            'starts_at' => $session->starts_at?->toIso8601String(),
                        ],
                        channels: $channels,
                    );
                    $created = $this->dispatcher->dispatch(
                        $intent,
                        hash('sha256', "exam.session_published:{$session->id}:v{$session->version}"),
                        NotificationRecipients::assignedApplicantsFor($session),
                        $intent->channels,
                    );

                    $safeResult = ['session_id' => $session->id, 'status' => $session->status, 'version' => $session->version];
                    if ($claim !== null) {
                        $this->receipts->complete($claim, $safeResult);
                    }
                    $this->recordAuditEvent->handle('exam.session_published', [
                        'exam_session_id' => $session->id,
                        'assignment_count' => $assignments->count(),
                        'delivery_count' => $created->count(),
                        'logistics_plan_id' => $plan->id,
                        'logistics_plan_revision' => $plan->revision,
                        'roster_fingerprint' => $fingerprint,
                    ], (string) $actor->id);

                    return $claim === null ? ($session->fresh() ?? $session) : new ActionExecutionResult($safeResult, replayed: false);
                });
            },
        );

        if ($published instanceof ExamSession || ! $published->replayed) {
            NotificationIntentDispatcher::enqueueAfterCommit($created);
        }

        return $published;
    }
}
