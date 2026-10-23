<?php

namespace App\Admission\Support;

use App\Examination\Support\PreflightResult;
use App\Models\Admission\AdmissionAttempt;

final class RequestApplicationCorrectionEvaluator
{
    public function evaluate(AdmissionAttempt $attempt, string $reason): PreflightResult
    {
        $statusEligible = $attempt->status === AdmissionAttempt::STATUS_PENDING;
        $reasonProvided = trim($reason) !== '';

        $checks = [
            [
                'key' => 'attempt_status_eligible',
                'label' => 'Attempt status',
                'status' => $statusEligible ? 'pass' : 'fail',
                'detail' => $statusEligible ? 'Attempt can be sent for correction' : 'Only pending attempts can be sent for correction',
            ],
            [
                'key' => 'reason_provided',
                'label' => 'Correction reason',
                'status' => $reasonProvided ? 'pass' : 'fail',
                'detail' => $reasonProvided ? 'Correction reason is provided' : 'Correction reason is required',
            ],
        ];

        return new PreflightResult(
            outcome: $statusEligible && $reasonProvided ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'status',
                'label' => 'Status',
                'before' => $attempt->status,
                'after' => AdmissionAttempt::STATUS_NEEDS_CORRECTION,
            ]],
        );
    }
}
