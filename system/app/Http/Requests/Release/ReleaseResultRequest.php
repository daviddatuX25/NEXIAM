<?php

namespace App\Http\Requests\Release;

use App\Release\Actions\ReleaseResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'mode' => ['nullable', Rule::in(ReleaseResult::MODES)],
        ];
    }
}
