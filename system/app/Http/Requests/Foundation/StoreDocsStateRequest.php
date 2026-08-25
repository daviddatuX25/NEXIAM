<?php

namespace App\Http\Requests\Foundation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Batch upsert of docs-workspace state entries.
 * Keys are control identifiers from the client brief page; values are
 * short strings (decision codes, flags, typed answers).
 */
class StoreDocsStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['required', 'string', 'in:client-brief'],
            'entries' => ['required', 'array', 'max:200'],
            'entries.*.key' => ['required', 'string', 'regex:/^[a-z0-9._-]{1,100}$/'],
            'entries.*.value' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
