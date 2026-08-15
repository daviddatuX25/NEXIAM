<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewOmrSheetRequest extends FormRequest
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
            'action' => ['required', Rule::in(['confirm', 'abstain'])],
            'admission_attempt_id' => ['nullable', 'integer', 'exists:admission_attempts,id'],
            'abstain_reason' => ['required_if:action,abstain', 'string', 'max:255'],
            'adjusted_scores' => ['sometimes', 'array'],
            'adjusted_scores.*.aptitude_area_id' => ['required', 'integer', 'distinct', 'exists:aptitude_areas,id'],
            'adjusted_scores.*.score' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }
}
