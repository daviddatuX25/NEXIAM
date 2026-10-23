<?php

namespace App\Examination\Support;

use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;

final class RecordExamAttendanceEvaluator
{
    public function evaluate(ExamAssignment $assignment, string $status): PreflightResult
    {
        $allowedStatuses = [
            ExamAttendanceRecord::OUTCOME_PRESENT,
            ExamAttendanceRecord::OUTCOME_ABSENT,
            ExamAttendanceRecord::OUTCOME_VOID,
        ];
        $statusValid = in_array($status, $allowedStatuses, true);
        $session = $assignment->examSession;
        $sessionAllowsAttendance = $session instanceof ExamSession && $session->attendanceIsOpen();

        $checks = [
            [
                'key' => 'attendance_status_valid',
                'label' => 'Attendance status',
                'status' => $statusValid ? 'pass' : 'fail',
                'detail' => $statusValid ? 'Attendance status is recognized' : 'Status must be present, absent, or void',
            ],
            [
                'key' => 'session_allows_attendance',
                'label' => 'Session status',
                'status' => $sessionAllowsAttendance ? 'pass' : 'fail',
                'detail' => $sessionAllowsAttendance ? 'Session is started and has not closed' : 'Attendance requires a started session that has not closed',
            ],
        ];

        return new PreflightResult(
            outcome: $statusValid && $sessionAllowsAttendance ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'attendance',
                'label' => 'Attendance',
                'before' => 'unrecorded',
                'after' => $status,
            ]],
        );
    }
}
