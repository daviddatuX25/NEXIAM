<?php

namespace App\Http\Requests\Setup;

use App\Models\Foundation\IntakeRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateIntakeRequirementRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => is_string($this->input('label')) ? trim($this->input('label')) : $this->input('label'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'applicant_track' => ['required', Rule::in(IntakeRequirement::TRACKS)],
            'item_kind' => ['required', Rule::in(IntakeRequirement::KINDS)],
            'is_mandatory' => ['required', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'expected_version' => ['sometimes', 'integer', 'min:1'],
            'code' => ['prohibited'],
            'institution_id' => ['prohibited'],
        ];
    }
}
