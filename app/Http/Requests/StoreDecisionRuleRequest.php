<?php

namespace App\Http\Requests;

use App\Models\AptitudeArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDecisionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'admin', 'counselor']) ?? false;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'aptitude_area_id' => ['nullable', 'integer', 'exists:aptitude_areas,id'],
            'min_score' => ['required', 'integer', 'min:0', 'max:100'],
            'max_score' => ['required', 'integer', 'min:0', 'max:100', 'gte:min_score'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
