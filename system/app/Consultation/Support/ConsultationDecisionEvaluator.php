<?php

namespace App\Consultation\Support;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\QuotaReservation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\Program;

final class ConsultationDecisionEvaluator
{
    /**
     * @var list<string>
     */
    private const VALID_DECISIONS = ['approve', 'modify', 'override', 'defer', 'refer'];

    public function evaluate(AdmissionAttempt $attempt, string $decision, ?int $programId): PreflightResult
    {
        $attemptAccepted = $attempt->status === AdmissionAttempt::STATUS_ACCEPTED;
        $resultReady = AttemptResultReadiness::isReady($attempt);
        $resultReleased = AttemptProgramFacts::isResultReleased($attempt);
        $decisionValid = in_array($decision, self::VALID_DECISIONS, true);
        $programExists = $programId === null || Program::query()->whereKey($programId)->exists();
        $quotaRemaining = $programId === null || ($programExists && QuotaReservation::varianceSnapshot($attempt, $programId)['remaining'] > 0);

        $checks = [
            [
                'key' => 'attempt_accepted',
                'label' => 'Application status',
                'status' => $attemptAccepted ? 'pass' : 'fail',
                'detail' => $attempt->status,
            ],
            [
                'key' => 'exam_completed_or_result_released',
                'label' => 'Examination readiness',
                'status' => ($resultReady || $resultReleased) ? 'pass' : 'fail',
                'detail' => $resultReleased ? 'Result released' : ($resultReady ? 'Result ready' : 'A frozen individual result document is required'),
            ],
            [
                'key' => 'decision_valid',
                'label' => 'Consultation decision',
                'status' => $decisionValid ? 'pass' : 'fail',
                'detail' => $decision,
            ],
        ];

        if ($programId !== null) {
            $checks[] = [
                'key' => 'program_exists',
                'label' => 'Selected program',
                'status' => $programExists ? 'pass' : 'fail',
                'detail' => $programExists ? (string) $programId : 'Program not found',
            ];
            $checks[] = [
                'key' => 'program_quota_remaining',
                'label' => 'Program quota',
                'status' => $quotaRemaining ? 'pass' : 'fail',
                'detail' => $quotaRemaining ? 'Quota available' : 'No quota remaining',
            ];
        }

        $outcome = array_filter($checks, static fn (array $check): bool => $check['status'] === 'fail') === [] ? 'pass' : 'fail';

        return new PreflightResult(
            outcome: $outcome,
            checks: $checks,
            diff: $outcome === 'pass' ? [
                [
                    'key' => 'decision',
                    'label' => 'Decision',
                    'before' => 'None',
                    'after' => $decision,
                ],
                [
                    'key' => 'program',
                    'label' => 'Program',
                    'before' => 'Before',
                    'after' => $programId === null ? 'After' : (string) $programId,
                ],
            ] : [],
        );
    }
}
