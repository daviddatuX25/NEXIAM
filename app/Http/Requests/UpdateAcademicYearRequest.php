<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year' => ['sometimes', 'string', 'max:9', 'unique:academic_years,academic_year,'.$this->route('academic_year')?->id],
            'semester' => ['sometimes', 'string', 'in:1,2,summer'],
            'is_active' => ['sometimes', 'boolean'],
            'application_start_date' => ['sometimes', 'date'],
            'application_end_date' => ['sometimes', 'date', 'after:application_start_date'],
        ];
    }
}
