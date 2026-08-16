<?php

namespace App\Consultation\Queries;

use App\Admission\Support\AttemptProgramFacts;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AptitudeArea;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultRelease;
use Illuminate\Support\Carbon;

/**
 * D-093: server-authored admission-support projection for the consultation
 * workspace. Real released/application/consultation evidence only — no
 * provider output, no synthetic recommendation. The analysis region truthfully
 * reports no_analysis (or a future plugin's status when one is configured).
 */
final class ConsultationAdmissionSupportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function for(AdmissionAttempt $attempt): array
    {
        $released = AttemptProgramFacts::isResultReleased($attempt);
        $ready = AttemptProgramFacts::isResultReady($attempt);

        return [
            'readiness' => [
                'accepted' => AttemptProgramFacts::isAccepted($attempt),
                'exam_completed' => $this->examCompleted($attempt),
                'grading_finalized' => $ready,
                'result_released' => $released,
                'advised' => AttemptProgramFacts::isAdvised($attempt),
                'selection' => $this->selectionState($attempt),
                'enrolling' => AttemptProgramFacts::isEnrolling($attempt),
                'enrolled' => AttemptProgramFacts::isEnrolled($attempt),
            ],
            'score_profile' => $released ? $this->scoreProfile($attempt) : null,
            'preferences' => array_values(array_filter([
                $attempt->program_preference_1_code,
                $attempt->program_preference_2_code,
                $attempt->program_preference_3_code,
            ])),
            'consultation_evidence' => $this->consultationEvidence($attempt),
            'program_pool' => $this->poolState($attempt),
            'follow_ups' => $this->followUpState($attempt),
            'analysis' => [
                'status' => $ready ? 'no_analysis' : 'result_not_ready',
                'engine' => null,
                'source' => null,
                'version' => null,
                'generated_at' => null,
                'message' => $ready
                    ? 'No analysis provider is configured. Consultation evidence is complete without one.'
                    : 'Consultation evidence unlocks after grading is finalized and the result is prepared.',
            ],
            // Founder 2026-08-15: two reserved visualization slots for the
            // deferred admission-support models. Truthful empty states only —
            // no engine output, no prediction, no auto-write authority.
            'models' => [
                [
                    'slot' => 'yu_kee',
                    'label' => 'YuKee-compatible model (K-Means artifact)',
                    'status' => $ready ? 'no_provider' : 'result_not_ready',
                    'message' => $ready
                        ? 'No provider configured — chart region reserved, no output, no prediction.'
                        : 'Unlocks after grading is finalized and the result is prepared.',
                ],
                [
                    'slot' => 'deterministic_rule_based',
                    'label' => 'Deterministic / rule-based model',
                    'status' => $ready ? 'no_provider' : 'result_not_ready',
                    'message' => $ready
                        ? 'No provider configured — chart region reserved, no output, no prediction.'
                        : 'Unlocks after grading is finalized and the result is prepared.',
                ],
            ],
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function selectionState(AdmissionAttempt $attempt): array
    {
        $selection = ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
            ->with('program')
            ->latest('id')
            ->first();

        return [
            'status' => $selection?->status,
            'program_code' => $selection?->program?->code,
            'source' => $selection?->source,
            'acknowledged' => $selection?->applicant_acknowledged_at !== null,
        ];
    }

    private function examCompleted(AdmissionAttempt $attempt): bool
    {
        return ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Applicant-portal hub projection: released per-domain score series only.
     * Returns null before release; scores never serialize pre-release.
     */
    public function releasedScoreProfile(AdmissionAttempt $attempt): ?array
    {
        return AttemptProgramFacts::isResultReleased($attempt) ? $this->scoreProfile($attempt) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreProfile(AdmissionAttempt $attempt): array
    {
        $sessionId = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->value('exam_session_id');

        $grading = $sessionId === null ? null : GradingSession::query()
            ->where('exam_session_id', $sessionId)
            ->where('status', GradingSession::STATUS_FINALIZED)
            ->latest('id')
            ->first();

        $areas = AptitudeArea::query()->where('is_active', true)->orderBy('id')->get();

        $series = [];
        foreach ($areas as $area) {
            $record = $grading === null ? null : ScoreRecord::query()
                ->where('grading_session_id', $grading->id)
                ->where('admission_attempt_id', $attempt->id)
                ->where('aptitude_area_id', $area->id)
                ->first();

            $series[] = [
                'area_code' => $area->code,
                'area_name' => $area->name,
                'score' => $record->converted_score ?? $record->score,
                'source' => $record->source ?? null,
            ];
        }

        $release = ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('is_current', true)
            ->first();

        return [
            'chart_id' => 'admission-support-score-profile',
            'title' => 'Released score profile',
            'unit' => 'converted score',
            'series' => $series,
            'source' => 'Released result document',
            'version' => $release?->release_version,
            'released_at' => self::isoOrNull($release?->released_at),
            'freshness' => 'current',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consultationEvidence(AdmissionAttempt $attempt): array
    {
        $decision = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->latest('id')
            ->first();

        return [
            'decision_type' => $decision?->decision_type,
            'notes' => $decision?->notes,
            'recorded_by' => $decision?->counselor?->name,
            'recorded_at' => self::isoOrNull($decision?->created_at),
            'version' => $decision?->version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function poolState(AdmissionAttempt $attempt): array
    {
        $pool = ProgramPool::currentFor($attempt->id);

        if ($pool === null) {
            return ['version' => null, 'options' => [], 'default' => null];
        }

        $selection = ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();

        return [
            'version' => $pool->version,
            'options' => $pool->options()->with('program')->get()->map(fn ($option) => [
                'program_code' => $option->program->code,
                'program_name' => $option->program->name,
                'basis' => $option->basis,
                'display_order' => $option->display_order,
            ])->all(),
            'default' => $selection?->source === ProgramSelection::SOURCE_GUIDANCE_DEFAULT
                ? $selection->program?->code
                : null,
            'selection_status' => $selection?->status,
            'selection_program' => $selection?->program?->code,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function followUpState(AdmissionAttempt $attempt): array
    {
        $rows = [];
        $followUps = ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->with('program')
            ->orderByDesc('id')
            ->get();

        foreach ($followUps as $followUp) {
            $rows[] = [
                'program_code' => $followUp->program?->code,
                'status' => $followUp->status,
                'reason' => $followUp->reason,
                'resolution_reason' => $followUp->resolution_reason,
                'opened_at' => self::isoOrNull($followUp->opened_at),
                'resolved_at' => self::isoOrNull($followUp->resolved_at),
            ];
        }

        return $rows;
    }

    private static function isoOrNull(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }
}
