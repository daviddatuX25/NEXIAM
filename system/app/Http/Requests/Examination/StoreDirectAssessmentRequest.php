<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDirectAssessmentRequest extends FormRequest
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
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'applicant_ids' => ['required', 'array', 'min:1'],
            'applicant_ids.*' => ['required', 'integer', 'exists:admission_attempts,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'string', 'max:20'],
            'end_time' => ['nullable', 'string', 'max:20'],
            'test_kit_version_id' => ['required', 'integer', 'exists:test_kit_versions,id'],
        ];
    }
}
