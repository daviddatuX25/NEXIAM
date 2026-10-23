<?php

namespace App\Reports\Queries;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;

final class ApplicationsCensusDataQuery implements ContextualReadToolHandler
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array
    {
        $institutionId = (int) ($context->institutionId ?? $actor->actorScope()->institutionId ?? 0);
        $cycleId = isset($input['academic_cycle_id']) && is_numeric($input['academic_cycle_id'])
            ? (int) $input['academic_cycle_id']
            : null;

        return $this->forXiamSafe($institutionId, $cycleId);
    }

    /**
     * @return array{
     *   institution_id: int,
     *   cycle_id: ?int,
     *   cycle_name: string,
     *   total_applications: int,
     *   status_distribution: list<array{status: string, count: int, percentage: float}>,
     *   program_distribution: list<array{program_code: string, count: int, percentage: float}>,
     *   sex_distribution: list<array{sex: string, count: int, percentage: float}>,
     *   as_of: string
     * }
     */
    public function forXiamSafe(int $institutionId, ?int $cycleId = null): array
    {
        $cycle = $cycleId !== null
            ? AcademicCycle::query()->where('institution_id', $institutionId)->where('id', $cycleId)->first()
            : AcademicCycle::query()->where('institution_id', $institutionId)->where('is_active', true)->first();
        $cycleId = $cycle?->id;

        $attemptTable = (new AdmissionAttempt)->getTable();
        $baseQuery = static fn () => AdmissionAttempt::query()->forPracticeSet(null)
            ->where("{$attemptTable}.academic_cycle_id", $cycleId)
            ->where("{$attemptTable}.institution_id", $institutionId);

        $totalApplications = $cycleId === null ? 0 : $baseQuery()->count();
        $statusDistribution = $cycleId === null ? collect() : $baseQuery()
            ->selectRaw("{$attemptTable}.status, COUNT(*) AS aggregate_count")
            ->groupBy("{$attemptTable}.status")
            ->orderBy("{$attemptTable}.status")
            ->get();
        $programDistribution = $cycleId === null ? collect() : $baseQuery()
            ->selectRaw("COALESCE(NULLIF({$attemptTable}.preferred_program_code, ''), NULLIF({$attemptTable}.program_preference_1_code, ''), 'Unspecified') AS program_code, COUNT(*) AS aggregate_count")
            ->groupBy('program_code')
            ->orderBy('program_code')
            ->get();
        $identityTable = (new ApplicantIdentity)->getTable();
        $sexDistribution = $cycleId === null ? collect() : $baseQuery()
            ->leftJoin($identityTable, "{$attemptTable}.applicant_identity_id", '=', "{$identityTable}.id")
            ->selectRaw("COALESCE(NULLIF({$identityTable}.sex, ''), 'Unspecified') AS sex, COUNT(*) AS aggregate_count")
            ->groupBy('sex')
            ->orderBy('sex')
            ->get();

        $distribution = static fn ($rows, string $label): array => $rows->map(
            static fn ($row): array => [
                $label => (string) $row->{$label},
                'count' => (int) $row->aggregate_count,
                'percentage' => $totalApplications > 0
                    ? round(((int) $row->aggregate_count / $totalApplications) * 100, 1)
                    : 0.0,
            ]
        )->values()->all();

        return [
            'institution_id' => $institutionId,
            'cycle_id' => $cycleId,
            'cycle_name' => $cycle?->staffLabel() ?? '',
            'total_applications' => $totalApplications,
            'status_distribution' => $distribution($statusDistribution, 'status'),
            'program_distribution' => $distribution($programDistribution, 'program_code'),
            'sex_distribution' => $distribution($sexDistribution, 'sex'),
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

        $programCode = isset($filters['program_code']) && trim((string) $filters['program_code']) !== ''
            ? trim((string) $filters['program_code'])
            : null;

        $cycle = $cycleId === null ? null : AcademicCycle::query()->with('institution')->find($cycleId);

        $attempts = $cycleId === null ? collect() : AdmissionAttempt::query()->forPracticeSet(null)
            ->with('identity')
            ->where('academic_cycle_id', $cycleId)
            ->when($programCode !== null, function ($query) use ($programCode): void {
                $query->where(function ($attemptQuery) use ($programCode): void {
                    $attemptQuery->where('preferred_program_code', $programCode)
                        ->orWhere('program_preference_1_code', $programCode);
                });
            })
            ->orderBy('id')
            ->get();

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
                'reference_code' => $this->escapeText((string) $attempt->reference_code),
                'applicant_name' => $this->escapeText($applicantName !== '' ? $applicantName : 'Unknown'),
                'sex' => ucfirst((string) ($identity?->sex ?? 'Unspecified')),
                'birthdate' => $identity?->birthdate ? $identity->birthdate->format('Y-m-d') : '',
                'strand' => $this->escapeText((string) ($attempt->strand ?? 'N/A')),
                'gwa' => $attempt->gwa !== null ? number_format((float) $attempt->gwa, 2) : '',
                'school' => $this->escapeText((string) ($attempt->last_school_enrolled ?? 'N/A')),
                'first_choice' => $this->escapeText((string) ($attempt->preferred_program_code ?: $attempt->program_preference_1_code ?: 'N/A')),
                'second_choice' => $this->escapeText((string) ($attempt->program_preference_2_code ?? 'N/A')),
                'status' => ucfirst(str_replace('_', ' ', (string) $attempt->status)),
                'submitted_at' => $attempt->created_at ? $attempt->created_at->format('Y-m-d') : '',
            ];
        })->all();

        $totalApplications = count($rows);
        $acceptedCount = $attempts->where('status', AdmissionAttempt::STATUS_ACCEPTED)->count();
        $pendingCount = $attempts->where('status', AdmissionAttempt::STATUS_PENDING)->count();
        $needsCorrectionCount = $attempts->where('status', AdmissionAttempt::STATUS_NEEDS_CORRECTION)->count();
        $dismissedCount = $attempts->where('status', AdmissionAttempt::STATUS_DISMISSED)->count();

        return [
            'institution_name' => $this->escapeText((string) ($cycle?->institution?->name ?? Institution::query()->value('name') ?? 'Institution')),
            'institution_code' => $this->escapeText((string) ($cycle?->institution?->code ?? Institution::query()->value('code') ?? '')),
            'academic_cycle' => $this->escapeText($cycle?->staffLabel() ?? ''),
            'report_date' => now()->toDateString(),
            'total_applications' => $totalApplications,
            'accepted_count' => $acceptedCount,
            'pending_count' => $pendingCount,
            'needs_correction_count' => $needsCorrectionCount,
            'dismissed_count' => $dismissedCount,
            'applications' => $rows,
            'prepared_by' => 'Admissions Clerk',
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
