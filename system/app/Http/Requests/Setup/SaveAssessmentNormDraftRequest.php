<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

final class SaveAssessmentNormDraftRequest extends FormRequest
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
            'request_id' => ['required', 'uuid'],
            'expected_revision' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'publisher_name' => ['nullable', 'string', 'max:255'],
            'form_code' => ['required', 'string', 'max:64'],
            'edition' => ['required', 'string', 'max:128'],
            'language' => ['required', 'string', 'max:128'],
            'applicable_population' => ['nullable', 'string', 'max:255'],
            'source_reference' => ['nullable', 'string', 'max:2048'],
            'permitted_use_reference' => ['nullable', 'string', 'max:2048'],
            'tables' => ['sometimes', 'array'],
            'tables.*.metric_code' => ['required', 'string', 'max:64'],
            'tables.*.output_scale' => ['required', 'in:percentile_rank,stanine'],
            'tables.*.intervals' => ['required', 'array'],
            'tables.*.intervals.*.min_score' => ['required', 'integer'],
            'tables.*.intervals.*.max_score' => ['required', 'integer'],
            'tables.*.intervals.*.output_value' => ['required', 'string', 'max:64'],
        ];
    }
}
