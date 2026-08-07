<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignApplicantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'registrar_administrator']) ?? false;
    }

    public function rules(): array
    {
        return [
            'applicant_ids' => ['required', 'array', 'min:1'],
            'applicant_ids.*' => ['required', 'integer', 'exists:applicants,id'],
            'backtrack' => ['nullable', 'boolean'],
        ];
    }
}
