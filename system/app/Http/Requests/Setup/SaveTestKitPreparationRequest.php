<?php

namespace App\Http\Requests\Setup;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class SaveTestKitPreparationRequest extends FormRequest
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
            'preparation_id' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'test_instrument_version_id' => ['present', 'nullable', 'integer', 'min:1'],
            'norm_package_version_id' => ['present', 'nullable', 'integer', 'min:1'],
            'answer_sheet_draft_id' => ['present', 'nullable', 'integer', 'min:1'],
            'template_version_id' => ['present', 'nullable', 'integer', 'min:1'],
            'booklet_version_id' => ['present', 'nullable', 'integer', 'min:1'],
            'booklet_set_id' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = [
                'preparation_id',
                'expected_revision',
                'idempotency_key',
                'test_instrument_version_id',
                'norm_package_version_id',
                'answer_sheet_draft_id',
                'template_version_id',
                'booklet_version_id',
                'booklet_set_id',
            ];

            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, 'The field is not allowed.');
            }
        });
    }
}
