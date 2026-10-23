<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveOmrPendingApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'applicant_number' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
