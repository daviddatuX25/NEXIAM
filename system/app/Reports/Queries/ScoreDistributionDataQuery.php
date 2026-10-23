<?php

namespace App\Reports\Queries;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;

final class ScoreDistributionDataQuery implements ContextualReadToolHandler
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array
    {
        $institutionId = (int) ($context->institutionId ?? $actor->actorScope()->institutionId ?? 0);
        $cycleId = isset($input['academic_cycle_id']) && is_numeric($input['academic_cycle_id']) ? (int) $input['academic_cycle_id'] : null;

        return $this->forXiamSafe($institutionId, $cycleId);
    }

    /**
     * @return array{
     *   institution_id: int,
     *   cycle_id: ?int,
     *   cycle_name: string,
     *   total_examinees_scored: int,
     *   subtests: list<array{
     *     subtest_code: string,
     *     subtest_name: string,
     *     examinee_count: int,
     *     mean_score: float|null,
     *     max_score: float|null,
     *     passing_count: int,
     *     pass_rate_percent: float|null
     *   }>,
     *   overall_passing_rate_percent: float|null,
     *   as_of: string
     * }
     */
    public function forXiamSafe(int $institutionId, ?int $cycleId = null): array
    {
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->when($cycleId !== null, fn ($query) => $query->whereKey($cycleId))
            ->when($cycleId === null, fn ($query) => $query->where('is_active', true))
            ->latest('id')
            ->first();
        $resolvedCycleId = $cycle?->id;

        $scores = $resolvedCycleId === null
            ? collect()
            : ScoreRecord::query()
                ->select(['aptitude_area_id', 'admission_attempt_id', 'score'])
                ->whereHas('gradingSession', fn ($q) => $q
                    ->where('status', GradingSession::STATUS_FINALIZED)
                    ->whereHas('examSession', fn ($sq) => $sq
                        ->forPracticeSet(null)
                        ->where('academic_cycle_id', $resolvedCycleId)
                        ->whereHas('academicCycle', fn ($c) => $c->where('institution_id', $institutionId))))
                ->get();

        $scoresByArea = $scores->groupBy('aptitude_area_id');
        $scoredAttemptIds = $scores->pluck('admission_attempt_id')->unique();
        $subtestRows = [];
        $overallPassingCount = 0;
        $overallExamineeCount = 0;

        foreach (AptitudeArea::query()->orderBy('name')->get(['id', 'code', 'name']) as $area) {
            $areaScores = $scoresByArea->get($area->id, collect())
                ->map(fn (ScoreRecord $record): float => (float) $record->score);
            $examineeCount = $areaScores->count();
            $passingCount = $areaScores->filter(fn (float $score): bool => $score >= 50.0)->count();

            $overallPassingCount += $passingCount;
            $overallExamineeCount += $examineeCount;
            $subtestRows[] = [
                'subtest_code' => (string) ($area->code ?? "SUB-{$area->id}"),
                'subtest_name' => (string) $area->name,
                'examinee_count' => $examineeCount,
                'mean_score' => $examineeCount > 0 ? round($areaScores->avg(), 1) : null,
                'max_score' => $examineeCount > 0 ? round($areaScores->max(), 1) : null,
                'passing_count' => $passingCount,
                'pass_rate_percent' => $examineeCount > 0 ? round(($passingCount / $examineeCount) * 100, 1) : null,
            ];
        }

        return [
            'institution_id' => $institutionId,
            'cycle_id' => $resolvedCycleId,
            'cycle_name' => $cycle?->staffLabel() ?? '',
            'total_examinees_scored' => $scoredAttemptIds->count(),
            'subtests' => $subtestRows,
            'overall_passing_rate_percent' => $overallExamineeCount > 0
                ? round(($overallPassingCount / $overallExamineeCount) * 100, 1)
                : null,
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $cycleId = isset($filters['academic_cycle_id'])
            ? (int) $filters['academic_cycle_id']
            : AcademicCycle::active()?->id;

        $cycle = $cycleId === null ? null : AcademicCycle::query()->with('institution')->find($cycleId);

        $areas = AptitudeArea::query()->orderBy('name')->get();

        $scores = $cycleId === null ? collect() : ScoreRecord::query()
            ->with(['admissionAttempt'])
            ->whereHas('gradingSession', function ($q) use ($cycleId): void {
                $q->where('status', GradingSession::STATUS_FINALIZED)
                    ->whereHas('examSession', fn ($sq) => $sq->forPracticeSet(null)->where('academic_cycle_id', $cycleId));
            })
            ->get();

        $subtestRows = [];
        $totalScoresCount = $scores->count();
        $totalRawSum = 0;
        $totalPassCount = 0;

        foreach ($areas as $index => $area) {
            $areaScores = $scores->where('aptitude_area_id', $area->id);
            $count = $areaScores->count();
            $meanRaw = $count > 0 ? (float) $areaScores->avg('raw_score') : null;
            $maxScore = $count > 0 ? (float) $areaScores->max('raw_score') : null;
            $passCount = $areaScores->filter(fn ($s) => (float) $s->raw_score >= 50.0)->count();
            $passRate = $count > 0 ? number_format(($passCount / $count) * 100, 1).'%' : 'N/A';

            $totalRawSum += $areaScores->sum('raw_score');
            $totalPassCount += $passCount;

            $subtestRows[] = [
                'row_no' => $index + 1,
                'subtest_code' => $this->escapeText($area->code ?? "SUB-{$area->id}"),
                'subtest_name' => $this->escapeText($area->name),
                'examinee_count' => $count,
                'mean_score' => $meanRaw === null ? 'N/A' : number_format($meanRaw, 1),
                'max_score' => $maxScore === null ? 'N/A' : number_format($maxScore, 1),
                'passing_count' => $passCount,
                'pass_rate' => $passRate,
                'stanine_mid' => $count > 0 ? 5 : 0,
            ];
        }

        $overallMean = $totalScoresCount > 0 ? number_format($totalRawSum / $totalScoresCount, 1) : 'N/A';
        $overallPassRate = $totalScoresCount > 0 ? number_format(($totalPassCount / $totalScoresCount) * 100, 1).'%' : 'N/A';

        return [
            'institution_name' => $this->escapeText((string) ($cycle?->institution?->name ?? Institution::query()->value('name') ?? 'Institution')),
            'institution_code' => $this->escapeText((string) ($cycle?->institution?->code ?? Institution::query()->value('code') ?? '')),
            'academic_cycle' => $this->escapeText($cycle?->staffLabel() ?? ''),
            'report_date' => now()->toDateString(),
            'total_subtests' => count($subtestRows),
            'total_scores_recorded' => $totalScoresCount,
            'overall_mean_score' => $overallMean,
            'overall_pass_rate' => $overallPassRate,
            'subtests' => $subtestRows,
            'prepared_by' => 'Psychometrician',
            'verified_by' => 'Testing Coordinator',
            'approved_by' => 'Head of Guidance & Testing',
        ];
    }

    private function escapeText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
