<?php

namespace App\Release\Actions;

use App\Consultation\Support\AttemptResultReadiness;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReleaseResult
{
    public const MODE_F2F = 'f2f';

    public const MODE_ONLINE = 'online';

    /**
     * @var list<string>
     */
    public const MODES = [
        self::MODE_F2F,
        self::MODE_ONLINE,
    ];

    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        AdmissionAttempt $attempt,
        ResultDocument $document,
        User $actor,
        string $idempotencyKey,
        string $mode = self::MODE_F2F,
    ): ResultRelease {
        if (! in_array($mode, self::MODES, true)) {
            throw new RuntimeException('Invalid release mode.');
        }

        // Idempotency check bound to target attempt + document
        $existing = ResultRelease::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            if ($existing->admission_attempt_id !== $attempt->id || $existing->result_document_id !== $document->id) {
                throw new RuntimeException('Idempotency key has already been used for a different admission attempt or document.');
            }

            return $existing;
        }

        $created = collect();

        $release = DB::transaction(function () use ($attempt, $document, $actor, $idempotencyKey, $mode, &$created): ResultRelease {
            // Lock attempt and document for update to guarantee concurrency safety
            $attempt = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $document = ResultDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            // Re-check idempotency under lock in case of concurrent execution
            $existing = ResultRelease::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->admission_attempt_id !== $attempt->id || $existing->result_document_id !== $document->id) {
                    throw new RuntimeException('Idempotency key has already been used for a different admission attempt or document.');
                }

                return $existing;
            }

            // Document Ownership Invariant: prevent foreign document release
            if ($document->admission_attempt_id !== $attempt->id) {
                throw new RuntimeException('Result document does not belong to this admission attempt.');
            }

            // Prerequisite 1: Document must be ready
            if ($document->status !== ResultDocument::STATUS_READY) {
                throw new RuntimeException('Result document is not ready.');
            }

            // Prerequisite 2: Attempt must be accepted
            if (! $attempt->isAccepted()) {
                throw new RuntimeException('Admission attempt must be accepted through intake verification before results release.');
            }

            // Prerequisite 3: the exact current document must contain frozen individual scores.
            if (
                (int) $attempt->current_result_document_id !== (int) $document->id
                || ! AttemptResultReadiness::isReady($attempt)
            ) {
                throw new RuntimeException('The current prepared individual result is required before release.');
            }

            // Consultation is not a release prerequisite; an exact current prepared individual result is.

            // Concurrency: Lock current active releases for this attempt
            $currentReleases = ResultRelease::query()
                ->where('admission_attempt_id', $attempt->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->get();

            // Demote current release: mark not current and record superseded/unreleased timestamp
            foreach ($currentReleases as $currentRelease) {
                $currentRelease->update([
                    'is_current' => false,
                    'superseded_at' => now(),
                    'unreleased_at' => now(),
                    'unrelease_reason' => 'Superseded by new release version.',
                ]);
            }

            $version = (int) ResultRelease::query()->where('admission_attempt_id', $attempt->id)->max('release_version') + 1;

            $release = ResultRelease::query()->create([
                'admission_attempt_id' => $attempt->id,
                'result_document_id' => $document->id,
                'released_by_user_id' => $actor->id,
                'release_version' => $version,
                'is_current' => true,
                'released_at' => now(),
                'idempotency_key' => $idempotencyKey,
                'mode' => $mode,
            ]);

            $this->recordAuditEvent->handle('release.result_released', [
                'release_id' => $release->id,
                'attempt_id' => $attempt->id,
                'document_id' => $document->id,
                'version' => $version,
                'mode' => $mode,
            ], (string) $actor->id);

            $recipient = NotificationRecipients::applicantFor($attempt);

            if ($recipient !== null) {
                $online = $mode === self::MODE_ONLINE;

                $intent = NotificationIntent::v1(
                    eventType: 'result.released.v1',
                    templateKey: $online ? 'result.released.online' : 'result.released.f2f',
                    payload: [
                        'institution_id' => $attempt->institution_id,
                        'domain' => 'results',
                        'canonical_slug' => $online ? 'released-online' : 'released-f2f',
                        'subject' => $online ? 'Your admission result is available' : 'Your admission result is ready',
                        'body' => $online
                            ? "Your admission result for {$attempt->reference_code} has been released. View it in the NEXIAM portal."
                            : "Your admission result for {$attempt->reference_code} has been released. Claim it at your consultation session.",
                        'action_label' => 'View result',
                        'action_url' => route('applicant.portal'),
                        'release_id' => $release->id,
                        'release_version' => $release->release_version,
                        'attempt_id' => $attempt->id,
                        'identity_id' => $recipient->id,
                        'mode' => $mode,
                        'event_context' => [
                            'application_no' => $attempt->reference_code,
                            'applicant_name' => $recipient->fullName(),
                            'institution_name' => $attempt->academicCycle?->institution?->name,
                            'portal_url' => route('applicant.portal'),
                        ],
                        'document_snapshot' => $online ? [
                            'mode' => 'result_document',
                            'document_id' => $document->id,
                            'document_path' => $document->storage_path,
                            'document_version' => $document->version,
                            'release_id' => $release->id,
                            'release_version' => $release->release_version,
                            'attempt_id' => $attempt->id,
                            'identity_id' => $recipient->id,
                            'institution_id' => $attempt->institution_id,
                            'document_checksum' => $document->checksum,
                            'paper_size' => 'a4',
                            'orientation' => 'portrait',
                        ] : null,
                    ],
                    channels: $online
                        ? [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL]
                        : [NotificationDelivery::CHANNEL_IN_APP],
                );

                $created = app(NotificationIntentDispatcher::class)->dispatch(
                    $intent,
                    hash('sha256', "result.released:{$release->id}:v{$version}"),
                    [$recipient],
                    $intent->channels,
                );
            }

            return $release;
        });

        NotificationIntentDispatcher::enqueueAfterCommit($created);

        return $release;
    }
}
