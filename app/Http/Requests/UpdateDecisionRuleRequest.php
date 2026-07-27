<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDecisionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'admin', 'counselor']) ?? false;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['sometimes', 'integer', 'exists:courses,id'],
            'aptitude_area_id' => ['nullable', 'integer', 'exists:aptitude_areas,id'],
            'min_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'max_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
