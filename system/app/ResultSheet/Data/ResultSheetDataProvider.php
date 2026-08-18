<?php

namespace App\ResultSheet\Data;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Program;
use App\Models\Release\ConsultationDecision;

/**
 * Builds the applicant data array consumed by the placeholder engine for one
 * admission attempt. Scores come from the finalized grading session for the
 * attempt's exam assignment; ratings are the frozen `converted_score` values —
 * never recomputed from live conversion tables.
 *
 * @phpstan-type ApplicantData array{id: int, name: string, family_name: string, first_name: string, middle_name: string, suffix: string, sex: string, gwa: string, course_applied: string, course_applied_code: string, strand: string, strand_or_prev_course: string, applicant_type: string, reference: string, exam_date: string, exam_time: string, exam_date_start: string, exam_date_end: string, exam_time_start: string, exam_time_end: string, room_name: string, scores: list<array{domain: string, area_code: string, raw: string, max: int, converted: string, rating: string, pct: int}>, overall_pct: int, recommended_course: string, recommended_course_code: string, counselor_comments: string, counselor_name: string}
 */
final class ResultSheetDataProvider
{
    /**
     * @return ApplicantData
     */
    public function forAttempt(AdmissionAttempt $attempt): array
    {
        $identity = $attempt->identity;
        $grading = $this->finalizedGradingFor($attempt);

        $scores = $grading === null
            ? []
            : $this->scoresFor($grading, $attempt->id);

        $pcts = array_map(fn (array $score): int => $score['pct'], $scores);
        $overallPct = $pcts === [] ? 0 : (int) round(array_sum($pcts) / count($pcts));

        $exam = $this->examFor($attempt, $grading);
        $recommended = $this->recommendedProgram($attempt);
        $decision = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->with('counselor')
            ->latest('id')
            ->first();

        return [
            'id' => $attempt->id,
            'name' => trim(implode(' ', array_filter([
                $identity?->first_name,
                $identity?->middle_name,
                $identity?->last_name,
                $identity?->suffix,
            ]))),
            'family_name' => $identity?->last_name ?? '',
            'first_name' => $identity?->first_name ?? '',
            'middle_name' => $identity?->middle_name ?? '',
            'suffix' => $identity?->suffix ?? '',
            'sex' => $identity?->sex ?? '',
            'gwa' => (string) ($attempt->gwa ?? ''),
            'course_applied' => $this->programName($attempt->program_preference_1_code),
            'course_applied_code' => (string) ($attempt->program_preference_1_code ?? ''),
            'strand' => (string) ($attempt->strand ?? ''),
            'strand_or_prev_course' => (string) ($attempt->strand ?? $attempt->last_school_enrolled ?? ''),
            'applicant_type' => (string) ($attempt->applicant_type ?? ''),
            'reference' => $attempt->reference_code,
            'exam_date' => $exam['date'],
            'exam_time' => $exam['time'],
            'exam_date_start' => $exam['date'],
            'exam_date_end' => $exam['date'],
            'exam_time_start' => $exam['time'],
            'exam_time_end' => $exam['time_end'],
            'room_name' => $exam['room'],
            'scores' => $scores,
            'overall_pct' => $overallPct,
            'recommended_course' => $recommended['name'],
            'recommended_course_code' => $recommended['code'],
            'counselor_comments' => (string) ($decision?->notes ?? ''),
            'counselor_name' => (string) ($decision?->counselor?->name ?? ''),
        ];
    }

    /**
     * @param  list<int>  $attemptIds
     * @return array<int, ApplicantData>
     */
    public function forAttempts(array $attemptIds): array
    {
        $attempts = AdmissionAttempt::query()
            ->with('identity')
            ->whereIn('id', $attemptIds)
            ->get()
            ->keyBy('id');

        $data = [];

        foreach ($attemptIds as $id) {
            $attempt = $attempts->get($id);

            if ($attempt === null) {
                continue;
            }

            $data[$id] = $this->forAttempt($attempt);
        }

        return $data;
    }

    private function finalizedGradingFor(AdmissionAttempt $attempt): ?GradingSession
    {
        $sessionId = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->value('exam_session_id');

        return $sessionId === null ? null : GradingSession::query()
            ->where('exam_session_id', $sessionId)
            ->where('status', GradingSession::STATUS_FINALIZED)
            ->latest('id')
            ->first();
    }

    /**
     * @return list<array{domain: string, area_code: string, raw: string, max: int, converted: string, rating: string, pct: int}>
     */
    private function scoresFor(GradingSession $grading, int $attemptId): array
    {
        $records = ScoreRecord::query()
            ->where('grading_session_id', $grading->id)
            ->where('admission_attempt_id', $attemptId)
            ->with('aptitudeArea')
            ->get();

        $areas = AptitudeArea::query()
            ->where('is_active', true)
            ->get(['id', 'code', 'name', 'max_items'])
            ->keyBy('id');

        $scores = [];

        foreach ($records as $record) {
            $area = $areas->get($record->aptitude_area_id);

            if ($area === null) {
                continue;
            }

            $raw = (string) $record->score;
            $converted = (string) ($record->converted_score ?? '');
            $max = (int) ($area->max_items ?? 0);
            $pct = $max > 0 ? min(100, max(0, (int) round(((float) $raw / $max) * 100))) : 0;

            $scores[] = [
                'domain' => (string) $area->name,
                'area_code' => (string) $area->code,
                'raw' => $raw,
                'max' => $max,
                'converted' => $converted,
                'rating' => $converted,
                'pct' => $pct,
            ];
        }

        return $scores;
    }

    /**
     * @return array{date: string, time: string, time_end: string, room: string}
     */
    private function examFor(AdmissionAttempt $attempt, ?GradingSession $grading): array
    {
        $examSession = $grading?->examSession ?? ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->latest('id')
            ->first()?->examSession;

        if ($examSession === null) {
            return ['date' => '', 'time' => '', 'time_end' => '', 'room' => ''];
        }

        return [
            'date' => $examSession->starts_at?->format('F d, Y') ?? '',
            'time' => $examSession->starts_at?->format('g:i A') ?? '',
            'time_end' => $examSession->effective_end_at?->format('g:i A') ?? '',
            'room' => (string) ($examSession->room?->name ?? ''),
        ];
    }

    /**
     * @return array{name: string, code: string}
     */
    private function recommendedProgram(AdmissionAttempt $attempt): array
    {
        $selection = ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('source', ProgramSelection::SOURCE_GUIDANCE_DEFAULT)
            ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
            ->with('program')
            ->latest('id')
            ->first();

        $code = $selection?->program?->code ?? $attempt->program_preference_1_code ?? '';
        $name = $selection?->program?->name ?? $this->programName($code);

        return ['name' => $name, 'code' => (string) $code];
    }

    private function programName(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        return (string) Program::query()->where('code', $code)->value('name');
    }
}
