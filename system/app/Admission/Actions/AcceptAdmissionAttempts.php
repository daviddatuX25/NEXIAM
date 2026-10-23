<?php

namespace App\Admission\Actions;

use App\Admission\Support\AcceptAdmissionAttemptEvaluator;
use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Support\ActionExecutionReceiptStore;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use App\Scheduling\Actions\AutoAssignEligibleAttempt;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class AcceptAdmissionAttempts
{
    private const ACTION_KEY = 'admissions.attempt.bulk_accept@1';

    public function __construct(
        private readonly AcceptAdmissionAttemptEvaluator $evaluator,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly ActionExecutionReceiptStore $receipts,
        private readonly AutoAssignEligibleAttempt $autoAssign,
    ) {}

    /**
     * @param  list<int>  $attemptIds
     * @return array{ok: true, accepted_count: int, attempt_ids: list<int>}
     */
    public function handle(
        array $attemptIds,
        int $cycleId,
        User $actor,
        ?ActionExecutionIdentity $executionIdentity = null,
    ): array {
        if (count($attemptIds) < 1 || count($attemptIds) > 25) {
            throw new RuntimeException('Bulk acceptance requires between 1 and 25 attempt IDs.');
        }

        $attemptIds = array_values(array_unique($attemptIds));
        sort($attemptIds);

        /** @var Collection<int, mixed> $createdDeliveries */
        $createdDeliveries = collect();
        /** @var Collection<int, AdmissionAttempt> $acceptedAttempts */
        $acceptedAttempts = collect();

        $result = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_EXAM,
            $cycleId,
            function () use ($attemptIds, $cycleId, $actor, $executionIdentity, &$createdDeliveries, &$acceptedAttempts): array {
                $receiptClaim = null;

                if ($executionIdentity !== null) {
                    $receiptClaim = $this->receipts->claim(
                        identity: $executionIdentity,
                        actor: $actor,
                        actionKey: self::ACTION_KEY,
                        target: ['cycle_id' => $cycleId],
                        command: ['attempt_ids' => $attemptIds],
                    );

                    if ($receiptClaim->replayed) {
                        return $receiptClaim->safeResult;
                    }
                }

                $attempts = AdmissionAttempt::query()
                    ->whereIn('id', $attemptIds)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                if ($attempts->count() !== count($attemptIds)) {
                    throw new RuntimeException('One or more admission attempts do not exist.');
                }

                foreach ($attempts as $attempt) {
                    if ((int) $attempt->academic_cycle_id !== $cycleId) {
                        throw new RuntimeException('All admission attempts must belong to the requested cycle.');
                    }

                    $evaluation = $this->evaluator->evaluate($attempt);

                    if ($evaluation->outcome !== 'pass') {
                        throw new RuntimeException('An admission attempt failed acceptance evaluation.');
                    }
                }

                foreach ($attempts as $attempt) {
                    $attempt->update([
                        'status' => AdmissionAttempt::STATUS_ACCEPTED,
                        'accepted_at' => now(),
                        'version' => $attempt->version + 1,
                    ]);
                    $acceptedAttempts->push($attempt);

                    $this->recordAuditEvent->handle('admission.attempt_accepted', [
                        'attempt_id' => $attempt->id,
                        'reference_code' => $attempt->reference_code,
                    ], (string) $actor->id);

                    $recipient = NotificationRecipients::applicantFor($attempt);

                    if ($recipient !== null) {
                        $cycle = $attempt->academicCycle;
                        $policy = AdmissionNotificationPolicy::fromStorage($cycle?->admission_notification_policy);
                        $channels = [NotificationDelivery::CHANNEL_IN_APP];

                        if ($policy->applicantStatusEmailEnabled) {
                            $channels[] = NotificationDelivery::CHANNEL_MAIL;
                        }

                        $intent = NotificationIntent::v1(
                            eventType: 'admission.accepted.v1',
                            templateKey: 'admission.accepted',
                            payload: [
                                ...AdmissionExaminationNoticeContext::admissionPayload($attempt, 'accepted'),
                                'subject' => 'Your admission application was accepted',
                                'body' => "Your application {$attempt->reference_code} has been accepted. Sign in to review the next steps.",
                                'action_label' => 'View application',
                                'action_url' => AdmissionExaminationNoticeContext::url('applicant.portal'),
                                'attempt_id' => $attempt->id,
                                'reference_code' => $attempt->reference_code,
                            ],
                            channels: $channels,
                        );

                        $createdDeliveries = $createdDeliveries->concat($this->dispatcher->dispatch(
                            $intent,
                            hash('sha256', "admission.accepted:{$attempt->id}:v{$attempt->version}"),
                            [$recipient],
                            $channels,
                        ));
                    }
                }

                $result = [
                    'ok' => true,
                    'accepted_count' => count($attemptIds),
                    'attempt_ids' => $attemptIds,
                ];

                if ($receiptClaim !== null) {
                    $this->receipts->complete($receiptClaim, $result);
                }

                return $result;
            },
        );

        NotificationIntentDispatcher::enqueueAfterCommit($createdDeliveries);

        foreach ($acceptedAttempts as $attempt) {
            try {
                $this->autoAssign->handle(AssignmentInvariant::DOMAIN_EXAM, $attempt, $actor);
            } catch (\Throwable $e) {
                Log::error('Exam continuous auto-assignment failed: '.$e->getMessage(), ['exception' => $e]);
            }
        }

        return $result;
    }
}
