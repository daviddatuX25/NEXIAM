<?php

namespace App\Admission\Actions;

use App\Admission\Support\DismissAdmissionAttemptEvaluator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DismissAdmissionAttempt
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly DismissAdmissionAttemptEvaluator $evaluator,
    ) {}

    public function handle(AdmissionAttempt $attempt, User $actor, string $reason, int $expectedVersion): AdmissionAttempt
    {
        $reason = trim($reason);

        $created = collect();
        $dismissed = DB::transaction(function () use ($attempt, $actor, $reason, $expectedVersion, &$created): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }
            $preflight = $this->evaluator->evaluate($attempt, $reason);
            if ($preflight->outcome === 'fail') {
                foreach ($preflight->checks as $check) {
                    if ($check['status'] === 'fail') {
                        throw new RuntimeException(match ($check['key']) {
                            'attempt_status_eligible' => 'Only pending attempts can be dismissed.',
                            'justification_provided' => 'Dismissal justification is required.',
                            default => 'Admission attempt cannot be dismissed.',
                        });
                    }
                }
            }

            $attempt->update([
                'status' => AdmissionAttempt::STATUS_DISMISSED,
                'dismissal_reason' => $reason,
                'dismissed_at' => now(),
                'version' => $attempt->version + 1,
            ]);

            $this->recordAuditEvent->handle('admission.attempt_dismissed', [
                'attempt_id' => $attempt->id,
                'reason' => $reason,
            ], (string) $actor->id);

            $recipient = NotificationRecipients::applicantFor($attempt);

            if ($recipient !== null) {
                $policy = AdmissionNotificationPolicy::fromStorage($attempt->academicCycle?->admission_notification_policy);
                $channels = [NotificationDelivery::CHANNEL_IN_APP];

                if ($policy->applicantStatusEmailEnabled) {
                    $channels[] = NotificationDelivery::CHANNEL_MAIL;
                }

                $intent = NotificationIntent::v1(
                    eventType: 'admission.dismissed.v1',
                    templateKey: 'admission.dismissed',
                    payload: [
                        ...AdmissionExaminationNoticeContext::admissionPayload($attempt, 'dismissed'),
                        'subject' => 'Your application status changed',
                        'body' => 'A decision has been recorded for your application. Sign in to the portal to review the available details.',
                        'action_label' => 'View application',
                        'action_url' => AdmissionExaminationNoticeContext::url('applicant.application', $attempt),
                        'attempt_id' => $attempt->id,
                        'reference_code' => $attempt->reference_code,
                    ],
                    channels: $channels,
                );

                $created = $this->dispatcher->dispatch(
                    $intent,
                    hash('sha256', "admission.dismissed:{$attempt->id}:v{$attempt->version}"),
                    [$recipient],
                    $channels,
                );
            }

            return $attempt->fresh();
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $dismissed;
    }
}
