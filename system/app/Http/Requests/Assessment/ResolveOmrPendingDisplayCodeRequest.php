<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveOmrPendingDisplayCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'display_code' => ['required', 'string', 'max:64'],
            'manual_confirmed' => ['required', 'boolean', 'accepted'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
