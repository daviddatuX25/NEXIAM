<?php

namespace App\Http\Requests\Scheduling;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewCohortDistributionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'target_session_ids' => ['required', 'array', 'min:1'],
            'target_session_ids.*' => ['required', 'integer'],
            'sort_strategy' => ['required', 'string', 'in:fcfs,alphabetical,program'],
            'packing_strategy' => ['required', 'string', 'in:sequential,balanced'],
        ];
    }
}
