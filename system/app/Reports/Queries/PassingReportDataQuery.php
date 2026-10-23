<?php

namespace App\Reports\Queries;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use Illuminate\Support\Collection;

final class PassingReportDataQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $cycleId = isset($filters['academic_cycle_id'])
            ? (int) $filters['academic_cycle_id']
            : AcademicCycle::active()?->id;
        $cutoffScore = isset($filters['cutoff_score']) && is_numeric($filters['cutoff_score'])
            ? (float) $filters['cutoff_score']
            : 75.0;
        $programCode = isset($filters['program_code']) && trim((string) $filters['program_code']) !== ''
            ? trim((string) $filters['program_code'])
            : null;

        $cycle = $cycleId === null ? null : AcademicCycle::query()->with('institution')->find($cycleId);
        $scores = $cycle === null ? collect() : ScoreRecord::query()
            ->with([
                'admissionAttempt.identity',
                'gradingSession',
            ])
            ->whereHas('gradingSession', function ($query) use ($cycleId): void {
                $query->where('status', GradingSession::STATUS_FINALIZED)
                    ->whereHas('examSession', fn ($examQuery) => $examQuery->forPracticeSet(null)->where('academic_cycle_id', $cycleId));
            })
            ->whereHas('admissionAttempt', function ($query) use ($cycleId, $programCode): void {
                $query->forPracticeSet(null)->where('academic_cycle_id', $cycleId)
                    ->when($programCode !== null, fn ($attemptQuery) => $attemptQuery->where('preferred_program_code', $programCode));
            })
            ->orderByDesc('grading_session_id')
            ->get();

        $scoreGroups = $scores->groupBy('admission_attempt_id');
        $latestScores = $scoreGroups->map(fn (Collection $records): Collection => $records
            ->groupBy('grading_session_id')
            ->sortKeysDesc()
            ->first());
        $passers = [];

        foreach ($latestScores as $attemptId => $records) {
            /** @var ScoreRecord|null $firstRecord */
            $firstRecord = $records->first();
            $attempt = $firstRecord?->admissionAttempt;
            if ($attempt === null) {
                continue;
            }

            $evaluation = $this->evaluateExamineeCutoff($records, $attempt, $cutoffScore);
            if ($evaluation['status'] !== 'passed' || $evaluation['composite_score'] === null) {
                continue;
            }

            $compositeScore = $evaluation['composite_score'];
            $identity = $attempt->identity;
            $applicantName = trim(implode(' ', array_filter([
                $identity?->first_name,
                $identity?->middle_name,
                $identity?->last_name,
                $identity?->suffix,
            ], static fn (?string $part): bool => $part !== null && $part !== '')));

            $passers[] = [
                '_last_name' => $identity?->last_name ?? '',
                'row_no' => 0,
                'reference_code' => $this->escapeText((string) $attempt->reference_code),
                'applicant_name' => $this->escapeText($applicantName !== '' ? $applicantName : 'Unknown'),
                'sex' => $this->escapeText((string) ($identity?->sex ?? '')),
                'strand' => $this->escapeText((string) ($attempt->strand ?? '')),
                'program_code' => $this->escapeText((string) ($attempt->preferred_program_code ?? '')),
                'composite_score' => $compositeScore,
                'stanine' => null,
                'rating' => null,
                'remarks' => 'Passed',
            ];
        }

        usort($passers, static fn (array $first, array $second): int => ($second['composite_score'] <=> $first['composite_score'])
            ?: strcasecmp($first['_last_name'], $second['_last_name'])
            ?: strcmp($first['reference_code'], $second['reference_code']));

        $passers = array_values(array_map(static function (array $passer, int $index): array {
            unset($passer['_last_name']);
            $passer['row_no'] = $index + 1;

            return $passer;
        }, $passers, array_keys($passers)));

        $totalExaminees = $latestScores->count();
        $totalPassed = count($passers);

        return [
            'institution_name' => $this->escapeText((string) ($cycle?->institution?->name ?? Institution::query()->value('name') ?? 'Institution')),
            'institution_code' => $this->escapeText((string) ($cycle?->institution?->code ?? Institution::query()->value('code') ?? '')),
            'academic_cycle' => $this->escapeText($cycle?->staffLabel() ?? ''),
            'report_date' => now()->toDateString(),
            'cutoff_score' => $cutoffScore,
            'total_examinees' => $totalExaminees,
            'total_passed' => $totalPassed,
            'passing_rate' => $totalExaminees === 0
                ? 'N/A'
                : number_format(($totalPassed / $totalExaminees) * 100, 2).'%',
            'passers' => $passers,
            'prepared_by' => $this->configuredSignatory('prepared_by', 'Admissions Records Officer'),
            'reviewed_by' => $this->configuredSignatory('reviewed_by', 'Guidance Counselor'),
            'approved_by' => $this->configuredSignatory('approved_by', 'Registrar'),
        ];
    }

    /**
     * Evaluate an examinee's pass/fail cutoff status.
     *
     * @param  Collection<int, ScoreRecord>  $records
     * @return array{status: string, composite_score: float|null, reason: string|null}
     */
    public function evaluateExamineeCutoff(Collection $records, AdmissionAttempt $attempt, float $cutoffScore): array
    {
        /** @var ScoreRecord|null $firstRecord */
        $firstRecord = $records->first();

        // Historical sessions: preserve existing area-based grading behavior unchanged
        $numericScores = $records->map(function (ScoreRecord $record): ?float {
            $value = $record->converted_score ?? $record->score;

            return is_numeric($value) ? (float) $value : null;
        })->filter(fn (?float $score): bool => $score !== null)->values();

        if ($numericScores->isEmpty()) {
            return [
                'status' => 'unavailable',
                'composite_score' => null,
                'reason' => 'No numeric scores recorded.',
            ];
        }

        $compositeScore = round((float) $numericScores->avg(), 2);
        if ($compositeScore >= $cutoffScore) {
            return [
                'status' => 'passed',
                'composite_score' => $compositeScore,
                'reason' => null,
            ];
        }

        return [
            'status' => 'failed',
            'composite_score' => $compositeScore,
            'reason' => null,
        ];
    }

    private function configuredSignatory(string $key, string $default): string
    {
        return $this->escapeText((string) config('reports.passing_signatories.'.$key, $default));
    }

    private function escapeText(string $value): string
    {
        return preg_match('/^\s*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
