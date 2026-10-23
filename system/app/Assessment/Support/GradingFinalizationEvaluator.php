<?php

namespace App\Assessment\Support;

use App\Examination\Support\PreflightResult;
use App\Models\Assessment\GradingSession;

final class GradingFinalizationEvaluator
{
    public function __construct(
        private readonly ScoreConverter $scoreConverter,
        private readonly AssessmentAdministrationFinalizationPredicate $predicate,
    ) {}

    public function evaluate(GradingSession $gradingSession): PreflightResult
    {
        $predicateResult = $this->predicate->evaluate($gradingSession);

        $isOpen = $gradingSession->status === GradingSession::STATUS_OPEN;
        $checks = [
            [
                'key' => 'grading_session_open',
                'label' => 'Grading session',
                'status' => $isOpen ? 'pass' : 'fail',
                'detail' => $isOpen ? 'Grading session is open' : 'Grading session is not open',
            ],
            [
                'key' => 'roster_scores_complete',
                'label' => 'Examinee scores',
                'status' => $predicateResult['ok'] ? 'pass' : 'fail',
                'detail' => $predicateResult['ok']
                    ? 'Every present examinee has valid scores for all active areas'
                    : ($predicateResult['reason'] ?? 'Present examinees need valid scores for every active area'),
                'state' => $predicateResult['state'],
            ],
        ];

        return new PreflightResult(
            outcome: $isOpen && $predicateResult['ok'] ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'status',
                'label' => 'Status',
                'before' => GradingSession::STATUS_OPEN,
                'after' => GradingSession::STATUS_FINALIZED,
            ]],
        );
    }
}
