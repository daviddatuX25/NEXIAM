<?php

namespace App\Admission\Support;

use App\Examination\Support\PreflightResult;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;

final class AcceptAdmissionAttemptEvaluator
{
    public function evaluate(AdmissionAttempt $attempt, ?int $expectedVersion = null): PreflightResult
    {
        $statusEligible = in_array($attempt->status, [
            AdmissionAttempt::STATUS_PENDING,
            AdmissionAttempt::STATUS_NEEDS_CORRECTION,
        ], true);
        $versionMatches = $expectedVersion === null || (int) $attempt->version === $expectedVersion;
        $cycle = AcademicCycle::query()
            ->lockForUpdate()
            ->whereKey($attempt->academic_cycle_id)
            ->first();
        $cycleActive = (bool) ($cycle?->is_active ?? false);

        $checks = [
            [
                'key' => 'attempt_status_eligible',
                'label' => 'Attempt status',
                'status' => $statusEligible ? 'pass' : 'fail',
                'detail' => $statusEligible ? 'Attempt can be accepted' : 'Attempt must be pending or need correction',
            ],
            [
                'key' => 'version_matches',
                'label' => 'Attempt version',
                'status' => $versionMatches ? 'pass' : 'fail',
                'detail' => $versionMatches ? 'Attempt version is current' : 'Attempt version is stale',
            ],
            [
                'key' => 'cycle_active',
                'label' => 'Academic cycle',
                'status' => $cycleActive ? 'pass' : 'fail',
                'detail' => $cycleActive ? 'Academic cycle is active' : 'Academic cycle is not active',
            ],
        ];

        return new PreflightResult(
            outcome: $statusEligible && $versionMatches && $cycleActive ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'status',
                'label' => 'Status',
                'before' => AdmissionAttempt::STATUS_PENDING,
                'after' => AdmissionAttempt::STATUS_ACCEPTED,
            ]],
        );
    }
}
