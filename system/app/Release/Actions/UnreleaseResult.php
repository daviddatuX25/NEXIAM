<?php

namespace App\Release\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnreleaseResult
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    public function handle(ResultRelease $release, User $actor, string $reason): ResultRelease
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Unrelease reason is required.');
        }

        $created = collect();
        $unreleased = DB::transaction(function () use ($release, $actor, $reason, &$created): ResultRelease {
            $release = ResultRelease::query()->with('admissionAttempt.identity')->lockForUpdate()->findOrFail($release->id);

            if (! $release->is_current || $release->unreleased_at !== null) {
                throw new RuntimeException('Release is not currently active.');
            }

            $release->update([
                'is_current' => false,
                'unreleased_at' => now(),
                'unrelease_reason' => $reason,
            ]);

            $this->recordAuditEvent->handle('release.result_unreleased', [
                'release_id' => $release->id,
                'attempt_id' => $release->admission_attempt_id,
                'reason' => $reason,
            ], (string) $actor->id);

            $attempt = $release->admissionAttempt;
            $recipient = $attempt !== null ? NotificationRecipients::applicantFor($attempt) : null;

            if ($recipient !== null) {
                $intent = NotificationIntent::v1(
                    eventType: 'result.unreleased.v1',
                    templateKey: 'result.unreleased',
                    payload: [
                        'institution_id' => $attempt->institution_id,
                        'domain' => 'results',
                        'canonical_slug' => 'unreleased',
                        'subject' => 'Admission result retracted for review',
                        'body' => "Your admission result has been retracted for review ({$reason}). You will be notified once the updated result is ready.",
                        'action_label' => 'View status',
                        'action_url' => route('applicant.portal'),
                        'release_id' => $release->id,
                        'release_version' => $release->release_version,
                        'identity_id' => $recipient->id,
                        'mode' => $release->mode,
                        'attempt_id' => $release->admission_attempt_id,
                    ],
                    channels: [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL],
                );

                $created = $created->concat($this->dispatcher->dispatch(
                    $intent,
                    hash('sha256', "result.unreleased:{$release->id}:v{$release->release_version}"),
                    [$recipient],
                    $intent->channels,
                ));
            }

            return $release->fresh();
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $unreleased;
    }
}
