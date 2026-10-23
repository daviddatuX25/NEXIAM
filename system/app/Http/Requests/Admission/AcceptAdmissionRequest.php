<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

class AcceptAdmissionRequest extends FormRequest
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
            'verify_documents' => ['sometimes', 'boolean'],
        ];
    }
}
