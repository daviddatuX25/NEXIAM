<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class StartExamSessionRequest extends FormRequest
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
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
        ];
    }
}
