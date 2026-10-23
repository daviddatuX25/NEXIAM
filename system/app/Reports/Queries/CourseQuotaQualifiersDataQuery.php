<?php

namespace App\Reports\Queries;

use App\Admission\Support\QuotaReservation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\ProgramQuota;
use App\Testing\PracticeWorkspace;

final class CourseQuotaQualifiersDataQuery
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
        $programCode = isset($filters['program_code']) && trim((string) $filters['program_code']) !== ''
            ? trim((string) $filters['program_code'])
            : null;
        $cycle = $cycleId === null ? null : AcademicCycle::query()->with('institution')->find($cycleId);

        $quotaQuery = ProgramQuota::query()->with('program')->where('academic_cycle_id', $cycleId);
        if ($programCode !== null) {
            $quotaQuery->whereHas('program', fn ($query) => $query->where('code', $programCode));
        }
        $targetQuota = $cycleId === null ? 0 : (int) $quotaQuery->sum('slots');

        // Calculate actual committed reservations for cycle via QuotaReservation::committedForCycle
        $practiceSetId = app(PracticeWorkspace::class)->currentId();
        $committedReservations = 0;
        if ($cycleId !== null) {
            $quotas = (clone $quotaQuery)->get();
            foreach ($quotas as $q) {
                $committedReservations += QuotaReservation::committedForCycle($cycleId, (int) $q->program_id, practiceSetId: $practiceSetId);
            }
        }
        $attempts = $cycleId === null ? collect() : AdmissionAttempt::query()->forPracticeSet(null)
            ->with('identity')
            ->where('academic_cycle_id', $cycleId)
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->when($programCode !== null, function ($query) use ($programCode): void {
                $query->where(function ($attemptQuery) use ($programCode): void {
                    $attemptQuery->where('preferred_program_code', $programCode)
                        ->orWhere('program_preference_1_code', $programCode);
                });
            })
            ->orderBy('accepted_at')
            ->orderBy('id')
            ->get();

        $qualifiers = $attempts->values()->map(function (AdmissionAttempt $attempt, int $index): array {
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
                'strand' => $this->escapeText((string) $attempt->strand),
                'gwa' => $attempt->gwa === null ? '' : number_format((float) $attempt->gwa, 2),
                'program_code' => $this->escapeText((string) ($attempt->preferred_program_code ?: $attempt->program_preference_1_code)),
                'admission_status' => 'Accepted',
                'accepted_at' => $attempt->accepted_at?->format('Y-m-d') ?? ($attempt->updated_at?->format('Y-m-d') ?? ''),
            ];
        })->all();
        $acceptedCount = count($qualifiers);
        $remainingSlots = max(0, $targetQuota - $acceptedCount);
        $remainingQuota = max(0, $targetQuota - $committedReservations);

        return [
            'institution_name' => $this->escapeText((string) ($cycle?->institution?->name ?? Institution::query()->value('name') ?? 'Institution')),
            'institution_code' => $this->escapeText((string) ($cycle?->institution?->code ?? Institution::query()->value('code') ?? '')),
            'academic_cycle' => $this->escapeText($cycle?->staffLabel() ?? ''),
            'report_date' => now()->toDateString(),
            'target_quota' => $targetQuota,
            'accepted_count' => $acceptedCount,
            'committed_reservations' => $committedReservations,
            'remaining_slots' => $remainingSlots,
            'remaining_quota' => $remainingQuota,
            'fill_rate' => $targetQuota > 0
                ? number_format(min(100, ($acceptedCount / $targetQuota) * 100), 2).'%'
                : '0.00%',
            'committed_fill_rate' => $targetQuota > 0
                ? number_format(min(100, ($committedReservations / $targetQuota) * 100), 2).'%'
                : '0.00%',
            'qualifiers' => $qualifiers,
            'prepared_by' => 'Registrar Clerk',
            'verified_by' => 'Admissions Officer',
            'approved_by' => 'Campus Registrar',
        ];
    }

    private function escapeText(string $value): string
    {
        return preg_match('/^\s*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
