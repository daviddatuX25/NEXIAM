<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetGuidanceProgramPoolRequest extends FormRequest
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
            'default_program_code' => ['required', 'string', 'exists:programs,code'],
            'reason' => ['nullable', 'string', 'max:500'],
            'options' => ['required', 'array', 'min:1'],
            'options.*.program_code' => ['required', 'string', 'distinct', 'exists:programs,code'],
            'options.*.display_order' => ['required', 'integer', 'min:1', 'distinct'],
            'options.*.basis' => ['required', Rule::in(['preference', 'guidance_option'])],
        ];
    }
}
