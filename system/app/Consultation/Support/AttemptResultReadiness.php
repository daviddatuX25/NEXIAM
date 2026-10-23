<?php

namespace App\Consultation\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Release\ResultDocument;

/**
 * Read-only readiness for an individually frozen result with its current
 * prepared document. It deliberately does not describe scheduling eligibility.
 */
final class AttemptResultReadiness
{
    public static function isReady(AdmissionAttempt $attempt): bool
    {
        $assignment = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();
        if ($assignment === null || $attempt->current_result_document_id === null) {
            return false;
        }

        $grading = GradingSession::query()
            ->where('exam_session_id', $assignment->exam_session_id)
            ->orderByDesc('id')
            ->first();
        if ($grading === null) {
            return false;
        }

        $document = ResultDocument::query()
            ->whereKey($attempt->current_result_document_id)
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', ResultDocument::STATUS_READY)
            ->first();
        if ($document === null) {
            return false;
        }

        $administrations = AssessmentAdministration::query()
            ->where('exam_assignment_id', $assignment->id)
            ->active()
            ->orderBy('id')
            ->get();
        if ($administrations->count() > 1) {
            return false;
        }

        $administration = $administrations->first();
        if ($administration === null) {
            return $grading->status === GradingSession::STATUS_FINALIZED;
        }

        $resultSets = AssessmentResultSet::query()
            ->where('administration_id', $administration->id)
            ->where('grading_session_id', $grading->id)
            ->where('exam_session_id', $assignment->exam_session_id)
            ->where('status', AssessmentResultSet::STATUS_FINALIZED)
            ->orderBy('id')
            ->get();
        if ($resultSets->count() !== 1) {
            return false;
        }

        return (int) ($document->snapshot_payload_json['_assessment_result_set_id'] ?? 0)
            === (int) $resultSets->first()->id;
    }
}
