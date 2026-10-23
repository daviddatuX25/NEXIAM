<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

final class TestKitCommandRequest extends FormRequest
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
            'test_kit_id' => ['nullable', 'integer', 'min:1', 'prohibits:code,name'],
            'code' => ['required_without:test_kit_id', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'name' => ['required_without:test_kit_id', 'nullable', 'string', 'max:160'],
            'template_version_id' => ['required', 'integer', 'min:1'],
            'booklet_version_id' => ['required', 'integer', 'min:1'],
            'booklet_set_id' => ['required', 'integer', 'min:1'],
            'test_instrument_version_id' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
