<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PrintStaffVoucherBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'cards' => ['required', 'array', 'min:1', 'max:100'],
            'cards.*.user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'cards.*.reference' => ['required', 'string', 'size:10'],
            'cards.*.token' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/D'],
            'cards.*.fallback' => ['required', 'string', 'regex:/^[A-Z2-7]{4}-[A-Z2-7]{4}$/D'],
            'cards.*.hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/D'],
            'format' => ['sometimes', Rule::in(['html', 'pdf'])],
            'layout' => ['sometimes', Rule::in(['grouped', 'individual'])],
        ];
    }
}
