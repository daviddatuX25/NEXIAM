<?php

namespace App\Reports\Queries;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;

final class DailyAdmissionsLogbookDataQuery implements ContextualReadToolHandler
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array
    {
        $institutionId = (int) ($context->institutionId ?? $actor->actorScope()->institutionId ?? 0);
        $cycleId = isset($input['academic_cycle_id']) && is_numeric($input['academic_cycle_id']) ? (int) $input['academic_cycle_id'] : null;
        $date = isset($input['date']) && is_string($input['date']) ? $input['date'] : null;

        return $this->forXiamSafe($institutionId, $cycleId, $date);
    }

    /**
     * @return array{
     *   institution_id: int,
     *   cycle_id: ?int,
     *   cycle_name: string,
     *   target_date: string,
     *   total_daily_applicants: int,
     *   strand_distribution: list<array{strand: string, count: int, percentage: float}>,
     *   applicant_type_distribution: list<array{applicant_type: string, count: int, percentage: float}>,
     *   hourly_distribution: list<array{hour: string, count: int}>,
     *   as_of: string
     * }
     */
    public function forXiamSafe(int $institutionId, ?int $cycleId = null, ?string $date = null): array
    {
        $cycleQuery = AcademicCycle::query()->where('institution_id', $institutionId);
        $cycle = $cycleId === null
            ? $cycleQuery->where('is_active', true)->first()
            : $cycleQuery->whereKey($cycleId)->first();
        $resolvedCycleId = $cycle?->id;

        $attemptsQuery = AdmissionAttempt::query()->forPracticeSet(null)
            ->where('institution_id', $institutionId)
            ->when($resolvedCycleId !== null, fn ($query) => $query->where('academic_cycle_id', $resolvedCycleId));

        if ($resolvedCycleId === null) {
            $attemptsQuery->whereRaw('1 = 0');
        }

        if ($date === null || trim($date) === '') {
            $latestDate = (clone $attemptsQuery)->latest('created_at')->value('created_at');
            $date = $latestDate?->toDateString() ?? now()->toDateString();
        }

        $attempts = $attemptsQuery
            ->whereDate('created_at', $date)
            ->get(['strand', 'applicant_type', 'created_at']);

        $total = $attempts->count();
        $strandCounts = [];
        $applicantTypeCounts = [];
        $hourCounts = [];

        foreach ($attempts as $attempt) {
            $strand = trim((string) $attempt->strand);
            $applicantType = trim((string) $attempt->applicant_type);
            $hour = (int) $attempt->created_at->format('G');

            $strandCounts[$strand !== '' ? $strand : 'N/A'] = ($strandCounts[$strand !== '' ? $strand : 'N/A'] ?? 0) + 1;
            $applicantTypeCounts[$applicantType !== '' ? $applicantType : 'N/A'] = ($applicantTypeCounts[$applicantType !== '' ? $applicantType : 'N/A'] ?? 0) + 1;
            $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
        }

        ksort($strandCounts);
        ksort($applicantTypeCounts);
        ksort($hourCounts);

        return [
            'institution_id' => $institutionId,
            'cycle_id' => $resolvedCycleId === null ? null : (int) $resolvedCycleId,
            'cycle_name' => $cycle?->staffLabel() ?? '',
            'target_date' => $date,
            'total_daily_applicants' => $total,
            'strand_distribution' => collect($strandCounts)->map(fn (int $count, string|int $strand): array => [
                'strand' => (string) $strand,
                'count' => $count,
                'percentage' => $total === 0 ? 0.0 : round(($count / $total) * 100, 2),
            ])->values()->all(),
            'applicant_type_distribution' => collect($applicantTypeCounts)->map(fn (int $count, string|int $applicantType): array => [
                'applicant_type' => (string) $applicantType,
                'count' => $count,
                'percentage' => $total === 0 ? 0.0 : round(($count / $total) * 100, 2),
            ])->values()->all(),
            'hourly_distribution' => collect($hourCounts)->map(fn (int $count, int $hour): array => [
                'hour' => sprintf('%02d:00', $hour),
                'count' => $count,
            ])->values()->all(),
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

        $filterDate = isset($filters['date']) && trim((string) $filters['date']) !== ''
            ? trim((string) $filters['date'])
            : null;

        $attemptsQuery = AdmissionAttempt::query()->forPracticeSet(null)
            ->with('identity')
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId));

        if ($filterDate !== null) {
            $attemptsQuery->whereDate('created_at', $filterDate);
        } else {
            // Find most recent intake date or fall back to today
            $latestDate = (clone $attemptsQuery)->latest('created_at')->value('created_at');
            if ($latestDate !== null) {
                $targetDate = $latestDate->format('Y-m-d');
                $attemptsQuery->whereDate('created_at', $targetDate);
                $filterDate = $targetDate;
            } else {
                $filterDate = now()->toDateString();
                $attemptsQuery->whereDate('created_at', $filterDate);
            }
        }

        $attempts = $attemptsQuery->orderBy('created_at')->get();

        $rows = $attempts->values()->map(function (AdmissionAttempt $attempt, int $index): array {
            $identity = $attempt->identity;
            $applicantName = trim(implode(' ', array_filter([
                $identity?->first_name,
                $identity?->middle_name,
                $identity?->last_name,
                $identity?->suffix,
            ], static fn (?string $part): bool => $part !== null && $part !== '')));

            return [
                'row_no' => $index + 1,
                'time_in' => $attempt->created_at ? $attempt->created_at->format('g:i A') : '—',
                'reference_code' => $this->escapeText((string) $attempt->reference_code),
                'applicant_name' => $this->escapeText($applicantName !== '' ? $applicantName : 'Unknown'),
                'sex' => ucfirst((string) ($identity?->sex ?? '—')),
                'strand' => $this->escapeText((string) ($attempt->strand ?? 'N/A')),
                'preferred_program' => $this->escapeText((string) ($attempt->preferred_program_code ?: $attempt->program_preference_1_code ?: 'N/A')),
                'school' => $this->escapeText((string) ($attempt->last_school_enrolled ?? 'N/A')),
                'transaction_status' => ucfirst(str_replace('_', ' ', (string) $attempt->status)),
                'attending_officer' => 'Admissions Desk',
            ];
        })->all();

        $totalToday = count($rows);
        $acceptedToday = $attempts->where('status', AdmissionAttempt::STATUS_ACCEPTED)->count();
        $pendingToday = $attempts->where('status', AdmissionAttempt::STATUS_PENDING)->count();
        $correctionToday = $attempts->where('status', AdmissionAttempt::STATUS_NEEDS_CORRECTION)->count();

        return [
            'institution_name' => $this->escapeText((string) ($cycle?->institution?->name ?? Institution::query()->value('name') ?? 'Institution')),
            'institution_code' => $this->escapeText((string) ($cycle?->institution?->code ?? Institution::query()->value('code') ?? '')),
            'academic_cycle' => $this->escapeText($cycle?->staffLabel() ?? ''),
            'report_date' => now()->toDateString(),
            'log_date' => $filterDate ?? now()->toDateString(),
            'total_intake_today' => $totalToday,
            'accepted_today' => $acceptedToday,
            'pending_today' => $pendingToday,
            'needs_correction_today' => $correctionToday,
            'admissions_log' => $rows,
            'prepared_by' => 'Receiving Clerk',
            'verified_by' => 'Admissions Officer',
            'approved_by' => 'University Registrar',
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
