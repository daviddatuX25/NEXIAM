<?php

namespace App\Release\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Release\DispatchNotificationOutboxEntry;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\NotificationOutbox;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReleaseResult
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(
        AdmissionAttempt $attempt,
        ResultDocument $document,
        User $actor,
        string $idempotencyKey,
    ): ResultRelease {
        $existing = ResultRelease::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $outboxId = null;

        $release = DB::transaction(function () use ($attempt, $document, $actor, $idempotencyKey, &$outboxId): ResultRelease {
            if ($document->status !== ResultDocument::STATUS_READY) {
                throw new RuntimeException('Result document is not ready.');
            }

            $hasDecision = ConsultationDecision::query()
                ->where('admission_attempt_id', $attempt->id)
                ->whereIn('decision_type', ['approve', 'modify', 'override'])
                ->exists();

            if (! $hasDecision) {
                throw new RuntimeException('Consultation decision prerequisite missing.');
            }

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
            ]);

            $this->recordAuditEvent->handle('release.result_released', [
                'release_id' => $release->id,
                'attempt_id' => $attempt->id,
            ], (string) $actor->id);

            $outbox = NotificationOutbox::query()->create([
                'source_type' => ResultRelease::class,
                'source_id' => $release->id,
                'recipient_type' => AdmissionAttempt::class,
                'recipient_id' => $attempt->id,
                'channel' => 'mail',
                'payload' => [
                    'template' => 'result.released',
                    'reference_code' => $attempt->reference_code,
                ],
                'status' => NotificationOutbox::STATUS_PENDING,
                'idempotency_key' => 'notify-release-'.$release->id,
            ]);

            $outboxId = $outbox->id;

            return $release;
        });

        if ($outboxId !== null) {
            DispatchNotificationOutboxEntry::dispatchSync($outboxId);
        }

        return $release;
    }
}
