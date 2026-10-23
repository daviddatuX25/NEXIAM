<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

final class FocusDirectAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'focus_attempt_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
