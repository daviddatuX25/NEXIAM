<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'test_administrator']);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls,txt', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a spreadsheet file.',
            'file.file' => 'The uploaded file is invalid.',
            'file.mimes' => 'File must be CSV, XLSX, or XLS.',
            'file.max' => 'File must not exceed 10MB.',
        ];
    }
}
