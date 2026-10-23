<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class PublishExamSessionRequest extends FormRequest
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
            'expected_version' => ['required', 'integer', 'min:1'],
            'test_kit_version_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:test_kit_versions,id'],
        ];
    }
}
