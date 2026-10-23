<?php

namespace App\Http\Requests\Setup;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class CreateTestKitPreparationRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'name' => ['required', 'string', 'max:255'],
            'expected_revision' => ['required', 'integer', 'in:0'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['code', 'name', 'expected_revision', 'idempotency_key'];

            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, 'The field is not allowed.');
            }
        });
    }
}
