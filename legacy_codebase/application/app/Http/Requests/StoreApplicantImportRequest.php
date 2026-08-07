<?php

namespace App\Http\Requests;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;

class StoreApplicantImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Application::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx,xls',
                'max:10240',
            ],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to import.',
            'file.file' => 'The uploaded file is invalid. Please try again.',
            'file.mimes' => 'File must be a spreadsheet (.csv, .xlsx, .xls, or .txt).',
            'file.max' => 'File must not exceed 10MB.',
            'academic_year_id.required' => 'Please select an academic year.',
            'academic_year_id.exists' => 'The selected academic year is invalid.',
        ];
    }
}
