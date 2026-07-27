<?php

namespace App\Http\Requests\Proctor;

use Illuminate\Foundation\Http\FormRequest;

class MarkSubmissionBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageRoster', $this->route('exam_session'));
    }

    public function rules(): array
    {
        return [
            'applicant_ids' => ['sometimes', 'array'],
            'applicant_ids.*' => ['integer', 'exists:applicants,id'],
        ];
    }
}
