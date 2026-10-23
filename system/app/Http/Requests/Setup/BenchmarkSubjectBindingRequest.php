<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class BenchmarkSubjectBindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $common = [
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:116'],
        ];
        if ($this->routeIs('setup.admission-support.benchmarks.subject-bindings.approve')) {
            return $common + [
                'source_id' => ['required', 'integer', 'min:1'],
                'source_version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
                'feature_key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,63}$/'],
                'unit' => ['required', 'string', 'max:64'],
                'model_required' => ['required', 'boolean'],
            ];
        }

        return $common + [
            'binding_ids' => ['required', 'array', 'min:1'],
            'binding_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), [...array_keys($this->rules()), '_token']) as $key) {
                $validator->errors()->add($key, 'Unexpected binding command field.');
            }
        }];
    }
}
