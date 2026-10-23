<?php

namespace App\Assessment\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use Illuminate\Support\Facades\DB;

class ScoreBaseline
{
    public function for(GradingSession $session, AdmissionAttempt $attempt): string
    {
        $assignment = DB::table('exam_assignments')
            ->where('exam_session_id', $session->exam_session_id)
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();
        $administration = $assignment === null ? null : DB::table('assessment_administrations')
            ->where('exam_assignment_id', $assignment->id)
            ->whereNull('superseded_at')
            ->orderByDesc('id')
            ->first();
        $adminId = $administration?->id;
        $examSession = DB::table('exam_sessions')
            ->where('id', $session->exam_session_id)
            ->first();

        $state = [
            'session' => [
                $session->id,
                $session->exam_session_id,
                $session->status,
                $examSession?->test_kit_version_id,
                $examSession?->test_instrument_version_id,
            ],
            'attempt' => [$attempt->id, $attempt->institution_id, $attempt->status],
            'assignment' => $assignment,
            'administration' => $administration,
            'raw' => $adminId === null ? [] : DB::table('assessment_raw_result_revisions')
                ->where('administration_id', $adminId)->where('is_current', true)->orderBy('metric_id')->get()->all(),
            'conversion' => $adminId === null ? [] : DB::table('assessment_conversion_revisions')
                ->where('administration_id', $adminId)->where('is_current', true)->orderBy('id')->get()->all(),
            'scores' => DB::table('score_records')->where('grading_session_id', $session->id)
                ->where('admission_attempt_id', $attempt->id)->orderBy('aptitude_area_id')->get()->all(),
        ];

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
