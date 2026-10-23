<?php

namespace App\Admission\Actions;

use App\Admission\Support\AcceptAdmissionAttemptEvaluator;
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
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AcceptAdmissionAttempt
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AutoAssignEligibleAttempt $autoAssign,
        private readonly AcceptAdmissionAttemptEvaluator $evaluator,
    ) {}

    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion): AdmissionAttempt
    {
        $created = collect();

        $accepted = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_EXAM,
            (int) $attempt->academic_cycle_id,
            function () use ($attempt, $actor, $expectedVersion, &$created): AdmissionAttempt {
                $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                $preflight = $this->evaluator->evaluate($attempt, $expectedVersion);
                if ($preflight->outcome === 'fail') {
                    foreach ($preflight->checks as $check) {
                        if ($check['status'] === 'fail') {
                            throw new RuntimeException(match ($check['key']) {
                                'version_matches' => 'Stale admission attempt version.',
                                'attempt_status_eligible' => 'Attempt is not eligible for acceptance.',
                                'cycle_active' => 'Academic cycle is not active.',
                                default => 'Admission attempt cannot be accepted.',
                            });
                        }
                    }
                }

                $attempt->update([
                    'status' => AdmissionAttempt::STATUS_ACCEPTED,
                    'accepted_at' => now(),
                    'version' => $attempt->version + 1,
                ]);

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

                    $created = $this->dispatcher->dispatch(
                        $intent,
                        hash('sha256', "admission.accepted:{$attempt->id}:v{$attempt->version}"),
                        [$recipient],
                        $channels,
                    );
                }

                return $attempt->fresh();
            },
        );

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        try {
            $this->autoAssign->handle(AssignmentInvariant::DOMAIN_EXAM, $accepted, $actor);
        } catch (\Throwable $exception) {
            Log::error('Exam continuous auto-assignment failed: '.$exception->getMessage(), ['exception' => $exception]);
        }

        return $accepted;
    }
}
