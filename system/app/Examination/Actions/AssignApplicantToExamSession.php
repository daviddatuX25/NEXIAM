<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssignApplicantToExamSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(ExamSession $session, AdmissionAttempt $attempt, User $actor, ?string $seat = null): ExamAssignment
    {
        return DB::transaction(function () use ($session, $attempt, $actor, $seat): ExamAssignment {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            // Single eligibility gate — authorized amendment while accepted keeps this true;
            // ReconsiderAdmissionAttempt flips it false without deleting history.
            if (! $attempt->isExamEligible()) {
                throw new RuntimeException('Only exam-eligible (accepted) attempts may be assigned.');
            }

            if (in_array($session->status, [ExamSession::STATUS_CANCELLED, ExamSession::STATUS_COMPLETED], true)) {
                throw new RuntimeException('Cannot assign to a terminal exam session.');
            }

            $hasActiveAssignment = ExamAssignment::query()
                ->active()
                ->where('admission_attempt_id', $attempt->id)
                ->exists();

            if ($hasActiveAssignment) {
                throw new RuntimeException('Attempt already has an active exam assignment.');
            }

            $activeAssignments = ExamAssignment::query()
                ->active()
                ->where('exam_session_id', $session->id)
                ->count();

            $room = $session->room()->firstOrFail();

            if ($activeAssignments >= $room->capacity) {
                throw new RuntimeException('Exam session capacity exceeded.');
            }

            $assignment = ExamAssignment::query()->create([
                'exam_session_id' => $session->id,
                'admission_attempt_id' => $attempt->id,
                'seat_label' => $seat,
                'status' => ExamAssignment::STATUS_ASSIGNED,
                'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
            ]);

            $this->recordAuditEvent->handle('exam.assignment_created', [
                'assignment_id' => $assignment->id,
                'exam_session_id' => $session->id,
                'attempt_id' => $attempt->id,
                'source' => $assignment->source,
            ], (string) $actor->id);

            return $assignment;
        });
    }
}
