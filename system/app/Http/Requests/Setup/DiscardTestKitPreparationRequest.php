<?php

namespace App\Http\Requests\Setup;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class DiscardTestKitPreparationRequest extends FormRequest
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['preparation_id', 'expected_revision', 'idempotency_key'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, 'The field is not allowed.');
            }
        });
    }
}
