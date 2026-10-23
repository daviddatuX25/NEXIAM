<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveApplicationOpeningTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::in(['verify', 'unsubscribe'])],
        ];
    }
}
