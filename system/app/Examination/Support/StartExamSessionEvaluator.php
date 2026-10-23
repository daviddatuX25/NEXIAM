<?php

namespace App\Examination\Support;

use App\Models\Examination\ExamSession;

final class StartExamSessionEvaluator
{
    public function evaluate(ExamSession $session): PreflightResult
    {
        $published = $session->status === ExamSession::STATUS_PUBLISHED && $session->closed_at === null;
        $hasAssignedCandidates = $session->assignments()
            ->active()
            ->exists();

        $checks = [
            [
                'key' => 'session_published',
                'label' => 'Session status',
                'status' => $published ? 'pass' : 'fail',
                'detail' => $published ? 'Session is published' : 'Session must be published',
            ],
            [
                'key' => 'roster_has_candidates',
                'label' => 'Assigned candidates',
                'status' => $hasAssignedCandidates ? 'pass' : 'fail',
                'detail' => $hasAssignedCandidates ? 'Roster has assigned candidates' : 'Roster must have assigned candidates',
            ],
        ];

        return new PreflightResult(
            outcome: $published && $hasAssignedCandidates ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'status',
                'label' => 'Status',
                'before' => ExamSession::STATUS_PUBLISHED,
                'after' => ExamSession::STATUS_IN_PROGRESS,
            ]],
        );
    }
}
