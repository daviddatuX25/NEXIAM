<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReserveExamSlot
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(
        ExamSession $session,
        AdmissionAttempt $attempt,
        User $actor,
        ?string $idempotencyKey = null,
    ): ExamAssignment {
        return DB::transaction(function () use ($session, $attempt, $actor, $idempotencyKey): ExamAssignment {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (! $attempt->isExamEligible()) {
                throw new RuntimeException('Only accepted applicants may reserve an exam slot.');
            }

            if (! $session->isOpenForReservation()) {
                throw new RuntimeException('This session is not open for applicant reservation.');
            }

            if (in_array($session->status, [ExamSession::STATUS_CANCELLED, ExamSession::STATUS_COMPLETED], true)) {
                throw new RuntimeException('This exam session is no longer available.');
            }

            if ((int) $attempt->academic_cycle_id !== (int) $session->academic_cycle_id) {
                throw new RuntimeException('Attempt is not in the same academic year as this session.');
            }

            $existingForSession = ExamAssignment::query()
                ->active()
                ->where('admission_attempt_id', $attempt->id)
                ->where('exam_session_id', $session->id)
                ->first();

            if ($existingForSession !== null) {
                return $existingForSession;
            }

            $otherActive = ExamAssignment::query()
                ->active()
                ->where('admission_attempt_id', $attempt->id)
                ->first();

            if ($otherActive !== null) {
                throw new RuntimeException('You already have an active exam assignment.');
            }

            $activeAssignments = ExamAssignment::query()
                ->active()
                ->where('exam_session_id', $session->id)
                ->count();

            $capacity = (int) $session->room()->value('capacity');
            if ($activeAssignments >= $capacity) {
                throw new RuntimeException('This exam session is full.');
            }

            $assignment = ExamAssignment::query()->create([
                'exam_session_id' => $session->id,
                'admission_attempt_id' => $attempt->id,
                'status' => ExamAssignment::STATUS_ASSIGNED,
                'source' => ExamAssignment::SOURCE_APPLICANT_RESERVED,
            ]);

            $this->recordAuditEvent->handle('exam.slot_reserved', [
                'assignment_id' => $assignment->id,
                'exam_session_id' => $session->id,
                'attempt_id' => $attempt->id,
                'source' => $assignment->source,
                'idempotency_key' => $idempotencyKey,
            ], (string) $actor->id);

            return $assignment;
        });
    }
}
