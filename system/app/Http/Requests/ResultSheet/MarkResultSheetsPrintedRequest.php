<?php

namespace App\Http\Requests\ResultSheet;

use Illuminate\Foundation\Http\FormRequest;

class MarkResultSheetsPrintedRequest extends FormRequest
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
            'document_ids' => ['required', 'array', 'min:1', 'max:200'],
            'document_ids.*' => ['integer'],
            'printed' => ['required', 'boolean'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'idempotency_key' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
