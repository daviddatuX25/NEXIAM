<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

class BulkAdmissionActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'attempts' => ['required', 'array', 'min:1'],
            'attempts.*.id' => ['required', 'integer', 'distinct'],
            'attempts.*.version' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
            'verify_documents' => ['nullable', 'boolean'],
        ];
    }
}
