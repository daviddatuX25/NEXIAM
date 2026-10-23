<?php

namespace App\Admission\Support;

use App\Examination\Support\PreflightResult;
use App\Models\Admission\AdmissionAttempt;

final class DismissAdmissionAttemptEvaluator
{
    public function evaluate(AdmissionAttempt $attempt, string $justification): PreflightResult
    {
        $statusEligible = $attempt->status === AdmissionAttempt::STATUS_PENDING;
        $justificationProvided = trim($justification) !== '';

        $checks = [
            [
                'key' => 'attempt_status_eligible',
                'label' => 'Attempt status',
                'status' => $statusEligible ? 'pass' : 'fail',
                'detail' => $statusEligible ? 'Attempt can be dismissed' : 'Only pending attempts can be dismissed',
            ],
            [
                'key' => 'justification_provided',
                'label' => 'Dismissal justification',
                'status' => $justificationProvided ? 'pass' : 'fail',
                'detail' => $justificationProvided ? 'Dismissal justification is provided' : 'Dismissal justification is required',
            ],
        ];

        return new PreflightResult(
            outcome: $statusEligible && $justificationProvided ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'status',
                'label' => 'Status',
                'before' => $attempt->status,
                'after' => AdmissionAttempt::STATUS_DISMISSED,
            ]],
        );
    }
}
