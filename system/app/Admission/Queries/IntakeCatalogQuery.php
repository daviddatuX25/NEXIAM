<?php

namespace App\Admission\Queries;

use App\Models\Foundation\IntakeGradeField;
use App\Models\Foundation\Program;
use Illuminate\Support\Collection;

/**
 * Active intake catalog (programs + grade fields) for a single institution.
 * Shared by the applicant apply form and the registrar attempt detail page so
 * both render the same active catalog shape.
 */
class IntakeCatalogQuery
{
    /**
     * @return array{programs: Collection<int, Program>, grade_fields: Collection<int, array<string, mixed>>}
     */
    public function forInstitution(int $institutionId): array
    {
        $programs = Program::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name']);

        $gradeFields = IntakeGradeField::activeForInstitution($institutionId)->map(fn (IntakeGradeField $field): array => [
            'code' => $field->code,
            'label' => $field->label,
            'field_kind' => $field->field_kind,
            'is_required' => $field->is_required,
            'min_value' => (float) $field->min_value,
            'max_value' => (float) $field->max_value,
        ])->values();

        return [
            'programs' => $programs,
            'grade_fields' => $gradeFields,
        ];
    }
}
