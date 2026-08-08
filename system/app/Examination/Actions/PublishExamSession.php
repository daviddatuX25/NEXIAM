<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Release\DispatchNotificationOutboxEntry;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Release\NotificationOutbox;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PublishExamSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(ExamSession $session, User $actor, int $expectedVersion): ExamSession
    {
        $outboxIds = [];

        $published = DB::transaction(function () use ($session, $actor, $expectedVersion, &$outboxIds): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if ($session->status !== ExamSession::STATUS_DRAFT) {
                throw new RuntimeException('Only draft sessions may be published.');
            }

            $assignments = ExamAssignment::query()
                ->active()
                ->where('exam_session_id', $session->id)
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty()) {
                throw new RuntimeException('Assign at least one applicant before publishing.');
            }

            $session->update([
                'status' => ExamSession::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => $session->version + 1,
            ]);

            foreach ($assignments as $assignment) {
                $outbox = NotificationOutbox::query()->create([
                    'source_type' => ExamSession::class,
                    'source_id' => $session->id,
                    'recipient_type' => 'admission_attempt',
                    'recipient_id' => $assignment->admission_attempt_id,
                    'channel' => 'mail',
                    'payload' => [
                        'type' => 'exam.session_published',
                        'exam_session_id' => $session->id,
                        'exam_assignment_id' => $assignment->id,
                        'attempt_id' => $assignment->admission_attempt_id,
                        'starts_at' => $session->starts_at?->toIso8601String(),
                        'code' => $session->code,
                    ],
                    'status' => NotificationOutbox::STATUS_PENDING,
                    'idempotency_key' => 'exam-published-'.$session->id.'-attempt-'.$assignment->admission_attempt_id.'-v'.$session->version,
                ]);
                $outboxIds[] = $outbox->id;
            }

            $this->recordAuditEvent->handle('exam.session_published', [
                'exam_session_id' => $session->id,
                'assignment_count' => $assignments->count(),
                'outbox_ids' => $outboxIds,
            ], (string) $actor->id);

            return $session->fresh();
        });

        foreach ($outboxIds as $outboxId) {
            DispatchNotificationOutboxEntry::dispatchSync($outboxId);
        }

        return $published;
    }
}
