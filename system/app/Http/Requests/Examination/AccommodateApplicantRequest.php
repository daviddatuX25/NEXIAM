<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class AccommodateApplicantRequest extends FormRequest
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
            'reference_code' => ['required_without:reference_codes', 'nullable', 'string', 'max:64'],
            'reference_codes' => ['required_without:reference_code', 'nullable', 'array', 'min:1', 'max:100'],
            'reference_codes.*' => ['required', 'string', 'distinct', 'max:64'],
            'reason_category' => ['required', 'string', 'in:travel_distance,tardiness_consideration,administrative_override'],
            'reason_notes' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
