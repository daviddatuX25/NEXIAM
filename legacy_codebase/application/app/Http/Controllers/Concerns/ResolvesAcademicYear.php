<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * DRY helper for controllers that need to resolve the current academic year
 * from a request parameter or fall back to the system-wide active year.
 *
 * Usage:
 *   [$activeAcademicYear, $queryAcademicYearId] = $this->resolveAcademicYear($request);
 */
trait ResolvesAcademicYear
{
    /**
     * Resolve the academic year ID to filter by.
     *
     * Priority:
     *  1. Explicit `academic_year_id` request parameter (user picked a specific year)
     *  2. The system-wide active academic year
     *  3. null (no filtering — show all years)
     *
     * @return array{0: ?AcademicYear, 1: ?int} [activeAcademicYear, queryAcademicYearId]
     */
    protected function resolveAcademicYear(Request $request): array
    {
        $activeAcademicYear = AcademicYear::active();

        $param = $request->input('academic_year_id');

        if ($param !== null && $param !== '') {
            $queryAcademicYearId = (int) $param;
        } else {
            $queryAcademicYearId = $activeAcademicYear?->id;
        }

        return [$activeAcademicYear, $queryAcademicYearId];
    }

    /**
     * Fetch all academic years for dropdown selectors, ordered newest-first.
     */
    protected function academicYearOptions(): Collection
    {
        return AcademicYear::query()
            ->orderByDesc('academic_year')
            ->orderBy('semester')
            ->get(['id', 'academic_year', 'semester', 'is_active']);
    }
}
