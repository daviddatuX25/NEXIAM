<?php

namespace App\Http\Requests\Setup;

use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeGradeField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIntakeGradeFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('intake_grade_fields', 'code')->where(fn ($q) => $q->where('institution_id', Institution::current()->id)),
            ],
            'label' => ['required', 'string', 'max:255'],
            'field_kind' => ['required', Rule::in([IntakeGradeField::KIND_GWA, IntakeGradeField::KIND_SUBJECT])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
