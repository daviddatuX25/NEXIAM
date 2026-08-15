<?php

namespace App\Assessment\Support;

use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Score-eligible roster for a grading session (GRE-1).
 *
 * An attempt is gradable only when its assignment is active, the applicant was
 * marked present, and a submission was recorded. Absent and not-submitted
 * attempts are excluded so finalize never demands blank grades for them —
 * "absence does not silently create a zero score" (GF-06 §5.2, master §6.6).
 */
final class GradingRoster
{
    /**
     * @return Builder<ExamAssignment>
     */
    public static function queryFor(GradingSession $gradingSession): Builder
    {
        return self::queryForSessionIds([$gradingSession->exam_session_id]);
    }

    /**
     * @param  array<int, int>  $examSessionIds
     * @return Builder<ExamAssignment>
     */
    public static function queryForSessionIds(array $examSessionIds): Builder
    {
        return ExamAssignment::query()
            ->whereIn('exam_session_id', $examSessionIds)
            ->active()
            ->whereHas(
                'attendanceRecord',
                fn (Builder $query): Builder => $query->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT),
            )
            ->whereHas('submissionRecord');
    }

    public static function includesAttempt(GradingSession $gradingSession, int $attemptId): bool
    {
        return self::queryFor($gradingSession)
            ->where('admission_attempt_id', $attemptId)
            ->exists();
    }

    /**
     * PHP-side eligibility predicate over a loaded assignment (with relations),
     * mirroring the SQL shape of queryFor for projection/stats code.
     */
    public static function isEligible(ExamAssignment $assignment): bool
    {
        return $assignment->isActive()
            && $assignment->attendanceRecord?->outcome === ExamAttendanceRecord::OUTCOME_PRESENT
            && $assignment->submissionRecord !== null;
    }

    /**
     * @param  Collection<int, ExamAssignment>  $assignments
     * @return Collection<int, ExamAssignment>
     */
    public static function filterEligible(Collection $assignments): Collection
    {
        return $assignments->filter(fn (ExamAssignment $assignment): bool => self::isEligible($assignment))->values();
    }
}
