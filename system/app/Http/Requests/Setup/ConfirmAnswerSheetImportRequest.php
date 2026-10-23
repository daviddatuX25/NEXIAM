<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmAnswerSheetImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'review_revision' => ['required', 'integer', 'min:1'],
            'review_token' => ['required', 'string', 'size:64'],
        ];
    }
}
