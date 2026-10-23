<?php

namespace App\Http\Requests\Omr\Canonical;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOmrDraftPagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(['add', 'remove', 'reorder'])],
            'page_no' => ['nullable', 'integer', 'min:1', 'max:4', 'required_if:operation,remove'],
            'order' => ['nullable', 'array', 'min:1', 'max:4', 'required_if:operation,reorder'],
            'order.*' => ['required', 'integer', 'min:1', 'max:4', 'distinct:strict'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
