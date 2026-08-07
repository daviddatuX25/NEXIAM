<?php

namespace App\Http\Requests\Release;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseResultRequest extends FormRequest
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
        ];
    }
}
