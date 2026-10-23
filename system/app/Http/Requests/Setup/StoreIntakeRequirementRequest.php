<?php

namespace App\Http\Requests\Setup;

use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirement;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreIntakeRequirementRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code')) ? mb_strtolower(trim($this->input('code'))) : $this->input('code'),
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
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('intake_requirements', 'code')->where(
                    fn (Builder $query): Builder => $query->where('institution_id', Institution::current()->id),
                ),
            ],
            'label' => ['required', 'string', 'max:255'],
            'applicant_track' => ['required', Rule::in(IntakeRequirement::TRACKS)],
            'item_kind' => ['required', Rule::in(IntakeRequirement::KINDS)],
            'is_mandatory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'institution_id' => ['prohibited'],
            'id' => ['prohibited'],
            'version' => ['prohibited'],
        ];
    }
}
