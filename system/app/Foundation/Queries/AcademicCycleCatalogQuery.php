<?php

namespace App\Foundation\Queries;

use App\Models\Foundation\AcademicCycle;

final class AcademicCycleCatalogQuery
{
    /**
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     school_year: string,
     *     semester: string,
     *     is_active: bool,
     *     starts_on: ?string,
     *     ends_on: ?string
     * }>
     */
    public function handle(int $institutionId): array
    {
        return AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->orderByDesc('is_active')
            ->orderBy('opens_on')
            ->get(['id', 'code', 'name', 'school_year', 'semester', 'is_active', 'opens_on', 'closes_on'])
            ->map(static fn (AcademicCycle $cycle): array => [
                'id' => (int) $cycle->id,
                'code' => (string) $cycle->code,
                'name' => (string) $cycle->name,
                'school_year' => (string) $cycle->school_year,
                'semester' => (string) $cycle->semester,
                'is_active' => (bool) $cycle->is_active,
                'starts_on' => $cycle->opens_on?->toDateString(),
                'ends_on' => $cycle->closes_on?->toDateString(),
            ])
            ->all();
    }
}
