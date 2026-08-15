<?php

namespace App\Consultation\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Release\ResultDocument;

/**
 * Derived "result-ready" predicate: grading finalized + result document
 * prepared. This alone delivers the early-assessment advantage — attempts
 * whose grading finishes first become schedulable first.
 *
 * Read-only derivation; never persisted.
 */
final class AttemptResultReadiness
{
    public static function isReady(AdmissionAttempt $attempt): bool
    {
        $sessionId = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->value('exam_session_id');
        if ($sessionId === null) {
            return false;
        }

        $gradingFinalized = GradingSession::query()
            ->where('exam_session_id', $sessionId)
            ->where('status', GradingSession::STATUS_FINALIZED)
            ->exists();

        if (! $gradingFinalized) {
            return false;
        }

        return ResultDocument::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', ResultDocument::STATUS_READY)
            ->exists();
    }
}
