<?php

namespace App\Http\Requests\ResultSheet;

use Illuminate\Foundation\Http\FormRequest;

class RequestResultSheetPrintRequest extends FormRequest
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
            'copies' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ];
    }
}
