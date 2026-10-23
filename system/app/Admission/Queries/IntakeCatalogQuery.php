<?php

namespace App\Admission\Queries;

use App\Models\Foundation\Program;
use Illuminate\Support\Collection;

/**
 * Active admission-offered program catalog for a single institution.
 */
class IntakeCatalogQuery
{
    /**
     * @return array{programs: Collection<int, Program>}
     */
    public function forInstitution(int $institutionId): array
    {
        $programs = Program::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->where('is_admission_offered', true)
            ->orderBy('name')
            ->get(['code', 'name']);

        return [
            'programs' => $programs,
        ];
    }
}
