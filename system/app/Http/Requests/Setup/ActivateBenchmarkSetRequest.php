<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class ActivateBenchmarkSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'benchmark_set_id' => ['required', 'integer', 'exists:admission_benchmark_sets,id'],
            'expected_current_activation_id' => ['present', 'nullable', 'integer'],
            'expected_content_sha256' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $allowed = ['_token', '_method', 'benchmark_set_id', 'expected_current_activation_id', 'expected_content_sha256', 'idempotency_key'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $unknown) {
                $validator->errors()->add($unknown, 'Unexpected request field.');
            }
        }];
    }
}
