<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

final class SetDocumentRequirementActiveRequest extends FormRequest
{
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
            'is_active' => ['required', 'boolean'],
            'expected_version' => ['sometimes', 'integer', 'min:1'],
            'code' => ['prohibited'],
            'label' => ['prohibited'],
            'applicant_track' => ['prohibited'],
            'item_kind' => ['prohibited'],
            'is_mandatory' => ['prohibited'],
            'sort_order' => ['prohibited'],
            'institution_id' => ['prohibited'],
        ];
    }
}
