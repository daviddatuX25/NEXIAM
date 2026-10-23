<?php

namespace App\Release\Support;

use App\Consultation\Support\AttemptResultReadiness;
use App\Examination\Support\PreflightResult;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ResultDocument;

final class ResultReleaseEvaluator
{
    public function evaluate(AdmissionAttempt $attempt): PreflightResult
    {
        $documentReady = $attempt->current_result_document_id !== null
            && ResultDocument::query()
                ->whereKey($attempt->current_result_document_id)
                ->where('admission_attempt_id', $attempt->id)
                ->where('status', ResultDocument::STATUS_READY)
                ->exists();
        $individualResultReady = AttemptResultReadiness::isReady($attempt);
        $accepted = $attempt->isAccepted();
        $checks = [
            [
                'key' => 'result_document_ready',
                'label' => 'Result document',
                'status' => $documentReady ? 'pass' : 'fail',
                'detail' => $documentReady ? 'A ready result document exists' : 'A ready result document is required',
            ],
            [
                'key' => 'individual_result_ready',
                'label' => 'Individual result',
                'status' => $individualResultReady ? 'pass' : 'fail',
                'detail' => $individualResultReady ? 'The current individual result is frozen and prepared' : 'A current prepared individual result is required',
            ],
            [
                'key' => 'attempt_accepted',
                'label' => 'Admission attempt',
                'status' => $accepted ? 'pass' : 'fail',
                'detail' => $accepted ? 'Admission attempt is accepted' : 'Admission attempt must be accepted',
            ],
        ];

        return new PreflightResult(
            outcome: $documentReady && $individualResultReady && $accepted ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'status',
                'label' => 'Status',
                'before' => 'unreleased',
                'after' => 'released',
            ]],
        );
    }
}
