<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListApplicationOpeningSubscribersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['pending', 'verified', 'consumed', 'unsubscribed', 'expired'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
