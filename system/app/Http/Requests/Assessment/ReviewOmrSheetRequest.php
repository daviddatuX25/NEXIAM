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
            'admission_attempt_id' => ['prohibited'],
            'abstain_reason' => ['required_if:action,abstain', 'string', 'max:255'],
            'adjusted_scores' => ['prohibited'],
            'per_area_scores' => ['prohibited'],
            'scores' => ['prohibited'],
        ];
    }
}
