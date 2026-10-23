<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class AuthorizeOmrPaperReproductionRequest extends FormRequest
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
            'display_code' => ['required', 'string', 'max:40'],
            'page_no' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'idempotency_key' => ['required', 'string', 'uuid'],
        ];
    }
}
