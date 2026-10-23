<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class AdoptOmrPendingCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
