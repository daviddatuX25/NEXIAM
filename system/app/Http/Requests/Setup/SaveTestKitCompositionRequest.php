<?php

namespace App\Http\Requests\Setup;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class SaveTestKitCompositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'preparation_id' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'component_vector' => ['required', 'array'],
            'rows' => ['required', 'array'],
            'rows.*' => ['required', 'array'],
            'rows.*.disposition' => ['required', 'string', 'in:physical,nonphysical_practice'],
            'rows.*.physical_item_uid' => ['present', 'nullable', 'string', 'size:36'],
            'rows.*.booklet_item_id' => ['present', 'nullable', 'integer', 'min:1'],
            'rows.*.formal_subtest_id' => ['required', 'integer', 'min:1'],
            'rows.*.formal_item_id' => ['required', 'integer', 'min:1'],
            'rows.*.option_tokens' => ['required', 'array'],
            'rows.*.option_tokens.*' => ['required', 'string', 'max:64'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = [
                'preparation_id', 'expected_revision', 'component_vector', 'rows', 'idempotency_key',
            ];
            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, 'The field is not allowed.');
            }

            $rowKeys = [
                'disposition', 'physical_item_uid', 'booklet_item_id', 'formal_subtest_id',
                'formal_item_id', 'option_tokens',
            ];
            foreach ((array) $this->input('rows', []) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (array_diff(array_keys($row), $rowKeys) as $key) {
                    $validator->errors()->add("rows.{$index}.{$key}", 'The field is not allowed.');
                }
            }
        });
    }
}
