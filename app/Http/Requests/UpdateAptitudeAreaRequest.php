<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAptitudeAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'test_administrator']) ?? false;
    }

    public function rules(): array
    {
        $aptitudeArea = $this->route('aptitude_area');
        $autoCompute = \App\Models\SystemSetting::enableNormalizedScores();

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('aptitude_areas', 'code')->ignore($aptitudeArea?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_items' => ['required', 'integer', 'min:1', 'max:999'],
            'formula' => ['nullable', 'string', 'max:500'],
            'scoring_method' => ['required', 'in:formula,conversion_table'],
            'conversion_table' => [$autoCompute ? 'required_if:scoring_method,conversion_table' : 'nullable', 'array'],
            'conversion_table.*.raw_score' => ['required', 'integer', 'min:0'],
            'conversion_table.*.percentile_output' => ['required', 'string', 'max:20'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active') && $this->is_active === '') {
            $this->merge(['is_active' => true]);
        }
    }
}
