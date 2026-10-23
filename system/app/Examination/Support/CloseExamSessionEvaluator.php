<?php

namespace App\Examination\Support;

use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;

final class CloseExamSessionEvaluator
{
    public function evaluate(ExamSession $session): PreflightResult
    {
        $inProgress = $session->attendanceIsOpen();
        $missingAttendance = ExamAssignment::query()
            ->where('exam_session_id', $session->id)
            ->active()
            ->whereDoesntHave('attendanceRecord', fn ($query) => $query->whereIn('outcome', [
                ExamAttendanceRecord::OUTCOME_PRESENT,
                ExamAttendanceRecord::OUTCOME_ABSENT,
                ExamAttendanceRecord::OUTCOME_VOID,
            ]))
            ->exists();
        $attendanceRecorded = ! $missingAttendance;
        $presentCount = ExamAssignment::query()
            ->where('exam_session_id', $session->id)
            ->active()
            ->whereHas('attendanceRecord', fn ($query) => $query->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT))
            ->count();
        $targetStatus = $presentCount > 0 ? ExamSession::STATUS_COMPLETED : ExamSession::STATUS_CANCELLED;

        $checks = [
            [
                'key' => 'session_in_progress',
                'label' => 'Session status',
                'status' => $inProgress ? 'pass' : 'fail',
                'detail' => $inProgress ? 'Session is in progress' : 'Session must be in progress',
            ],
            [
                'key' => 'attendance_recorded_for_roster',
                'label' => 'Roster attendance',
                'status' => $attendanceRecorded ? 'pass' : 'fail',
                'detail' => $attendanceRecorded ? 'All assigned candidates have attendance recorded' : 'Attendance is missing for one or more assigned candidates',
            ],
        ];

        return new PreflightResult(
            outcome: $inProgress && $attendanceRecorded ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'status',
                'label' => 'Status',
                'before' => ExamSession::STATUS_IN_PROGRESS,
                'after' => $targetStatus,
            ]],
        );
    }
}
