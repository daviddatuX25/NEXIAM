<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicCycleRequest extends FormRequest
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
            'school_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', Rule::in(['1', '2'])],
            'application_start_date' => ['nullable', 'date'],
            'application_end_date' => ['nullable', 'date', 'after_or_equal:application_start_date'],
        ];
    }
}
