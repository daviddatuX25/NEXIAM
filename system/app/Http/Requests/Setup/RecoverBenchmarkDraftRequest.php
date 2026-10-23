<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class RecoverBenchmarkDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'in:0'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'expected_source_digest' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['_token', '_method', 'expected_version', 'reason', 'idempotency_key', 'expected_source_digest'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $unexpected) {
                $validator->errors()->add($unexpected, 'Unexpected request field.');
            }
        }];
    }
}
