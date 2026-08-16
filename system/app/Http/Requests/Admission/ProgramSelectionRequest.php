<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared payload for applicant select/transfer (student_self choices).
 */
class ProgramSelectionRequest extends FormRequest
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
            'idempotency_key' => ['required', 'string', 'max:128'],
            'program_code' => ['required', 'string', 'exists:programs,code'],
        ];
    }
}
