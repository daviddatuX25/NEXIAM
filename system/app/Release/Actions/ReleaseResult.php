<?php

namespace App\Release\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
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

        $existing = ResultRelease::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $created = collect();

        $release = DB::transaction(function () use ($attempt, $document, $actor, $idempotencyKey, $mode, &$created): ResultRelease {
            if ($document->status !== ResultDocument::STATUS_READY) {
                throw new RuntimeException('Result document is not ready.');
            }

            // I2 (2026-08-14): consultation is NOT a release prerequisite —
            // release requires grading finalized + document prepared only.
            $sessionId = ExamAssignment::query()
                ->where('admission_attempt_id', $attempt->id)
                ->value('exam_session_id');

            if ($sessionId === null) {
                throw new RuntimeException('Exam assignment required before release.');
            }

            $gradingFinalized = GradingSession::query()
                ->where('exam_session_id', $sessionId)
                ->where('status', GradingSession::STATUS_FINALIZED)
                ->exists();

            if (! $gradingFinalized) {
                throw new RuntimeException('Grading must be finalized before release.');
            }

            ResultRelease::query()
                ->where('admission_attempt_id', $attempt->id)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'unreleased_at' => now(),
                ]);

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
                'mode' => $mode,
            ], (string) $actor->id);

            $recipient = NotificationRecipients::applicantFor($attempt);

            if ($recipient !== null) {
                $online = $mode === self::MODE_ONLINE;

                $intent = NotificationIntent::v1(
                    eventType: 'result.released.v1',
                    templateKey: $online ? 'result.released.online' : 'result.released.f2f',
                    payload: [
                        'subject' => $online ? 'Your admission result is available' : 'Your admission result is ready',
                        'body' => $online
                            ? "Your admission result for {$attempt->reference_code} has been released. View it in the NEXIAM portal."
                            : "Your admission result for {$attempt->reference_code} has been released. Claim it at your consultation session.",
                        'action_label' => 'View result',
                        'action_url' => route('applicant.portal'),
                        'attempt_id' => $attempt->id,
                        'reference_code' => $attempt->reference_code,
                        'mode' => $mode,
                    ],
                    channels: [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL],
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
