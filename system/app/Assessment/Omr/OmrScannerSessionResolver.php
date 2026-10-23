<?php

namespace App\Assessment\Omr;

use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

use function trim;

/**
 * Resolves generic scanner context from Applicant Number alone.
 *
 * Names, birthdates, time, room, handwriting, observed Set, and browser
 * session identifiers are deliberately absent from this boundary.
 */
final class OmrScannerSessionResolver
{
    public function routeBound(GradingSession $gradingSession): OmrScannerContext
    {
        return OmrScannerContext::routeBound($gradingSession);
    }

    public function discover(string $applicantNumber, User $actor): OmrScannerContext
    {
        $cycle = $this->resolveActorCycle($actor);
        $applicantNumber = trim($applicantNumber);
        if ($applicantNumber === '') {
            return OmrScannerContext::selectionRequired([], 'applicant_unresolved');
        }

        $assignments = ExamAssignment::query()
            ->with([
                'admissionAttempt',
                'examSession.gradingSession.omrCaptureBatches',
                'examSession.room',
                'examSession.academicCycle',
                'submissionRecord.seatAllocation.sessionSeat.materialSnapshot',
            ])
            ->active()
            ->whereHas('attendanceRecord', fn (Builder $query): Builder => $query->where(
                'outcome',
                ExamAttendanceRecord::OUTCOME_PRESENT,
            ))
            ->whereHas('admissionAttempt', fn (Builder $query): Builder => $query->where(
                'reference_code',
                $applicantNumber,
            ))
            ->whereHas('examSession.gradingSession', fn (Builder $query): Builder => $query->whereIn(
                'status',
                [GradingSession::STATUS_OPEN, GradingSession::STATUS_REVIEW],
            ))
            ->whereHas(
                'examSession.gradingSession.omrCaptureBatches',
                fn (Builder $query): Builder => $query->whereIn('status', OmrCaptureBatch::ACTIVE_STATUSES),
            )
            ->whereHas('examSession.academicCycle', function (Builder $query) use ($cycle): void {
                $query
                    ->whereKey($cycle->id)
                    ->where('institution_id', $cycle->institution_id)
                    ->where('code', $cycle->code);
            });

        $assignments = $assignments->orderBy('id')->get();

        $eligible = $assignments->filter(fn (ExamAssignment $assignment): bool => $this->hasFrozenSubmission($assignment));
        $candidates = $this->candidates($eligible);

        if ($eligible->count() === 1) {
            /** @var ExamAssignment $assignment */
            $assignment = $eligible->first();

            return OmrScannerContext::autoResolved($assignment->examSession->gradingSession);
        }

        if ($eligible->count() > 1) {
            return OmrScannerContext::selectionRequired($candidates, 'session_ambiguous');
        }

        if ($assignments->count() === 1) {
            /** @var ExamAssignment $assignment */
            $assignment = $assignments->first();
            $code = $assignment->submissionRecord === null ? 'submission_missing' : 'seat_missing';

            return OmrScannerContext::selectionRequired($this->candidates($assignments), $code);
        }

        return OmrScannerContext::selectionRequired([], 'session_unresolved');
    }

    private function resolveActorCycle(User $actor): AcademicCycle
    {
        $scope = $actor->actorScope();
        $institutionId = $scope->institutionId;
        $cycleCode = trim((string) $scope->activeCycleCode);

        if ($scope->office !== 'guidance'
            || $institutionId === null
            || (int) $institutionId < 1
            || $cycleCode === '') {
            throw new AuthorizationException('OMR scanner scope is not available.');
        }

        $cycle = AcademicCycle::query()
            ->where('institution_id', (int) $institutionId)
            ->where('code', $cycleCode)
            ->first();
        if ($cycle === null) {
            throw new AuthorizationException('OMR scanner scope is not available.');
        }

        return $cycle;
    }

    private function hasFrozenSubmission(ExamAssignment $assignment): bool
    {
        $submission = $assignment->submissionRecord;
        $allocation = $submission?->seatAllocation;
        $seat = $allocation?->sessionSeat;
        $snapshot = $seat?->materialSnapshot;

        return $submission !== null
            && $allocation !== null
            && (int) $allocation->exam_assignment_id === (int) $assignment->id
            && $seat !== null
            && $snapshot !== null
            && (int) $snapshot->exam_session_id === (int) $assignment->exam_session_id;
    }

    /**
     * @param  Collection<int, ExamAssignment>  $assignments
     * @return list<array{session_id: int, exam_session_id: int, exam_code: string, room_name: string, monitoring_url: string}>
     */
    private function candidates(Collection $assignments): array
    {
        return $assignments
            ->map(function (ExamAssignment $assignment): array {
                $gradingSession = $assignment->examSession->gradingSession;
                $examSession = $assignment->examSession;

                return [
                    'session_id' => (int) $gradingSession->id,
                    'exam_session_id' => (int) $examSession->id,
                    'exam_code' => (string) $examSession->code,
                    'room_name' => (string) ($examSession->room?->name ?? '—'),
                    'monitoring_url' => route('scoring.sessions.show', ['examSession' => $examSession->id]),
                ];
            })
            ->values()
            ->all();
    }
}
