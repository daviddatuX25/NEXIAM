<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scores' => ['required', 'array'],
            'scores.*.aptitude_area_id' => ['required', 'integer', 'exists:aptitude_areas,id'],
            'scores.*.raw_score' => ['nullable', 'integer', 'min:0'],
            'scores.*.max_score' => ['nullable', 'integer', 'min:0'],
            'scores.*.normalized_score' => ['nullable', 'numeric', 'min:0'],
            'scores.*.percentile_string' => ['nullable', 'string', 'max:20'],
        ];
    }
}
