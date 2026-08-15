<?php

namespace App\Admission\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AcceptAdmissionAttempt
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {
    }

    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion): AdmissionAttempt
    {
        $created = collect();

        $accepted = DB::transaction(function () use ($attempt, $actor, $expectedVersion, &$created): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            if ($attempt->status !== AdmissionAttempt::STATUS_PENDING
                && $attempt->status !== AdmissionAttempt::STATUS_NEEDS_CORRECTION) {
                throw new RuntimeException('Attempt is not eligible for acceptance.');
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
                $intent = NotificationIntent::v1(
                    eventType: 'admission.accepted.v1',
                    templateKey: 'admission.accepted',
                    payload: [
                        'subject' => 'Your admission application was accepted',
                        'body' => "Your application {$attempt->reference_code} has been accepted. The exam office will contact you with the next steps.",
                        'action_label' => 'View application',
                        'action_url' => route('applicant.portal'),
                        'attempt_id' => $attempt->id,
                        'reference_code' => $attempt->reference_code,
                    ],
                );

                $created = $this->dispatcher->dispatch(
                    $intent,
                    hash('sha256', "admission.accepted:{$attempt->id}:v{$attempt->version}"),
                    [$recipient],
                    $intent->channels,
                );
            }

            return $attempt->fresh();
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $accepted;
    }
}
