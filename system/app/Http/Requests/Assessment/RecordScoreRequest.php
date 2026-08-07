<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class RecordScoreRequest extends FormRequest
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
            'admission_attempt_id' => ['required', 'integer', 'exists:admission_attempts,id'],
            'aptitude_area_id' => ['required', 'integer', 'exists:aptitude_areas,id'],
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }
}
