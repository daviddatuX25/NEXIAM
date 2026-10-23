<?php

namespace App\Reports\Queries;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;

final class FeederHighSchoolOriginDataQuery implements ContextualReadToolHandler
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

        $cycle = $cycleId === null ? null : AcademicCycle::query()->with('institution')->find($cycleId);

        $attempts = $cycleId === null ? collect() : AdmissionAttempt::query()->forPracticeSet(null)
            ->where('academic_cycle_id', $cycleId)
            ->get();

        $bySchool = $attempts->groupBy(function (AdmissionAttempt $a) {
            return trim((string) ($a->last_school_enrolled ?: 'Unspecified School'));
        });

        $schoolRows = [];
        $rowNo = 1;
        foreach ($bySchool->sortByDesc(fn ($group) => $group->count()) as $schoolName => $group) {
            $totalInSchool = $group->count();
            $acceptedInSchool = $group->where('status', AdmissionAttempt::STATUS_ACCEPTED)->count();
            $firstAttempt = $group->first();
            $city = $firstAttempt?->city ?: 'N/A';
            $province = $firstAttempt?->province ?: 'N/A';

            $schoolRows[] = [
                'row_no' => $rowNo++,
                'school_name' => $this->escapeText($schoolName),
                'city' => $this->escapeText($city),
                'province' => $this->escapeText($province),
                'applicant_count' => $totalInSchool,
                'accepted_count' => $acceptedInSchool,
                'conversion_rate' => $totalInSchool > 0
                    ? number_format(($acceptedInSchool / $totalInSchool) * 100, 1).'%'
                    : '0.0%',
            ];
        }

        $byProvince = $attempts->groupBy(function (AdmissionAttempt $a) {
            return trim((string) ($a->province ?: 'Unspecified Province'));
        })->sortByDesc(fn ($group) => $group->count());

        $topProvince = $byProvince->keys()->first() ?? 'N/A';
        $topSchool = count($schoolRows) > 0 ? $schoolRows[0]['school_name'] : 'N/A';

        return [
            'institution_name' => $this->escapeText((string) ($cycle?->institution?->name ?? Institution::query()->value('name') ?? 'Institution')),
            'institution_code' => $this->escapeText((string) ($cycle?->institution?->code ?? Institution::query()->value('code') ?? '')),
            'academic_cycle' => $this->escapeText($cycle?->staffLabel() ?? ''),
            'report_date' => now()->toDateString(),
            'total_schools' => count($schoolRows),
            'total_applicants' => $attempts->count(),
            'top_province' => $this->escapeText($topProvince),
            'top_school' => $this->escapeText($topSchool),
            'feeder_schools' => $schoolRows,
            'prepared_by' => 'Admissions Clerk',
            'verified_by' => 'Admissions Officer',
            'approved_by' => 'University Registrar',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
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
     *   total_feeders_count: int,
     *   total_applicants: int,
     *   top_schools: list<array{school_name: string, city: string, province: string, applicant_count: int, accepted_count: int, conversion_rate_percent: float}>,
     *   province_distribution: list<array{province: string, count: int, percentage: float}>,
     *   as_of: string
     * }
     */
    public function forXiamSafe(int $institutionId, ?int $cycleId = null): array
    {
        $cycleQuery = AcademicCycle::query()->where('institution_id', $institutionId);
        $cycle = $cycleId === null
            ? $cycleQuery->where('is_active', true)->first()
            : $cycleQuery->whereKey($cycleId)->first();

        $cycleId = $cycle?->id === null ? null : (int) $cycle->id;
        $attempts = $cycleId === null || $institutionId < 1
            ? null
            : AdmissionAttempt::query()->forPracticeSet(null)
                ->where('academic_cycle_id', $cycleId)
                ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId));

        $schoolGroups = $attempts === null
            ? collect()
            : (clone $attempts)
                ->select([
                    'last_school_enrolled',
                    'province',
                ])
                ->selectRaw('MIN(city) as city')
                ->selectRaw('COUNT(*) as applicant_count')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as accepted_count', [AdmissionAttempt::STATUS_ACCEPTED])
                ->groupBy('last_school_enrolled', 'province')
                ->orderByDesc('applicant_count')
                ->orderBy('last_school_enrolled')
                ->get();

        $totalApplicants = (int) $schoolGroups->sum('applicant_count');
        $topSchools = $schoolGroups->take(15)->map(function ($group): array {
            $applicantCount = (int) $group->applicant_count;
            $acceptedCount = (int) $group->accepted_count;

            return [
                'school_name' => trim((string) ($group->last_school_enrolled ?: 'Unspecified School')),
                'city' => trim((string) ($group->city ?: 'N/A')),
                'province' => trim((string) ($group->province ?: 'Unspecified Province')),
                'applicant_count' => $applicantCount,
                'accepted_count' => $acceptedCount,
                'conversion_rate_percent' => $applicantCount > 0
                    ? round(($acceptedCount / $applicantCount) * 100, 1)
                    : 0.0,
            ];
        })->values()->all();

        $provinceGroups = $attempts === null
            ? collect()
            : (clone $attempts)
                ->select('province')
                ->selectRaw('COUNT(*) as applicant_count')
                ->groupBy('province')
                ->orderByDesc('applicant_count')
                ->orderBy('province')
                ->get();

        $provinceDistribution = $provinceGroups->take(10)->map(function ($group) use ($totalApplicants): array {
            $count = (int) $group->applicant_count;

            return [
                'province' => trim((string) ($group->province ?: 'Unspecified Province')),
                'count' => $count,
                'percentage' => $totalApplicants > 0
                    ? round(($count / $totalApplicants) * 100, 1)
                    : 0.0,
            ];
        })->values()->all();

        return [
            'institution_id' => $institutionId,
            'cycle_id' => $cycleId,
            'cycle_name' => $cycle?->staffLabel() ?? '',
            'total_feeders_count' => $schoolGroups->count(),
            'total_applicants' => $totalApplicants,
            'top_schools' => $topSchools,
            'province_distribution' => $provinceDistribution,
            'as_of' => now()->toIso8601String(),
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
