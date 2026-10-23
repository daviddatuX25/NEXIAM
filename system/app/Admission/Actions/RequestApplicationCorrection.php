<?php

namespace App\Admission\Actions;

use App\Admission\Support\RequestApplicationCorrectionEvaluator;
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

/**
 * Ask the applicant to fix missing/invalid evidence (GF-06 RequestApplicationCorrection).
 * Distinct from in-place CorrectAdmissionAttempt (which stays in the current queue status).
 */
class RequestApplicationCorrection
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
        private readonly RequestApplicationCorrectionEvaluator $evaluator,
    ) {}

    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion, string $reason): AdmissionAttempt
    {
        $reason = trim($reason);

        $created = collect();
        $requested = DB::transaction(function () use ($attempt, $actor, $expectedVersion, $reason, &$created): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            $preflight = $this->evaluator->evaluate($attempt, $reason);
            if ($preflight->outcome === 'fail') {
                foreach ($preflight->checks as $check) {
                    if ($check['status'] === 'fail') {
                        throw new RuntimeException(match ($check['key']) {
                            'attempt_status_eligible' => 'Only pending attempts can be sent for correction.',
                            'reason_provided' => 'Correction reason is required.',
                            default => 'Admission correction cannot be requested.',
                        });
                    }
                }
            }

            $attempt->update([
                'status' => AdmissionAttempt::STATUS_NEEDS_CORRECTION,
                'correction_notes' => $reason,
                'version' => $attempt->version + 1,
            ]);

            $this->recordAuditEvent->handle('admission.correction_requested', [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
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
                    eventType: 'admission.correction_requested.v1',
                    templateKey: 'admission.correction_requested',
                    payload: [
                        ...AdmissionExaminationNoticeContext::admissionPayload($attempt, 'correction-requested'),
                        'subject' => 'Application correction requested',
                        'body' => "A correction was requested for your application {$attempt->reference_code}. Please review the notes and re-upload the required details.",
                        'action_label' => 'Correct application',
                        'action_url' => AdmissionExaminationNoticeContext::url('applicant.correction', $attempt),
                        'attempt_id' => $attempt->id,
                        'reference_code' => $attempt->reference_code,
                    ],
                    channels: $channels,
                );

                $created = $this->dispatcher->dispatch(
                    $intent,
                    hash('sha256', "admission.correction_requested:{$attempt->id}:v{$attempt->version}"),
                    [$recipient],
                    $channels,
                );
            }

            return $attempt->fresh();
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $requested;
    }
}
