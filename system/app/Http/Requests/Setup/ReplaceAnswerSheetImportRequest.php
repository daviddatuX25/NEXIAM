<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceAnswerSheetImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bundle' => ['required', 'file', 'mimes:zip', 'max:102400'],
            'recovery_ledger' => ['nullable', 'file', 'mimes:json', 'max:5120'],
        ];
    }
}
