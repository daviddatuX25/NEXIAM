<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year' => ['required', 'string', 'max:9', 'unique:academic_years,academic_year'],
            'semester' => ['required', 'string', 'in:1,2,summer'],
            'is_active' => ['sometimes', 'boolean'],
            'application_start_date' => ['required', 'date'],
            'application_end_date' => ['required', 'date', 'after:application_start_date'],
        ];
    }
}
